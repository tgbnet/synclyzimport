<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$latestVideos = db()->query('SELECT v.id, v.title, v.description, v.category, v.tags, v.created_at, u.username FROM videos v JOIN users u ON u.id = v.user_id ORDER BY v.created_at DESC LIMIT 8')->fetchAll();
$latestAlbums = db()->query('SELECT a.id, a.title, a.created_at, u.username, (SELECT image_path FROM photos p WHERE p.album_id = a.id ORDER BY p.created_at ASC LIMIT 1) AS cover FROM albums a JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 6')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1>Self-Hosted Video Sharing CMS</h1>
<p>Share videos, organize content by category/tags, host photo albums, and connect with the community.</p>

<h2>Latest Videos</h2>
<div class="card-grid">
    <?php foreach ($latestVideos as $video): ?>
        <article class="card">
            <h3><a href="video.php?id=<?= (int) $video['id'] ?>"><?= e($video['title']) ?></a></h3>
            <p>By <a href="profile.php?u=<?= urlencode($video['username']) ?>"><?= e($video['username']) ?></a></p>
            <p><?= e($video['category']) ?></p>
            <p><?= e(mb_strimwidth($video['description'], 0, 130, '...')) ?></p>
        </article>
    <?php endforeach; ?>
</div>

<h2>Latest Photo Albums</h2>
<div class="card-grid">
    <?php foreach ($latestAlbums as $album): ?>
        <article class="card">
            <?php if ($album['cover']): ?>
                <img class="photo-thumb" src="<?= e($album['cover']) ?>" alt="Album cover">
            <?php endif; ?>
            <h3><a href="album.php?id=<?= (int) $album['id'] ?>"><?= e($album['title']) ?></a></h3>
            <p>By <a href="profile.php?u=<?= urlencode($album['username']) ?>"><?= e($album['username']) ?></a></p>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
