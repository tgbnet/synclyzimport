<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$user = currentUser();

if (isPost()) {
    if (!$user) {
        flash('error', 'Please log in first.');
        redirect('login.php');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($title === '') {
        flash('error', 'Album title is required.');
        redirect('albums.php');
    }

    $stmt = db()->prepare('INSERT INTO albums (user_id, title, description, created_at) VALUES (:user_id, :title, :description, NOW())');
    $stmt->execute([
        'user_id' => $user['id'],
        'title' => $title,
        'description' => $description,
    ]);

    flash('ok', 'Album created. You can now upload photos.');
    redirect('albums.php');
}

$albums = db()->query('SELECT a.id, a.title, a.description, a.created_at, u.username, (SELECT image_path FROM photos p WHERE p.album_id = a.id ORDER BY p.created_at ASC LIMIT 1) AS cover FROM albums a JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC')->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1>Photo Albums</h1>
<?php if ($user): ?>
<form method="post">
    <label>Album title</label>
    <input type="text" name="title" required>
    <label>Description</label>
    <textarea name="description" rows="3"></textarea>
    <button type="submit">Create album</button>
</form>
<?php endif; ?>

<div class="card-grid">
    <?php foreach ($albums as $album): ?>
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
