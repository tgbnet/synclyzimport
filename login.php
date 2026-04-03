<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

if (isPost()) {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = :identifier OR email = :identifier LIMIT 1');
    $stmt->execute(['identifier' => $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('error', 'Invalid credentials.');
        redirect('login.php');
    }

    $_SESSION['user_id'] = (int) $user['id'];
    flash('ok', 'Logged in successfully.');
    redirect('index.php');
}

require __DIR__ . '/includes/header.php';
?>
<h1>Log in</h1>
<form method="post">
    <label>Username or Email</label>
    <input type="text" name="identifier" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit">Log in</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
