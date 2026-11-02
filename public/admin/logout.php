<?php
define('IN_ADMIN', true);
require dirname(__DIR__) . '/_boot.php';
logout_user();
redirect(u('admin/login.php'));
