<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$user = currentUser();
$albumId = (int) ($_GET['id'] ?? 0);

$albumStmt = db()->prepare('SELECT a.*, u.username FROM albums a JOIN users u ON u.id = a.user_id WHERE a.id = :id LIMIT 1');
$albumStmt->execute(['id' => $albumId]);
$album = $albumStmt->fetch();

if (!$album) {
    flash('error', 'Album not found.');
    redirect('albums.php');
}

if (isPost()) {
    if (!$user || (int) $user['id'] !== (int) $album['user_id']) {
        flash('error', 'Only album owner can upload photos.');
        redirect('album.php?id=' . $albumId);
    }

    if (empty($_FILES['photo']['name'])) {
        flash('error', 'Select a photo file.');
        redirect('album.php?id=' . $albumId);
    }

    $maxBytes = (int) config('app', 'max_photo_size_mb') * 1024 * 1024;
    if ((int) $_FILES['photo']['size'] > $maxBytes) {
        flash('error', 'Photo exceeds max upload size.');
        redirect('album.php?id=' . $albumId);
    }

    $ext = strtolower(pathinfo((string) $_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        flash('error', 'Invalid photo format.');
        redirect('album.php?id=' . $albumId);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetDir = (string) config('app', 'photo_upload_dir');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $absolutePath = $targetDir . $filename;
    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $absolutePath)) {
        flash('error', 'Photo upload failed.');
        redirect('album.php?id=' . $albumId);
    }

    $publicPath = baseUrl('public/uploads/photos/' . $filename);
    $insert = db()->prepare('INSERT INTO photos (album_id, image_path, caption, created_at) VALUES (:album_id, :image_path, :caption, NOW())');
    $insert->execute([
        'album_id' => $albumId,
        'image_path' => $publicPath,
        'caption' => trim((string) ($_POST['caption'] ?? '')),
    ]);

    flash('ok', 'Photo uploaded.');
    redirect('album.php?id=' . $albumId);
}

$photosStmt = db()->prepare('SELECT id, image_path, caption, created_at FROM photos WHERE album_id = :album_id ORDER BY created_at DESC');
$photosStmt->execute(['album_id' => $albumId]);
$photos = $photosStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1><?= e($album['title']) ?></h1>
<p>By <a href="profile.php?u=<?= urlencode($album['username']) ?>"><?= e($album['username']) ?></a></p>
<p><?= nl2br(e((string) $album['description'])) ?></p>

<?php if ($user && (int) $user['id'] === (int) $album['user_id']): ?>
<form method="post" enctype="multipart/form-data">
    <label>Upload photo</label>
    <input type="file" name="photo" accept="image/*" required>
    <label>Caption</label>
    <input type="text" name="caption">
    <button type="submit">Add photo</button>
</form>
<?php endif; ?>

<div class="card-grid">
    <?php foreach ($photos as $photo): ?>
        <article class="card">
            <img class="photo-thumb" src="<?= e($photo['image_path']) ?>" alt="<?= e($photo['caption']) ?>">
            <p><?= e($photo['caption']) ?></p>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
