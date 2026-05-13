<?php

$projectRoot = __DIR__;
$isWindows = DIRECTORY_SEPARATOR === '\\';
$defaultPython = $isWindows ? 'python' : $projectRoot . '/py/venv/bin/python3';

return [
    'project_root' => $projectRoot,
    'source_root' => getenv('CS_SOURCE_ROOT') ?: $projectRoot . DIRECTORY_SEPARATOR . '1',
    'tmp_root' => $projectRoot . DIRECTORY_SEPARATOR . 'tmp',
    'data_root' => $projectRoot . DIRECTORY_SEPARATOR . 'data',
    'task_root' => $projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'tasks',
    'logs_root' => $projectRoot . DIRECTORY_SEPARATOR . 'logs',
    'python_bin' => getenv('CS_PYTHON_BIN') ?: $defaultPython,
    'zip_script' => $projectRoot . DIRECTORY_SEPARATOR . 'py' . DIRECTORY_SEPARATOR . 'zipper.py',
    'session_name' => 'CSFILESESSID',
    'login_password' => getenv('CS_APP_PASSWORD') ?: 'Aa#112211',
    'max_failed_attempts' => 5,
    'lock_minutes' => 10,
    'form_token_ttl' => 300,
    'list_poll_seconds' => 5,
    'zip_cache_ttl' => 86400,
    'zip_compress_level' => 6,
    'python_memory_limit_mb' => 512,
    'blocked_extensions' => ['php', 'phtml', 'php3', 'php4', 'php5', 'py', 'htaccess', 'env', 'ini', 'sh'],
    'blocked_basenames' => ['.htaccess', '.env', '.user.ini', 'web.config'],
];
