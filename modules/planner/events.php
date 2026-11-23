<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_events_lib.php';
require_login();

$uid = current_user_id();
$errors = [];
$today = date('Y-m-d');
$form = ['title' => '', 'description' => '', 'location' => '', 'category' => 'Education', 'date' => '', 'age_group' => '0-5', 'event_mode' => 'Online',
         'start_time' => '10:00', 'end_time' => '11:00', 'event_type' => 'Workshop', 'accessibility_level' => 'Medium'];
$openCreate = false;

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($action === 'create') {
        [$data, $errors] = read_event_form();
        $form = array_merge($form, $data);
        if (!$errors) {
            db_exec("INSERT INTO events (title, name, description, location, category, date, accessibility_level, event_type, age_group, organizer_id, start_time, end_time, event_mode, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'Upcoming')", 'sssssssssisss',
                $data['title'], $data['title'], $data['description'], $data['location'], $data['category'], $data['date'], $data['accessibility_level'],
                $data['event_type'], $data['age_group'], $uid, $data['start_time'], $data['end_time'], $data['event_mode']);
            flash('Event created – parents can now RSVP.');
            redirect('modules/planner/events.php');
        }
        $openCreate = true;
    } elseif ($action === 'rsvp') {
        $eid = post_int('event_id'); $status = post_str('status');
        $ev = db_one('SELECT event_id, title, organizer_id FROM events WHERE event_id = ?', 'i', $eid);
        if ($ev) {
            if ($status === 'Remove') {
                db_exec('DELETE FROM event_participants WHERE event_id = ? AND participant_id = ?', 'ii', $eid, $uid);
                flash('Your RSVP was removed.', 'info');
            } elseif (in_array($status, ['Interested', 'Going'], true)) {
                db_exec('INSERT INTO event_participants (event_id, participant_id, status) VALUES (?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status)', 'iis', $eid, $uid, $status);
                notify((int) $ev['organizer_id'], current_user()['username'] . ' is ' . strtolower($status) . ' in “' . $ev['title'] . '”.', 'modules/planner/participants.php?event_id=' . $eid);
                flash($status === 'Going' ? 'See you there! You are marked as going.' : 'Marked as interested.');
            }
        }
        redirect('modules/planner/events.php?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'when' => $_GET['when'] ?? ''])));
    } elseif ($action === 'delete') {
        $eid = post_int('event_id');
        if (db_exec('DELETE FROM events WHERE event_id = ? AND organizer_id = ?', 'ii', $eid, $uid)) flash('Event deleted.');
        else flash('You can only delete events you created.', 'danger');
        redirect('modules/planner/events.php');
    }
}

$when = pick((string) ($_GET['when'] ?? 'upcoming'), ['upcoming', 'past', 'mine', 'going'], 'upcoming');
$q = trim((string) ($_GET['q'] ?? ''));
$cat = trim((string) ($_GET['category'] ?? ''));
$mode = pick((string) ($_GET['mode'] ?? ''), ['', 'Online', 'Offline'], '');

$sql = "SELECT e.*, (SELECT COUNT(*) FROM event_participants x WHERE x.event_id = e.event_id AND x.status='Going') AS going,
               (SELECT COUNT(*) FROM event_participants x WHERE x.event_id = e.event_id AND x.status='Interested') AS interested,
               (SELECT x.status FROM event_participants x WHERE x.event_id = e.event_id AND x.participant_id = ?) AS my_status,
               u.username AS organizer
        FROM events e LEFT JOIN user u ON u.user_id = e.organizer_id WHERE 1=1";
$types = 'i'; $params = [$uid];
if ($when === 'upcoming') { $sql .= ' AND e.date >= ?'; $types .= 's'; $params[] = $today; }
if ($when === 'past')     { $sql .= ' AND e.date < ?';  $types .= 's'; $params[] = $today; }
if ($when === 'mine')     { $sql .= ' AND e.organizer_id = ?'; $types .= 'i'; $params[] = $uid; }
if ($when === 'going')    { $sql .= " AND e.event_id IN (SELECT event_id FROM event_participants WHERE participant_id = ?)"; $types .= 'i'; $params[] = $uid; }
if ($q !== '')   { $sql .= ' AND (e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)'; $l = '%' . addcslashes($q, '%_\\') . '%'; $types .= 'sss'; array_push($params, $l, $l, $l); }
if ($cat !== '') { $sql .= ' AND e.category = ?'; $types .= 's'; $params[] = $cat; }
if ($mode !== '') { $sql .= ' AND e.event_mode = ?'; $types .= 's'; $params[] = $mode; }
$sql .= $when === 'past' ? ' ORDER BY e.date DESC' : ' ORDER BY e.date ASC, e.start_time ASC';
$events = db_all($sql . ' LIMIT 60', $types, ...$params);

$page_title = 'Events'; $active = 'events';
include ROOT_PATH . '/includes/header.php';
$tabs = ['upcoming' => 'Upcoming', 'going' => 'My RSVPs', 'mine' => 'Hosted by me', 'past' => 'Past'];
?>
<div class="container">
    <div class="page-header"><div><h1><i class="fa-solid fa-calendar-days"></i> Events</h1><p>Workshops, webinars and meet-ups for families.</p></div>
        <button class="btn btn-primary" data-modal-open="#eventModal"><i class="fa-solid fa-plus"></i> Create event</button></div>

    <div class="tabs">
        <?php foreach ($tabs as $k => $l): ?><a class="tab<?= $when === $k ? ' active' : '' ?>" href="?when=<?= $k ?>"><?= $l ?></a><?php endforeach; ?>
    </div>
    <form class="card mb-3" method="get"><input type="hidden" name="when" value="<?= e($when) ?>">
        <div class="form-row" style="align-items:end">
            <div class="form-group mb-0"><label for="q">Search</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Title, place…"></div>
            <div class="form-group mb-0"><label for="fc">Category</label><select id="fc" name="category"><option value="">All</option><?php foreach (EVENT_CATEGORIES as $c): ?><option<?= $cat === $c ? ' selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
            <div class="form-group mb-0"><label for="fm">Mode</label><select id="fm" name="mode"><option value="">Any</option><option<?= $mode === 'Online' ? ' selected' : '' ?>>Online</option><option<?= $mode === 'Offline' ? ' selected' : '' ?>>Offline</option></select></div>
            <div class="form-group mb-0"><button class="btn btn-outline btn-block" type="submit"><i class="fa-solid fa-filter"></i> Filter</button></div>
        </div></form>

    <?php if (!$events): ?>
        <div class="card empty-state"><i class="fa-regular fa-calendar empty-icon"></i><h3>No events found</h3><p>Try another filter – or host the first event yourself.</p>
            <button class="btn btn-primary" data-modal-open="#eventModal">Create an event</button></div>
    <?php endif; ?>
    <div class="grid grid-auto" style="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))">
    <?php foreach ($events as $ev):
        $d = $ev['date'] ? strtotime($ev['date']) : null; $past = $ev['date'] && $ev['date'] < $today; $mine = (int) $ev['organizer_id'] === $uid; ?>
        <article class="card event-card card-hover">
            <div class="flex gap-2 mb-2">
                <div class="event-date"><small><?= $d ? e(date('M', $d)) : '' ?></small><b><?= $d ? e(date('j', $d)) : '–' ?></b></div>
                <div class="flex-1"><h3 class="mb-0"><?= e($ev['title']) ?></h3>
                    <div class="flex gap-1 flex-wrap mt-1">
                        <span class="badge badge-info"><?= e($ev['event_type']) ?></span><span class="badge"><?= e($ev['category']) ?></span>
                        <span class="badge badge-muted"><i class="fa-solid fa-<?= $ev['event_mode'] === 'Online' ? 'video' : 'location-dot' ?>"></i> <?= e($ev['event_mode']) ?></span>
                        <?php if ($past): ?><span class="badge badge-warning">Past</span><?php endif; ?></div></div>
            </div>
            <p class="text-muted text-sm"><?= e(truncate((string) $ev['description'], 140)) ?></p>
            <ul class="meta-list mb-2">
                <li><i class="fa-regular fa-clock"></i><?= $ev['start_time'] ? e(date('g:i A', strtotime($ev['start_time']))) . ' – ' . e(date('g:i A', strtotime($ev['end_time']))) : 'Time to be announced' ?></li>
                <li><i class="fa-solid fa-location-dot"></i><?= e($ev['location']) ?></li>
                <li><i class="fa-solid fa-child"></i>Ages <?= e($ev['age_group']) ?> · <?= e($ev['accessibility_level']) ?> accessibility</li>
                <li><i class="fa-solid fa-users"></i><?= (int) $ev['going'] ?> going · <?= (int) $ev['interested'] ?> interested</li>
            </ul>
            <div style="margin-top:auto">
            <?php if (!$past): ?>
                <form method="post" class="btn-group"><?= csrf_field() ?><input type="hidden" name="action" value="rsvp"><input type="hidden" name="event_id" value="<?= (int) $ev['event_id'] ?>">
                    <button class="btn btn-sm <?= $ev['my_status'] === 'Going' ? 'btn-primary' : 'btn-outline' ?>" name="status" value="Going"><i class="fa-solid fa-check"></i> Going</button>
                    <button class="btn btn-sm <?= $ev['my_status'] === 'Interested' ? 'btn-primary' : 'btn-outline' ?>" name="status" value="Interested"><i class="fa-regular fa-star"></i> Interested</button>
                    <?php if ($ev['my_status']): ?><button class="btn btn-ghost btn-sm" name="status" value="Remove">Remove</button><?php endif; ?></form>
            <?php endif; ?>
            <?php if ($mine): ?>
                <div class="btn-group mt-1">
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('modules/planner/participants.php?event_id=' . $ev['event_id'])) ?>"><i class="fa-solid fa-users"></i> Attendees</a>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('modules/planner/edit_event.php?event_id=' . $ev['event_id'])) ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                    <form method="post" data-confirm="Delete this event and all RSVPs?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?= (int) $ev['event_id'] ?>"><button class="btn btn-ghost btn-sm"><i class="fa-solid fa-trash"></i> Delete</button></form>
                </div>
            <?php elseif ($ev['organizer']): ?><div class="text-xs text-muted mt-1">Hosted by @<?= e($ev['organizer']) ?></div><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</div>

<div class="modal-backdrop<?= $openCreate ? ' open' : '' ?>" id="eventModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="evTitle" style="width:min(680px,100%)">
        <div class="modal-header"><h3 id="evTitle">Create an event</h3><button class="btn btn-ghost btn-sm" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>
        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="create"><?php event_form_fields($form); ?>
            <div class="btn-group"><button class="btn btn-primary" type="submit">Publish event</button><button class="btn btn-outline" type="button" data-modal-close>Cancel</button></div></form>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
