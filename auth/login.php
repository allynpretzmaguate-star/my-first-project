<?php
require_once dirname(__DIR__) . '/config/config.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (too_many_attempts()) {
        $errors[] = 'Too many failed login attempts. Please try again in a few minutes.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $errors[] = 'Please enter both username and password.';
        } else {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
                reset_login_attempts();
                session_regenerate_id(true); // prevent session fixation

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['ua_hash']   = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

                log_activity($user['id'], 'LOGIN', 'User logged in');
                redirect('dashboard.php');
            } elseif ($user && $user['status'] !== 'active') {
                $errors[] = 'This account has been disabled. Contact an administrator.';
            } else {
                register_failed_attempt();
                $errors[] = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-brand">
                <div class="auth-logo">AI</div>
                <h1><?= h(APP_NAME) ?></h1>
                <p>Sign in to continue</p>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
            <?php endforeach; ?>

            <form method="POST" action="login.php" autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autofocus
                           value="<?= h($_POST['username'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Log In</button>
            </form>

            <a href="<?= BASE_URL ?>auth/guest.php" class="btn btn-outline btn-block" style="margin-top:10px">
                Sign in as Guest
            </a>

            <div class="auth-hint" style="margin-top:14px;">
                Don't have an account? <a href="<?= BASE_URL ?>auth/register.php">Create one here</a>
            </div>

            <div class="auth-hint">
                Default admin: <code>admin</code> / <code>Admin@123</code> — change this after first login.
            </div>
        </div>
    </div>
</body>
</html>