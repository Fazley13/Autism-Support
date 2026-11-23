<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (isLoggedIn() && current_user()) {
    redirect('modules/social/newsfeed.php');
}

$page_title = 'Support for parents of autistic children';
include ROOT_PATH . '/includes/header.php';

$features = [
    ['fa-users',          '',   'Community newsfeed',   'Share wins and worries, comment, like and follow other parents who truly understand.'],
    ['fa-chart-line',     'c2', 'Behaviour tracking',   'Log behaviours and intensity, then see patterns, triggers and progress over time.'],
    ['fa-list-check',     'c3', 'Daily planner',        'Build predictable routines with prioritised tasks, deadlines and a daily progress bar.'],
    ['fa-calendar-days',  'c4', 'Events',               'Find and join workshops, webinars and therapy sessions – or host your own.'],
    ['fa-book-open',      'c5', 'Educational resources','Curated videos, articles and guides for cognitive and behavioural development.'],
    ['fa-envelope',       '',   'Private messaging',    'Talk one-to-one with other parents and experts in a safe, private inbox.'],
    ['fa-circle-question','c2', 'Expert Q&A',           'Ask questions and get advice from experienced caregivers and professionals.'],
    ['fa-file-lines',     'c3', 'Reports & analytics',  'Printable behaviour and therapy reports to share with teachers and therapists.'],
];
?>
<section class="hero">
    <div class="container hero-grid">
      <div>
        <span class="badge badge-accent mb-2"><i class="fa-solid fa-heart"></i> Made for parents &amp; caregivers</span>
        <h1>You are not alone on this <em>journey</em></h1>
        <p class="lead">ParentConnect brings community, behaviour tracking, daily planning and trusted resources together in one calm, easy-to-use place.</p>
        <div class="btn-group">
            <a class="btn btn-primary btn-lg" href="<?= e(url('auth/signup.php')) ?>"><i class="fa-solid fa-user-plus"></i> Create free account</a>
            <a class="btn btn-outline btn-lg" href="<?= e(url('auth/login.php')) ?>">Log in</a>
        </div>
      </div>
      <div class="hero-art" aria-hidden="true">
        <div class="mock m1"><strong><i class="fa-solid fa-list-check"></i> Today's routine</strong><br>☀️ Morning walk · ✔<br>🎨 Art time · ✔<br>📚 Story &amp; calm-down</div>
        <div class="mock m2"><strong><i class="fa-solid fa-chart-line"></i> Calm days this week</strong><div class="bars"><span style="height:40%"></span><span style="height:55%"></span><span style="height:35%"></span><span style="height:75%"></span><span style="height:90%"></span></div></div>
        <div class="mock m3"><strong><i class="fa-solid fa-heart"></i> Community</strong><br>“Visual timers helped us so much — thank you!”</div>
      </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-title">
            <h2>Everything you need, in one place</h2>
            <p>Simple tools designed to reduce overwhelm and help you support your child every day.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($features as [$icon, $c, $title, $text]): ?>
                <div class="card card-hover">
                    <div class="feature-icon <?= e($c) ?>"><i class="fa-solid <?= e($icon) ?>"></i></div>
                    <h3><?= e($title) ?></h3>
                    <p class="text-muted mb-0"><?= e($text) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" style="padding-top:0">
    <div class="container">
        <div class="cta-band">
            <h2>Ready to feel more supported?</h2>
            <p>Join parents who are tracking progress, planning routines and sharing encouragement every day.</p>
            <a class="btn btn-lg" href="<?= e(url('auth/signup.php')) ?>">Get started – it's free</a>
        </div>
    </div>
</section>
<?php include ROOT_PATH . '/includes/footer.php'; ?>
