<?php
require_once __DIR__ . '/lib.php';
redirect(current_user() ? 'dashboard.php' : 'login.php');
