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

            <div class="assistant-draft-card" data-draft-id="<?= e((string) ($draft['id'] ?? '')) ?>">

                <div class="assistant-draft-card__header">

                    <span class="assistant-draft-card__badge">Draft preview</span>

                    <span class="assistant-draft-card__action"><?= e(str_replace('_', ' ', (string) ($draft['action'] ?? 'action'))) ?></span>

                </div>

                <dl class="assistant-draft-card__fields">

                    <?php foreach (($draft['preview'] ?? []) as $label => $value): ?>

                        <div class="assistant-draft-row">

                            <dt><?= e((string) $label) ?></dt>

                            <dd><?= e((string) $value) ?></dd>

                        </div>

                    <?php endforeach; ?>

                </dl>

                <button type="button" class="btn btn-primary btn-sm assistant-confirm-btn" data-draft-id="<?= e((string) ($draft['id'] ?? '')) ?>"<?= Auth::isReadOnly() ? ' disabled title="Read-only account"' : '' ?>>

                    Confirm

                </button>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>

