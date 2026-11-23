<?php
/** JSON API for the newsfeed: likes, comments, follow, delete post. */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
$action = $_GET['action'] ?? '';

/* ---------- read-only (GET) ---------- */
if (!is_post()) {
    $postId = get_int('post_id');
    if (!fetch_visible_post($postId, $uid)) json_out(['success' => false, 'message' => 'Post not found.'], 404);

    if ($action === 'likes') {
        $rows = db_all('SELECT u.user_id, u.username FROM likes l JOIN user u ON u.user_id = l.user_id WHERE l.post_id = ? ORDER BY l.like_id DESC LIMIT 50', 'i', $postId);
        json_out(['success' => true, 'likes' => $rows]);
    }
    if ($action === 'comments') {
        $rows = db_all('SELECT c.comment_id, c.user_id, c.comment, c.created_at, u.username, u.profile_photo
                        FROM comments c JOIN user u ON u.user_id = c.user_id WHERE c.post_id = ? ORDER BY c.comment_id ASC LIMIT 200', 'i', $postId);
        $owner = (int) db_value('SELECT user_id FROM post WHERE post_id = ?', 'i', $postId);
        foreach ($rows as &$r) {
            $r['avatar'] = avatar_url($r);
            $r['ago'] = time_ago($r['created_at']);
            $r['can_delete'] = ((int) $r['user_id'] === $uid || $owner === $uid);
            unset($r['profile_photo'], $r['created_at']);
        }
        json_out(['success' => true, 'comments' => $rows]);
    }
    json_out(['success' => false, 'message' => 'Unknown action.'], 400);
}

/* ---------- mutations (POST + CSRF) ---------- */
csrf_check();
$in = json_input();
$action = $in['action'] ?? $action;

switch ($action) {
    case 'like': {
        $postId = (int) ($in['post_id'] ?? 0);
        $post = fetch_visible_post($postId, $uid);
        if (!$post) json_out(['success' => false, 'message' => 'Post not found.'], 404);
        $existing = db_one('SELECT like_id FROM likes WHERE user_id = ? AND post_id = ?', 'ii', $uid, $postId);
        if ($existing) {
            db_exec('DELETE FROM likes WHERE like_id = ?', 'i', $existing['like_id']);
            $liked = false;
        } else {
            db_exec('INSERT IGNORE INTO likes (user_id, post_id) VALUES (?, ?)', 'ii', $uid, $postId);
            $liked = true;
            notify((int) $post['user_id'], current_user()['username'] . ' liked your post.', 'modules/social/newsfeed.php#post-' . $postId);
        }
        $count = (int) db_value('SELECT COUNT(*) FROM likes WHERE post_id = ?', 'i', $postId);
        json_out(['success' => true, 'liked' => $liked, 'count' => $count]);
    }
    case 'comment_add': {
        $postId = (int) ($in['post_id'] ?? 0);
        $text = trim((string) ($in['comment'] ?? ''));
        $post = fetch_visible_post($postId, $uid);
        if (!$post) json_out(['success' => false, 'message' => 'Post not found.'], 404);
        if ($text === '' || mb_strlen($text) > 1000) json_out(['success' => false, 'message' => 'Comment must be 1–1000 characters.'], 422);
        db_exec('INSERT INTO comments (post_id, user_id, comment) VALUES (?, ?, ?)', 'iis', $postId, $uid, $text);
        $cid = db_insert_id();
        notify((int) $post['user_id'], current_user()['username'] . ' commented on your post.', 'modules/social/newsfeed.php#post-' . $postId);
        $count = (int) db_value('SELECT COUNT(*) FROM comments WHERE post_id = ?', 'i', $postId);
        $me = current_user();
        json_out(['success' => true, 'count' => $count, 'comment' => [
            'comment_id' => $cid, 'user_id' => $uid, 'comment' => $text, 'username' => $me['username'],
            'avatar' => avatar_url($me), 'ago' => 'just now', 'can_delete' => true,
        ]]);
    }
    case 'comment_del': {
        $cid = (int) ($in['comment_id'] ?? 0);
        $row = db_one('SELECT c.post_id, c.user_id, p.user_id AS owner FROM comments c JOIN post p ON p.post_id = c.post_id WHERE c.comment_id = ?', 'i', $cid);
        if (!$row || ((int) $row['user_id'] !== $uid && (int) $row['owner'] !== $uid)) json_out(['success' => false, 'message' => 'You cannot delete this comment.'], 403);
        db_exec('DELETE FROM comments WHERE comment_id = ?', 'i', $cid);
        $count = (int) db_value('SELECT COUNT(*) FROM comments WHERE post_id = ?', 'i', $row['post_id']);
        json_out(['success' => true, 'count' => $count]);
    }
    case 'post_del': {
        $postId = (int) ($in['post_id'] ?? 0);
        $post = db_one('SELECT photo FROM post WHERE post_id = ? AND user_id = ?', 'ii', $postId, $uid);
        if (!$post) json_out(['success' => false, 'message' => 'You cannot delete this post.'], 403);
        db_exec('DELETE FROM post WHERE post_id = ? AND user_id = ?', 'ii', $postId, $uid);
        delete_media($post['photo']);
        json_out(['success' => true]);
    }
    case 'follow': {
        $target = (int) ($in['user_id'] ?? 0);
        if ($target === $uid) json_out(['success' => false, 'message' => 'You cannot follow yourself.'], 422);
        if (!db_one('SELECT 1 AS x FROM user WHERE user_id = ?', 'i', $target)) json_out(['success' => false, 'message' => 'User not found.'], 404);
        $ex = db_one('SELECT follower_id FROM followers WHERE user_id = ? AND follower_user_id = ?', 'ii', $target, $uid);
        if ($ex) {
            db_exec('DELETE FROM followers WHERE follower_id = ?', 'i', $ex['follower_id']);
            $following = false;
        } else {
            db_exec('INSERT IGNORE INTO followers (user_id, follower_user_id) VALUES (?, ?)', 'ii', $target, $uid);
            $following = true;
            notify($target, current_user()['username'] . ' started following you.', 'modules/social/view_profile.php?user_id=' . $uid);
        }
        $cnt = (int) db_value('SELECT COUNT(*) FROM followers WHERE user_id = ?', 'i', $target);
        json_out(['success' => true, 'following' => $following, 'followers' => $cnt]);
    }
}
json_out(['success' => false, 'message' => 'Unknown action.'], 400);
