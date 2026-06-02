<?php

declare(strict_types=1);

function chatbotSetLastEntity(string $type, int $id, string $name): void
{
    $_SESSION['chatbot_last_entity'] = [
        'type' => $type,
        'id'   => $id,
        'name' => $name,
    ];
}

function chatbotGetLastEntity(): ?array
{
    $entity = $_SESSION['chatbot_last_entity'] ?? null;

    return is_array($entity) ? $entity : null;
}

function chatbotRememberTurn(string $userMessage, string $botReply): void
{
    $history = $_SESSION['chatbot_history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }

    $history[] = [
        'user' => mb_strimwidth(trim($userMessage), 0, 500, '…'),
        'bot'  => mb_strimwidth(trim($botReply), 0, 800, '…'),
        'at'   => time(),
    ];

    $_SESSION['chatbot_history'] = array_slice($history, -12);
}

function chatbotClearSession(): void
{
    unset(
        $_SESSION['chatbot_last_topic'],
        $_SESSION['chatbot_last_entity'],
        $_SESSION['chatbot_history'],
        $_SESSION['chatbot_appointment_pending']
    );
}

function chatbotIsContextualFollowUp(string $message): bool
{
    $normalized = strtolower(trim($message));

    if (preg_match(
        '/\b(her|his|their|them|she|he|him|they|that client|this client|same client|that case|this case)\b/',
        $normalized
    )) {
        return true;
    }

    if (preg_match('/\b(what about|how about|and what about)\b/', $normalized)) {
        return true;
    }

    return (bool) preg_match(
        '/^(tell me more|what else|anything else|more info|more information|more details|go on|continue|what about that|and theirs?|what about them|and them)$/i',
        $normalized
    );
}

function chatbotReplyForMetaQuestions(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (preg_match('/\b(who are you|what are you|your name)\b/', $normalized)) {
        $title = companyAdminAiTitle();

        return "I'm **{$title}** — your built-in assistant for this notary portal. "
            . 'I answer from **live data** in your system (clients, cases, invoices, appointments) '
            . 'and general notary guidance — **no API key required**.';
    }

    if (preg_match('/\b(what can you do|what do you do|your capabilities|help me|are you chatgpt|like chatgpt)\b/', $normalized)) {
        return "I answer **any question about this system** — live data, how-tos, and general topics:\n\n"
            . "• **Live data** — clients, cases, invoices, payments, appointments, documents, notifications\n"
            . "• **How-to** — *How do I add a client?*, *Where are settings?*, *Approve appointment requests*\n"
            . "• **Search** — client names, case numbers, overdue invoices, revenue\n"
            . "• **Follow-ups** — after a client profile, try *what about her invoices* or *list them*\n"
            . "• **Notary help** — definitions, drafts, general advice (optional AI if configured)\n\n"
            . 'Ask in plain English — no special phrasing required.';
    }

    return null;
}

function chatbotReplyForClientFocus(int $clientId, string $message): ?string
{
    $client = Database::fetch('SELECT * FROM clients WHERE id = ?', [$clientId]);
    if (!$client) {
        return null;
    }

    $normalized = strtolower(trim($message));
    $name = clientFullName($client);
    chatbotSetLastEntity('client', $clientId, $name);

    if (preg_match('/\b(invoice|invoices|bill|billing|unpaid|pending|outstanding)\b/', $normalized)) {
        syncOverdueInvoices();
        $statusCol = invoiceStatusColumn();
        $rows = Database::fetchAll(
            "SELECT i.invoice_number, i.total, i.due_date, i.{$statusCol} AS invoice_status
             FROM invoices i
             WHERE i.client_id = ?
             ORDER BY i.created_at DESC
             LIMIT 10",
            [$clientId]
        );
        if ($rows === []) {
            return "**{$name}** has no invoices on file.";
        }
        $lines = ["**Invoices for {$name}:**", ''];
        foreach ($rows as $row) {
            $status = ucwords(str_replace('_', ' ', $row['invoice_status'] ?? 'pending'));
            $lines[] = '• **' . ($row['invoice_number'] ?? 'Invoice') . '** — '
                . formatCurrency((float) ($row['total'] ?? 0)) . " (*{$status}*)";
        }

        return implode("\n", $lines);
    }

    if (preg_match('/\b(appointment|appointments|schedule|meeting|calendar)\b/', $normalized)) {
        $startSql = appointmentStartSql('a');
        $rows = Database::fetchAll(
            "SELECT a.title, a.status, {$startSql} AS start_time
             FROM appointments a
             WHERE a.client_id = ?
             ORDER BY {$startSql} DESC
             LIMIT 8",
            [$clientId]
        );
        if ($rows === []) {
            return "**{$name}** has no appointments on file.";
        }
        $lines = ["**Appointments for {$name}:**", ''];
        foreach ($rows as $row) {
            $when = !empty($row['start_time']) ? formatDateTime($row['start_time']) : 'TBD';
            $lines[] = '• **' . ($row['title'] ?? 'Appointment') . '** — '
                . $when . ' (*' . ucfirst($row['status'] ?? 'scheduled') . '*)';
        }

        return implode("\n", $lines);
    }

    if (preg_match('/\b(document|documents|doc|docs|upload|uploaded|uploads|file|files)\b/', $normalized)) {
        $rows = Database::fetchAll(
            "SELECT d.original_name, d.file_name, d.created_at, cs.case_number
             FROM documents d
             JOIN cases cs ON cs.id = d.case_id
             WHERE cs.client_id = ?
             ORDER BY d.created_at DESC
             LIMIT 10",
            [$clientId]
        );
        if ($rows === []) {
            return "**{$name}** has no documents uploaded yet.";
        }

        return chatbotFormatDocumentList($rows, "**Documents for {$name}:**");
    }

    if (preg_match('/\b(payment|payments|paid|receipt|receipts)\b/', $normalized)) {
        $paymentCol = paymentStatusColumn();
        $rows = Database::fetchAll(
            "SELECT p.amount, p.paid_at, i.invoice_number
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             WHERE i.client_id = ?
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC
             LIMIT 8",
            [$clientId]
        );
        if ($rows === []) {
            return "**{$name}** has no recorded payments yet.";
        }
        $lines = ["**Payments for {$name}:**", ''];
        foreach ($rows as $row) {
            $when = !empty($row['paid_at']) ? formatDateTime($row['paid_at']) : '—';
            $lines[] = '• ' . formatCurrency((float) ($row['amount'] ?? 0))
                . ' — ' . ($row['invoice_number'] ?? 'Invoice') . " — {$when}";
        }

        return implode("\n", $lines);
    }

    if (preg_match('/\b(case|cases|matter|matters)\b/', $normalized)) {
        $cases = Database::fetchAll(
            "SELECT cs.case_number, cs.title, cs.status, cl.first_name, cl.last_name, cl.company_name
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE cs.client_id = ?
             ORDER BY cs.updated_at DESC
             LIMIT 10",
            [$clientId]
        );

        return formatChatbotCaseList($cases, "**Cases for {$name}:**");
    }

    return formatChatbotClientDetail($client);
}

function chatbotReplyForNamedClientFocus(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (preg_match('/\b(all clients|any clients?|clients have|clients uploaded|have clients|do clients|did clients)\b/', $normalized)) {
        return null;
    }

    if (!preg_match(
        '/\b(document|documents|doc|docs|upload|uploaded|uploads|file|files|'
        . 'invoice|invoices|appointment|appointments|payment|payments|receipt|receipts|case|cases)\b/',
        $normalized
    )) {
        return null;
    }

    $clients = findClientsForChatbot($message);
    if (count($clients) !== 1) {
        return null;
    }

    return chatbotReplyForClientFocus((int) $clients[0]['id'], $message);
}

function chatbotReplyForContextualFollowUp(string $message): ?string
{
    if (!chatbotIsContextualFollowUp($message)) {
        return null;
    }

    $entity = chatbotGetLastEntity();
    if ($entity === null) {
        return 'I\'m not sure what you\'re referring to. Ask about a **client name** or **case number** first, then follow up with *what about her invoices* or similar.';
    }

    $type = (string) ($entity['type'] ?? '');
    $id = (int) ($entity['id'] ?? 0);
    $name = (string) ($entity['name'] ?? '');

    if ($type === 'client' && $id > 0) {
        return chatbotReplyForClientFocus($id, $message);
    }

    if ($type === 'case' && $id > 0) {
        $case = Database::fetch(
            'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name, cl.email, cl.phone, cl.id AS client_id
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE cs.id = ?',
            [$id]
        );
        if ($case) {
            chatbotSetLastEntity('client', (int) $case['client_id'], clientFullName($case));

            return chatbotReplyForClientFocus((int) $case['client_id'], $message)
                ?? formatChatbotCaseDetail($case);
        }
    }

    return "I still have **{$name}** in context — try *what about their invoices*, *appointments*, or *cases*.";
}
