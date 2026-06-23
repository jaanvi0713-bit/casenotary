<?php

/** @var array<string, mixed> $company */

$assistantExampleClient = AssistantService::exampleClientName();

?>

<div class="assistant-message assistant-message-bot assistant-welcome">

    <div class="assistant-bot-avatar" aria-hidden="true">

        <i class="bi bi-robot"></i>

    </div>

    <div class="assistant-bubble assistant-bubble-rich">

        <p class="assistant-welcome__title mb-2">

            <strong>Welcome to the <?= e(companyBrandName($company)) ?> AI Assistant!</strong>

        </p>

        <p class="assistant-welcome__text mb-0">

            From this unified space, you can instantly view dashboard metrics, search clients or cases, scan documents for quick Q&amp;As, handle client intake, schedule appointments, and draft messages or reminders. To get started, select a quick prompt below or try typing a command like: &ldquo;Schedule appointment for <?= e($assistantExampleClient) ?> tomorrow at 2pm.&rdquo;

        </p>

    </div>

</div>

