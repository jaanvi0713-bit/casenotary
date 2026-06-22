<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
$pageTitle = 'Request a Service';
$pageSubtitle = 'Tell us what you need — we will suggest the right service and fee band.';
$analysis = null;
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyRequest()) {
        flash('error', 'Invalid request. Please try again.');
        header('Location: ' . clientUrl('pages/intake.php'));
        exit;
    }

    $description = trim((string) ($_POST['matter_description'] ?? ''));
    $action = trim((string) ($_POST['intake_action'] ?? 'preview'));

    if ($description === '') {
        flash('error', 'Please describe what you need.');
        header('Location: ' . clientUrl('pages/intake.php'));
        exit;
    }

    if ($action === 'submit') {
        ClientIntakeService::submit($clientId, $description);
        flash('success', 'Your request has been sent. We will contact you shortly.');
        header('Location: ' . clientUrl('pages/dashboard.php'));
        exit;
    }

    $analysis = ClientIntakeService::analyze($description);
    $submitted = true;
}

require __DIR__ . '/../includes/header.php';
?>

<div class="case-panel client-intake-page">
    <h2 class="case-panel-title">Smart intake</h2>
    <p class="text-muted">Describe your notary need in your own words. We will suggest a service type, typical fee range, and documents you may need to bring.</p>

    <form method="post" action="<?= clientUrl('pages/intake.php') ?>" class="client-intake-form">
        <?= CSRF::field() ?>
        <input type="hidden" name="intake_action" value="<?= $submitted ? 'submit' : 'preview' ?>" id="intakeActionField">
        <div class="mb-3">
            <label class="form-label" for="matterDescription">What do you need?</label>
            <textarea name="matter_description" id="matterDescription" class="form-control" rows="5" required placeholder="e.g. I need a power of attorney notarised for use in Mauritius..."><?= e($_POST['matter_description'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><?= $submitted ? 'Submit request to office' : 'Get suggestions' ?></button>
        <?php if ($submitted): ?>
            <button type="button" class="btn btn-soft ms-2" onclick="document.getElementById('intakeActionField').value='preview'; this.form.submit();">Revise description</button>
        <?php endif; ?>
    </form>

    <?php if ($analysis): ?>
    <div class="client-intake-results mt-4">
        <h3 class="h6">Suggested for you</h3>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="client-intake-card">
                    <span class="client-intake-card__label">Service</span>
                    <strong><?= e($analysis['service']) ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="client-intake-card">
                    <span class="client-intake-card__label">Typical fee band</span>
                    <strong><?= formatCurrency($analysis['fee_min']) ?> – <?= formatCurrency($analysis['fee_max']) ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="client-intake-card">
                    <span class="client-intake-card__label">Checklist preview</span>
                    <ul class="small mb-0 ps-3">
                        <?php foreach (array_slice($analysis['checklist'], 0, 4) as $item): ?>
                            <li><?= e($item['label'] ?? '') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="alert alert-info mt-3 mb-0"><?= nl2br(e($analysis['notes'])) ?></div>
        <p class="small text-muted mt-2 mb-0">Final fees are confirmed after review. Click <strong>Submit request to office</strong> when you are ready.</p>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
