<?php
require_once __DIR__ . '/../../config/config.php';
if (!isLoggedIn()) { redirect('auth/login.php'); }
$userId = (int)$_SESSION['user_id'];
$recipientId = filter_input(INPUT_GET, 'to', FILTER_VALIDATE_INT) ?: 0;
$error = '';
$success = '';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientId = (int)($_POST['to'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($recipientId < 1 || $recipientId === $userId || $message === '' || mb_strlen($message) > 4000) {
        $error = 'Choose a recipient and enter a message (up to 4,000 characters).';
    } else {
        $check = $conn->prepare('SELECT user_id FROM user WHERE user_id = ?');
        $check->bind_param('i', $recipientId); $check->execute();
        $validRecipient = $check->get_result()->num_rows === 1; $check->close();
        if (!$validRecipient) { $error = 'That recipient could not be found.'; }
        else {
            $stmt = $conn->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $userId, $recipientId, $message);
            if ($stmt->execute()) { $stmt->close(); header('Location: update.php?to=' . $recipientId); exit; }
            $error = 'Message could not be sent. Please try again.'; $stmt->close();
        }
    }
}
$users = $conn->prepare('SELECT user_id, username FROM user WHERE user_id <> ? ORDER BY username');
$users->bind_param('i', $userId); $users->execute(); $userList = $users->get_result();
$recipientName = '';
$messages = null;
if ($recipientId > 0 && $recipientId !== $userId) {
    $recipient = $conn->prepare('SELECT username FROM user WHERE user_id = ?');
    $recipient->bind_param('i', $recipientId); $recipient->execute();
    $recipientName = (string)($recipient->get_result()->fetch_assoc()['username'] ?? '');
    $recipient->close();
    if ($recipientName !== '') {
        $read = $conn->prepare('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?');
        $read->bind_param('ii', $recipientId, $userId); $read->execute(); $read->close();
        $stmt = $conn->prepare('SELECT sender_id, message, created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC');
        $stmt->bind_param('iiii', $userId, $recipientId, $recipientId, $userId); $stmt->execute(); $messages = $stmt->get_result();
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>messages · ParentConnect</title><link rel="stylesheet" href="<?= SITE_URL ?>assets/css/style.css"></head><body>
<?php include __DIR__ . '/../../includes/header.php'; ?>
<main class="app-content"><h1>Private messages</h1><p>Connect privately with another member of the community.</p>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card" style="margin-bottom:20px"><form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center"><label for="to">Choose a member</label><select id="to" name="to" required><option value="">Select a member</option><?php while($u=$userList->fetch_assoc()): ?><option value="<?= (int)$u['user_id'] ?>" <?= $recipientId===(int)$u['user_id']?'selected':'' ?>><?= htmlspecialchars($u['username'],ENT_QUOTES,'UTF-8') ?></option><?php endwhile; ?></select><button class="btn btn-primary" type="submit">Open conversation</button></form></div>
<?php if ($recipientName !== ''): ?>
<section class="card"><h2>Conversation with <?= htmlspecialchars($recipientName,ENT_QUOTES,'UTF-8') ?></h2>
<div style="display:grid;gap:12px;margin:18px 0"><?php if ($messages && $messages->num_rows): while($m=$messages->fetch_assoc()): ?><div style="max-width:80%;justify-self:<?= (int)$m['sender_id']===$userId?'end':'start' ?>;padding:12px 16px;border-radius:15px;background:<?= (int)$m['sender_id']===$userId?'#e8f3f7':'#f1f0f8' ?>"><p style="margin:0"><?= nl2br(htmlspecialchars($m['message'],ENT_QUOTES,'UTF-8')) ?></p><small><?= htmlspecialchars($m['created_at'],ENT_QUOTES,'UTF-8') ?></small></div><?php endwhile; else: ?><p>No messages yet. Start the conversation below.</p><?php endif; ?></div>
<form method="post"><input type="hidden" name="to" value="<?= $recipientId ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8') ?>"><label for="message">Your message</label><textarea id="message" name="message" rows="3" maxlength="4000" required></textarea><button class="btn btn-primary" type="submit">Send message</button></form></section>
<?php elseif ($recipientId): ?><div class="alert alert-error">Member not found.</div><?php endif; ?>
</main><?php include __DIR__ . '/../../includes/footer.php'; ?></body></html>
