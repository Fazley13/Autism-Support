<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$eid = get_int('event_id');
$ev = db_one('SELECT event_id, title, date, organizer_id FROM events WHERE event_id = ?', 'i', $eid);
if (!$ev) { flash('Event not found.', 'danger'); redirect('modules/planner/events.php'); }
$isOwner = (int) $ev['organizer_id'] === $uid;

$people = db_all("SELECT u.user_id, u.username, u.first_name, u.last_name, u.profile_photo, ep.status
    FROM event_participants ep JOIN user u ON u.user_id = ep.participant_id WHERE ep.event_id = ? ORDER BY ep.status = 'Going' DESC, u.username", 'i', $eid);

$page_title = 'Attendees'; $active = 'events';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><div><h1><i class="fa-solid fa-users"></i> Attendees</h1><p><?= e($ev['title']) ?> · <?= e($ev['date']) ?></p></div>
        <a class="btn btn-outline" href="<?= e(url('modules/planner/events.php')) ?>">← All events</a></div>
    <div class="card">
        <?php if (!$people): ?><div class="empty-state"><i class="fa-solid fa-user-group empty-icon"></i><p class="mb-0">No one has RSVP'd yet.</p></div><?php endif; ?>
        <?php foreach ($people as $p): ?>
            <div class="person-row">
                <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $p['user_id'])) ?>"><img class="avatar" src="<?= e(avatar_url($p)) ?>" alt=""></a>
                <div class="who"><a href="<?= e(url('modules/social/view_profile.php?user_id=' . $p['user_id'])) ?>"><?= e(display_name($p)) ?></a><div class="text-muted text-sm">@<?= e($p['username']) ?></div></div>
                <span class="badge <?= $p['status'] === 'Going' ? 'badge-success' : 'badge-info' ?>"><?= e($p['status']) ?></span>
                <?php if ((int) $p['user_id'] !== $uid): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('modules/social/messages.php?with=' . $p['user_id'])) ?>" title="Message"><i class="fa-solid fa-envelope"></i></a><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
