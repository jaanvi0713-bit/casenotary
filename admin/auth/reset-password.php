<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::guest();

$error   = '';
$success = '';
$token   = $_GET['token'] ?? '';
$email   = $_GET['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::verifyRequest()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email           = trim($_POST['email'] ?? '');
        $token           = $_POST['token'] ?? '';
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirmation'] ?? '';

        if (($strengthError = passwordStrengthError($password)) !== null) {
            $error = $strengthError;
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $result = Auth::resetPassword($email, $token, $password);

            if ($result['success']) {
                flash('success', $result['message']);
                redirect('auth/login.php');
            }

            $error = $result['message'];
        }
    }
}

$company = getCompanySettings();
$pageTitle = 'Reset Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= e(companyBrandName($company)) ?></title>
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
                    <h2>Reset Password</h2>
                    <p>Create a new secure password</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="email" value="<?= e($email) ?>">

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Password" required minlength="8"
                               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}"
                               title="Password must be at least 8 characters, including uppercase(s), lowercase(s), and number(s).">
                        <label for="password"><i class="bi bi-lock me-2"></i>New Password</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                               placeholder="Confirm Password" required minlength="8">
                        <label for="password_confirmation"><i class="bi bi-lock-fill me-2"></i>Confirm Password</label>
                    </div>

                    <p class="text-muted small mb-4">Password must be at least 8 characters, including uppercase(s), lowercase(s), and number(s).</p>

                    <button type="submit" class="btn btn-primary btn-auth w-100 mb-3">
                        <span>Update Password</span>
                        <i class="bi bi-check-lg"></i>
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
