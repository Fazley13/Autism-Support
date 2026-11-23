<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_lib.php';
require_login();

if (!is_post()) redirect('modules/social/newsfeed.php');
csrf_check();

$content = post_str('content', 5000);
$privacy = pick(post_str('privacy'), array_keys(POST_PRIVACY), 'public');
$category = pick(post_str('category'), array_keys(POST_CATEGORIES), 'general');

try {
    $photo = save_image_upload('photo');
    if ($content === '' && !$photo) {
        flash('Write something or add a photo to share.', 'warning');
    } else {
        db_exec('INSERT INTO post (user_id, content, privacy, category, photo) VALUES (?, ?, ?, ?, ?)', 'issss', current_user_id(), $content, $privacy, $category, $photo);
        flash('Your post is live!', 'success');
    }
} catch (RuntimeException $ex) {
    flash($ex->getMessage(), 'danger');
}
redirect('modules/social/newsfeed.php');
