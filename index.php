<?php
require_once __DIR__ . '/lib.php';
redirect(current_user() ? 'bank_list.php' : 'login.php');
