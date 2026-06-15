<?php

declare(strict_types=1);

function chatbotIsReadOnly(): bool
{
    return Auth::check() && Auth::isReadOnly();
}

function chatbotCanExecuteActions(): bool
{
    if (!Auth::check() || chatbotIsReadOnly()) {
        return false;
    }

    return Auth::canManage(RoleAccess::PERMISSION_CASES)
        || Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)
        || Auth::canManage(RoleAccess::PERMISSION_PAYMENTS)
        || Auth::can(RoleAccess::PERMISSION_NOTIFICATIONS);
}

function chatbotAppendCaseScope(array &$where, array &$params, string $caseAlias = 'cs', string $clientAlias = 'cl'): void
{
    appendCaseTenantScope($where, $params, $caseAlias, $clientAlias);
    appendAssignedCaseScope($where, $params, $caseAlias);
}

function chatbotUserCanAccessCaseId(int $caseId): bool
{
    if ($caseId <= 0) {
        return false;
    }

    $where = ['cs.id = ?'];
    $params = [$caseId];
    chatbotAppendCaseScope($where, $params, 'cs', 'cl');

    return (bool) Database::fetch(
        'SELECT cs.id FROM cases cs JOIN clients cl ON cl.id = cs.client_id WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
        $params
    );
}

function chatbotFetchCaseById(int $caseId): ?array
{
    if ($caseId <= 0) {
        return null;
    }

    $where = ['cs.id = ?'];
    $params = [$caseId];
    chatbotAppendCaseScope($where, $params, 'cs', 'cl');

    return Database::fetch(
        'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name, cl.email, cl.phone, cl.id AS client_id
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         WHERE ' . implode(' AND ', $where) . '
         LIMIT 1',
        $params
    ) ?: null;
}

function chatbotReadOnlyNotice(): string
{
    return 'Your account is **read-only**. You can view data but cannot make changes through the assistant.';
}
