</main>

<footer class="site-footer">
    <div class="container">
        <div class="flex items-center gap-1">
            <img src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="24" height="24" style="border-radius:7px">
            <span>© <?= date('Y') ?> <strong><?= e(SITE_NAME) ?></strong> – supporting parents of autistic children.</span>
        </div>
        <nav class="flex gap-2" aria-label="Footer">
            <?php if (isLoggedIn()): ?>
                <a href="<?= e(url('modules/reports/resource.php')) ?>">Resources</a>
                <a href="<?= e(url('modules/reports/qna.php')) ?>">Ask an expert</a>
            <?php else: ?>
                <a href="<?= e(url('index.php')) ?>">Home</a>
                <a href="<?= e(url('auth/login.php')) ?>">Log in</a>
            <?php endif; ?>
        </nav>
    </div>
</footer>

<script src="<?= e(asset('js/app.js')) ?>?v=<?= (int) @filemtime(ROOT_PATH . '/assets/js/app.js') ?>"></script>
<?php foreach (($extra_js ?? []) as $js): ?>
<script src="<?= e(preg_match('#^https?://#', $js) ? $js : url($js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
