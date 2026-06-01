<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Clients';
$clients = getAllClients();
$pageSubtitle = count($clients) . ' registered clients';

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">Client Directory</h2>
            <p class="saas-card-subtitle">All registered client profiles</p>
        </div>
        <a href="<?= url('pages/client-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Client</a>
    </div>
    <div class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="tableSearch" placeholder="Search clients...">
        </div>
    </div>
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
                            <th>Location</th>
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
                                <td>
                                    <?php if ($client['city']): ?>
                                        <?= e($client['city'] . ', ' . $client['state']) ?>
                                    <?php else: ?>
                                        —
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
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
