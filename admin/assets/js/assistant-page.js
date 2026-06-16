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

    var attachLabel = document.getElementById("assistantAttachLabel");
    var attachBar = document.getElementById("assistantAttachBar");
    var attachClearBtn = document.getElementById("assistantAttachClearBtn");

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



    function clearAttachment() {
        if (attachInput) {
            attachInput.value = "";
        }

        if (attachBar) {
            attachBar.classList.add("d-none");
        }

        if (attachLabel) {
            attachLabel.textContent = "";
        }
    }

    function showAttachment(fileName) {
        if (!attachBar || !attachLabel) {
            return;
        }

        attachLabel.textContent = "Attached: " + fileName;
        attachBar.classList.remove("d-none");
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



    function formatRichText(text) {

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

    function appendUserMessage(text, turnIndex) {
        var index = typeof turnIndex === "number" ? turnIndex : countMessageTurns();
        var built = buildMessageWrap("user", index, text);
        var bubble = document.createElement("div");
        bubble.className = "assistant-bubble";
        bubble.textContent = text;
        built.content.appendChild(bubble);
        built.content.appendChild(createMessageActions("user"));
        messages.appendChild(built.wrap);
        scrollToBottom();
    }

    function appendAssistantTurn(turn, turnIndex) {
        var index = typeof turnIndex === "number" ? turnIndex : countMessageTurns();
        var plainText = turn.content || "";
        var built = buildMessageWrap("assistant", index, plainText);

        var bubble = document.createElement("div");
        bubble.className = "assistant-bubble assistant-bubble-rich";
        bubble.innerHTML = formatRichText(plainText);
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

                appendUserMessage(turn.content || "", index);

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

        var unavailable = status.enabled === false;
        if (unavailable) {
            sendBtn.setAttribute("data-offline", "1");
            input.disabled = true;
            sendBtn.disabled = true;
            if (attachBtn) {
                attachBtn.disabled = true;
            }
        } else if (!busy && !readOnly) {
            sendBtn.removeAttribute("data-offline");
            input.disabled = false;
            sendBtn.disabled = false;
            if (attachBtn) {
                attachBtn.disabled = false;
            }
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
                }
            }

            if (!data || typeof data !== "object") {
                data = {
                    success: false,
                    message: "Unexpected server response (" + response.status + ")."
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
            headers: {
                "X-CSRF-Token": csrfToken,
                "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
        }).then(parseResponsePayload);
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

        var hasFile = attachInput && attachInput.files && attachInput.files.length > 0;



        if (!text && !hasFile) {

            return;

        }



        if (messages.querySelector(".assistant-welcome")) {

            messages.innerHTML = "";

        }



        appendUserMessage(text || "[Document upload]");

        var pendingUserMessage = messages.lastElementChild;

        input.value = "";

        setBusy(true);



        var request;

        if (hasFile) {

            var formData = new FormData();

            formData.append("action", "chat");

            formData.append("message", text);

            formData.append("attachment", attachInput.files[0]);

            request = postForm(formData);

        } else {

            request = postJson({ action: "chat", message: text });

        }



        request

            .then(function (result) {

                handleChatResponse(result);
                if (hasFile) {
                    clearAttachment();
                }

            })

            .catch(function (error) {

                if (pendingUserMessage && pendingUserMessage.parentNode === messages) {

                    pendingUserMessage.remove();

                }

                if (text) {
                    input.value = text;
                    autoGrowInput();
                }

                appendAssistantTurn({ content: "Error: " + (error.message || "Unable to reach the assistant.") });

            })

            .finally(function () {

                setBusy(false);

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

            if (attachInput.files && attachInput.files[0]) {

                showAttachment(attachInput.files[0].name);

            } else {

                clearAttachment();

            }

        });

    }



    if (attachClearBtn) {

        attachClearBtn.addEventListener("click", function () {

            clearAttachment();

        });

    }



    document.querySelectorAll(".assistant-prompt-btn").forEach(function (btn) {

        btn.addEventListener("click", function () {

            input.value = btn.getAttribute("data-prompt") || "";
            autoGrowInput();
            input.focus();

        });

    });



    document.querySelectorAll(".assistant-bubble-rich[data-rich]").forEach(function (bubble) {

        bubble.innerHTML = formatRichText(bubble.textContent || "");

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
    var initialOllamaOnline = form.getAttribute("data-ollama-online");
    if (initialEnabled !== null || initialOllamaOnline !== null) {
        updateAssistantAvailability({
            enabled: initialEnabled !== "0",
            ollama_online: initialOllamaOnline !== "0"
        });
    }



    scrollToBottom();

})();


