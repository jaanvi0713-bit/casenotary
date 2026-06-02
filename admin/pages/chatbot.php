<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'AI Assistant';
$pageSubtitle = 'Ask about clients, cases, payments & appointments';
$company = getCompanySettings();

require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="saas-card chatbot-card">
            <div class="saas-card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="chatbot-avatar">
                        <i class="bi bi-robot"></i>
                    </div>
                    <div>
                        <h2 class="saas-card-title mb-0">Notary Admin AI</h2>
                        <p class="saas-card-subtitle">Powered by your live business data</p>
                    </div>
                </div>
                <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> Online</span>
            </div>
            <div class="saas-card-body p-0">
                <div class="chatbot-messages" id="chatMessages">
                    <div class="chat-message chat-message-bot">
                        <div class="chat-avatar"><i class="bi bi-robot"></i></div>
                        <div class="chat-bubble">
                            <p>Hello! I'm your admin AI assistant for <strong><?= e(companyBrandName($company)) ?></strong>.</p>
                            <p class="mb-0">Ask me about clients, cases, payments, appointments, or type <strong>help</strong> to see what I can do.</p>
                        </div>
                    </div>
                </div>
                <div class="chatbot-input-area">
                    <form id="chatForm" class="chatbot-form">
                        <?= CSRF::field() ?>
                        <input type="text" class="form-control" id="chatInput"
                               placeholder="Ask something... e.g. &quot;How many active cases?&quot;"
                               autocomplete="off" required>
                        <button type="submit" class="btn btn-primary" id="chatSendBtn">
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="saas-card">
            <div class="saas-card-header">
                <h2 class="saas-card-title">Quick Prompts</h2>
            </div>
            <div class="saas-card-body">
                <div class="chat-prompts">
                    <button type="button" class="chat-prompt-btn" data-prompt="Give me a dashboard summary">
                        <i class="bi bi-grid-1x2"></i> Dashboard summary
                    </button>
                    <button type="button" class="chat-prompt-btn" data-prompt="How many clients do we have?">
                        <i class="bi bi-people"></i> Client count
                    </button>
                    <button type="button" class="chat-prompt-btn" data-prompt="List active cases">
                        <i class="bi bi-briefcase"></i> Active cases
                    </button>
                    <button type="button" class="chat-prompt-btn" data-prompt="What is our total revenue?">
                        <i class="bi bi-cash-stack"></i> Total revenue
                    </button>
                    <button type="button" class="chat-prompt-btn" data-prompt="Show upcoming appointments">
                        <i class="bi bi-calendar-event"></i> Upcoming appointments
                    </button>
                    <button type="button" class="chat-prompt-btn" data-prompt="List recent payments">
                        <i class="bi bi-credit-card"></i> Recent payments
                    </button>
                </div>
            </div>
        </div>

        <div class="saas-card mt-4">
            <div class="saas-card-header">
                <h2 class="saas-card-title">Capabilities</h2>
            </div>
            <div class="saas-card-body">
                <ul class="chat-capabilities">
                    <li><i class="bi bi-check-circle text-primary"></i> Real-time client &amp; case data</li>
                    <li><i class="bi bi-check-circle text-primary"></i> Payment &amp; revenue insights</li>
                    <li><i class="bi bi-check-circle text-primary"></i> Appointment scheduling info</li>
                    <li><i class="bi bi-check-circle text-primary"></i> Business dashboard overview</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
document.addEventListener("DOMContentLoaded", function() {
    const chatForm = document.getElementById("chatForm");
    const chatInput = document.getElementById("chatInput");
    const chatMessages = document.getElementById("chatMessages");
    const sendBtn = document.getElementById("chatSendBtn");
    const csrfToken = document.querySelector("input[name=\"_csrf_token\"]")?.value;

    function formatReply(text) {
        return text
            .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
            .replace(/\*(.*?)\*/g, "<em>$1</em>")
            .replace(/\n/g, "<br>");
    }

    function appendMessage(content, type) {
        const wrapper = document.createElement("div");
        wrapper.className = "chat-message chat-message-" + type;

        const avatar = document.createElement("div");
        avatar.className = "chat-avatar";
        avatar.innerHTML = type === "bot"
            ? "<i class=\"bi bi-robot\"></i>"
            : "<i class=\"bi bi-person-fill\"></i>";

        const bubble = document.createElement("div");
        bubble.className = "chat-bubble";
        bubble.innerHTML = type === "bot" ? formatReply(content) : "<p class=\"mb-0\">" + content + "</p>";

        wrapper.appendChild(avatar);
        wrapper.appendChild(bubble);
        chatMessages.appendChild(wrapper);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function showTyping() {
        const typing = document.createElement("div");
        typing.className = "chat-message chat-message-bot chat-typing";
        typing.id = "chatTyping";
        typing.innerHTML = "<div class=\"chat-avatar\"><i class=\"bi bi-robot\"></i></div><div class=\"chat-bubble\"><span></span><span></span><span></span></div>";
        chatMessages.appendChild(typing);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function hideTyping() {
        document.getElementById("chatTyping")?.remove();
    }

    async function sendMessage(message) {
        if (!message.trim()) return;

        appendMessage(message, "user");
        chatInput.value = "";
        sendBtn.disabled = true;
        showTyping();

        try {
            const response = await fetch("' . url('api/chatbot.php') . '", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({ message: message, _csrf_token: csrfToken })
            });

            const data = await response.json();
            hideTyping();

            if (data.success) {
                appendMessage(data.reply, "bot");
            } else {
                appendMessage(data.message || "Something went wrong. Please try again.", "bot");
            }
        } catch (err) {
            hideTyping();
            appendMessage("Unable to reach the AI assistant. Please try again.", "bot");
        }

        sendBtn.disabled = false;
        chatInput.focus();
    }

    chatForm.addEventListener("submit", function(e) {
        e.preventDefault();
        sendMessage(chatInput.value);
    });

    document.querySelectorAll(".chat-prompt-btn").forEach(function(btn) {
        btn.addEventListener("click", function() {
            sendMessage(this.dataset.prompt);
        });
    });
});
</script>';

require __DIR__ . '/../includes/footer.php';
