<?php
/** Shared helpers for the social module (included by pages in this folder). */

const POST_CATEGORIES = [
    'general' => 'General', 'autism' => 'Autism', 'ADHD' => 'ADHD', 'anxiety' => 'Anxiety', 'sleep' => 'Sleep',
    'feeding' => 'Feeding / eating', 'epilepsy' => 'Epilepsy', 'depression' => 'Depression', 'OCD' => 'OCD',
    'Bipolar Disorder' => 'Bipolar disorder', 'Schizophrenia' => 'Schizophrenia', 'Down syndrome' => 'Down syndrome',
];
const POST_PRIVACY = ['public' => 'Public', 'friends-only' => 'Followers only', 'private' => 'Only me'];

/** SQL fragment (alias p) – bind: viewer id, viewer id */
function post_visible_sql(): string
{
    return "(p.privacy = 'public' OR p.user_id = ? OR (p.privacy = 'friends-only' AND p.user_id IN (SELECT user_id FROM followers WHERE follower_user_id = ?)))";
}

/** Base SELECT for post cards. Bind order: viewer (liked), viewer (following). */
function post_select_sql(): string
{
    return "SELECT p.post_id, p.user_id, p.content, p.privacy, p.category, p.created_at, p.photo,
                   u.username, u.first_name, u.last_name, u.profile_photo,
                   (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.post_id) AS like_count,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
                   (SELECT COUNT(*) FROM likes l2 WHERE l2.post_id = p.post_id AND l2.user_id = ?) AS liked,
                   (SELECT COUNT(*) FROM followers f WHERE f.user_id = p.user_id AND f.follower_user_id = ?) AS is_following
            FROM post p JOIN user u ON u.user_id = p.user_id ";
}

function fetch_visible_post(int $postId, int $uid): ?array
{
    return db_one("SELECT p.post_id, p.user_id, p.privacy FROM post p WHERE p.post_id = ? AND " . post_visible_sql(), 'iii', $postId, $uid, $uid);
}

function render_post(array $p, int $uid): void
{
    $own = (int) $p['user_id'] === $uid;
    $id = (int) $p['post_id'];
    $photo = media_url($p['photo'] ?? '', 'post');
    ?>
    <article class="card post" id="post-<?= $id ?>" data-post="<?= $id ?>">
        <div class="post-head">
            <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $p['user_id'])) ?>"><img class="avatar" src="<?= e(avatar_url($p)) ?>" alt="" loading="lazy"></a>
            <div class="who">
                <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $p['user_id'])) ?>"><?= e(display_name($p)) ?></a>
                <div class="post-meta">
                    <span>@<?= e($p['username']) ?></span><span>·</span><span title="<?= e(fmt_dt($p['created_at'])) ?>"><?= e(time_ago($p['created_at'])) ?></span><span>·</span>
                    <span title="<?= e(POST_PRIVACY[$p['privacy']] ?? '') ?>"><i class="fa-solid fa-<?= $p['privacy'] === 'public' ? 'earth-asia' : ($p['privacy'] === 'private' ? 'lock' : 'user-group') ?>"></i></span>
                    <span class="badge"><?= e(POST_CATEGORIES[$p['category']] ?? ucfirst((string) $p['category'])) ?></span>
                </div>
            </div>
            <?php if ($own): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('modules/social/edit_post.php?post_id=' . $id)) ?>" title="Edit post" aria-label="Edit post"><i class="fa-solid fa-pen"></i></a>
                <button class="btn btn-ghost btn-sm" type="button" data-delete-post="<?= $id ?>" title="Delete post" aria-label="Delete post"><i class="fa-solid fa-trash"></i></button>
            <?php else: ?>
                <button class="btn btn-outline btn-sm" type="button" data-follow="<?= (int) $p['user_id'] ?>"><?= $p['is_following'] ? 'Following' : 'Follow' ?></button>
            <?php endif; ?>
        </div>
        <?php if (trim((string) $p['content']) !== ''): ?><div class="post-body"><?= nl2br(e($p['content'])) ?></div><?php endif; ?>
        <?php if ($photo): ?><img class="post-image" src="<?= e($photo) ?>" alt="Photo shared in post" loading="lazy"><?php endif; ?>
        <div class="post-stats">
            <button type="button" class="btn btn-ghost btn-sm" data-likes-toggle="<?= $id ?>"><span data-like-count="<?= $id ?>"><?= (int) $p['like_count'] ?></span> likes</button>
            <button type="button" class="btn btn-ghost btn-sm" data-comments-toggle="<?= $id ?>"><span data-comment-count="<?= $id ?>"><?= (int) $p['comment_count'] ?></span> comments</button>
        </div>
        <div class="post-actions">
            <button type="button" class="btn btn-ghost btn-sm<?= $p['liked'] ? ' liked' : '' ?>" data-like="<?= $id ?>" aria-pressed="<?= $p['liked'] ? 'true' : 'false' ?>"><i class="fa-<?= $p['liked'] ? 'solid' : 'regular' ?> fa-heart"></i> Like</button>
            <button type="button" class="btn btn-ghost btn-sm" data-comments-toggle="<?= $id ?>"><i class="fa-regular fa-comment"></i> Comment</button>
        </div>
        <div class="likes-popover" id="likes-<?= $id ?>"></div>
        <div class="comments" id="comments-<?= $id ?>">
            <div class="comments-list"></div>
            <form class="comment-form" data-comment-form="<?= $id ?>">
                <textarea placeholder="Write a supportive comment…" maxlength="1000" required aria-label="Comment"></textarea>
                <button class="btn btn-primary btn-sm" type="submit" data-no-lock><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </div>
    </article>
    <?php
}

function category_options(string $selected = 'general'): string
{
    $o = '';
    foreach (POST_CATEGORIES as $v => $l) {
        $o .= '<option value="' . e($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $o;
}
function privacy_options(string $selected = 'public'): string
{
    $o = '';
    foreach (POST_PRIVACY as $v => $l) {
        $o .= '<option value="' . e($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $o;
}
