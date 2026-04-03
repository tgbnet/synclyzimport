<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$user = requireAuth();

if (isPost()) {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? 'Other'));
    $tags = trim((string) ($_POST['tags'] ?? ''));

    if ($title === '' || empty($_FILES['video']['name'])) {
        flash('error', 'Title and video file are required.');
        redirect('upload.php');
    }

    $maxBytes = (int) config('app', 'max_video_size_mb') * 1024 * 1024;
    if ((int) $_FILES['video']['size'] > $maxBytes) {
        flash('error', 'Video exceeds max upload size.');
        redirect('upload.php');
    }

    $ext = strtolower(pathinfo((string) $_FILES['video']['name'], PATHINFO_EXTENSION));
    $allowed = ['mp4', 'webm', 'ogg'];
    if (!in_array($ext, $allowed, true)) {
        flash('error', 'Allowed video formats: mp4, webm, ogg');
        redirect('upload.php');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetDir = (string) config('app', 'video_upload_dir');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $absolutePath = $targetDir . $filename;
    if (!move_uploaded_file($_FILES['video']['tmp_name'], $absolutePath)) {
        flash('error', 'Upload failed.');
        redirect('upload.php');
    }

    $publicPath = baseUrl('public/uploads/videos/' . $filename);
    $stmt = db()->prepare('INSERT INTO videos (user_id, title, description, category, tags, video_path, created_at) VALUES (:user_id, :title, :description, :category, :tags, :video_path, NOW())');
    $stmt->execute([
        'user_id' => $user['id'],
        'title' => $title,
        'description' => $description,
        'category' => in_array($category, videoCategories(), true) ? $category : 'Other',
        'tags' => $tags,
        'video_path' => $publicPath,
    ]);

    flash('ok', 'Video uploaded.');
    redirect('gallery.php');
}

require __DIR__ . '/includes/header.php';
?>
<h1>Upload Video</h1>
<form method="post" enctype="multipart/form-data">
    <label>Title</label>
    <input type="text" name="title" required>
    <label>Description</label>
    <textarea name="description" rows="4"></textarea>
    <label>Category</label>
    <select name="category">
        <?php foreach (videoCategories() as $cat): ?>
            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
        <?php endforeach; ?>
    </select>
    <label>Tags (comma-separated)</label>
    <input type="text" name="tags">
    <label>Video file</label>
    <input type="file" name="video" accept="video/mp4,video/webm,video/ogg" required>
    <button type="submit">Upload</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
