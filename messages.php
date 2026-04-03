<?php

declare(strict_types=1);

require __DIR__ . '/includes/functions.php';

$user = requireAuth();

if (isPost()) {
    $toUsername = trim((string) ($_POST['to_username'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));

    if ($toUsername === '' || $body === '') {
        flash('error', 'Recipient and message are required.');
        redirect('messages.php');
    }

    $toStmt = db()->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $toStmt->execute(['username' => $toUsername]);
    $recipient = $toStmt->fetch();

    if (!$recipient) {
        flash('error', 'Recipient not found.');
        redirect('messages.php');
    }

    $insert = db()->prepare('INSERT INTO messages (sender_id, recipient_id, body, created_at) VALUES (:sender_id, :recipient_id, :body, NOW())');
    $insert->execute([
        'sender_id' => $user['id'],
        'recipient_id' => $recipient['id'],
        'body' => $body,
    ]);

    flash('ok', 'Message sent.');
    redirect('messages.php');
}

$inboxStmt = db()->prepare('SELECT m.body, m.created_at, u.username AS sender FROM messages m JOIN users u ON u.id = m.sender_id WHERE m.recipient_id = :id ORDER BY m.created_at DESC');
$inboxStmt->execute(['id' => $user['id']]);
$inbox = $inboxStmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<h1>Messages</h1>
<form method="post">
    <label>To (username)</label>
    <input type="text" name="to_username" required>
    <label>Message</label>
    <textarea name="body" rows="4" required></textarea>
    <button type="submit">Send message</button>
</form>

<h2>Inbox</h2>
<?php foreach ($inbox as $message): ?>
    <article class="card">
        <p><strong>From:</strong> <?= e($message['sender']) ?> | <?= e($message['created_at']) ?></p>
        <p><?= nl2br(e($message['body'])) ?></p>
    </article>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
