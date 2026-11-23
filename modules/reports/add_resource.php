<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$id = get_int('id', post_int('id'));
$types = db_all('SELECT id, type_name FROM resource_types ORDER BY type_name');
$r = ['title' => '', 'description' => '', 'resource_type_id' => $types[0]['id'] ?? 0, 'external_link' => ''];
if ($id) {
    $row = db_one('SELECT r.*, d.external_link, d.file_path FROM resources r LEFT JOIN resource_details d ON d.resource_id = r.id WHERE r.id = ? AND r.user_id = ?', 'ii', $id, $uid);
    if (!$row) { flash('Resource not found or not yours.', 'danger'); redirect('modules/reports/resource.php'); }
    $r = $row;
}
$errors = [];
if (is_post()) {
    csrf_check();
    $r['title'] = post_str('title', 255); $r['description'] = post_str('description', 3000);
    $r['resource_type_id'] = post_int('resource_type_id'); $r['external_link'] = post_str('external_link', 255);
    if ($r['title'] === '') $errors[] = 'Please enter a title.';
    if (!db_one('SELECT 1 AS x FROM resource_types WHERE id = ?', 'i', $r['resource_type_id'])) $errors[] = 'Please choose a type.';
    if ($r['external_link'] === '' && empty($row['file_path'])) $errors[] = 'Please add a link (website or video URL).';
    if ($r['external_link'] !== '' && !preg_match('~^https?://[^\s]+$~i', $r['external_link'])) $errors[] = 'The link must start with http:// or https://';
    if (!$errors) {
        if ($id) {
            db_exec('UPDATE resources SET title=?, description=?, resource_type_id=? WHERE id=? AND user_id=?', 'ssiii', $r['title'], $r['description'], $r['resource_type_id'], $id, $uid);
            if (db_one('SELECT 1 AS x FROM resource_details WHERE resource_id = ?', 'i', $id)) db_exec('UPDATE resource_details SET external_link=? WHERE resource_id=?', 'si', $r['external_link'] ?: null, $id);
            else db_exec('INSERT INTO resource_details (resource_id, external_link) VALUES (?,?)', 'is', $id, $r['external_link']);
            flash('Resource updated.');
        } else {
            db_exec('INSERT INTO resources (user_id, title, description, resource_type_id) VALUES (?,?,?,?)', 'issi', $uid, $r['title'], $r['description'], $r['resource_type_id']);
            db_exec('INSERT INTO resource_details (resource_id, external_link) VALUES (?,?)', 'is', db_insert_id(), $r['external_link']);
            flash('Thanks for sharing!');
        }
        redirect('modules/reports/resource.php');
    }
}
$page_title = $id ? 'Edit resource' : 'Share a resource'; $active = 'resources';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><h1><i class="fa-solid fa-<?= $id ? 'pen' : 'plus' ?>"></i> <?= e($page_title) ?></h1></div>
    <form class="card" method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>
        <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" maxlength="255" value="<?= e($r['title']) ?>" required></div>
        <div class="form-group"><label for="description">Why is it helpful?</label><textarea id="description" name="description" maxlength="3000"><?= e($r['description']) ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label for="resource_type_id">Type</label><select id="resource_type_id" name="resource_type_id"><?php foreach ($types as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) $r['resource_type_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e(ucfirst($t['type_name'])) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="external_link">Link</label><input type="url" id="external_link" name="external_link" value="<?= e($r['external_link']) ?>" placeholder="https://…"><div class="form-hint">YouTube links get a thumbnail and in-page player.</div></div>
        </div>
        <div class="btn-group"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button><a class="btn btn-outline" href="<?= e(url('modules/reports/resource.php')) ?>">Cancel</a></div>
    </form>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
