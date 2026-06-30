<?php

/** @var array<string, mixed> $turn */

/** @var int $turnIndex */

$role = (string) ($turn['role'] ?? '');

$content = (string) ($turn['content'] ?? '');

$turnIndex = (int) ($turnIndex ?? 0);



if ($role === 'user'): ?>

    <div class="assistant-message assistant-message-user" data-turn-index="<?= $turnIndex ?>" data-content="<?= e($content) ?>">

        <div class="assistant-message__content">

            <div class="assistant-bubble"><?= nl2br(e($content)) ?></div>

            <div class="assistant-message__actions">

                <button type="button" class="assistant-message-action" data-action="copy" title="Copy" aria-label="Copy message">

                    <i class="bi bi-copy"></i>

                </button>

                <button type="button" class="assistant-message-action" data-action="edit" title="Edit" aria-label="Edit message">

                    <i class="bi bi-pencil"></i>

                </button>

            </div>

        </div>

    </div>

<?php elseif ($role === 'assistant'): ?>

    <div class="assistant-message assistant-message-bot" data-turn-index="<?= $turnIndex ?>" data-content="<?= e($content) ?>">

        <div class="assistant-message__content">

            <div class="assistant-bubble assistant-bubble-rich" data-rich="1"><?= nl2br(e($content)) ?></div>

            <div class="assistant-message__actions">

                <button type="button" class="assistant-message-action" data-action="copy" title="Copy" aria-label="Copy message">

                    <i class="bi bi-copy"></i>

                </button>

            </div>

        </div>

        <?php if (!empty($turn['alerts']) && is_array($turn['alerts'])): ?>

            <div class="assistant-alerts">

                <?php foreach ($turn['alerts'] as $alert): ?>

                    <div class="assistant-alert assistant-alert--<?= e((string) ($alert['level'] ?? 'warning')) ?>">

                        <strong><?= e((string) ($alert['title'] ?? 'Alert')) ?></strong>

                        <span><?= e((string) ($alert['message'] ?? '')) ?></span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <?php if (!empty($turn['draft']) && is_array($turn['draft'])): ?>

            <?php $draft = $turn['draft']; ?>

            <?php
            $draftAction = (string) ($draft['action'] ?? '');
            $hasEditable = AssistantDraftEdit::editableKeys($draftAction) !== [];
            ?>

            <div class="assistant-draft-card" data-draft-id="<?= e((string) ($draft['id'] ?? '')) ?>" data-draft-action="<?= e($draftAction) ?>">

                <div class="assistant-draft-card__header">

                    <span class="assistant-draft-card__badge">Draft preview</span>

                    <span class="assistant-draft-card__action"><?= e(str_replace('_', ' ', $draftAction ?: 'action')) ?></span>

                </div>

                <dl class="assistant-draft-card__fields">

                    <?php foreach (($draft['preview'] ?? []) as $label => $value): ?>

                        <?php $isEditable = AssistantDraftEdit::isEditableKey($draftAction, (string) $label); ?>

                        <div class="assistant-draft-row<?= $isEditable ? ' assistant-draft-row--editable' : '' ?>">

                            <dt><?= e((string) $label) ?></dt>

                            <?php if ($isEditable && !Auth::isReadOnly()): ?>

                                <?php if ((string) $label === 'Description'): ?>

                                    <dd><textarea class="form-control form-control-sm assistant-draft-input" rows="2" data-preview-key="<?= e((string) $label) ?>"><?= e((string) $value) ?></textarea></dd>

                                <?php else: ?>

                                    <dd><input type="text" class="form-control form-control-sm assistant-draft-input" data-preview-key="<?= e((string) $label) ?>" value="<?= e((string) $value) ?>"></dd>

                                <?php endif; ?>

                            <?php else: ?>

                                <dd><?= e((string) $value) ?></dd>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </dl>

                <div class="assistant-draft-card__actions">

                    <?php if ($hasEditable && !Auth::isReadOnly()): ?>

                        <button type="button" class="btn btn-soft btn-sm assistant-draft-save-btn" data-draft-id="<?= e((string) ($draft['id'] ?? '')) ?>">

                            Save changes

                        </button>

                    <?php endif; ?>

                    <button type="button" class="btn btn-primary btn-sm assistant-confirm-btn" data-draft-id="<?= e((string) ($draft['id'] ?? '')) ?>"<?= Auth::isReadOnly() ? ' disabled title="Read-only account"' : '' ?>>

                        Confirm

                    </button>

                </div>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>

