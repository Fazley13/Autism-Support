<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$with = get_int('with');
$partner = null; $thread = [];
if ($with && $with !== $uid) {
    $partner = db_one('SELECT user_id, username, first_name, last_name, profile_photo FROM user WHERE user_id = ?', 'i', $with);
    if ($partner) {
        db_exec('UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0', 'ii', $with, $uid);
        $thread = array_reverse(db_all('SELECT id, sender_id, body, created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY id DESC LIMIT 100', 'iiii', $uid, $with, $with, $uid));
    }
}

$convs = db_all('SELECT m.partner_id, MAX(m.id) AS last_id, SUM(m.unread) AS unread FROM (
        SELECT id, IF(sender_id = ?, receiver_id, sender_id) AS partner_id, IF(receiver_id = ? AND is_read = 0, 1, 0) AS unread
        FROM messages WHERE sender_id = ? OR receiver_id = ?) m GROUP BY m.partner_id ORDER BY last_id DESC LIMIT 50', 'iiii', $uid, $uid, $uid, $uid);
$meta = [];
if ($convs) {
    $pids = implode(',', array_map('intval', array_column($convs, 'partner_id')));
    $lids = implode(',', array_map('intval', array_column($convs, 'last_id')));
    foreach (db_all("SELECT user_id, username, first_name, last_name, profile_photo FROM user WHERE user_id IN ($pids)") as $u) $meta[$u['user_id']]['u'] = $u;
    foreach (db_all("SELECT id, sender_id, receiver_id, body FROM messages WHERE id IN ($lids)") as $m) {
        $pid = (int) $m['sender_id'] === $uid ? $m['receiver_id'] : $m['sender_id'];
        $meta[$pid]['last'] = $m;
    }
}

$page_title = 'Messages'; $active = 'messages';
$extra_js = ['assets/js/messages.js'];
include ROOT_PATH . '/includes/header.php';
$lastId = $thread ? (int) end($thread)['id'] : 0;
?>
<script>window.PAGE = <?= json_encode(['api' => url('modules/social/messages_api.php'), 'with' => $partner ? (int) $partner['user_id'] : 0, 'last' => $lastId, 'base' => url('modules/social/messages.php')]) ?>;</script>
<div class="container">
    <div class="card chat-layout<?= $partner ? ' has-thread' : '' ?>">
        <aside class="chat-list" aria-label="Conversations">
            <div style="padding:.85rem 1rem;border-bottom:1px solid var(--border)">
                <div class="flex justify-between items-center mb-1"><strong style="font-size:1.1rem">Messages</strong></div>
                <div class="input-icon"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="userSearch" placeholder="Start a new chat…" autocomplete="off" aria-label="Find a person to message"></div>
                <div id="userResults"></div>
            </div>
            <?php if (!$convs): ?><div class="empty-state"><i class="fa-regular fa-envelope empty-icon"></i><p class="mb-0 text-sm">No conversations yet. Search above to say hello.</p></div><?php endif; ?>
            <?php foreach ($convs as $c): $u = $meta[$c['partner_id']]['u'] ?? null; if (!$u) continue; $last = $meta[$c['partner_id']]['last'] ?? null; ?>
                <a class="chat-item<?= $partner && (int) $partner['user_id'] === (int) $c['partner_id'] ? ' active' : '' ?>" href="?with=<?= (int) $c['partner_id'] ?>">
                    <img class="avatar" src="<?= e(avatar_url($u)) ?>" alt="">
                    <div class="flex-1"><div class="flex justify-between"><strong><?= e(display_name($u)) ?></strong><?php if ($c['unread'] > 0): ?><span class="badge-dot"><?= (int) $c['unread'] ?></span><?php endif; ?></div>
                        <div class="text-muted text-sm truncate"><?= $last && (int) $last['sender_id'] === $uid ? 'You: ' : '' ?><?= e(truncate((string) ($last['body'] ?? ''), 40)) ?></div></div>
                </a>
            <?php endforeach; ?>
        </aside>
        <section class="chat-pane">
        <?php if ($partner): ?>
            <div class="chat-head">
                <a class="btn btn-ghost btn-sm" href="?" aria-label="Back to conversations" id="chatBack"><i class="fa-solid fa-arrow-left"></i></a>
                <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $partner['user_id'])) ?>"><img class="avatar avatar-sm" src="<?= e(avatar_url($partner)) ?>" alt=""></a>
                <div><strong><?= e(display_name($partner)) ?></strong><div class="text-muted text-xs">@<?= e($partner['username']) ?></div></div>
            </div>
            <div class="chat-messages" id="chatMessages" aria-live="polite">
                <?php if (!$thread): ?><p class="text-muted text-center" id="chatEmpty">Say hello 👋 – this is the start of your conversation.</p><?php endif; ?>
                <?php foreach ($thread as $m): ?>
                    <div class="bubble<?= (int) $m['sender_id'] === $uid ? ' mine' : '' ?>" data-id="<?= (int) $m['id'] ?>"><?= nl2br(e($m['body'])) ?><time><?= e(fmt_dt($m['created_at'], 'M j, g:i A')) ?></time></div>
                <?php endforeach; ?>
            </div>
            <form class="chat-form" id="chatForm"><textarea id="chatInput" maxlength="2000" placeholder="Type a message… (Enter to send, Shift+Enter for a new line)" aria-label="Message" rows="1"></textarea>
                <button class="btn btn-primary" type="submit" data-no-lock><i class="fa-solid fa-paper-plane"></i><span class="sr-only">Send</span></button></form>
        <?php else: ?>
            <div class="empty-state" style="margin:auto"><i class="fa-regular fa-comments empty-icon"></i><h3>Your private inbox</h3><p>Pick a conversation or search for a parent to start chatting.</p></div>
        <?php endif; ?>
        </section>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
