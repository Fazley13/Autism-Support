<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    $_SESSION = [];
    session_destroy();
    session_start();               // fresh session just for the flash message
    flash('You have been logged out.', 'info');
}
redirect(is_post() ? 'auth/login.php' : 'index.php');
