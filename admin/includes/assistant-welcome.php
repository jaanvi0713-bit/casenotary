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
            Built-in assistant — no external AI server required. Ask for **dashboard metrics**, **search** clients or cases, **schedule appointments**, **create cases/clients**, **client intake**, **document scan &amp; Q&amp;A**, **send reminders** (confirm to send), **message drafts**, or **notary definitions &amp; FAQs**.
        </p>
        <p class="assistant-welcome__text mb-0">
            Select a quick prompt below, type _what can you do?_, or start with e.g. _Schedule appointment for Louis Macwell tomorrow at 2pm_.
        </p>
    </div>
</div>
