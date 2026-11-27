<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';
$count = (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($count > 0) {
    flash('error', 'An admin account already exists. Please sign in.');
    redirect('admin.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $username)) {
        $error = 'Use 3–80 characters: letters, numbers, dots, dashes or underscores.';
    } elseif (strlen($password) < 10) {
        $error = 'Choose a password with at least 10 characters.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match.';
    } else {
        $insert = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
        $insert->execute(['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)db()->lastInsertId();
        $_SESSION['admin_username'] = $username;
        flash('success', 'Admin account created. You are now signed in.');
        redirect('admin.php');
    }
}
$pageTitle = 'Create admin account';
$activePage = 'admin';
require __DIR__ . '/includes/header.php';
?>
<section class="setup-shell page-shell"><div class="setup-card reveal"><div class="setup-icon">✳</div><div class="eyebrow"><span class="eyebrow-line"></span> ONE-TIME SETUP</div><h1>Create your<br><em>admin account.</em></h1><p>This page is available only until the first admin account is created.</p>
<?php if ($error): ?><div class="inline-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form-stack" data-validate><?= csrf_field() ?><label class="field-label" for="setupUsername">Username</label><input class="text-input" id="setupUsername" name="username" required minlength="3" maxlength="80" autocomplete="username" placeholder="Choose a username"><label class="field-label" for="setupPassword">Password</label><input class="text-input" id="setupPassword" type="password" name="password" required minlength="10" autocomplete="new-password" placeholder="At least 10 characters"><label class="field-label" for="confirmPassword">Confirm password</label><input class="text-input" id="confirmPassword" type="password" name="confirm_password" required minlength="10" autocomplete="new-password" placeholder="Enter it once more"><button type="submit" class="button button-primary button-block">Create admin account <span class="button-arrow">↗</span></button></form><a class="back-link setup-back" href="admin.php">← Back to staff sign in</a></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
