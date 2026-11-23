<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_login();

$uid = current_user_id();
$errors = [];

if (is_post()) {
    csrf_check();
    $action = post_str('action');
    if ($action === 'ask') {
        $t = post_str('question_text', 2000);
        if (mb_strlen($t) < 5) $errors[] = 'Please write your question (at least 5 characters).';
        else { db_exec('INSERT INTO questions (user_id, question_text) VALUES (?,?)', 'is', $uid, $t); flash('Your question was posted.'); redirect('modules/reports/qna.php'); }
    } elseif ($action === 'answer') {
        $qid = post_int('question_id'); $t = post_str('answer_text', 3000);
        $q = db_one('SELECT user_id FROM questions WHERE question_id = ?', 'i', $qid);
        if (!$q || $t === '') flash('Please write an answer.', 'warning');
        else {
            db_exec('INSERT INTO answers (question_id, user_id, answer_text) VALUES (?,?,?)', 'iis', $qid, $uid, $t);
            notify((int) $q['user_id'], current_user()['username'] . ' answered your question.', 'modules/reports/qna.php#q-' . $qid);
            flash('Answer posted.');
        }
        redirect('modules/reports/qna.php#q-' . $qid);
    } elseif ($action === 'edit_question') {
        $qid = post_int('question_id'); $t = post_str('question_text', 2000);
        if (mb_strlen($t) >= 5 && db_exec('UPDATE questions SET question_text = ? WHERE question_id = ? AND user_id = ?', 'sii', $t, $qid, $uid) >= 0) flash('Question updated.');
        redirect('modules/reports/qna.php#q-' . $qid);
    } elseif ($action === 'del_question') {
        db_exec('DELETE FROM questions WHERE question_id = ? AND user_id = ?', 'ii', post_int('question_id'), $uid);
        flash('Question deleted.'); redirect('modules/reports/qna.php');
    } elseif ($action === 'del_answer') {
        db_exec('DELETE FROM answers WHERE answer_id = ? AND user_id = ?', 'ii', post_int('answer_id'), $uid);
        flash('Answer deleted.'); redirect('modules/reports/qna.php#q-' . post_int('question_id'));
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT q.question_id, q.user_id, q.question_text, q.created_at, u.username, u.first_name, u.last_name, u.profile_photo,
        (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.question_id) AS answer_count FROM questions q JOIN user u ON u.user_id = q.user_id';
$ty = ''; $pa = [];
if ($q !== '') { $sql .= ' WHERE q.question_text LIKE ?'; $ty = 's'; $pa[] = '%' . addcslashes($q, '%_\\') . '%'; }
$questions = db_all($sql . ' ORDER BY q.created_at DESC, q.question_id DESC LIMIT 50', $ty, ...$pa);
$answers = [];
if ($questions) {
    $ids = array_map('intval', array_column($questions, 'question_id'));
    $rows = db_all('SELECT a.answer_id, a.question_id, a.user_id, a.answer_text, a.created_at, u.username, u.first_name, u.last_name, u.profile_photo
        FROM answers a JOIN user u ON u.user_id = a.user_id WHERE a.question_id IN (' . implode(',', $ids) . ') ORDER BY a.created_at ASC');
    foreach ($rows as $a) $answers[$a['question_id']][] = $a;
}

$page_title = 'Questions & answers'; $active = 'qna';
include ROOT_PATH . '/includes/header.php';
?>
<div class="container-sm">
    <div class="page-header"><div><h1><i class="fa-solid fa-circle-question"></i> Questions &amp; answers</h1><p>Ask the community and experienced caregivers.</p></div></div>
    <form class="card mb-3" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="ask">
        <?php foreach ($errors as $er): ?><?= alert_html($er, 'danger') ?><?php endforeach; ?>
        <label class="form-label" for="question_text">Ask a question</label>
        <textarea id="question_text" name="question_text" maxlength="2000" placeholder="e.g. How do you handle bedtime routines with a sensory-sensitive child?" required></textarea>
        <button class="btn btn-primary mt-1" type="submit"><i class="fa-solid fa-paper-plane"></i> Post question</button></form>

    <form class="flex gap-1 mb-2" method="get"><input class="flex-1" type="search" name="q" value="<?= e($q) ?>" placeholder="Search questions…" aria-label="Search questions"><button class="btn btn-outline" type="submit">Search</button></form>

    <?php if (!$questions): ?><div class="card empty-state"><i class="fa-regular fa-comments empty-icon"></i><h3>No questions yet</h3><p>Be the first to ask – someone has probably been through the same thing.</p></div><?php endif; ?>
    <?php foreach ($questions as $qq): $qid = (int) $qq['question_id']; $own = (int) $qq['user_id'] === $uid; ?>
        <article class="card qa-question mb-2" id="q-<?= $qid ?>">
            <div class="flex gap-2">
                <a href="<?= e(url('modules/social/view_profile.php?user_id=' . $qq['user_id'])) ?>"><img class="avatar" src="<?= e(avatar_url($qq)) ?>" alt=""></a>
                <div class="flex-1">
                    <div class="text-sm"><strong><?= e(display_name($qq)) ?></strong> <span class="text-muted">· <?= e(time_ago($qq['created_at'])) ?></span></div>
                    <h3 style="font-weight:700"><?= nl2br(e($qq['question_text'])) ?></h3>
                </div>
                <?php if ($own): ?><div class="flex gap-1">
                    <button class="btn btn-ghost btn-sm" type="button" data-edit-q="<?= $qid ?>" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
                    <form method="post" data-confirm="Delete this question and its answers?"><?= csrf_field() ?><input type="hidden" name="action" value="del_question"><input type="hidden" name="question_id" value="<?= $qid ?>"><button class="btn btn-ghost btn-sm" aria-label="Delete"><i class="fa-solid fa-trash"></i></button></form></div><?php endif; ?>
            </div>
            <?php if ($own): ?><form method="post" class="hidden mt-1" id="editq-<?= $qid ?>"><?= csrf_field() ?><input type="hidden" name="action" value="edit_question"><input type="hidden" name="question_id" value="<?= $qid ?>">
                <textarea name="question_text" maxlength="2000" required><?= e($qq['question_text']) ?></textarea><button class="btn btn-primary btn-sm mt-1">Save</button></form><?php endif; ?>

            <details class="mt-2" <?= $qq['answer_count'] && $q !== '' ? 'open' : '' ?>>
                <summary class="fw-bold" style="cursor:pointer"><?= (int) $qq['answer_count'] ?> <?= (int) $qq['answer_count'] === 1 ? 'answer' : 'answers' ?></summary>
                <?php foreach ($answers[$qid] ?? [] as $a): ?>
                    <div class="answer"><div class="text-sm"><a href="<?= e(url('modules/social/view_profile.php?user_id=' . $a['user_id'])) ?>"><strong><?= e(display_name($a)) ?></strong></a> <span class="text-muted">· <?= e(time_ago($a['created_at'])) ?></span>
                        <?php if ((int) $a['user_id'] === $uid): ?><form method="post" style="display:inline" data-confirm="Delete your answer?"><?= csrf_field() ?><input type="hidden" name="action" value="del_answer"><input type="hidden" name="answer_id" value="<?= (int) $a['answer_id'] ?>"><input type="hidden" name="question_id" value="<?= $qid ?>"><button class="btn btn-ghost btn-sm" aria-label="Delete answer"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></div>
                        <div><?= nl2br(e($a['answer_text'])) ?></div></div>
                <?php endforeach; ?>
                <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="answer"><input type="hidden" name="question_id" value="<?= $qid ?>">
                    <textarea name="answer_text" rows="2" maxlength="3000" placeholder="Share your experience or advice…" required style="min-height:70px"></textarea><button class="btn btn-outline btn-sm mt-1" type="submit">Post answer</button></form>
            </details>
        </article>
    <?php endforeach; ?>
</div>
<script>document.addEventListener('click',function(e){var b=e.target.closest('[data-edit-q]');if(b){document.getElementById('editq-'+b.getAttribute('data-edit-q')).classList.toggle('hidden');}});</script>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
