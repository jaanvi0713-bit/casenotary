<?php
/** @var array<string, mixed> $company */
?>
<div class="assistant-message assistant-message-bot assistant-welcome">
    <div class="assistant-bot-avatar" aria-hidden="true">
        <i class="bi bi-robot"></i>
    </div>
    <div class="assistant-bubble assistant-bubble-rich">
        <p class="assistant-welcome__title mb-2">
            <strong>Welcome to the <?= e(companyBrandName($company)) ?> AI Assistant!</strong>
        </p>
        <p class="assistant-welcome__text mb-2">
            I am ready to help manage your cases and clients. You can ask me to look up client files, check active case statuses, view monthly revenue metrics, or track compliance tasks.
        </p>
        <p class="assistant-welcome__text mb-0">
            Select a quick prompt below or type your inquiry to begin.
        </p>
    </div>
</div>
