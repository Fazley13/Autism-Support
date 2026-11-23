<?php
const EVENT_CATEGORIES = ['Education', 'Health', 'Awareness', 'Therapy', 'Social', 'Parenting'];
const EVENT_TYPES = ['Workshop', 'Webinar', 'Seminar', 'Support group', 'Therapy session', 'Meet-up'];
const EVENT_AGES = ['0-5', '6-10', '11-15', '16+', 'All ages'];
const EVENT_ACCESS = ['Low', 'Medium', 'High'];

/** Validate the event form → [cleanData, errors] */
function read_event_form(): array
{
    $d = [
        'title' => post_str('title', 255), 'description' => post_str('description', 3000), 'location' => post_str('location', 255),
        'category' => pick(post_str('category'), EVENT_CATEGORIES, 'Education'), 'date' => post_str('date'),
        'age_group' => pick(post_str('age_group'), EVENT_AGES, 'All ages'), 'event_mode' => pick(post_str('event_mode'), ['Online', 'Offline'], 'Online'),
        'start_time' => post_str('start_time'), 'end_time' => post_str('end_time'),
        'event_type' => pick(post_str('event_type'), EVENT_TYPES, 'Workshop'), 'accessibility_level' => pick(post_str('accessibility_level'), EVENT_ACCESS, 'Medium'),
    ];
    $err = [];
    if ($d['title'] === '') $err[] = 'Please enter an event title.';
    if ($d['location'] === '') $err[] = 'Please enter a location (or "Online").';
    if (!DateTime::createFromFormat('Y-m-d', $d['date'])) $err[] = 'Please choose a valid date.';
    foreach (['start_time', 'end_time'] as $k) if (!preg_match('/^\d{2}:\d{2}$/', $d[$k])) $err[] = 'Please enter valid start and end times.';
    if (!$err && $d['end_time'] <= $d['start_time']) $err[] = 'End time must be after the start time.';
    return [$d, array_values(array_unique($err))];
}

function event_form_fields(array $f): void
{
    $opt = function (array $list, string $sel) { foreach ($list as $o) echo '<option' . ($o === $sel ? ' selected' : '') . '>' . e($o) . '</option>'; };
    ?>
    <div class="form-group"><label for="ev_title">Title</label><input type="text" id="ev_title" name="title" maxlength="255" value="<?= e($f['title']) ?>" required></div>
    <div class="form-group"><label for="ev_desc">Description</label><textarea id="ev_desc" name="description" maxlength="3000"><?= e($f['description']) ?></textarea></div>
    <div class="form-row">
        <div class="form-group"><label for="ev_loc">Location</label><input type="text" id="ev_loc" name="location" value="<?= e($f['location']) ?>" placeholder="Address or “Online”" required></div>
        <div class="form-group"><label for="ev_date">Date</label><input type="date" id="ev_date" name="date" value="<?= e($f['date']) ?>" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="ev_st">Starts</label><input type="time" id="ev_st" name="start_time" value="<?= e(substr((string) $f['start_time'], 0, 5)) ?>" required></div>
        <div class="form-group"><label for="ev_et">Ends</label><input type="time" id="ev_et" name="end_time" value="<?= e(substr((string) $f['end_time'], 0, 5)) ?>" required></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="ev_cat">Category</label><select id="ev_cat" name="category"><?php $opt(EVENT_CATEGORIES, (string) $f['category']); ?></select></div>
        <div class="form-group"><label for="ev_type">Type</label><select id="ev_type" name="event_type"><?php $opt(EVENT_TYPES, (string) $f['event_type']); ?></select></div>
        <div class="form-group"><label for="ev_mode">Mode</label><select id="ev_mode" name="event_mode"><?php $opt(['Online', 'Offline'], (string) $f['event_mode']); ?></select></div>
    </div>
    <div class="form-row">
        <div class="form-group"><label for="ev_age">Age group</label><select id="ev_age" name="age_group"><?php $opt(EVENT_AGES, (string) $f['age_group']); ?></select></div>
        <div class="form-group"><label for="ev_acc">Accessibility level</label><select id="ev_acc" name="accessibility_level"><?php $opt(EVENT_ACCESS, (string) $f['accessibility_level']); ?></select></div>
    </div>
    <?php
}
