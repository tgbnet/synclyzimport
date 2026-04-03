<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

if (isPost()) {
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        flash('error', 'All fields are required.');
        redirect('register.php');
    }

    $exists = db()->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $exists->execute(['username' => $username, 'email' => $email]);
    if ($exists->fetch()) {
        flash('error', 'Username or email already exists.');
        redirect('register.php');
    }

    $isFirst = userCount() === 0 ? 1 : 0;
    $stmt = db()->prepare('INSERT INTO users (username, email, password_hash, is_admin, created_at) VALUES (:username, :email, :password_hash, :is_admin, NOW())');
    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'is_admin' => $isFirst,
    ]);

    flash('ok', 'Account created. You can now log in.');
    redirect('login.php');
}

require __DIR__ . '/includes/header.php';
?>
<h1>Create account</h1>
<form method="post">
    <label>Username</label>
    <input type="text" name="username" maxlength="50" required>
    <label>Email</label>
    <input type="email" name="email" maxlength="190" required>
    <label>Password</label>
    <input type="password" name="password" minlength="8" required>
    <button type="submit">Sign up</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
