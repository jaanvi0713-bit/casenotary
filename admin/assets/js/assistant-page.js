/**

 * Admin AI Assistant

 */

(function () {

    "use strict";



    var form = document.getElementById("assistantForm");

    var input = document.getElementById("assistantInput");

    var messages = document.getElementById("assistantMessages");

    var newChatBtn = document.getElementById("assistantNewChatBtn");

    var sendBtn = document.getElementById("assistantSendBtn");

    var attachBtn = document.getElementById("assistantAttachBtn");

    var attachInput = document.getElementById("assistantAttachment");

    var attachList = document.getElementById("assistantAttachList");
    var attachBar = document.getElementById("assistantAttachBar");

    var DOC_CTX_KEY = "assistant_active_documents";
    var LEGACY_DOC_CTX_KEY = "assistant_active_document_text";
    var MAX_PENDING_ATTACHMENTS = 5;
    var activeDocumentItems = [];
    var pendingAttachments = [];

    function loadActiveDocumentItems() {
        if (activeDocumentItems.length) {
            return activeDocumentItems;
        }

        try {
            var raw = sessionStorage.getItem(DOC_CTX_KEY);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    activeDocumentItems = parsed;
                }
            }
        } catch (error) {
            activeDocumentItems = [];
        }

        if (!activeDocumentItems.length) {
            try {
                var legacy = sessionStorage.getItem(LEGACY_DOC_CTX_KEY) || "";
                if (legacy.trim()) {
                    activeDocumentItems = [{
                        name: "Uploaded document",
                        text: legacy.trim(),
                        source: "upload"
                    }];
                }
            } catch (error) {
                // Ignore.
            }
        }

        return activeDocumentItems;
    }

    function saveActiveDocumentItems(items) {
        activeDocumentItems = (items || []).filter(function (item) {
            return item && String(item.text || "").trim();
        }).map(function (item) {
            return {
                id: String(item.id || ""),
                name: String(item.name || "Document"),
                text: String(item.text || "").trim(),
                source: String(item.source || "upload")
            };
        });

        if (!activeDocumentItems.length) {
            return;
        }

        try {
            sessionStorage.setItem(DOC_CTX_KEY, JSON.stringify(activeDocumentItems));
        } catch (error) {
            // Ignore quota errors; in-memory copy still works for this page.
        }
    }

    function clearActiveDocumentItems() {
        activeDocumentItems = [];
        try {
            sessionStorage.removeItem(DOC_CTX_KEY);
            sessionStorage.removeItem(LEGACY_DOC_CTX_KEY);
        } catch (error) {
            // Ignore.
        }
    }

    loadActiveDocumentItems();

    var libraryList = document.getElementById("assistantLibraryList");
    var libraryCard = document.getElementById("assistantLibrary");
    var assistantLayout = document.getElementById("assistantLayout");
    var libraryToggleBtn = document.getElementById("assistantLibraryToggleBtn");
    var libraryCloseBtn = document.getElementById("assistantLibraryCloseBtn");
    var libraryBackdrop = document.getElementById("assistantLibraryBackdrop");
    var libraryCollapsedKey = "assistantLibraryCollapsed";



    if (!form || !input || !messages) {

        return;

    }



    var csrfInput = form.querySelector('input[name="_csrf_token"]');

    var csrfToken = csrfInput ? csrfInput.value : "";
    var readOnly = form.getAttribute("data-read-only") === "1";

    var apiUrl = "../api/assistant.php";

    var busy = false;

    var activeConversationId = null;

    var welcomeHtml = "";

    function getWelcomeHtml() {
        if (welcomeHtml) {
            return welcomeHtml;
        }

        var tpl = document.getElementById("assistantWelcomeTemplate");
        if (tpl && tpl.content && tpl.content.firstElementChild) {
            welcomeHtml = tpl.innerHTML.trim();
        }

        return welcomeHtml;
    }

    var welcomeTpl = document.getElementById("assistantWelcomeTemplate");
    if (welcomeTpl) {
        welcomeHtml = welcomeTpl.innerHTML.trim();
    }



    function attachmentKind(fileName) {
        var ext = String(fileName || "").split(".").pop().toLowerCase();

        if (ext === "pdf") {
            return { type: "PDF document", icon: "bi-file-earmark-pdf", kind: "pdf" };
        }

        if (["jpg", "jpeg", "png", "gif", "webp", "bmp", "svg"].indexOf(ext) >= 0) {
            return { type: "Image", icon: "bi-file-earmark-image", kind: "image" };
        }

        if (ext === "html" || ext === "htm") {
            return { type: "HTML document", icon: "bi-file-earmark-code", kind: "html" };
        }

        if (ext === "txt") {
            return { type: "Text file", icon: "bi-file-earmark-text", kind: "text" };
        }

        return { type: ext ? ext.toUpperCase() + " file" : "File", icon: "bi-file-earmark", kind: "file" };
    }

    function clearAllAttachments() {
        pendingAttachments = [];

        if (attachInput) {
            attachInput.value = "";
        }

        renderAttachmentPreviews();
    }

    function removePendingAttachment(id) {
        pendingAttachments = pendingAttachments.filter(function (entry) {
            return entry.id !== id;
        });
        renderAttachmentPreviews();
    }

    function renderAttachmentPreviews() {
        if (!attachList || !attachBar) {
            return;
        }

        attachList.innerHTML = "";

        if (!pendingAttachments.length) {
            attachBar.classList.add("d-none");
            if (form) {
                form.classList.remove("assistant-form--has-attachment");
            }
            return;
        }

        attachBar.classList.remove("d-none");
        if (form) {
            form.classList.add("assistant-form--has-attachment");
        }

        pendingAttachments.forEach(function (entry) {
            var meta = attachmentKind(entry.name);
            var card = document.createElement("div");
            card.className = "assistant-attach-preview__card";
            if (entry.status === "pending" || entry.status === "reading") {
                card.classList.add("assistant-attach-preview__card--reading");
            }
            card.setAttribute("data-attach-id", entry.id);

            card.innerHTML = '<div class="assistant-attach-preview__icon assistant-attach-preview__icon--'
                + meta.kind + '" aria-hidden="true"><i class="bi ' + meta.icon + '"></i></div>'
                + '<div class="assistant-attach-preview__meta">'
                + '<span class="assistant-attach-preview__name">' + escapeHtml(entry.name) + "</span>"
                + '<span class="assistant-attach-preview__type">' + escapeHtml(meta.type) + "</span>"
                + "</div>"
                + '<button type="button" class="assistant-attach-preview__remove" title="Remove attachment" aria-label="Remove '
                + escapeHtml(entry.name) + '"><i class="bi bi-x-lg"></i></button>';

            var removeBtn = card.querySelector(".assistant-attach-preview__remove");
            if (removeBtn) {
                removeBtn.addEventListener("click", function () {
                    removePendingAttachment(entry.id);
                });
            }

            attachList.appendChild(card);
        });
    }

    function addPendingFiles(fileList) {
        if (!fileList || !fileList.length) {
            return;
        }

        var remaining = MAX_PENDING_ATTACHMENTS - pendingAttachments.length;
        if (remaining <= 0) {
            window.alert("You can attach up to " + MAX_PENDING_ATTACHMENTS + " files at once.");
            return;
        }

        var added = 0;
        Array.prototype.forEach.call(fileList, function (file) {
            if (added >= remaining) {
                return;
            }

            pendingAttachments.push({
                id: "att-" + Date.now() + "-" + Math.random().toString(36).slice(2, 8),
                name: file.name,
                file: file,
                status: "pending",
                documentText: "",
                source: "",
                fromScreenshot: false
            });
            added += 1;
        });

        if (fileList.length > added) {
            window.alert("Only " + MAX_PENDING_ATTACHMENTS + " files can be attached at once.");
        }

        renderAttachmentPreviews();
    }

    function prepareAttachmentEntry(entry) {
        var file = entry.file;
        if (!file) {
            return Promise.resolve(entry);
        }

        entry.status = "reading";
        renderAttachmentPreviews();

        if (window.AssistantPdfClient && window.AssistantPdfClient.isPdfFile(file)) {
            return window.AssistantPdfClient.prepare(file).then(function (prepared) {
                return Object.assign({}, entry, {
                    documentText: prepared.documentText || entry.documentText || "",
                    file: prepared.file || file,
                    status: "ready"
                });
            }).catch(function () {
                return Object.assign({}, entry, { status: "ready" });
            });
        }

        if (window.AssistantImageOcr && window.AssistantImageOcr.isImageFile(file)) {
            return window.AssistantImageOcr.prepare(file).then(function (prepared) {
                return Object.assign({}, entry, {
                    documentText: prepared.documentText || "",
                    file: prepared.file || file,
                    fromScreenshot: !!prepared.fromScreenshot,
                    source: prepared.fromScreenshot ? "screenshot" : entry.source,
                    status: "ready"
                });
            }).catch(function () {
                return Object.assign({}, entry, { status: "ready" });
            });
        }

        if (/\.(html?|htm)$/i.test(file.name || "") || (file.type || "").indexOf("text/html") === 0) {
            return new Promise(function (resolve) {
                var reader = new FileReader();
                reader.onload = function () {
                    var html = String(reader.result || "");
                    var text = html
                        .replace(/<script[\s\S]*?<\/script>/gi, " ")
                        .replace(/<style[\s\S]*?<\/style>/gi, " ")
                        .replace(/<[^>]+>/g, " ")
                        .replace(/\s+/g, " ")
                        .trim();
                    resolve(Object.assign({}, entry, { documentText: text, status: "ready" }));
                };
                reader.onerror = function () {
                    resolve(Object.assign({}, entry, { status: "ready" }));
                };
                reader.readAsText(file);
            });
        }

        return Promise.resolve(Object.assign({}, entry, { status: "ready" }));
    }

    function prepareAllAttachments(entries) {
        if (!entries.length) {
            return Promise.resolve([]);
        }

        return Promise.all(entries.map(function (entry) {
            return prepareAttachmentEntry(entry);
        }));
    }

    function escapeHtml(text) {

        return String(text)

            .replace(/&/g, "&amp;")

            .replace(/</g, "&lt;")

            .replace(/>/g, "&gt;")

            .replace(/"/g, "&quot;");

    }

    function safeMarkdownLink(_match, label, url) {
        var href = String(url || "").trim();
        if (/^(https?:\/\/|\/|\.\.\/)/i.test(href)) {
            return '<a href="' + escapeHtml(href) + '" rel="noopener noreferrer">' + escapeHtml(label) + "</a>";
        }

        return escapeHtml(label) + " (" + escapeHtml(href) + ")";
    }



    function shouldSendDocumentContext(message) {
        var text = String(message || "").trim();
        if (!text) {
            return false;
        }

        if (/\b(this|the|our|your)\s+(notary\s+)?(system|software|portal|platform|app)\b/i.test(text)) {
            return false;
        }

        if (/\bnotary\s+system\b/i.test(text) || /^(system|portal|help|what can u do|what can you do)\??$/i.test(text)) {
            return false;
        }

        if (/\b(calculate|calculation|compute|\d+\s*%|percent|revenue|clients?|cases?|appointments?|dashboard|intake|jurat|apostille)\b/i.test(text)) {
            return false;
        }

        if (/\b(extract|scan|read|ocr|analy[sz]e|summarize|document|pdf|letter|invoice|receipt|attachment|upload|what does (?:this|the) (?:doc|document|say))\b/i.test(text)) {
            return true;
        }

        if (/\b(this|that|the|uploaded|attached)\b.*\b(document|doc|file|pdf|letter|invoice|receipt)\b/i.test(text)) {
            return true;
        }

        if (/\b(what is|what's|how much is|how much was)\b.*\b(the )?(amount|total|fee|fees|balance|price|cost|payment|paid|vat|subtotal|grand total)\b/i.test(text)) {
            return true;
        }

        if (/\b(the )?(amount|total fee|grand total|payment received|balance due|receipt number|invoice number|quotation number|due date|bill to|billed to|vat amount|subtotal)\b/i.test(text)) {
            return true;
        }

        if (/\b(vat|subtotal|grand total|amount due|amount paid|financial|breakdown|quotation)\b/i.test(text)) {
            return true;
        }

        if (/\b(all documents|all files|each document|every document|both documents|both files|compare|across all)\b/i.test(text)) {
            return true;
        }

        return /\b(amount|fee|total|balance|paid|payment|vat)\b.*\b(on|in|from|for)\b/i.test(text);
    }

    function looksLikeDocumentMessage(message) {
        var text = String(message || "").trim();
        if (!text) {
            return true;
        }

        return shouldSendDocumentContext(text);
    }

    function formatExcerptSpacing(text) {
        var out = String(text || "").trim();
        if (!out) {
            return "";
        }

        var labels = [
            "Bill To:",
            "Bill to:",
            "Ship To:",
            "Issue Date:",
            "Due Date:",
            "Due date:",
            "Invoice reference:",
            "Case reference",
            "Description",
            "Quantity",
            "Unit Price",
            "Subtotal",
            "VAT Amount",
            "VAT Total",
            "Total fee:",
            "Thank you"
        ];

        labels.forEach(function (label) {
            var escaped = label.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
            out = out.replace(new RegExp("\\s+(" + escaped + ")", "gi"), "\n\n$1");
        });

        out = out.replace(/(Bill To:)\s*/gi, "$1\n");
        out = out.replace(/(Bill to:)\s*/gi, "$1\n");

        out = out.replace(/\s+(RECEIPT\s+#)/gi, "\n\n$1");
        out = out.replace(/\s+(INVOICE\s+#)/gi, "\n\n$1");
        out = out.replace(/\s+(INV-\d{4}-)/gi, "\n\n$1");

        out = out.replace(/\s+([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/gi, "\n$1");
        out = out.replace(/\s+(\+\d{7,15})\b/g, "\n$1");
        out = out.replace(/,\s*(\d{4,6})\s+/g, ",\n$1 ");
        out = out.replace(/\s+([a-z]{4,})\s+(\+\d{7,})/g, "\n$1\n$2");
        out = out.replace(/([a-z0-9.-]+\.[a-z]{2,})\s+([a-z]{4,})\s+/gi, "$1\n$2\n");

        out = out.replace(/\n{3,}/g, "\n\n");

        return out.trim();
    }

    function excerptToDisplayHtml(lines) {
        var combined = Array.isArray(lines) ? lines.join(" ").replace(/\s+/g, " ").trim() : String(lines || "").trim();
        if (!combined) {
            return "";
        }

        var formatted = formatExcerptSpacing(combined);
        var sections = formatted.split(/\n\n+/);
        var html = "";

        sections.forEach(function (section) {
            section = section.trim();
            if (!section) {
                return;
            }

            html += '<p class="assistant-doc-excerpt__block">';
            html += escapeHtml(section).replace(/\n/g, "<br>");
            html += "</p>";
        });

        return html;
    }

    function formatDocumentAnalysis(text) {
        var raw = String(text || "").trim();
        if (!/^\*\*Document analysis\*\*/i.test(raw)) {
            return null;
        }

        var body = raw
            .replace(/\n\n\*\*Compliance flags detected\*\*[\s\S]*$/i, "")
            .trim();

        var browserBadge = /_\(extracted in browser\)_/i.test(body);
        var imageBadge = /_\(image\)_/i.test(body);
        var screenshotBadge = /_\(from screenshot\)_/i.test(body);

        body = body
            .replace(/^\*\*Document analysis\*\*\s*/i, "")
            .replace(/_\(extracted in browser\)_/gi, "")
            .replace(/_\(from screenshot\)_/gi, "")
            .replace(/_\(image\)_/gi, "")
            .trim();

        var summaryPart = "";
        var detailsPart = body;
        var excerptPart = "";

        var summarySplit = body.split(/\n\*\*Summary\*\*\n*/i);
        if (summarySplit.length > 1) {
            var afterSummary = summarySplit.slice(1).join("\n**Summary**\n");
            var restSplit = afterSummary.split(/\n\*\*(?:Key details extracted|Invoice details|Notable clauses|Extracted text)\*\*\n*/i);
            summaryPart = (restSplit[0] || "").trim();
            detailsPart = restSplit.length > 1 ? restSplit.slice(1).join("\n").trim() : "";
        }

        var sections = detailsPart.split(/\n\*\*Extracted text\*\*\n*/i);
        if (sections.length > 1) {
            detailsPart = (sections[0] || "").trim();
            excerptPart = (sections[1] || "").trim();
        } else if (!summaryPart) {
            detailsPart = (sections[0] || body).trim();
        }

        var summaryBullets = [];
        summaryPart.split(/\n/).forEach(function (line) {
            line = line.trim();
            if (!line) {
                return;
            }
            if (/^[-*•]\s+/.test(line) || /^\d+[.)]\s+/.test(line)) {
                summaryBullets.push(line.replace(/^[-*•]\s+/, "").replace(/^\d+[.)]\s+/, ""));
            } else if (summaryBullets.length === 0 || summaryPart.split(/\n/).length <= 3) {
                summaryBullets.push(line);
            }
        });

        var fields = [];
        detailsPart.split(/\n/).forEach(function (line) {
            line = line.trim();
            if (!line || /^\*\*(?:Key details extracted|Invoice details)\*\*$/i.test(line)) {
                return;
            }

            var match = line.match(/^•\s*\*\*([^*]+):\*\*\s*(.+)$/);
            if (match) {
                fields.push({ label: match[1].trim(), value: match[2].trim() });
            }
        });

        var excerptRaw = [];
        excerptPart.split(/\n/).forEach(function (line) {
            line = line.trim();
            if (line.indexOf("•") === 0) {
                excerptRaw.push(line.replace(/^•\s*/, ""));
            }
        });

        if (fields.length === 0 && excerptRaw.length === 0 && summaryBullets.length === 0) {
            return (
                '<div class="assistant-doc-card">'
                + '<div class="assistant-doc-card__header">'
                + '<span class="assistant-doc-card__title">Document analysis</span>'
                + (screenshotBadge ? '<span class="assistant-doc-card__badge assistant-doc-card__badge--shot">Screenshot</span>' : "")
                + (browserBadge ? '<span class="assistant-doc-card__badge">Browser</span>' : "")
                + (imageBadge ? '<span class="assistant-doc-card__badge">Image</span>' : "")
                + "</div>"
                + '<div class="assistant-doc-card__body">' + formatRichText(body) + "</div>"
                + "</div>"
            );
        }

        var html = '<div class="assistant-doc-card">';
        html += '<div class="assistant-doc-card__header">';
        html += '<span class="assistant-doc-card__title">Document analysis</span>';
        if (browserBadge) {
            html += '<span class="assistant-doc-card__badge">Browser</span>';
        }
        if (screenshotBadge) {
            html += '<span class="assistant-doc-card__badge assistant-doc-card__badge--shot">Screenshot</span>';
        }
        if (imageBadge) {
            html += '<span class="assistant-doc-card__badge assistant-doc-card__badge--image">Image</span>';
        }
        html += "</div>";

        if (summaryBullets.length) {
            html += '<div class="assistant-doc-card__section assistant-doc-card__section--summary">';
            html += '<h4 class="assistant-doc-card__section-title">Summary</h4>';
            html += '<ul class="assistant-doc-summary">';
            summaryBullets.forEach(function (item) {
                html += "<li>" + escapeHtml(item) + "</li>";
            });
            html += "</ul></div>";
        }

        if (fields.length) {
            var detailsTitle = /\*\*Invoice details\*\*/i.test(body) ? "Invoice details" : "Key details";
            html += '<div class="assistant-doc-card__section">';
            html += '<h4 class="assistant-doc-card__section-title">' + detailsTitle + '</h4>';
            html += '<dl class="assistant-doc-card__fields">';
            fields.forEach(function (field) {
                if (field.label === "Line items") {
                    html += '<div class="assistant-doc-row assistant-doc-row--list">';
                    html += "<dt>" + escapeHtml(field.label) + "</dt>";
                    html += '<dd><ul class="assistant-doc-line-items">';
                    field.value.split("\n").forEach(function (item) {
                        item = item.trim();
                        if (item) {
                            html += "<li>" + escapeHtml(item) + "</li>";
                        }
                    });
                    html += "</ul></dd></div>";
                    return;
                }

                if (field.label === "Document type") {
                    return;
                }

                html += '<div class="assistant-doc-row">';
                html += "<dt>" + escapeHtml(field.label) + "</dt>";
                html += "<dd>" + escapeHtml(field.value).replace(/\n/g, "<br>") + "</dd>";
                html += "</div>";
            });
            html += "</dl></div>";
        }

        var excerptHtml = excerptToDisplayHtml(excerptRaw);
        if (excerptHtml) {
            html += '<div class="assistant-doc-card__section">';
            html += '<h4 class="assistant-doc-card__section-title">Extracted text</h4>';
            html += '<div class="assistant-doc-excerpt">' + excerptHtml + "</div></div>";
        }

        html += "</div>";
        return html;
    }

    function formatRichText(text) {
        var documentHtml = formatDocumentAnalysis(text);
        if (documentHtml) {
            return documentHtml;
        }

        var safe = escapeHtml(text).replace(/\n/g, "<br>");

        safe = safe.replace(/\[([^\]]+)\]\(([^)]+)\)/g, safeMarkdownLink);

        safe = safe.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");

        safe = safe.replace(/_([^_]+)_/g, "<em>$1</em>");

        return safe;
    }



    function scrollToBottom() {

        messages.scrollTop = messages.scrollHeight;

    }



    function renderAlerts(alerts) {

        if (!alerts || !alerts.length) {

            return null;

        }



        var wrap = document.createElement("div");

        wrap.className = "assistant-alerts";



        alerts.forEach(function (alert) {

            var item = document.createElement("div");

            item.className = "assistant-alert assistant-alert--" + (alert.level || "warning");

            item.innerHTML = "<strong>" + escapeHtml(alert.title || "Alert") + "</strong><span>"

                + escapeHtml(alert.message || "") + "</span>";

            wrap.appendChild(item);

        });



        return wrap;

    }



    function renderDraft(draft) {

        if (!draft || !draft.id) {

            return null;

        }



        var card = document.createElement("div");

        card.className = "assistant-draft-card";

        card.setAttribute("data-draft-id", draft.id);



        var header = document.createElement("div");

        header.className = "assistant-draft-card__header";

        header.innerHTML = '<span class="assistant-draft-card__badge">Draft preview</span>'

            + '<span class="assistant-draft-card__action">' + escapeHtml((draft.action || "action").replace(/_/g, " ")) + "</span>";

        card.appendChild(header);



        var dl = document.createElement("dl");

        dl.className = "assistant-draft-card__fields";

        Object.keys(draft.preview || {}).forEach(function (key) {

            var row = document.createElement("div");

            row.className = "assistant-draft-row";

            row.innerHTML = "<dt>" + escapeHtml(key) + "</dt><dd>" + escapeHtml(draft.preview[key]) + "</dd>";

            dl.appendChild(row);

        });

        card.appendChild(dl);



        var confirmBtn = document.createElement("button");

        confirmBtn.type = "button";

        confirmBtn.className = "btn btn-primary btn-sm assistant-confirm-btn";

        confirmBtn.setAttribute("data-draft-id", draft.id);

        confirmBtn.textContent = "Confirm";
        if (readOnly) {
            confirmBtn.disabled = true;
            confirmBtn.title = "Read-only account";
        }

        card.appendChild(confirmBtn);



        return card;

    }



    function createMessageActions(role) {
        var actions = document.createElement("div");
        actions.className = "assistant-message__actions";

        var copyBtn = document.createElement("button");
        copyBtn.type = "button";
        copyBtn.className = "assistant-message-action";
        copyBtn.setAttribute("data-action", "copy");
        copyBtn.setAttribute("title", "Copy");
        copyBtn.setAttribute("aria-label", "Copy message");
        copyBtn.innerHTML = '<i class="bi bi-copy"></i>';
        actions.appendChild(copyBtn);

        if (role === "user") {
            var editBtn = document.createElement("button");
            editBtn.type = "button";
            editBtn.className = "assistant-message-action";
            editBtn.setAttribute("data-action", "edit");
            editBtn.setAttribute("title", "Edit");
            editBtn.setAttribute("aria-label", "Edit message");
            editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
            actions.appendChild(editBtn);
        }

        return actions;
    }

    function copyPlainText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var textarea = document.createElement("textarea");
            textarea.value = text;
            textarea.setAttribute("readonly", "");
            textarea.style.position = "fixed";
            textarea.style.left = "-9999px";
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand("copy");
                document.body.removeChild(textarea);
                resolve();
            } catch (error) {
                document.body.removeChild(textarea);
                reject(error);
            }
        });
    }

    function flashCopyFeedback(button) {
        var icon = button.querySelector("i");
        if (!icon) {
            return;
        }

        icon.className = "bi bi-check2";
        window.setTimeout(function () {
            icon.className = "bi bi-copy";
        }, 1500);
    }

    function countMessageTurns() {
        return messages.querySelectorAll(".assistant-message[data-turn-index]").length;
    }

    function buildMessageWrap(role, turnIndex, plainText) {
        var wrap = document.createElement("div");
        wrap.className = "assistant-message assistant-message-" + (role === "user" ? "user" : "bot");
        wrap.setAttribute("data-turn-index", String(turnIndex));
        wrap.setAttribute("data-content", plainText);

        var content = document.createElement("div");
        content.className = "assistant-message__content";
        wrap.appendChild(content);

        return { wrap: wrap, content: content };
    }

    function renderChatAttachments(attachments) {
        if (!attachments || !attachments.length) {
            return null;
        }

        var wrap = document.createElement("div");
        wrap.className = "assistant-chat-attachments";

        attachments.forEach(function (attachment) {
            var name = attachment && attachment.name ? attachment.name : "File";
            var meta = attachmentKind(name);
            var kind = attachment && attachment.kind ? attachment.kind : meta.kind;

            var chip = document.createElement("div");
            chip.className = "assistant-chat-attachment";
            chip.innerHTML = '<span class="assistant-chat-attachment__icon assistant-chat-attachment__icon--'
                + kind + '" aria-hidden="true"><i class="bi ' + meta.icon + '"></i></span>'
                + '<span class="assistant-chat-attachment__meta">'
                + '<span class="assistant-chat-attachment__name">' + escapeHtml(name) + "</span>"
                + '<span class="assistant-chat-attachment__type">' + escapeHtml(meta.type) + "</span>"
                + "</span>";
            wrap.appendChild(chip);
        });

        return wrap;
    }

    function buildUserPlainContent(text, attachments) {
        var parts = [];

        if (attachments && attachments.length) {
            attachments.forEach(function (attachment) {
                parts.push("[Attached: " + (attachment.name || "file") + "]");
            });
        }

        if (text) {
            parts.push(text);
        }

        return parts.join("\n");
    }

    function appendUserMessage(text, turnIndex, attachments) {
        var index = typeof turnIndex === "number" ? turnIndex : countMessageTurns();
        var messageText = String(text || "").trim();
        var plainText = buildUserPlainContent(messageText, attachments);
        var built = buildMessageWrap("user", index, plainText);
        var attachEl = renderChatAttachments(attachments);

        if (attachEl) {
            built.content.appendChild(attachEl);
        }

        if (messageText) {
            var bubble = document.createElement("div");
            bubble.className = "assistant-bubble";
            bubble.textContent = messageText;
            built.content.appendChild(bubble);
        }

        built.content.appendChild(createMessageActions("user"));
        messages.appendChild(built.wrap);
        scrollToBottom();
    }

    function appendThinkingMessage() {
        var built = buildMessageWrap("assistant", countMessageTurns(), "");
        built.wrap.className += " assistant-message-thinking";
        built.wrap.setAttribute("data-thinking", "1");

        var bubble = document.createElement("div");
        bubble.className = "assistant-bubble assistant-bubble-thinking";
        bubble.setAttribute("role", "status");
        bubble.setAttribute("aria-live", "polite");
        bubble.setAttribute("aria-label", "Thinking");
        bubble.innerHTML = '<span class="assistant-thinking__label">Thinking</span>'
            + '<span class="assistant-thinking__dots" aria-hidden="true"><i></i><i></i><i></i></span>';
        built.content.appendChild(bubble);
        messages.appendChild(built.wrap);
        scrollToBottom();

        return built.wrap;
    }

    function removeThinkingMessage(el) {
        if (el && el.parentNode === messages) {
            el.remove();
        }
    }

    function appendAssistantTurn(turn, turnIndex) {
        var index = typeof turnIndex === "number" ? turnIndex : countMessageTurns();
        var plainText = turn.content || "";
        var built = buildMessageWrap("assistant", index, plainText);

        var bubble = document.createElement("div");
        bubble.className = "assistant-bubble assistant-bubble-rich";
        var formatted = formatRichText(plainText);
        if (formatted.indexOf("assistant-doc-card") !== -1) {
            bubble.className += " assistant-bubble--document";
        }
        bubble.innerHTML = formatted;
        built.content.appendChild(bubble);
        built.content.appendChild(createMessageActions("assistant"));

        var alerts = renderAlerts(turn.alerts);
        if (alerts) {
            built.wrap.appendChild(alerts);
        }

        var draft = renderDraft(turn.draft);
        if (draft) {
            built.wrap.appendChild(draft);
        }

        messages.appendChild(built.wrap);
        scrollToBottom();
    }



    function showWelcome() {
        messages.innerHTML = getWelcomeHtml();
    }



    function renderMessages(history) {

        messages.innerHTML = "";



        if (!history || !history.length) {

            showWelcome();

            return;

        }



        history.forEach(function (turn, index) {

            if (turn.role === "user") {

                appendUserMessage(turn.content || "", index, turn.attachments || []);

            } else if (turn.role === "assistant") {

                appendAssistantTurn(turn, index);

            }

        });

    }



    function formatLibraryTime(value) {

        if (!value) {

            return "";

        }



        var date = new Date(value.replace(" ", "T"));

        if (isNaN(date.getTime())) {

            return value;

        }



        var now = new Date();

        var sameDay = date.toDateString() === now.toDateString();

        if (sameDay) {

            return date.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });

        }



        return date.toLocaleDateString([], { month: "short", day: "numeric" });

    }



    function renderLibrary(conversations, activeId) {

        if (!libraryList) {

            return;

        }



        activeConversationId = activeId || null;

        libraryList.innerHTML = "";



        if (!conversations || !conversations.length) {

            var empty = document.createElement("p");

            empty.className = "assistant-library-empty text-muted small px-3 py-3 mb-0";

            empty.id = "assistantLibraryEmpty";

            empty.textContent = "No chats yet. Start a conversation to save it here.";

            libraryList.appendChild(empty);

            return;

        }



        conversations.forEach(function (item) {

            var row = document.createElement("div");

            row.className = "assistant-library-item" + (activeConversationId === item.id ? " is-active" : "");

            row.setAttribute("role", "listitem");

            row.setAttribute("data-id", String(item.id));



            var mainBtn = document.createElement("button");

            mainBtn.type = "button";

            mainBtn.className = "assistant-library-item__main";

            mainBtn.setAttribute("data-action", "load");



            var title = document.createElement("span");

            title.className = "assistant-library-item__title";

            title.textContent = item.title || "New chat";

            mainBtn.appendChild(title);



            var preview = document.createElement("span");

            preview.className = "assistant-library-item__preview";

            preview.textContent = item.preview || "";

            mainBtn.appendChild(preview);



            if (item.updated_at) {

                var time = document.createElement("time");

                time.className = "assistant-library-item__time";

                time.setAttribute("datetime", item.updated_at);

                time.textContent = formatLibraryTime(item.updated_at);

                mainBtn.appendChild(time);

            }



            row.appendChild(mainBtn);



            var actions = document.createElement("div");

            actions.className = "assistant-library-item__actions";



            var renameBtn = document.createElement("button");

            renameBtn.type = "button";

            renameBtn.className = "assistant-library-action";

            renameBtn.setAttribute("data-action", "rename");

            renameBtn.setAttribute("title", "Rename");

            renameBtn.innerHTML = '<i class="bi bi-pencil"></i>';

            actions.appendChild(renameBtn);



            var deleteBtn = document.createElement("button");

            deleteBtn.type = "button";

            deleteBtn.className = "assistant-library-action assistant-library-action--danger";

            deleteBtn.setAttribute("data-action", "delete");

            deleteBtn.setAttribute("title", "Delete");

            deleteBtn.innerHTML = '<i class="bi bi-trash3"></i>';

            actions.appendChild(deleteBtn);



            row.appendChild(actions);

            libraryList.appendChild(row);

        });

    }



    function syncLibraryFromResponse(data) {

        if (!data || !libraryList) {

            return;

        }



        if (typeof data.conversation_id !== "undefined") {

            activeConversationId = data.conversation_id;

        }



        if (Array.isArray(data.conversations)) {

            renderLibrary(data.conversations, activeConversationId);

        }

    }



    function setBusy(state) {

        busy = state;

        input.disabled = readOnly || state || sendBtn.hasAttribute("data-offline");

        sendBtn.disabled = readOnly || state || sendBtn.hasAttribute("data-offline");

        if (attachBtn) {

            attachBtn.disabled = readOnly || state || sendBtn.hasAttribute("data-offline");

        }

    }



    function updateAssistantAvailability(status) {
        if (!status) {
            return;
        }

        var hardDisabled = status.enabled === false;
        if (hardDisabled) {
            sendBtn.setAttribute("data-offline", "1");
            input.disabled = true;
            sendBtn.disabled = true;
            if (attachBtn) {
                attachBtn.disabled = true;
            }
            document.querySelectorAll(".assistant-prompt-btn").forEach(function (btn) {
                btn.disabled = true;
            });
        } else if (!busy && !readOnly) {
            sendBtn.removeAttribute("data-offline");
            input.disabled = false;
            sendBtn.disabled = false;
            if (attachBtn) {
                attachBtn.disabled = false;
            }
            document.querySelectorAll(".assistant-prompt-btn").forEach(function (btn) {
                btn.disabled = false;
            });
        }
    }



    function parseResponsePayload(response) {
        return response.text().then(function (raw) {
            var data = null;
            if (raw) {
                try {
                    data = JSON.parse(raw);
                } catch (error) {
                    data = null;
                    // Some PHP setups emit warnings/notices before JSON.
                    // Attempt to recover by parsing from the first JSON object.
                    try {
                        var trimmed = String(raw).trim();
                        var startObj = trimmed.indexOf("{");
                        var startArr = trimmed.indexOf("[");
                        var start = -1;
                        if (startObj !== -1 && startArr !== -1) {
                            start = Math.min(startObj, startArr);
                        } else {
                            start = startObj !== -1 ? startObj : startArr;
                        }

                        if (start >= 0) {
                            var endObj = trimmed.lastIndexOf("}");
                            var endArr = trimmed.lastIndexOf("]");
                            var end = -1;
                            if (endObj !== -1 && endArr !== -1) {
                                end = Math.max(endObj, endArr);
                            } else {
                                end = endObj !== -1 ? endObj : endArr;
                            }

                            if (end >= start) {
                                data = JSON.parse(trimmed.slice(start, end + 1));
                            }
                        }
                    } catch (e) {
                        data = null;
                    }
                }
            }

            if (!data || typeof data !== "object") {
                data = {
                    success: false,
                    message: "Unexpected server response (" + response.status + ")."
                        + " Server reply: " + String(raw || "").trim().slice(0, 180)
                };
            }

            if (!response.ok && (!data.message || typeof data.message !== "string")) {
                data.message = "Request failed (" + response.status + ").";
            }

            return { ok: response.ok, data: data };
        });
    }

    function postJson(payload) {
        return fetch(apiUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-Token": csrfToken,
                "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify(payload)
        }).then(parseResponsePayload);
    }

    function postForm(formData) {
        formData.append("_csrf_token", csrfToken);

        return fetch(apiUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "X-CSRF-Token": csrfToken,
                "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
        }).then(parseResponsePayload);
    }

    function buildChatPayload(message) {
        var payload = { action: "chat", message: message };

        if (shouldSendDocumentContext(message)) {
            var items = loadActiveDocumentItems();
            if (items.length) {
                payload.document_items = items;
                if (items.length === 1) {
                    payload.document_text = items[0].text;
                    if (items[0].source) {
                        payload.document_source = items[0].source;
                    }
                }
            }
        }

        return payload;
    }

    function rememberDocumentContext(preparedEntries, responseData) {
        var items = [];

        if (responseData && Array.isArray(responseData.document_items) && responseData.document_items.length) {
            items = responseData.document_items;
        } else if (preparedEntries && preparedEntries.length) {
            preparedEntries.forEach(function (entry) {
                if (!entry || !entry.documentText) {
                    return;
                }

                items.push({
                    id: entry.id || "",
                    name: entry.name || "Document",
                    text: entry.documentText,
                    source: entry.fromScreenshot ? "screenshot" : (entry.source || "upload")
                });
            });
        } else if (responseData && responseData.document_context) {
            items = [{
                name: "Uploaded document",
                text: responseData.document_context,
                source: "upload"
            }];
        }

        saveActiveDocumentItems(items);
    }



    function handleChatResponse(result) {

        if (!result.ok || !result.data.success) {

            throw new Error((result.data && result.data.message) || "Request failed.");

        }



        updateAssistantAvailability(result.data.status);

        syncLibraryFromResponse(result.data);

        renderMessages(result.data.messages || []);

    }



    form.addEventListener("submit", function (event) {

        event.preventDefault();



        if (busy) {

            return;

        }



        var text = input.value.trim();

        var hasPending = pendingAttachments.length > 0;

        if (hasPending && pendingAttachments.some(function (entry) {
            return entry.status === "reading" || entry.status === "pending";
        })) {
            appendAssistantTurn({
                content: "Please wait until attached files finish **reading** before sending."
            });
            return;
        }

        if (!text && !hasPending) {

            return;

        }

        if (!hasPending && /\b(extract|scan|read|ocr|analy[sz]e)\b/i.test(text)) {

            appendAssistantTurn({
                content: "Attach the **PDFs, screenshots, or letters** with the paperclip first, then send your message. "
                    + "You can attach up to " + MAX_PENDING_ATTACHMENTS + " files in one message."
            });

            return;

        }



        if (messages.querySelector(".assistant-welcome")) {

            messages.innerHTML = "";

        }

        var attachmentSnapshot = hasPending
            ? pendingAttachments.map(function (entry) {
                var meta = attachmentKind(entry.name);
                return { name: entry.name, kind: meta.kind };
            })
            : [];

        appendUserMessage(text, undefined, attachmentSnapshot);

        var pendingUserMessage = messages.lastElementChild;
        var thinkingEl = appendThinkingMessage();

        input.value = "";

        setBusy(true);

        var defaultPlaceholder = input.getAttribute("placeholder") || "";
        var entriesToProcess = hasPending ? pendingAttachments.slice() : [];
        var processAttachments = hasPending;
        var preparePromise = Promise.resolve([]);

        if (processAttachments) {
            if (entriesToProcess.length === 1) {
                input.placeholder = "Reading file…";
            } else {
                input.placeholder = "Reading " + entriesToProcess.length + " files…";
            }

            preparePromise = prepareAllAttachments(entriesToProcess);
        }



        preparePromise

            .then(function (preparedEntries) {

                var hasPreparedFile = preparedEntries.some(function (entry) { return !!(entry && entry.file); });
                var hasPreparedText = preparedEntries.some(function (entry) {
                    return !!(entry && entry.documentText);
                });

                if (hasPreparedFile || hasPreparedText) {

                    var formData = new FormData();

                    formData.append("action", "chat");

                    formData.append("message", text);

                    var documentItems = [];

                    preparedEntries.forEach(function (entry) {
                        if (entry.documentText) {
                            documentItems.push({
                                id: entry.id || "",
                                name: entry.name || "Document",
                                text: entry.documentText,
                                source: entry.fromScreenshot ? "screenshot" : (entry.source || "")
                            });
                        }

                        if (entry.file) {
                            formData.append("attachments[]", entry.file, entry.file.name);
                        }
                    });

                    if (documentItems.length) {
                        formData.append("document_items", JSON.stringify(documentItems));
                    }

                    if (documentItems.length === 1) {
                        formData.append("document_text", documentItems[0].text);
                        if (documentItems[0].source) {
                            formData.append("document_source", documentItems[0].source);
                        }
                    }

                    return postForm(formData).then(function (result) {

                        return { result: result, sentDocument: true, preparedEntries: preparedEntries };

                    });

                }

                if (!text) {

                    throw new Error(hasPending ? "No content could be read from these files." : "Enter a message.");

                }

                return postJson(buildChatPayload(text)).then(function (result) {

                    return { result: result, sentDocument: false };

                });

            })

            .then(function (payload) {

                removeThinkingMessage(thinkingEl);

                handleChatResponse(payload.result);

                if (payload.sentDocument) {
                    rememberDocumentContext(payload.preparedEntries, payload.result && payload.result.data);
                    clearAllAttachments();
                } else if (payload.result && payload.result.data) {
                    if (Array.isArray(payload.result.data.document_items) && payload.result.data.document_items.length) {
                        saveActiveDocumentItems(payload.result.data.document_items);
                    } else if (payload.result.data.document_context) {
                        saveActiveDocumentItems([{
                            name: "Uploaded document",
                            text: payload.result.data.document_context,
                            source: "upload"
                        }]);
                    }
                }

            })

            .catch(function (error) {

                removeThinkingMessage(thinkingEl);

                if (pendingUserMessage && pendingUserMessage.parentNode === messages) {

                    pendingUserMessage.remove();

                }

                if (text) {
                    input.value = text;
                    autoGrowInput();
                }

                var errMsg = error && error.message ? error.message : "Unable to reach the assistant.";
                if (errMsg === "Failed to fetch" || errMsg.indexOf("NetworkError") !== -1) {
                    errMsg = "Could not reach the server. Check that WAMP is running, then try again. "
                        + "If you attached large files, try fewer or smaller PDFs.";
                }

                appendAssistantTurn({ content: "Error: " + errMsg });

            })

            .finally(function () {

                setBusy(false);
                input.placeholder = defaultPlaceholder;

                input.focus();

            });

    });



    messages.addEventListener("click", function (event) {

        var actionBtn = event.target.closest(".assistant-message-action");
        if (actionBtn && !busy) {
            var messageWrap = actionBtn.closest(".assistant-message");
            if (!messageWrap) {
                return;
            }

            var action = actionBtn.getAttribute("data-action");
            var plainText = messageWrap.getAttribute("data-content") || "";

            if (action === "copy") {
                copyPlainText(plainText)
                    .then(function () {
                        flashCopyFeedback(actionBtn);
                    })
                    .catch(function () {
                        window.alert("Could not copy message.");
                    });
                return;
            }

            if (action === "edit") {
                var turnIndex = parseInt(messageWrap.getAttribute("data-turn-index"), 10);
                if (isNaN(turnIndex) || turnIndex < 0) {
                    return;
                }

                setBusy(true);

                postJson({ action: "edit_message", index: turnIndex })
                    .then(function (result) {
                        if (!result.ok || !result.data.success) {
                            throw new Error((result.data && result.data.message) || "Could not edit message.");
                        }

                        renderMessages(result.data.messages || []);
                        input.value = plainText;
                        autoGrowInput();
                        input.focus();
                        if (typeof input.setSelectionRange === "function") {
                            var end = plainText.length;
                            input.setSelectionRange(end, end);
                        }
                    })
                    .catch(function (error) {
                        window.alert(error.message || "Could not edit message.");
                    })
                    .finally(function () {
                        setBusy(false);
                        if (plainText.trim()) {
                            if (typeof form.requestSubmit === "function") {
                                form.requestSubmit();
                            } else {
                                form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
                            }
                        }
                    });
            }

            return;
        }

        var btn = event.target.closest(".assistant-confirm-btn");

        if (!btn || busy) {

            return;

        }



        var draftId = btn.getAttribute("data-draft-id");

        if (!draftId || btn.disabled) {

            return;

        }



        if (!window.confirm("Apply this change to the system?")) {

            return;

        }



        btn.disabled = true;

        setBusy(true);



        postJson({ action: "confirm", draft_id: draftId })

            .then(function (result) {

                if (!result.ok || !result.data.success) {

                    throw new Error((result.data && result.data.message) || "Confirmation failed.");

                }



                var card = btn.closest(".assistant-draft-card");

                if (card) {

                    card.classList.add("assistant-draft-card--confirmed");

                    btn.remove();

                }



                handleChatResponse(result);

            })

            .catch(function (error) {

                window.alert(error.message || "Could not confirm draft.");

                btn.disabled = false;

            })

            .finally(function () {

                setBusy(false);

            });

    });



    function startNewChat() {

        if (busy) {

            return;

        }



        setBusy(true);

        postJson({ action: "new_chat" })

            .then(function (result) {

                if (!result.ok || !result.data.success) {

                    throw new Error((result.data && result.data.message) || "Could not start a new chat.");

                }

                renderMessages([]);

                clearActiveDocumentItems();

                updateAssistantAvailability(result.data.status);

                syncLibraryFromResponse(result.data);

            })

            .catch(function (error) {

                window.alert(error.message || "Could not start a new chat.");

            })

            .finally(function () {

                setBusy(false);

                input.focus();

            });

    }



    function setLibraryOpen(open, persist) {
        if (!libraryCard || !assistantLayout) {
            return;
        }

        libraryCard.hidden = !open;
        assistantLayout.classList.toggle("assistant-layout--library-open", open);

        if (libraryBackdrop) {
            libraryBackdrop.hidden = !open;
        }

        if (libraryToggleBtn) {
            libraryToggleBtn.classList.toggle("is-active", open);
            libraryToggleBtn.setAttribute("aria-expanded", open ? "true" : "false");
        }

        if (persist !== false) {
            try {
                localStorage.setItem(libraryCollapsedKey, open ? "0" : "1");
            } catch (error) {
                /* ignore */
            }
        }
    }

    function toggleLibrary() {
        var isOpen = assistantLayout && assistantLayout.classList.contains("assistant-layout--library-open");
        setLibraryOpen(!isOpen);
    }

    if (libraryToggleBtn) {
        libraryToggleBtn.addEventListener("click", toggleLibrary);
    }

    if (libraryCloseBtn) {
        libraryCloseBtn.addEventListener("click", function () {
            setLibraryOpen(false);
        });
    }

    if (libraryBackdrop) {
        libraryBackdrop.addEventListener("click", function () {
            setLibraryOpen(false);
        });
    }

    if (newChatBtn) {
        newChatBtn.addEventListener("click", startNewChat);
    }

    try {
        setLibraryOpen(localStorage.getItem(libraryCollapsedKey) === "0", false);
    } catch (error) {
        setLibraryOpen(false, false);
    }



    if (libraryList) {

        libraryList.addEventListener("click", function (event) {

            var actionBtn = event.target.closest("[data-action]");

            if (!actionBtn || busy) {

                return;

            }



            var item = actionBtn.closest(".assistant-library-item");

            if (!item) {

                return;

            }



            var conversationId = parseInt(item.getAttribute("data-id"), 10);

            if (!conversationId) {

                return;

            }



            var action = actionBtn.getAttribute("data-action");



            if (action === "load") {

                setBusy(true);

                postJson({ action: "load_chat", conversation_id: conversationId })

                    .then(function (result) {

                        if (!result.ok || !result.data.success) {

                            throw new Error((result.data && result.data.message) || "Could not load chat.");

                        }

                        handleChatResponse(result);

                    })

                    .catch(function (error) {

                        window.alert(error.message || "Could not load chat.");

                    })

                    .finally(function () {

                        setBusy(false);

                    });

                return;

            }



            if (action === "rename") {

                event.stopPropagation();

                var currentTitle = item.querySelector(".assistant-library-item__title");

                var nextTitle = window.prompt("Rename chat", currentTitle ? currentTitle.textContent : "");

                if (nextTitle === null) {

                    return;

                }



                nextTitle = nextTitle.trim();

                if (!nextTitle) {

                    window.alert("Title cannot be empty.");

                    return;

                }



                setBusy(true);

                postJson({ action: "rename_chat", conversation_id: conversationId, title: nextTitle })

                    .then(function (result) {

                        if (!result.ok || !result.data.success) {

                            throw new Error((result.data && result.data.message) || "Could not rename chat.");

                        }

                        syncLibraryFromResponse(result.data);

                    })

                    .catch(function (error) {

                        window.alert(error.message || "Could not rename chat.");

                    })

                    .finally(function () {

                        setBusy(false);

                    });

                return;

            }



            if (action === "delete") {

                event.stopPropagation();

                if (!window.confirm("Delete this chat permanently?")) {

                    return;

                }



                setBusy(true);

                postJson({ action: "delete_chat", conversation_id: conversationId })

                    .then(function (result) {

                        if (!result.ok || !result.data.success) {

                            throw new Error((result.data && result.data.message) || "Could not delete chat.");

                        }

                        renderMessages(result.data.messages || []);

                        updateAssistantAvailability(result.data.status);

                        syncLibraryFromResponse(result.data);

                    })

                    .catch(function (error) {

                        window.alert(error.message || "Could not delete chat.");

                    })

                    .finally(function () {

                        setBusy(false);

                    });

            }

        });

    }



    if (attachBtn && attachInput) {

        attachBtn.addEventListener("click", function () {

            attachInput.click();

        });



        attachInput.addEventListener("change", function () {

            if (attachInput.files && attachInput.files.length) {
                addPendingFiles(attachInput.files);
            }

            attachInput.value = "";

        });

    }



    document.querySelectorAll(".assistant-prompt-btn").forEach(function (btn) {

        btn.addEventListener("click", function () {

            if (busy || readOnly) {
                return;
            }

            if (sendBtn.hasAttribute("data-offline")) {
                return;
            }

            var prompt = (btn.getAttribute("data-prompt") || "").trim();
            if (!prompt) {
                return;
            }

            input.value = prompt;
            autoGrowInput();

            if (messages.querySelector(".assistant-welcome")) {
                messages.innerHTML = "";
            }

            if (typeof form.requestSubmit === "function") {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
            }

        });

    });



    document.querySelectorAll(".assistant-bubble-rich[data-rich]").forEach(function (bubble) {

        var plain = bubble.textContent || "";
        var formatted = formatRichText(plain);
        bubble.innerHTML = formatted;
        if (formatted.indexOf("assistant-doc-card") !== -1) {
            bubble.classList.add("assistant-bubble--document");
        }

    });



    function autoGrowInput() {
        input.style.height = "var(--assistant-input-height)";
        input.style.height = Math.min(input.scrollHeight, 128) + "px";
    }

    autoGrowInput();

    input.addEventListener("input", autoGrowInput);

    input.addEventListener("keydown", function (event) {

        if (event.key === "Enter" && !event.shiftKey) {

            event.preventDefault();

            form.requestSubmit();

        }

    });



    var activeItem = libraryList ? libraryList.querySelector(".assistant-library-item.is-active") : null;

    if (activeItem) {

        activeConversationId = parseInt(activeItem.getAttribute("data-id"), 10) || null;

    }

    var initialEnabled = form.getAttribute("data-enabled");
    if (initialEnabled !== null) {
        updateAssistantAvailability({
            enabled: initialEnabled !== "0",
            portal_enabled: true
        });
    }



    scrollToBottom();

})();


