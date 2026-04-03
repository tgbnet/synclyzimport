<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$category = trim((string) ($_GET['category'] ?? ''));
$tag = trim((string) ($_GET['tag'] ?? ''));

$sql = 'SELECT v.id, v.title, v.description, v.category, v.tags, v.created_at, u.username FROM videos v JOIN users u ON u.id = v.user_id';
$params = [];
$conditions = [];

if ($category !== '') {
    $conditions[] = 'v.category = :category';
    $params['category'] = $category;
}
if ($tag !== '') {
    $conditions[] = 'v.tags LIKE :tag';
    $params['tag'] = '%' . $tag . '%';
}
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY v.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1>Video Gallery</h1>
<form method="get">
    <label>Category</label>
    <select name="category">
        <option value="">All categories</option>
        <?php foreach (videoCategories() as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= e($cat) ?></option>
        <?php endforeach; ?>
    </select>
    <label>Tag contains</label>
    <input type="text" name="tag" value="<?= e($tag) ?>">
    <button type="submit">Filter</button>
</form>

<div class="card-grid">
    <?php foreach ($videos as $video): ?>
        <article class="card">
            <h3><a href="video.php?id=<?= (int) $video['id'] ?>"><?= e($video['title']) ?></a></h3>
            <p>By <a href="profile.php?u=<?= urlencode($video['username']) ?>"><?= e($video['username']) ?></a></p>
            <p>Category: <?= e($video['category']) ?></p>
            <p>Tags: <?= e($video['tags']) ?></p>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
