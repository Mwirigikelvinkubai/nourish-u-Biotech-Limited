<?php
require_once __DIR__ . '/config/config.php';
redirect(url(is_logged_in() ? 'dashboard.php' : 'login.php'));
