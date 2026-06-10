<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$pageTitle = 'AI Assistant';
$pageSubtitle = 'Ask about your appointments, cases, and documents';
$pageBodyClass = 'page-chatbot';
$company = getCompanySettings();
$quickPrompts = [
    ['icon' => 'bi-calendar-event', 'label' => 'My appointments', 'prompt' => 'When is my next appointment?'],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Documents needed', 'prompt' => 'What documents do I need to bring?'],
    ['icon' => 'bi-credit-card', 'label' => 'Outstanding invoices', 'prompt' => 'Show my outstanding invoices'],
    ['icon' => 'bi-briefcase', 'label' => 'Case status', 'prompt' => 'What is the status of my cases?'],
    ['icon' => 'bi-telephone', 'label' => 'Contact office', 'prompt' => 'What are your office hours and contact details?'],
];

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header">
        <div class="d-flex align-items-center gap-3">
            <div class="chatbot-avatar">
                <i class="bi bi-robot"></i>
            </div>
            <div>
                <h2 class="saas-card-title mb-0">Client Assistant</h2>
                <p class="saas-card-subtitle mb-0"><?= e(companyBrandName($company)) ?> — your matters only</p>
            </div>
        </div>
    </div>
    <div class="saas-card-body p-0">
        <div class="chatbot-messages" id="clientChatMessages" style="min-height: 320px; max-height: 55vh; overflow-y: auto;">
            <div class="chat-message chat-message-bot chat-welcome p-3">
                <div class="chat-avatar"><i class="bi bi-robot"></i></div>
                <div class="chat-bubble">
                    <p>Hello! I can help with your <strong>appointments</strong>, <strong>cases</strong>, <strong>payments</strong>, and <strong>documents</strong>.</p>
                    <p class="mb-0">I cannot give legal advice — contact your coordinator for case-specific legal questions.</p>
                </div>
            </div>
        </div>
        <div class="p-3 border-top">
            <div class="chat-prompts d-flex flex-wrap gap-2 mb-3" id="clientChatPrompts">
                <?php foreach ($quickPrompts as $prompt): ?>
                    <button type="button" class="chat-prompt-btn btn btn-soft btn-sm" data-prompt="<?= e($prompt['prompt']) ?>">
                        <i class="bi <?= e($prompt['icon']) ?>"></i> <?= e($prompt['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <form id="clientChatForm" class="chatbot-form d-flex gap-2" data-no-global-loading>
                <?= CSRF::field() ?>
                <input type="text" class="form-control" id="clientChatInput" name="message" placeholder="Ask anything…" autocomplete="off" required>
                <button type="submit" class="btn btn-primary" id="clientChatSend"><i class="bi bi-send-fill"></i></button>
            </form>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
(function() {
    const form = document.getElementById("clientChatForm");
    const input = document.getElementById("clientChatInput");
    const box = document.getElementById("clientChatMessages");
    const apiUrl = ' . json_encode(clientUrl('api/chatbot.php')) . ';
    const csrfInput = form ? form.querySelector("[name=_csrf_token]") : null;

    function esc(s) {
        const d = document.createElement("div");
        d.textContent = s || "";
        return d.innerHTML;
    }

    function formatReply(text) {
        return esc(text)
            .replace(/\\*\\*(.+?)\\*\\*/g, "<strong>$1</strong>")
            .replace(/\\[(.+?)\\]\\((.+?)\\)/g, "<a href=\"$2\">$1</a>")
            .replace(/\\n/g, "<br>");
    }

    function append(type, text) {
        const wrap = document.createElement("div");
        wrap.className = "chat-message chat-message-" + type + " p-3 d-flex gap-2";
        wrap.innerHTML = "<div class=\"chat-avatar\"><i class=\"bi bi-" + (type === "bot" ? "robot" : "person-fill") + "\"></i></div>"
            + "<div class=\"chat-bubble\"><p class=\"mb-0\">" + (type === "bot" ? formatReply(text) : esc(text)) + "</p></div>";
        box.appendChild(wrap);
        box.scrollTop = box.scrollHeight;
    }

    async function send(message) {
        const fd = new FormData();
        if (csrfInput) fd.append(csrfInput.name, csrfInput.value);
        fd.append("message", message);
        append("user", message);
        input.value = "";
        append("bot", "…");
        const typing = box.lastElementChild;
        try {
            const res = await fetch(apiUrl, { method: "POST", body: fd, credentials: "same-origin" });
            const data = await res.json();
            typing.remove();
            append("bot", data.success ? data.reply : (data.message || "Something went wrong."));
        } catch (e) {
            typing.remove();
            append("bot", "Could not reach the assistant. Please try again.");
        }
    }

    if (form) {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
            const msg = (input.value || "").trim();
            if (msg) send(msg);
        });
    }

    document.querySelectorAll("#clientChatPrompts [data-prompt]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            send(btn.getAttribute("data-prompt") || "");
        });
    });
})();
</script>';
require __DIR__ . '/../includes/footer.php';
