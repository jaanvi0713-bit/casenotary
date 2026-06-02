<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::guest();

$error   = '';
$success = '';
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyRequest()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $result = Auth::createPasswordReset($email);
            $success = $result['message'];

            if (!empty($result['reset_link']) && ($config = require __DIR__ . '/../config/config.php') && $config['debug']) {
                flash('reset_link', $result['reset_link']);
            }
        }
    }
}

$company = getCompanySettings();
$pageTitle = 'Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(companyBrandName($company)) ?></title>
    <?= renderFaviconTags($company) ?>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <style>
        :root {
            --primary: <?= e($company['primary_color']) ?>;
            --secondary: <?= e($company['secondary_color']) ?>;
            --dark-accent: <?= e($company['dark_accent']) ?>;
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-wrapper auth-wrapper-single">
        <div class="auth-form-panel auth-form-panel-full">
            <div class="auth-form-container">
                <div class="auth-form-header text-center">
                    <div class="auth-logo-sm mx-auto mb-3">
                        <?= renderCompanyLogo('auth-sm', $company, 'admin') ?>
                    </div>
                    <h2>Forgot Password</h2>
                    <p>Enter your email and we'll send you a reset link</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div>
                    <?php if ($link = flash('reset_link')): ?>
                        <div class="alert alert-info">
                            <small><strong>Dev mode:</strong> <a href="<?= e($link) ?>"><?= e($link) ?></a></small>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <?= CSRF::field() ?>
                    <div class="form-floating mb-4">
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="Email" value="<?= e($email) ?>" required autofocus>
                        <label for="email"><i class="bi bi-envelope me-2"></i>Email Address</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-auth w-100 mb-3">
                        <span>Send Reset Link</span>
                        <i class="bi bi-send"></i>
                    </button>

                    <a href="<?= url('auth/login.php') ?>" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-left me-2"></i>Back to Sign In
                    </a>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
