<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
if (is_post()) {
    csrf_check();
    $action = post_str('action');
    if ($action === 'read_all') { db_exec('UPDATE notifications SET seen = 1 WHERE user_id = ?', 'i', $uid); flash('All notifications marked as read.'); }
    elseif ($action === 'clear') { db_exec('DELETE FROM notifications WHERE user_id = ?', 'i', $uid); flash('Notifications cleared.'); }
    elseif ($action === 'open') {
        $n = db_one('SELECT link FROM notifications WHERE id = ? AND user_id = ?', 'ii', post_int('id'), $uid);
        db_exec('UPDATE notifications SET seen = 1 WHERE id = ? AND user_id = ?', 'ii', post_int('id'), $uid);
        redirect($n && $n['link'] && strpos($n['link'], '//') === false ? $n['link'] : 'modules/reports/notification.php');
    }
    redirect('modules/reports/notification.php');
}

$filter = ($_GET['f'] ?? '') === 'unread' ? 'unread' : 'all';
$items = db_all('SELECT id, message, link, seen, created_at FROM notifications WHERE user_id = ?' . ($filter === 'unread' ? ' AND seen = 0' : '') . ' ORDER BY created_at DESC, id DESC LIMIT 100', 'i', $uid);
$unread = unread_notifications();

$page_title = 'Notifications'; $active = 'notifications';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><div><h1><i class="fa-solid fa-bell"></i> Notifications</h1><p><?= $unread ?> unread</p></div>
        <div class="btn-group">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-outline btn-sm"<?= $unread ? '' : ' disabled' ?>><i class="fa-solid fa-check-double"></i> Mark all read</button></form>
            <form method="post" data-confirm="Delete all notifications?"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-ghost btn-sm"<?= $items ? '' : ' disabled' ?>><i class="fa-solid fa-trash"></i> Clear all</button></form></div></div>
    <div class="pills mb-2"><a class="pill<?= $filter === 'all' ? ' active' : '' ?>" href="?">All</a><a class="pill<?= $filter === 'unread' ? ' active' : '' ?>" href="?f=unread">Unread</a></div>
    <div class="card" style="padding:0;overflow:hidden">
        <?php if (!$items): ?><div class="empty-state"><i class="fa-regular fa-bell empty-icon"></i><h3>You're all caught up</h3><p class="mb-0">New likes, comments, followers and event RSVPs will show up here.</p></div><?php endif; ?>
        <?php foreach ($items as $n): ?>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="open"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                <button type="submit" class="notif<?= $n['seen'] ? '' : ' unread' ?>" style="width:100%;border:0;background:none;font:inherit;text-align:left;cursor:pointer;border-bottom:1px solid var(--border)">
                    <i class="fa-solid fa-<?= $n['seen'] ? 'bell' : 'circle' ?> text-muted" style="margin-top:.35rem;<?= $n['seen'] ? '' : 'color:var(--accent)' ?>"></i>
                    <span class="flex-1"><?= e($n['message']) ?><br><span class="text-muted text-xs"><?= e(time_ago($n['created_at'])) ?></span></span></button></form>
        <?php endforeach; ?>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
