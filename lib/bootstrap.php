<?php

$config = require __DIR__ . '/../config.php';

date_default_timezone_set('Asia/Shanghai');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['session_name']);
    session_start();
}

require_once __DIR__ . '/helpers.php';
