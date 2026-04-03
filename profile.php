<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$username = trim((string) ($_GET['u'] ?? ''));
$stmt = db()->prepare('SELECT id, username, bio, created_at FROM users WHERE username = :username LIMIT 1');
$stmt->execute(['username' => $username]);
$profile = $stmt->fetch();

if (!$profile) {
    flash('error', 'Profile not found.');
    redirect('index.php');
}

$videosStmt = db()->prepare('SELECT id, title, category, created_at FROM videos WHERE user_id = :user_id ORDER BY created_at DESC');
$videosStmt->execute(['user_id' => $profile['id']]);
$videos = $videosStmt->fetchAll();

$albumsStmt = db()->prepare('SELECT id, title, created_at FROM albums WHERE user_id = :user_id ORDER BY created_at DESC');
$albumsStmt->execute(['user_id' => $profile['id']]);
$albums = $albumsStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1><?= e($profile['username']) ?>'s profile</h1>
<p>Joined: <?= e($profile['created_at']) ?></p>
<p><?= nl2br(e((string) $profile['bio'])) ?></p>

<h2>Videos</h2>
<ul>
    <?php foreach ($videos as $video): ?>
        <li><a href="video.php?id=<?= (int) $video['id'] ?>"><?= e($video['title']) ?></a> (<?= e($video['category']) ?>)</li>
    <?php endforeach; ?>
</ul>

<h2>Albums</h2>
<ul>
    <?php foreach ($albums as $album): ?>
        <li><a href="album.php?id=<?= (int) $album['id'] ?>"><?= e($album['title']) ?></a></li>
    <?php endforeach; ?>
</ul>
<?php require __DIR__ . '/includes/footer.php'; ?>
