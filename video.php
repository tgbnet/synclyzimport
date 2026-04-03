<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT v.*, u.username FROM videos v JOIN users u ON u.id = v.user_id WHERE v.id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$video = $stmt->fetch();

if (!$video) {
    flash('error', 'Video not found.');
    redirect('gallery.php');
}

require __DIR__ . '/includes/header.php';
$embed = '<iframe src="' . e(baseUrl('embed.php?id=' . (int) $video['id'])) . '" width="640" height="360" frameborder="0" allowfullscreen></iframe>';
?>
<article>
    <h1><?= e($video['title']) ?></h1>
    <p>By <a href="profile.php?u=<?= urlencode($video['username']) ?>"><?= e($video['username']) ?></a> on <?= e($video['created_at']) ?></p>
    <video class="video-player" controls src="<?= e($video['video_path']) ?>"></video>
    <p><?= nl2br(e($video['description'])) ?></p>
    <p>Category: <?= e($video['category']) ?> | Tags: <?= e($video['tags']) ?></p>

    <h3>Embed this video</h3>
    <textarea rows="3" readonly><?= e($embed) ?></textarea>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
