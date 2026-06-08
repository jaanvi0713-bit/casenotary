<?php
require_once __DIR__ . '/../../admin/core/bootstrap.php';

header('Location: ' . clientLoginUrl(TenantService::resolveLoginCompanyFromRequest() ?: null));
exit;
