<?php

require_once __DIR__ . '/../core/bootstrap.php';



Auth::requirePage('assistant');



AssistantService::ensureSessionIntegrity();

AssistantActions::rehydrateDraftsFromHistory(AssistantService::history());

$pageTitle = 'AI Assistant';

$pageSubtitle = 'Operations, search, intake & compliance';

$pageBodyClass = 'page-assistant';

$status = AssistantService::status();

$history = AssistantService::history();

$quickPrompts = AssistantService::quickPrompts();

$library = AssistantService::library();

$conversationId = AssistantService::conversationId();

$libraryAvailable = AssistantChatStore::isAvailable();

$company = getCompanySettings();



require __DIR__ . '/../includes/header.php';

?>



<div class="assistant-layout" id="assistantLayout">

    <button type="button" class="assistant-library-backdrop" id="assistantLibraryBackdrop" hidden aria-label="Close library"></button>

    <aside class="saas-card assistant-library-sidebar" id="assistantLibrary" aria-label="Chat library" hidden>
        <div class="assistant-library-sidebar__header">
            <h3 class="saas-card-title h6 mb-0">Library</h3>
            <button type="button" class="assistant-library-close" id="assistantLibraryCloseBtn" title="Close library" aria-label="Close library">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="assistant-library-panel">
            <?php if (!$libraryAvailable): ?>
                <p class="assistant-library-empty text-muted small px-3 py-3 mb-0">
                    Run <code>php admin/sql/migrate_assistant_chats.php</code> to enable chat history.
                </p>
            <?php else: ?>
                <div class="assistant-library-list" id="assistantLibraryList" role="list">
                    <?php if ($library === []): ?>
                        <p class="assistant-library-empty text-muted small px-3 py-3 mb-0" id="assistantLibraryEmpty">
                            No chats yet. Start a conversation to save it here.
                        </p>
                    <?php else: ?>
                        <?php foreach ($library as $item): ?>
                            <div class="assistant-library-item<?= $conversationId === (int) $item['id'] ? ' is-active' : '' ?>"
                                 role="listitem"
                                 data-id="<?= (int) $item['id'] ?>">
                                <button type="button" class="assistant-library-item__main" data-action="load">
                                    <span class="assistant-library-item__title"><?= e($item['title']) ?></span>
                                    <span class="assistant-library-item__preview"><?= e($item['preview']) ?></span>
                                    <?php if ($item['updated_at'] !== ''): ?>
                                        <time class="assistant-library-item__time" datetime="<?= e($item['updated_at']) ?>">
                                            <?= e(formatDateTime($item['updated_at'])) ?>
                                        </time>
                                    <?php endif; ?>
                                </button>
                                <div class="assistant-library-item__actions">
                                    <button type="button" class="assistant-library-action" data-action="rename" title="Rename">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="assistant-library-action assistant-library-action--danger" data-action="delete" title="Delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <div class="saas-card assistant-card">

        <div class="saas-card-header assistant-chat-header">
            <div class="assistant-chat-header__title">
                <h2 class="saas-card-title mb-0">AI Assistant</h2>
            </div>
            <div class="assistant-chat-header__actions">
                <button type="button" class="btn btn-soft btn-sm assistant-library-toggle" id="assistantLibraryToggleBtn" title="Chat library" aria-expanded="false" aria-controls="assistantLibrary">
                    <i class="bi bi-journal-text"></i>
                    <span>Library</span>
                </button>
                <button type="button" class="btn btn-soft btn-sm" id="assistantNewChatBtn" title="New chat">
                    <i class="bi bi-plus-lg"></i>
                    <span class="d-none d-sm-inline ms-1">New chat</span>
                </button>
            </div>
        </div>

        <div class="saas-card-body p-0">

            <div class="assistant-messages" id="assistantMessages" aria-live="polite">

                <?php if ($history === []): ?>
                    <?php require __DIR__ . '/../includes/assistant-welcome.php'; ?>
                <?php else: ?>
                    <?php foreach ($history as $turnIndex => $turn): ?>
                        <?php require __DIR__ . '/../includes/assistant-message.php'; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

            <div class="assistant-input-area">

                <form id="assistantForm" class="assistant-form" enctype="multipart/form-data" data-no-global-loading data-read-only="<?= Auth::isReadOnly() ? '1' : '0' ?>">

                    <?= CSRF::field() ?>

                    <input type="file" id="assistantAttachment" name="attachment" class="d-none"

                           accept=".pdf,.txt,.jpg,.jpeg,.png,.gif,.webp,image/*,application/pdf">

                    <button type="button" class="btn btn-soft assistant-attach-btn" id="assistantAttachBtn" title="Attach PDF or image">

                        <i class="bi bi-paperclip"></i>

                    </button>

                    <textarea

                        id="assistantInput"

                        class="form-control"

                        rows="1"

                        placeholder="Ask about clients, cases, fees, or say ‘create a case for…’"

                    ></textarea>

                    <button type="submit" class="btn btn-primary" id="assistantSendBtn">

                        <i class="bi bi-send-fill"></i>

                        <span class="visually-hidden">Send</span>

                    </button>

                </form>

                <div id="assistantAttachBar" class="assistant-attach-bar d-none" aria-live="polite">
                    <span id="assistantAttachLabel" class="assistant-input-hint mb-0"></span>
                    <button type="button" class="assistant-attach-clear" id="assistantAttachClearBtn" title="Remove attachment" aria-label="Remove attachment">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <?php if (!$status['enabled']): ?>

                    <p class="assistant-input-hint mb-0 mt-2">

                        The AI assistant is disabled in settings.

                    </p>

                <?php elseif (!$status['ollama_online']): ?>

                    <p class="assistant-input-hint mb-0 mt-2">

                        Open-ended chat needs the AI model online. Dashboard, search, calculations, and actions still work.

                    </p>

                <?php else: ?>

                    <p class="assistant-input-hint mb-0 mt-2">Tip: attach a PDF or photo, then ask to scan or extract details.</p>

                <?php endif; ?>

            </div>

        </div>

    </div>



    <div class="saas-card assistant-prompts-card">

        <div class="saas-card-header">

            <h3 class="saas-card-title h6 mb-0">Quick prompts</h3>

            <p class="assistant-prompts-subtitle mb-0">One-click starters</p>

        </div>

        <div class="saas-card-body">
            <div class="assistant-prompt-grid">
                <?php foreach ($quickPrompts as $prompt): ?>
                    <button type="button"
                            class="assistant-prompt-btn"
                            data-prompt="<?= e($prompt['prompt']) ?>">
                        <span class="assistant-prompt-btn__icon" aria-hidden="true">
                            <i class="bi <?= e($prompt['icon']) ?>"></i>
                        </span>
                        <span class="assistant-prompt-btn__text"><?= e($prompt['label']) ?></span>
                        <i class="bi bi-arrow-right assistant-prompt-btn__arrow" aria-hidden="true"></i>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <template id="assistantWelcomeTemplate">
        <?php require __DIR__ . '/../includes/assistant-welcome.php'; ?>
    </template>

</div>



<?php

$pageScripts = '<script src="' . adminAsset('js/assistant-page.js') . '"></script>';

require __DIR__ . '/../includes/footer.php';


