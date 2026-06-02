<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'AI Assistant';
$pageSubtitle = 'Ask naturally about clients, cases, payments & appointments';
$company = getCompanySettings();
$quickPrompts = getChatbotDefaultQuickPrompts();
$userId = (int) (Auth::id() ?? 0);

require __DIR__ . '/../includes/header.php';
?>

<div class="chatbot-page" id="chatbotApp"
     data-api-url="../api/chatbot.php"
     data-user-id="<?= $userId ?>">

    <aside class="chatbot-sidebar" id="chatSidebar" aria-label="Saved chats">
        <div class="chatbot-sidebar-header">
            <h2 class="chatbot-sidebar-title">Chats</h2>
            <button type="button" class="btn btn-soft btn-sm chatbot-sidebar-close" id="chatSidebarClose" title="Hide sidebar" aria-label="Hide sidebar">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        <button type="button" class="btn btn-primary w-100 chatbot-new-btn" id="chatNewBtn">
            <i class="bi bi-plus-lg me-1"></i> New chat
        </button>
        <div id="chatHistoryList" class="chat-history-list">
            <p class="text-muted small mb-0">Loading chats…</p>
        </div>
    </aside>

    <div class="chatbot-main">
        <button type="button" class="btn btn-soft btn-sm chatbot-sidebar-open" id="chatSidebarOpen" title="Show chats" aria-label="Show chats">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>

        <div class="chatbot-workspace-grid">
            <div class="saas-card chatbot-panel chatbot-panel-chat">
                <div class="saas-card-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="chatbot-avatar">
                            <i class="bi bi-robot"></i>
                        </div>
                        <div>
                            <h2 class="saas-card-title mb-0"><?= e(companyAdminAiTitle($company)) ?></h2>
                            <p class="saas-card-subtitle mb-0">Chat naturally</p>
                        </div>
                    </div>
                    <span class="badge bg-success"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem"></i> Online</span>
                </div>
                <div class="saas-card-body p-0 chatbot-panel-body">
                    <div class="chatbot-messages" id="chatMessages">
                        <div class="chat-message chat-message-bot chat-welcome">
                            <div class="chat-avatar"><i class="bi bi-robot"></i></div>
                            <div class="chat-bubble">
                                <p>Hello! I'm your AI assistant for <strong><?= e(companyBrandName($company)) ?></strong>.</p>
                                <p class="mb-0">Ask me anything about your clients, cases, invoices, or appointments. Try typing <strong>&quot;morning briefing&quot;</strong>, <strong>&quot;active cases&quot;</strong>, or ask about a specific client, like <strong>&quot;details of Emily&quot;</strong>.</p>
                            </div>
                        </div>
                    </div>
                    <div class="chatbot-input-area">
                        <div id="chatAttachmentPreview" class="chat-attachment-preview d-none" aria-live="polite"></div>
                        <form id="chatForm" class="chatbot-form" enctype="multipart/form-data">
                            <?= CSRF::field() ?>
                            <input type="file"
                                   id="chatAttachments"
                                   name="attachments[]"
                                   class="d-none"
                                   multiple
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.doc,.docx,image/*">
                            <button type="button" class="btn btn-soft chatbot-attach-btn" id="chatAttachBtn"
                                    title="Attach up to 10 files" aria-label="Attach files">
                                <i class="bi bi-paperclip"></i>
                            </button>
                            <input type="text" class="form-control" id="chatInput" name="message"
                                   placeholder="Ask anything… e.g. &quot;morning briefing&quot;"
                                   autocomplete="off">
                            <button type="submit" class="btn btn-primary" id="chatSendBtn" aria-label="Send message">
                                <i class="bi bi-send-fill"></i>
                            </button>
                        </form>
                        <p class="chatbot-input-hint mb-0">
                            Attach images or PDFs (up to 10) using the clip button beside the message box.
                        </p>
                    </div>
                </div>
            </div>

            <div class="saas-card chatbot-panel chatbot-panel-prompts">
                <div class="saas-card-header">
                    <h2 class="saas-card-title mb-0">Quick Prompts</h2>
                </div>
                <div class="saas-card-body chatbot-panel-body">
                    <div class="chat-prompts" id="chatPrompts">
                        <?php foreach ($quickPrompts as $index => $prompt): ?>
                            <button type="button" class="chat-prompt-btn"
                                    data-prompt-index="<?= (int) $index ?>"
                                    data-prompt="<?= e($prompt['prompt']) ?>"
                                    title="Send this prompt">
                                <i class="bi <?= e($prompt['icon']) ?>"></i> <?= e($prompt['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>window.CHATBOT_DEFAULT_PROMPTS = '
    . json_encode($quickPrompts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)
    . ';</script>'
    . '<script src="' . adminAsset('js/chatbot-page.js') . '"></script>';
require __DIR__ . '/../includes/footer.php';
