<?php

declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';
$user = requireAdmin();

$stats = [
    'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'videos' => (int) db()->query('SELECT COUNT(*) FROM videos')->fetchColumn(),
    'albums' => (int) db()->query('SELECT COUNT(*) FROM albums')->fetchColumn(),
    'messages' => (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
];

require __DIR__ . '/../includes/header.php';
?>
<h1>Site Admin</h1>
<p>Welcome, <?= e($user['username']) ?>. Use the admin tools to manage users, videos, and settings.</p>
<ul>
    <li>Total users: <?= $stats['users'] ?></li>
    <li>Total videos: <?= $stats['videos'] ?></li>
    <li>Total albums: <?= $stats['albums'] ?></li>
    <li>Total messages: <?= $stats['messages'] ?></li>
</ul>
<p><a href="users.php">Manage users</a> | <a href="videos.php">Manage videos</a> | <a href="settings.php">Site settings</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
