<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('clients');

$pageTitle = 'Clients';
$q = trim((string) ($_GET['q'] ?? ''));
$perPage = 10;
$page = requestPageNumber();
$totalClients = countClients($q);
$totalPages = max(1, (int) ceil($totalClients / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$clients = getClientsPaginated($page, $perPage, $q);
$pageSubtitle = $totalClients . ' registered clients';
$canManageClients = Auth::canManage(RoleAccess::PERMISSION_CLIENTS);

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">Client Directory</h2>
            <p class="saas-card-subtitle">All registered client profiles</p>
        </div>
        <?php if ($canManageClients): ?>
        <a href="<?= url('pages/client-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Client</a>
        <?php endif; ?>
    </div>
    <form method="get" class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="tableSearch" name="q" value="<?= e($q) ?>" placeholder="Search clients...">
        </div>
    </form>
    <div class="card-body p-0">
        <?php if (empty($clients)): ?>
            <div class="empty-state py-5">
                <i class="bi bi-people"></i>
                <p>No clients found. Clients will appear here once registered.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table mb-0" id="dataTable">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Company</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="col-location">Location</th>
                            <th>Cases</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <div class="case-cell">
                                        <strong><?= e(clientFullName($client)) ?></strong>
                                        <small>ID #<?= (int) $client['id'] ?></small>
                                    </div>
                                </td>
                                <td><?= e($client['company_name'] ?: '—') ?></td>
                                <td><?= e($client['email']) ?></td>
                                <td><?= e($client['phone'] ?: '—') ?></td>
                                <td class="client-address-cell">
                                    <?php $addressLines = clientAddressLines($client); ?>
                                    <?php if ($addressLines === []): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <div class="client-address-block" title="<?= e(clientAddressSummary($client)) ?>">
                                            <?php foreach ($addressLines as $index => $line): ?>
                                                <span class="client-address-line<?= $index === 0 ? ' client-address-line-primary' : '' ?>"><?= e($line) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark"><?= (int) $client['case_count'] ?></span></td>
                                <td><?= statusBadge($client['user_status']) ?></td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="<?= url('pages/client-form.php?id=' . (int) $client['id']) ?>" class="btn btn-soft btn-sm">Edit</a>
                                        <form method="post" action="<?= url('actions/client-action.php') ?>" class="m-0" onsubmit="return confirm('Delete this client permanently? All cases, documents, and appointments will be removed.');">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="action" value="delete_client">
                                            <input type="hidden" name="client_id" value="<?= (int) $client['id'] ?>">
                                            <button type="submit" class="btn btn-soft-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= count($clients) ?> of <?= $totalClients ?> clients
                </small>
                <?= renderPaginationNav($page, $totalPages) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
