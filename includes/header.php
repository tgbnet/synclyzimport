<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = currentUser();
$appName = config('app', 'name') ?: 'Video CMS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?></title>
    <link rel="stylesheet" href="<?= e(baseUrl('styles.css')) ?>">
</head>
<body>
<header>
    <div class="nav-wrap">
        <a class="logo" href="<?= e(baseUrl('index.php')) ?>"><?= e($appName) ?></a>
        <nav>
            <a href="<?= e(baseUrl('gallery.php')) ?>">Video Gallery</a>
            <a href="<?= e(baseUrl('albums.php')) ?>">Photo Albums</a>
            <?php if ($user): ?>
                <a href="<?= e(baseUrl('upload.php')) ?>">Upload Video</a>
                <a href="<?= e(baseUrl('messages.php')) ?>">Messages</a>
                <a href="<?= e(baseUrl('profile.php?u=' . $user['username'])) ?>">Profile</a>
                <?php if ((int) $user['is_admin'] === 1): ?>
                    <a href="<?= e(baseUrl('siteadmin/index.php')) ?>">Admin</a>
                <?php endif; ?>
                <a href="<?= e(baseUrl('logout.php')) ?>">Logout</a>
            <?php else: ?>
                <a href="<?= e(baseUrl('login.php')) ?>">Log in</a>
                <a href="<?= e(baseUrl('register.php')) ?>">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
    <?php if ($ok = flash('ok')): ?><p class="flash ok"><?= e($ok) ?></p><?php endif; ?>
    <?php if ($error = flash('error')): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
