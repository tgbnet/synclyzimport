<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT title, video_path FROM videos WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$video = $stmt->fetch();

if (!$video) {
    http_response_code(404);
    echo 'Video not found.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#000;display:flex;align-items:center;justify-content:center;min-height:100vh;">
<video controls style="width:100%;max-height:100vh;" src="<?= e($video['video_path']) ?>"></video>
</body>
</html>
