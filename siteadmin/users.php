<?php

declare(strict_types=1);

require __DIR__ . '/../includes/functions.php';
requireAdmin();

if (isPost()) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($userId > 0 && in_array($action, ['make_admin', 'remove_admin', 'delete'], true)) {
        if ($action === 'make_admin') {
            db()->prepare('UPDATE users SET is_admin = 1 WHERE id = :id')->execute(['id' => $userId]);
        } elseif ($action === 'remove_admin') {
            db()->prepare('UPDATE users SET is_admin = 0 WHERE id = :id')->execute(['id' => $userId]);
        } else {
            db()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
        }
        flash('ok', 'User action completed.');
    }
    redirect('users.php');
}

$users = db()->query('SELECT id, username, email, is_admin, created_at FROM users ORDER BY created_at DESC')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<h1>Manage Users</h1>
<table>
    <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Admin</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= (int) $u['id'] ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= (int) $u['is_admin'] === 1 ? 'Yes' : 'No' ?></td>
            <td>
                <form method="post" class="inline">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button name="action" value="make_admin">Make Admin</button>
                    <button name="action" value="remove_admin">Remove Admin</button>
                    <button name="action" value="delete" onclick="return confirm('Delete user?')">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php require __DIR__ . '/../includes/footer.php'; ?>
