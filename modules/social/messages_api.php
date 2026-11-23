<?php
/** JSON API for private messaging. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();

function msg_row(array $m, int $uid): array
{
    return ['id' => (int) $m['id'], 'mine' => (int) $m['sender_id'] === $uid, 'body' => $m['body'], 'time' => fmt_dt($m['created_at'], 'M j, g:i A')];
}

if (!is_post()) {
    $action = $_GET['action'] ?? '';
    if ($action === 'fetch') {
        $with = get_int('with'); $after = get_int('after');
        $rows = db_all('SELECT id, sender_id, body, created_at FROM messages
            WHERE id > ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) ORDER BY id ASC LIMIT 100', 'iiiii', $after, $uid, $with, $with, $uid);
        db_exec('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0', 'ii', $with, $uid);
        json_out(['success' => true, 'messages' => array_map(fn($m) => msg_row($m, $uid), $rows), 'unread_total' => unread_messages()]);
    }
    if ($action === 'users') {
        $q = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) json_out(['success' => true, 'users' => []]);
        $l = '%' . addcslashes($q, '%_\\') . '%';
        $rows = db_all('SELECT user_id, username, first_name, last_name, profile_photo FROM user WHERE user_id <> ? AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ?) ORDER BY username LIMIT 8', 'isss', $uid, $l, $l, $l);
        json_out(['success' => true, 'users' => array_map(fn($u) => ['id' => (int) $u['user_id'], 'name' => display_name($u), 'username' => $u['username'], 'avatar' => avatar_url($u)], $rows)]);
    }
    json_out(['success' => false, 'message' => 'Unknown action.'], 400);
}

csrf_check();
$in = json_input();
if (($in['action'] ?? '') === 'send') {
    $to = (int) ($in['to'] ?? 0);
    $body = trim((string) ($in['body'] ?? ''));
    if ($to === $uid) json_out(['success' => false, 'message' => 'You cannot message yourself.'], 422);
    if (!db_one('SELECT 1 AS x FROM user WHERE user_id = ?', 'i', $to)) json_out(['success' => false, 'message' => 'Recipient not found.'], 404);
    if ($body === '' || mb_strlen($body) > 2000) json_out(['success' => false, 'message' => 'Message must be 1–2000 characters.'], 422);
    db_exec('INSERT INTO messages (sender_id, receiver_id, body) VALUES (?,?,?)', 'iis', $uid, $to, $body);
    $m = db_one('SELECT id, sender_id, body, created_at FROM messages WHERE id = ?', 'i', db_insert_id());
    json_out(['success' => true, 'message' => msg_row($m, $uid)]);
}
json_out(['success' => false, 'message' => 'Unknown action.'], 400);
