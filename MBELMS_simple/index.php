<?php
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect('/pages/dashboard.php');
}
redirect('/pages/login.php');
