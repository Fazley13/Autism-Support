<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_events_lib.php';
require_login();

$uid = current_user_id();
$eid = get_int('event_id', post_int('event_id'));
$ev = db_one('SELECT * FROM events WHERE event_id = ? AND organizer_id = ?', 'ii', $eid, $uid);
if (!$ev) { flash('You can only edit events you created.', 'danger'); redirect('modules/planner/events.php'); }

$errors = [];
if (is_post()) {
    csrf_check();
    [$d, $errors] = read_event_form();
    $ev = array_merge($ev, $d);
    if (!$errors) {
        db_exec('UPDATE events SET title=?, name=?, description=?, location=?, category=?, date=?, accessibility_level=?, event_type=?, age_group=?, start_time=?, end_time=?, event_mode=? WHERE event_id=? AND organizer_id=?',
            'ssssssssssssii', $d['title'], $d['title'], $d['description'], $d['location'], $d['category'], $d['date'], $d['accessibility_level'], $d['event_type'], $d['age_group'], $d['start_time'], $d['end_time'], $d['event_mode'], $eid, $uid);
        $going = db_all('SELECT participant_id FROM event_participants WHERE event_id = ?', 'i', $eid);
        foreach ($going as $g) notify((int) $g['participant_id'], 'The event “' . $d['title'] . '” was updated.', 'modules/planner/events.php');
        flash('Event updated.');
        redirect('modules/planner/events.php?when=mine');
    }
}
$page_title = 'Edit event'; $active = 'events';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><h1><i class="fa-solid fa-pen"></i> Edit event</h1></div>
    <div class="card">
        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $eid ?>">
            <?php event_form_fields($ev); ?>
            <div class="btn-group"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                <a class="btn btn-outline" href="<?= e(url('modules/planner/events.php?when=mine')) ?>">Cancel</a></div>
        </form>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
