<?php
require_once __DIR__ . '/../core/bootstrap.php';

$portal = $_GET['portal'] ?? $_POST['portal'] ?? 'admin';
if (!in_array($portal, ['admin', 'client'], true)) {
    $portal = 'admin';
}

Auth::guest($portal);

$error = '';
$email = old('email');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyRequest()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $portal   = $_POST['portal'] ?? 'admin';

        if (!in_array($portal, ['admin', 'client'], true)) {
            $portal = 'admin';
        }

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
            setOld(['email' => $email]);
        } else {
            $result = Auth::attempt($email, $password, $portal);

            if ($result['success']) {
                clearOld();
                if ($portal === 'client') {
                    header('Location: ' . clientUrl('pages/dashboard.php'));
                    exit;
                }
                redirect('pages/dashboard.php');
            }

            $error = $result['message'];
            setOld(['email' => $email]);
        }
    }
}

$company = getCompanySettings();
$pageTitle = 'Sign In';
$isClientPortal = $portal === 'client';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e($company['company_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <style>
        :root {
            --primary: <?= e($company['primary_color']) ?>;
            --secondary: <?= e($company['secondary_color']) ?>;
            --dark-accent: <?= e($company['dark_accent'] ?? '#000000') ?>;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-wrapper">
        <div class="auth-visual">
            <div class="auth-visual-content">
                <div class="auth-brand">
                    <div class="auth-logo">
                        <i class="bi <?= $isClientPortal ? 'bi-person-badge' : 'bi-shield-check' ?>"></i>
                    </div>
                    <h1><?= e($company['company_name']) ?></h1>
                    <p id="portalDescription">
                        <?= $isClientPortal
                            ? 'Secure client portal to view cases, documents, quotations, and appointments.'
                            : 'Secure admin portal for managing notary operations, clients, cases, and documents.' ?>
                    </p>
                </div>
                <div class="auth-features" id="portalFeatures">
                    <?php if ($isClientPortal): ?>
                        <div class="auth-feature"><i class="bi bi-folder2-open"></i><span>View your cases & documents</span></div>
                        <div class="auth-feature"><i class="bi bi-receipt"></i><span>Track invoices & payments</span></div>
                        <div class="auth-feature"><i class="bi bi-calendar-event"></i><span>See upcoming appointments</span></div>
                    <?php else: ?>
                        <div class="auth-feature"><i class="bi bi-lock-fill"></i><span>Enterprise-grade security</span></div>
                        <div class="auth-feature"><i class="bi bi-graph-up-arrow"></i><span>Real-time analytics</span></div>
                        <div class="auth-feature"><i class="bi bi-people-fill"></i><span>Client & case management</span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="auth-form-panel">
            <div class="auth-form-container auth-form-container-wide">
                <div class="auth-form-header">
                    <h2>Welcome back</h2>
                    <p id="portalSubtitle"><?= $isClientPortal ? 'Sign in to your client portal' : 'Sign in to your admin account' ?></p>
                </div>

                <div class="portal-selector" role="tablist">
                    <button type="button" class="portal-option <?= !$isClientPortal ? 'active' : '' ?>" data-portal="admin">
                        <i class="bi bi-shield-lock"></i>
                        <span><strong>Admin</strong><small>Manage operations</small></span>
                    </button>
                    <button type="button" class="portal-option <?= $isClientPortal ? 'active' : '' ?>" data-portal="client">
                        <i class="bi bi-person-circle"></i>
                        <span><strong>Client</strong><small>View your cases</small></span>
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($msg = flash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="auth-form" id="loginForm" novalidate>
                    <?= CSRF::field() ?>
                    <input type="hidden" name="portal" id="portalInput" value="<?= e($portal) ?>">

                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="Email" value="<?= e($email) ?>" required autofocus>
                        <label for="email"><i class="bi bi-envelope me-2"></i>Email Address</label>
                    </div>

                    <div class="form-floating mb-3 auth-password-field">
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Password" required autocomplete="current-password">
                        <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                        <button type="button" class="password-toggle" id="togglePassword" tabindex="-1" aria-label="Show password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4" id="adminExtras" style="<?= $isClientPortal ? 'display:none;' : '' ?>">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="<?= url('auth/forgot-password.php') ?>" class="auth-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-auth w-100" id="submitBtn">
                        <span><?= $isClientPortal ? 'Sign In to Client Portal' : 'Sign In to Admin Portal' ?></span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <div class="auth-footer">
                    <p>Protected by secure authentication &amp; encryption</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset('js/app.js') ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var portalInput = document.getElementById('portalInput');
        var submitBtn = document.querySelector('#submitBtn span');
        var portalSubtitle = document.getElementById('portalSubtitle');
        var adminExtras = document.getElementById('adminExtras');
        var portalDescription = document.getElementById('portalDescription');
        var authLogo = document.querySelector('.auth-logo i');

        document.querySelectorAll('.portal-option').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var portal = btn.dataset.portal;
                document.querySelectorAll('.portal-option').forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                portalInput.value = portal;

                if (portal === 'client') {
                    submitBtn.textContent = 'Sign In to Client Portal';
                    portalSubtitle.textContent = 'Sign in to your client portal';
                    adminExtras.style.display = 'none';
                    portalDescription.textContent = 'Secure client portal to view cases, documents, quotations, and appointments.';
                    authLogo.className = 'bi bi-person-badge';
                } else {
                    submitBtn.textContent = 'Sign In to Admin Portal';
                    portalSubtitle.textContent = 'Sign in to your admin account';
                    adminExtras.style.display = '';
                    portalDescription.textContent = 'Secure admin portal for managing notary operations, clients, cases, and documents.';
                    authLogo.className = 'bi bi-shield-check';
                }
            });
        });
    });
    </script>
</body>
</html>
