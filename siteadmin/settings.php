<?php

declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';
requireAdmin();

if (isPost()) {
    $siteName = trim((string) ($_POST['site_name'] ?? ''));
    if ($siteName !== '') {
        $stmt = db()->prepare('UPDATE settings SET setting_value = :value WHERE setting_key = :key');
        $stmt->execute(['value' => $siteName, 'key' => 'site_name']);
        flash('ok', 'Site name updated in DB settings table. Update config/config.php to make it active at runtime.');
    }
    redirect('settings.php');
}

$settings = db()->query('SELECT setting_key, setting_value FROM settings ORDER BY setting_key')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h1>Site Settings</h1>
<form method="post">
    <label>Site Name (stored in DB setting)</label>
    <input type="text" name="site_name" value="<?= e((string) config('app', 'name')) ?>">
    <button type="submit">Save</button>
</form>

<h2>Current DB Settings</h2>
<table>
    <thead><tr><th>Key</th><th>Value</th></tr></thead>
    <tbody>
    <?php foreach ($settings as $setting): ?>
        <tr><td><?= e($setting['setting_key']) ?></td><td><?= e($setting['setting_value']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../includes/footer.php'; ?>
