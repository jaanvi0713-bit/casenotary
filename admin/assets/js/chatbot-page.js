/**
 * Admin AI Assistant page
 */
(function() {
    "use strict";

    function ready(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    ready(function() {
        const app = document.getElementById("chatbotApp");
        if (!app) return;

        const chatForm = document.getElementById("chatForm");
        const chatInput = document.getElementById("chatInput");
        const chatMessages = document.getElementById("chatMessages");
        const sendBtn = document.getElementById("chatSendBtn");
        const attachBtn = document.getElementById("chatAttachBtn");
        const attachInput = document.getElementById("chatAttachments");
        const attachPreview = document.getElementById("chatAttachmentPreview");
        const chatPromptsEl = document.getElementById("chatPrompts");
        const chatHistoryList = document.getElementById("chatHistoryList");
        const newChatBtn = document.getElementById("chatNewBtn");
        const chatSidebarClose = document.getElementById("chatSidebarClose");
        const chatSidebarOpen = document.getElementById("chatSidebarOpen");
        const csrfInput = chatForm ? chatForm.querySelector('[name="_csrf_token"]') : null;
        const apiUrl = app.getAttribute("data-api-url") || "../api/chatbot.php";
        const userId = app.getAttribute("data-user-id") || "0";
        const promptsKey = "casenotary_chat_prompts_" + userId;
        const activeChatKey = "casenotary_active_chat_" + userId;
        const sidebarKey = "casenotary_chat_sidebar_" + userId;
        const maxAttachments = 10;

        let selectedFiles = [];
        let lastUserPrompt = "";
        let isSending = false;
        let activeConversationId = 0;
        let conversations = [];
        let pendingSyncOnSend = false;
        const chatEditHint = document.getElementById("chatEditHint");

        function getCsrfToken() {
            return csrfInput ? csrfInput.value : "";
        }

        function defaultPromptList() {
            const raw = window.CHATBOT_DEFAULT_PROMPTS;
            return Array.isArray(raw) ? raw.slice() : [];
        }

        let promptCatalog = defaultPromptList();

        function escapeHtml(text) {
            const div = document.createElement("div");
            div.textContent = text == null ? "" : String(text);
            return div.innerHTML;
        }

        function escapeAttr(text) {
            return String(text == null ? "" : text)
                .replace(/&/g, "&amp;")
                .replace(/"/g, "&quot;")
                .replace(/</g, "&lt;");
        }

        function stripMarkdown(text) {
            return (text || "")
                .replace(/\*\*(.*?)\*\*/g, "$1")
                .replace(/\*(.*?)\*/g, "$1")
                .replace(/\[([^\]]+)\]\([^)]+\)/g, "$1")
                .replace(/_([^_]+)_/g, "$1");
        }

        function bulletsToTable(text) {
            return stripMarkdown(text).split("\n")
                .map(function(line) { return line.trim(); })
                .filter(function(line) { return line.indexOf("•") === 0 || line.indexOf("-") === 0; })
                .map(function(line) { return line.replace(/^[•\-]\s*/, ""); })
                .join("\n");
        }

        function hasBulletList(text) {
            return /^[\s]*[•\-]/m.test(text || "");
        }

        function formatTextSegment(text) {
            let t = text.replace(/\*\*(.+?)\*\*/g, "<<<B>>>$1<<</B>>>");
            t = t.replace(/\*(.+?)\*/g, "<<<I>>>$1<<</I>>>");
            t = escapeHtml(t);
            t = t.replace(/&lt;&lt;&lt;B&gt;&gt;&gt;/g, "<strong>").replace(/&lt;&lt;&lt;\/B&gt;&gt;&gt;/g, "</strong>");
            t = t.replace(/&lt;&lt;&lt;I&gt;&gt;&gt;/g, "<em>").replace(/&lt;&lt;&lt;\/I&gt;&gt;&gt;/g, "</em>");
            t = t.replace(/\n/g, "<br>");
            return t;
        }

        function formatReply(text) {
            text = text || "";
            let html = "";
            let lastIndex = 0;
            const linkRe = /\[([^\]]+)\]\(([^)]+)\)/g;
            let match;

            while ((match = linkRe.exec(text)) !== null) {
                html += formatTextSegment(text.slice(lastIndex, match.index));
                html += '<a href="' + escapeHtml(match[2]) + '" class="chat-link" target="_blank" rel="noopener">'
                    + escapeHtml(match[1]) + "</a>";
                lastIndex = match.index + match[0].length;
            }

            html += formatTextSegment(text.slice(lastIndex));
            return html;
        }

        async function apiJson(payload) {
            const csrfToken = getCsrfToken();
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify(Object.assign({ _csrf_token: csrfToken }, payload))
            });

            let data = null;
            try { data = await response.json(); } catch (e) { data = null; }
            return { ok: response.ok, data: data };
        }

        function setActiveConversationId(id) {
            activeConversationId = parseInt(id, 10) || 0;
            if (activeConversationId > 0) {
                localStorage.setItem(activeChatKey, String(activeConversationId));
            } else {
                localStorage.removeItem(activeChatKey);
            }
            renderHistoryList();
        }

        function collectMessagesFromDom() {
            const items = [];
            if (!chatMessages) return items;

            chatMessages.querySelectorAll(".chat-message:not(.chat-welcome):not(.chat-typing)").forEach(function(el) {
                if (el.classList.contains("chat-message-user")) {
                    items.push({
                        type: "user",
                        text: el.getAttribute("data-prompt") || "",
                        attachments: el.getAttribute("data-attachments") || ""
                    });
                } else if (el.classList.contains("chat-message-bot")) {
                    items.push({
                        type: "bot",
                        text: el.getAttribute("data-reply") || "",
                        attachments: ""
                    });
                }
            });
            return items;
        }

        function clearMessageArea() {
            if (!chatMessages) return;
            chatMessages.querySelectorAll(".chat-message:not(.chat-welcome)").forEach(function(el) {
                el.remove();
            });
            lastUserPrompt = "";
        }

        function setSidebarHidden(hidden) {
            app.classList.toggle("sidebar-hidden", hidden);
            localStorage.setItem(sidebarKey, hidden ? "1" : "0");
            updateSidebarOpenButton(hidden);
        }

        function updateSidebarOpenButton(hidden) {
            if (!chatSidebarOpen) return;

            chatSidebarOpen.title = hidden ? "Show chats" : "Hide chats";
            chatSidebarOpen.setAttribute("aria-label", hidden ? "Show chats" : "Hide chats");
            chatSidebarOpen.innerHTML = hidden
                ? '<i class="bi bi-layout-sidebar-inset"></i>'
                : '<i class="bi bi-layout-sidebar-inset-reverse"></i>';
        }

        if (chatSidebarClose) {
            chatSidebarClose.addEventListener("click", function() {
                setSidebarHidden(true);
            });
        }

        if (chatSidebarOpen) {
            chatSidebarOpen.addEventListener("click", function() {
                const hidden = app.classList.contains("sidebar-hidden");
                setSidebarHidden(!hidden);
            });
        }

        if (localStorage.getItem(sidebarKey) === "1") {
            setSidebarHidden(true);
        } else {
            updateSidebarOpenButton(false);
        }

        function getCustomPrompts() {
            try {
                const raw = localStorage.getItem(promptsKey);
                const parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function saveCustomPrompts(prompts) {
            localStorage.setItem(promptsKey, JSON.stringify(prompts));
        }

        function getAllPrompts() {
            const defaults = defaultPromptList();
            const custom = getCustomPrompts();
            const seen = {};
            const merged = [];

            defaults.forEach(function(p) {
                if (p && p.prompt && !seen[p.prompt]) {
                    seen[p.prompt] = true;
                    merged.push(p);
                }
            });

            custom.forEach(function(p) {
                if (p && p.prompt && !seen[p.prompt]) {
                    seen[p.prompt] = true;
                    merged.push(p);
                }
            });

            if (!merged.length && chatPromptsEl) {
                chatPromptsEl.querySelectorAll(".chat-prompt-btn").forEach(function(btn) {
                    const prompt = (btn.getAttribute("data-prompt") || "").trim();
                    if (prompt && !seen[prompt]) {
                        seen[prompt] = true;
                        merged.push({
                            icon: "bi-chat-dots",
                            label: prompt,
                            prompt: prompt
                        });
                    }
                });
            }

            return merged;
        }

        function renderQuickPrompts() {
            if (!chatPromptsEl) return;

            promptCatalog = getAllPrompts();
            if (!promptCatalog.length) return;

            chatPromptsEl.innerHTML = promptCatalog.map(function(p, index) {
                const icon = p.icon || "bi-chat-dots";
                const label = escapeHtml(p.label || p.prompt);
                const prompt = escapeAttr(p.prompt || "");
                return '<button type="button" class="chat-prompt-btn" data-prompt-index="' + index
                    + '" data-prompt="' + prompt + '" title="Send this prompt">'
                    + '<i class="bi ' + escapeHtml(icon) + '"></i> ' + label + "</button>";
            }).join("");
        }

        function promptFromButton(btn) {
            const direct = btn.getAttribute("data-prompt");
            if (direct) return direct;

            const index = parseInt(btn.getAttribute("data-prompt-index"), 10);
            if (!isNaN(index) && promptCatalog[index] && promptCatalog[index].prompt) {
                return String(promptCatalog[index].prompt);
            }

            return "";
        }

        function fillPrompt(text) {
            if (!chatInput) return;
            chatInput.value = text;
            chatInput.focus();
            chatInput.setSelectionRange(text.length, text.length);
            chatInput.scrollIntoView({ block: "nearest", behavior: "smooth" });
        }

        function setEditingState(active) {
            if (chatForm) {
                chatForm.classList.toggle("is-editing", active);
            }
            if (chatEditHint) {
                chatEditHint.classList.toggle("d-none", !active);
            }
            if (!active) {
                pendingSyncOnSend = false;
            }
        }

        function removeMessagesFrom(fromEl) {
            if (!chatMessages || !fromEl) return;

            const all = Array.from(
                chatMessages.querySelectorAll(".chat-message:not(.chat-welcome):not(.chat-typing)")
            );
            const start = all.indexOf(fromEl);
            if (start < 0) return;

            for (let i = all.length - 1; i >= start; i--) {
                all[i].remove();
            }

            const remaining = chatMessages.querySelectorAll(".chat-message-user:not(.chat-welcome)");
            if (remaining.length) {
                const lastUser = remaining[remaining.length - 1];
                lastUserPrompt = lastUser.getAttribute("data-prompt") || "";
            } else {
                lastUserPrompt = "";
            }
        }

        async function startEditMessage(userEl) {
            if (!userEl || isSending) return;

            const text = (userEl.getAttribute("data-prompt") || "").trim();
            if (!text || text === "(attached files)") {
                alert("Messages with attachments cannot be edited. Send a new message instead.");
                return;
            }

            removeMessagesFrom(userEl);
            pendingSyncOnSend = true;
            setEditingState(true);
            fillPrompt(text);

            try {
                await saveCurrentMessages();
                await apiJson({ action: "clear" });
            } catch (err) {
                /* keep edit mode; sync_messages will fix history on send */
            }
        }

        function buildMessageActions(type, promptText, replyText) {
            const actions = document.createElement("div");
            actions.className = "chat-message-actions";

            if (type === "user" && promptText) {
                actions.innerHTML = '<button type="button" class="chat-msg-action-btn" data-action="copy" title="Copy prompt"><i class="bi bi-clipboard"></i></button>'
                    + '<button type="button" class="chat-msg-action-btn" data-action="edit" title="Edit and resend"><i class="bi bi-pencil"></i></button>';
                return actions;
            }

            if (type === "bot") {
                let html = '<button type="button" class="chat-msg-action-btn" data-action="copy-reply" title="Copy reply"><i class="bi bi-clipboard"></i></button>';
                if (hasBulletList(replyText)) {
                    html += '<button type="button" class="chat-msg-action-btn" data-action="copy-table" title="Copy list"><i class="bi bi-table"></i></button>';
                }
                html += '<button type="button" class="chat-msg-action-btn" data-action="regenerate" title="Regenerate"><i class="bi bi-arrow-clockwise"></i></button>';
                actions.innerHTML = html;
            }

            return actions;
        }

        function appendMessage(content, type, attachmentNames) {
            if (!chatMessages) return;

            const wrapper = document.createElement("div");
            wrapper.className = "chat-message chat-message-" + type;

            const avatar = document.createElement("div");
            avatar.className = "chat-avatar";
            avatar.innerHTML = type === "bot" ? '<i class="bi bi-robot"></i>' : '<i class="bi bi-person-fill"></i>';

            const bubble = document.createElement("div");
            bubble.className = "chat-bubble";
            const promptText = (content || "").trim();
            bubble.innerHTML = type === "bot"
                ? formatReply(content)
                : '<p class="mb-0">' + escapeHtml(promptText || "(attached files)") + "</p>";

            if (attachmentNames && attachmentNames.length) {
                bubble.innerHTML += '<p class="chat-msg-attachments small mb-0 mt-2"><i class="bi bi-paperclip me-1"></i>'
                    + attachmentNames.map(function(n) {
                        return '<span class="badge bg-light text-dark me-1">' + escapeHtml(n) + "</span>";
                    }).join("") + "</p>";
            }

            wrapper.appendChild(avatar);

            const contentWrap = document.createElement("div");
            contentWrap.className = "chat-message-content";
            contentWrap.appendChild(bubble);

            if (type === "user" && promptText) {
                wrapper.setAttribute("data-prompt", promptText);
                if (attachmentNames && attachmentNames.length) {
                    wrapper.setAttribute("data-attachments", attachmentNames.join("|"));
                }
                contentWrap.appendChild(buildMessageActions("user", promptText, ""));
            } else if (type === "bot") {
                wrapper.setAttribute("data-reply", content || "");
                contentWrap.classList.add("chat-message-content-bot");
                contentWrap.appendChild(buildMessageActions("bot", "", content || ""));
            }

            wrapper.appendChild(contentWrap);
            chatMessages.appendChild(wrapper);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function renderMessages(messages) {
            clearMessageArea();
            (messages || []).forEach(function(item) {
                if (item.type === "user") {
                    const names = item.attachments ? item.attachments.split("|").filter(Boolean) : [];
                    appendMessage(item.text, "user", names);
                    lastUserPrompt = item.text || lastUserPrompt;
                } else if (item.type === "bot") {
                    appendMessage(item.text, "bot");
                }
            });
        }

        function renderHistoryList() {
            if (!chatHistoryList) return;

            if (!conversations.length) {
                chatHistoryList.innerHTML = '<p class="text-muted small p-3 mb-0">No saved chats yet. Start a conversation and it will appear here.</p>';
                return;
            }

            chatHistoryList.innerHTML = conversations.map(function(chat) {
                const isActive = parseInt(chat.id, 10) === activeConversationId;
                return '<div class="chat-history-item' + (isActive ? " is-active" : "") + '" data-id="' + chat.id + '">'
                    + '<button type="button" class="chat-history-main" data-action="open" data-id="' + chat.id + '">'
                    + '<span class="chat-history-title">' + escapeHtml(chat.title || "New chat") + "</span>"
                    + '<span class="chat-history-meta">' + escapeHtml(chat.updated_label || chat.created_label || "") + "</span>"
                    + '<span class="chat-history-preview">' + escapeHtml(chat.preview || "") + "</span>"
                    + "</button>"
                    + '<div class="chat-history-actions">'
                    + '<button type="button" class="btn btn-soft btn-sm" data-action="rename" data-id="' + chat.id + '" title="Rename"><i class="bi bi-pencil"></i></button>'
                    + '<button type="button" class="btn btn-soft btn-sm" data-action="delete" data-id="' + chat.id + '" title="Delete"><i class="bi bi-trash"></i></button>'
                    + "</div></div>";
            }).join("");
        }

        async function loadConversations() {
            if (!chatHistoryList) return;

            const result = await apiJson({ action: "list" });
            if (!result.ok || !result.data) {
                chatHistoryList.innerHTML = '<p class="text-muted small p-3 mb-0">Saved chats unavailable.</p>';
                conversations = [];
                return;
            }

            if (!result.data.success) {
                chatHistoryList.innerHTML = '<p class="text-muted small p-3 mb-0">'
                    + escapeHtml(result.data.message || "Saved chats unavailable.") + "</p>";
                conversations = [];
                return;
            }

            conversations = result.data.conversations || [];
            renderHistoryList();
        }

        async function openConversation(id) {
            const chatId = parseInt(id, 10);
            if (chatId <= 0) return;

            const result = await apiJson({ action: "get", id: chatId });
            if (!result.ok || !result.data || !result.data.success) {
                alert((result.data && result.data.message) || "Could not open this chat.");
                return;
            }

            await apiJson({ action: "clear" });

            const conversation = result.data.conversation;
            setActiveConversationId(conversation.id);
            renderMessages(conversation.messages || []);
            if (chatInput) chatInput.focus();
        }

        async function saveCurrentMessages() {
            const messages = collectMessagesFromDom();
            if (!messages.length && activeConversationId <= 0) return null;

            const result = await apiJson({
                action: "save",
                id: activeConversationId,
                messages: messages
            });

            if (result.ok && result.data && result.data.success && result.data.conversation) {
                setActiveConversationId(result.data.conversation.id);
                await loadConversations();
                return result.data.conversation;
            }

            return null;
        }

        async function startNewChat() {
            await apiJson({ action: "clear" });
            clearMessageArea();
            setActiveConversationId(0);
            setEditingState(false);
            if (chatInput) chatInput.value = "";
            if (chatInput) chatInput.focus();
        }

        async function renameConversation(id, currentTitle) {
            const title = prompt("Rename chat:", currentTitle || "New chat");
            if (title === null) return;

            const trimmed = title.trim();
            if (!trimmed) {
                alert("Title cannot be empty.");
                return;
            }

            const result = await apiJson({ action: "rename", id: id, title: trimmed });
            if (!result.ok || !result.data || !result.data.success) {
                alert((result.data && result.data.message) || "Could not rename chat.");
                return;
            }

            await loadConversations();
        }

        async function deleteConversation(id) {
            const chat = conversations.find(function(c) { return parseInt(c.id, 10) === parseInt(id, 10); });
            const label = chat ? chat.title : "this chat";
            if (!confirm('Delete "' + label + '"? This cannot be undone.')) return;

            const result = await apiJson({ action: "delete", id: id });
            if (!result.ok || !result.data || !result.data.success) {
                alert((result.data && result.data.message) || "Could not delete chat.");
                return;
            }

            if (parseInt(id, 10) === activeConversationId) {
                clearMessageArea();
                setActiveConversationId(0);
            }

            await loadConversations();

            if (activeConversationId <= 0 && conversations.length) {
                await openConversation(conversations[0].id);
            }
        }

        function removeLastTurn() {
            if (!chatMessages) return;
            const messages = chatMessages.querySelectorAll(".chat-message:not(.chat-welcome):not(.chat-typing)");
            if (messages.length >= 2) {
                messages[messages.length - 1].remove();
                messages[messages.length - 2].remove();
            } else if (messages.length === 1) {
                messages[0].remove();
            }
        }

        function showTyping() {
            if (!chatMessages) return;
            const typing = document.createElement("div");
            typing.className = "chat-message chat-message-bot chat-typing";
            typing.id = "chatTyping";
            typing.innerHTML = '<div class="chat-avatar"><i class="bi bi-robot"></i></div><div class="chat-bubble"><span></span><span></span><span></span></div>';
            chatMessages.appendChild(typing);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function hideTyping() {
            const typing = document.getElementById("chatTyping");
            if (typing) typing.remove();
        }

        function clearAttachments() {
            selectedFiles = [];
            if (attachInput) attachInput.value = "";
            renderAttachmentPreview();
        }

        async function sendMessage(message, options) {
            options = options || {};
            if (isSending) return;

            const files = options.regenerate ? [] : selectedFiles.slice(0, maxAttachments);
            const trimmed = (message || "").trim();
            if (!trimmed && !files.length) return;

            let syncMessages = null;
            if (pendingSyncOnSend) {
                syncMessages = collectMessagesFromDom();
                pendingSyncOnSend = false;
            }

            if (options.regenerate) {
                removeLastTurn();
                await saveCurrentMessages();
                await apiJson({ action: "clear" });
            } else {
                const fileNames = files.map(function(f) { return f.name; });
                appendMessage(trimmed || "(attached files)", "user", fileNames);
                lastUserPrompt = trimmed;
            }

            setEditingState(false);

            if (chatInput) chatInput.value = "";
            isSending = true;
            if (sendBtn) sendBtn.disabled = true;
            if (attachBtn) attachBtn.disabled = true;
            showTyping();

            try {
                const csrfToken = getCsrfToken();
                let response;

                if (files.length) {
                    const formData = new FormData();
                    formData.append("message", trimmed);
                    formData.append("conversation_id", String(activeConversationId || 0));
                    formData.append("_csrf_token", csrfToken);
                    files.forEach(function(file) {
                        formData.append("attachments[]", file);
                    });

                    response = await fetch(apiUrl, {
                        method: "POST",
                        headers: {
                            "X-CSRF-Token": csrfToken
                        },
                        body: formData
                    });
                } else {
                    const payload = {
                        message: trimmed,
                        conversation_id: activeConversationId || 0,
                        regenerate: !!options.regenerate,
                        _csrf_token: csrfToken
                    };

                    if (syncMessages) {
                        payload.sync_messages = syncMessages;
                    }

                    response = await fetch(apiUrl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-Token": csrfToken
                        },
                        body: JSON.stringify(payload)
                    });
                }

                let data = null;
                try { data = await response.json(); } catch (e) { data = null; }

                hideTyping();

                if (!response.ok || !data) {
                    const errText = data && data.message
                        ? data.message
                        : "Unable to reach the AI assistant (HTTP " + response.status + "). Please refresh and try again.";
                    appendMessage(errText, "bot");
                    return;
                }

                if (data.success) {
                    appendMessage(data.reply, "bot");
                    if (data.conversation_id) {
                        setActiveConversationId(data.conversation_id);
                    }
                    await loadConversations();
                } else {
                    appendMessage(data.message || "Something went wrong. Please try again.", "bot");
                }
            } catch (err) {
                hideTyping();
                appendMessage("Unable to reach the AI assistant. Please try again.", "bot");
            } finally {
                clearAttachments();
                isSending = false;
                if (sendBtn) sendBtn.disabled = false;
                if (attachBtn) attachBtn.disabled = false;
                if (chatInput) chatInput.focus();
            }
        }

        async function regenerateReply() {
            if (!lastUserPrompt || isSending) return;
            await sendMessage(lastUserPrompt, { regenerate: true });
        }

        function handleQuickPromptClick(e) {
            const btn = e.target.closest(".chat-prompt-btn");
            if (!btn || !chatPromptsEl || !chatPromptsEl.contains(btn)) return;

            e.preventDefault();
            e.stopPropagation();

            const prompt = promptFromButton(btn).trim();
            if (!prompt) return;

            sendMessage(prompt);
        }

        if (chatHistoryList) {
            chatHistoryList.addEventListener("click", function(e) {
                const btn = e.target.closest("[data-action]");
                if (!btn) return;

                const id = parseInt(btn.getAttribute("data-id"), 10);
                const action = btn.getAttribute("data-action");

                if (action === "open") openConversation(id);
                if (action === "rename") {
                    const chat = conversations.find(function(c) { return parseInt(c.id, 10) === id; });
                    renameConversation(id, chat ? chat.title : "");
                }
                if (action === "delete") deleteConversation(id);
            });
        }

        if (chatMessages) {
            chatMessages.addEventListener("click", function(e) {
                const btn = e.target.closest(".chat-msg-action-btn");
                if (!btn) return;

                e.preventDefault();
                e.stopPropagation();

                const userEl = btn.closest(".chat-message-user");
                const botEl = btn.closest(".chat-message-bot");

                if (userEl) {
                    const text = userEl.getAttribute("data-prompt") || "";
                    const action = btn.getAttribute("data-action");
                    if (action === "copy") copyText(text, btn);
                    if (action === "edit") startEditMessage(userEl);
                    return;
                }

                if (botEl) {
                    const reply = botEl.getAttribute("data-reply") || "";
                    const action = btn.getAttribute("data-action");
                    if (action === "copy-reply") copyText(stripMarkdown(reply), btn);
                    if (action === "copy-table") copyText(bulletsToTable(reply), btn);
                    if (action === "regenerate") regenerateReply();
                }
            });
        }

        function showCopied(btn) {
            const icon = btn.querySelector("i");
            if (!icon) return;
            const prev = icon.className;
            icon.className = "bi bi-check2";
            setTimeout(function() { icon.className = prev; }, 1600);
        }

        function copyText(text, btn) {
            if (!text) return;
            const done = function() { if (btn) showCopied(btn); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function() {
                    fallbackCopy(text, done);
                });
            } else {
                fallbackCopy(text, done);
            }
        }

        function fallbackCopy(text, onCopied) {
            const ta = document.createElement("textarea");
            ta.value = text;
            ta.setAttribute("readonly", "");
            ta.style.position = "fixed";
            ta.style.left = "-9999px";
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand("copy"); onCopied(); } catch (err) { /* ignore */ }
            document.body.removeChild(ta);
        }

        if (attachBtn && attachInput) {
            attachBtn.addEventListener("click", function() { attachInput.click(); });
            attachInput.addEventListener("change", function() {
                addSelectedFiles(attachInput.files);
                attachInput.value = "";
            });
        }

        if (chatForm) {
            chatForm.addEventListener("submit", function(e) {
                e.preventDefault();
                sendMessage(chatInput ? chatInput.value : "");
            });
        }

        if (chatPromptsEl) {
            chatPromptsEl.addEventListener("click", handleQuickPromptClick);
        }

        if (newChatBtn) {
            newChatBtn.addEventListener("click", startNewChat);
        }

        function fileKey(file) {
            return file.name + "|" + file.size + "|" + (file.lastModified || 0);
        }

        function syncFileInput() {
            if (!attachInput) return;
            const dt = new DataTransfer();
            selectedFiles.forEach(function(file) { dt.items.add(file); });
            attachInput.files = dt.files;
        }

        function renderAttachmentPreview() {
            if (!attachPreview) return;
            if (!selectedFiles.length) {
                attachPreview.classList.add("d-none");
                attachPreview.innerHTML = "";
                syncFileInput();
                return;
            }

            attachPreview.classList.remove("d-none");
            attachPreview.innerHTML = selectedFiles.map(function(file, index) {
                const icon = (file.type || "").indexOf("image/") === 0 ? "bi-image" : "bi-file-earmark";
                return '<span class="chat-attach-chip"><i class="bi ' + icon + ' me-1"></i>'
                    + '<span class="chat-attach-chip-name">' + escapeHtml(file.name) + "</span>"
                    + '<button type="button" class="chat-attach-remove" data-index="' + index + '"><i class="bi bi-x-lg"></i></button></span>';
            }).join("") + '<button type="button" class="chat-attach-clear-all btn btn-link btn-sm p-0 ms-1">Clear all</button>';

            attachPreview.querySelectorAll(".chat-attach-remove").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    selectedFiles.splice(parseInt(btn.getAttribute("data-index"), 10), 1);
                    renderAttachmentPreview();
                });
            });

            const clearAll = attachPreview.querySelector(".chat-attach-clear-all");
            if (clearAll) {
                clearAll.addEventListener("click", function() {
                    selectedFiles = [];
                    if (attachInput) attachInput.value = "";
                    renderAttachmentPreview();
                });
            }

            syncFileInput();
        }

        function addSelectedFiles(fileList) {
            Array.from(fileList || []).forEach(function(file) {
                const key = fileKey(file);
                if (!selectedFiles.some(function(f) { return fileKey(f) === key; }) && selectedFiles.length < maxAttachments) {
                    selectedFiles.push(file);
                }
            });
            renderAttachmentPreview();
        }

        const managePromptsBtn = document.getElementById("chatManagePromptsBtn");
        const savePromptBtn = document.getElementById("chatSavePromptBtn");
        const customPromptInput = document.getElementById("chatCustomPromptInput");
        const customPromptLabel = document.getElementById("chatCustomPromptLabel");
        const customPromptList = document.getElementById("chatCustomPromptList");

        function renderCustomPromptList() {
            if (!customPromptList) return;
            const custom = getCustomPrompts();
            if (!custom.length) {
                customPromptList.innerHTML = '<p class="text-muted small mb-0">No custom prompts yet.</p>';
                return;
            }
            customPromptList.innerHTML = custom.map(function(p, index) {
                return '<div class="chat-custom-prompt-item"><span><strong>' + escapeHtml(p.label || "Prompt")
                    + '</strong><br><span class="text-muted small">' + escapeHtml(p.prompt) + "</span></span>"
                    + '<button type="button" class="btn btn-soft btn-sm chat-remove-prompt" data-index="' + index
                    + '"><i class="bi bi-trash"></i></button></div>';
            }).join("");

            customPromptList.querySelectorAll(".chat-remove-prompt").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    const list = getCustomPrompts();
                    list.splice(parseInt(btn.getAttribute("data-index"), 10), 1);
                    saveCustomPrompts(list);
                    renderCustomPromptList();
                    renderQuickPrompts();
                });
            });
        }

        if (savePromptBtn) {
            savePromptBtn.addEventListener("click", function() {
                const prompt = customPromptInput ? (customPromptInput.value || "").trim() : "";
                const label = customPromptLabel ? (customPromptLabel.value || "").trim() : "";
                const finalLabel = label || prompt.slice(0, 40);
                if (!prompt) return;
                const custom = getCustomPrompts();
                custom.push({ icon: "bi-star", label: finalLabel, prompt: prompt });
                saveCustomPrompts(custom);
                if (customPromptInput) customPromptInput.value = "";
                if (customPromptLabel) customPromptLabel.value = "";
                renderCustomPromptList();
                renderQuickPrompts();
            });
        }

        if (managePromptsBtn) {
            managePromptsBtn.addEventListener("click", renderCustomPromptList);
        }

        promptCatalog = getAllPrompts();
        renderQuickPrompts();

        (async function init() {
            await loadConversations();

            const savedId = parseInt(localStorage.getItem(activeChatKey) || "0", 10);
            const savedExists = conversations.some(function(c) { return parseInt(c.id, 10) === savedId; });

            if (savedExists) {
                await openConversation(savedId);
            } else if (conversations.length) {
                await openConversation(conversations[0].id);
            }
        })();
    });
})();
