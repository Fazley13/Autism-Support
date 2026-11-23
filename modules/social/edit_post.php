<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

$uid = current_user_id();
$postId = get_int('post_id', post_int('post_id'));
$post = db_one('SELECT * FROM post WHERE post_id = ? AND user_id = ?', 'ii', $postId, $uid);
if (!$post) {
    flash('That post does not exist or is not yours.', 'danger');
    redirect('modules/social/newsfeed.php');
}

$error = '';
if (is_post()) {
    csrf_check();
    $content = post_str('content', 5000);
    $privacy = pick(post_str('privacy'), array_keys(POST_PRIVACY), 'public');
    $category = pick(post_str('category'), array_keys(POST_CATEGORIES), 'general');
    try {
        $photo = $post['photo'];
        if (!empty($_POST['remove_photo'])) { delete_media($photo); $photo = null; }
        $new = save_image_upload('photo');
        if ($new) { delete_media($photo); $photo = $new; }
        if ($content === '' && !$photo) throw new RuntimeException('A post needs some text or a photo.');
        db_exec('UPDATE post SET content = ?, privacy = ?, category = ?, photo = ? WHERE post_id = ? AND user_id = ?', 'ssssii', $content, $privacy, $category, $photo, $postId, $uid);
        flash('Post updated.');
        redirect('modules/social/newsfeed.php#post-' . $postId);
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
    $post['content'] = $content; $post['privacy'] = $privacy; $post['category'] = $category;
}

$page_title = 'Edit post'; $active = 'newsfeed';
include ROOT_PATH . '/includes/header.php';
$img = media_url($post['photo'] ?? '', 'post');
?>
<div class="container-sm">
    <div class="page-header"><h1><i class="fa-solid fa-pen"></i> Edit post</h1></div>
    <div class="card">
        <?php if ($error): ?><?= alert_html($error, 'danger') ?><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?><input type="hidden" name="post_id" value="<?= $postId ?>">
            <div class="form-group"><label for="content">Your post</label><textarea id="content" name="content" maxlength="5000"><?= e($post['content']) ?></textarea></div>
            <div class="form-row">
                <div class="form-group"><label for="category">Topic</label><select id="category" name="category"><?= category_options((string) $post['category']) ?></select></div>
                <div class="form-group"><label for="privacy">Who can see it</label><select id="privacy" name="privacy"><?= privacy_options((string) $post['privacy']) ?></select></div>
            </div>
            <?php if ($img): ?>
                <div class="form-group"><img class="post-image" style="border-radius:var(--radius-sm);max-height:240px" src="<?= e($img) ?>" alt="Current photo">
                    <label class="check-row mt-1"><input type="checkbox" name="remove_photo" value="1"> Remove this photo</label></div>
            <?php endif; ?>
            <label class="file-drop"><i class="fa-solid fa-camera"></i> <?= $img ? 'Replace photo' : 'Add a photo' ?> (max 3 MB)
                <input type="file" name="photo" accept="image/*" data-preview="#preview"></label>
            <img id="preview" class="img-preview" alt="Preview">
            <div class="btn-group mt-3">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                <a class="btn btn-outline" href="<?= e(url('modules/social/newsfeed.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
