<?php

declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';
requireAdmin();

if (isPost()) {
    $videoId = (int) ($_POST['video_id'] ?? 0);
    if ($videoId > 0) {
        $stmt = db()->prepare('SELECT video_path FROM videos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $videoId]);
        $video = $stmt->fetch();

        if ($video) {
            $publicPrefix = baseUrl('public/uploads/videos/');
            $relative = str_replace($publicPrefix, '', (string) $video['video_path']);
            $filePath = (string) config('app', 'video_upload_dir') . $relative;
            if (is_file($filePath)) {
                unlink($filePath);
            }
            db()->prepare('DELETE FROM videos WHERE id = :id')->execute(['id' => $videoId]);
            flash('ok', 'Video deleted.');
        }
    }
    redirect('videos.php');
}

$videos = db()->query('SELECT v.id, v.title, v.category, v.created_at, u.username FROM videos v JOIN users u ON u.id = v.user_id ORDER BY v.created_at DESC')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h1>Manage Videos</h1>
<table>
    <thead><tr><th>ID</th><th>Title</th><th>Owner</th><th>Category</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($videos as $video): ?>
        <tr>
            <td><?= (int) $video['id'] ?></td>
            <td><a href="../video.php?id=<?= (int) $video['id'] ?>"><?= e($video['title']) ?></a></td>
            <td><?= e($video['username']) ?></td>
            <td><?= e($video['category']) ?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="video_id" value="<?= (int) $video['id'] ?>">
                    <button type="submit" onclick="return confirm('Delete video?')">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../includes/footer.php'; ?>
