<?php

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos($haystack, $needle) !== false;
    }
}

function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function ensure_directories(): void
{
    $config = app_config();
    foreach (['tmp_root', 'data_root', 'task_root', 'logs_root'] as $key) {
        if (!is_dir($config[$key])) {
            mkdir($config[$key], 0777, true);
        }
    }
}

function client_ip(): string
{
    $keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $raw = explode(',', (string) $_SERVER[$key])[0];
            return trim($raw);
        }
    }
    return '0.0.0.0';
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_relative_path(?string $path): string
{
    if ($path === null || $path === '' || $path === '/') {
        return '';
    }

    $path = str_replace(['\\', "\0"], ['/', ''], $path);
    $path = trim($path, '/');
    if ($path === '') {
        return '';
    }

    $parts = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            throw new RuntimeException('非法路径');
        }
        $parts[] = $segment;
    }
    return implode(DIRECTORY_SEPARATOR, $parts);
}

function source_root_realpath(): string
{
    $config = app_config();
    $root = realpath($config['source_root']);
    if ($root === false || !is_dir($root)) {
        throw new RuntimeException('源目录不存在');
    }
    return $root;
}

function resolve_source_path(?string $relativePath, bool $mustExist = true): string
{
    $root = source_root_realpath();
    $normalized = normalize_relative_path($relativePath);
    $candidate = $normalized === '' ? $root : $root . DIRECTORY_SEPARATOR . $normalized;

    if (!$mustExist) {
        $parent = dirname($candidate);
        $parentReal = realpath($parent);
        if ($parentReal === false || !str_starts_with($parentReal, $root)) {
            throw new RuntimeException('非法路径');
        }
        return $candidate;
    }

    $real = realpath($candidate);
    if ($real === false || !str_starts_with($real, $root)) {
        throw new RuntimeException('非法路径');
    }
    return $real;
}

function relative_from_root(string $fullPath): string
{
    $root = source_root_realpath();
    if ($fullPath === $root) {
        return '';
    }
    $relative = substr($fullPath, strlen($root));
    return ltrim(str_replace('\\', '/', $relative), '/');
}

function is_blocked_file(string $path): bool
{
    $config = app_config();
    $basename = strtolower(basename($path));
    if (in_array($basename, $config['blocked_basenames'], true)) {
        return true;
    }

    $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
    return $extension !== '' && in_array($extension, $config['blocked_extensions'], true);
}

function list_tree(string $absolutePath): array
{
    $items = [];
    $entries = @scandir($absolutePath);
    if ($entries === false) {
        return $items;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $absolutePath . DIRECTORY_SEPARATOR . $entry;
        if (!file_exists($fullPath) || is_link($fullPath) || is_blocked_file($fullPath)) {
            continue;
        }

        $isDir = is_dir($fullPath);
        $item = [
            'path' => relative_from_root($fullPath),
            'name' => $entry,
            'type' => $isDir ? 'dir' : 'file',
            'size' => $isDir ? 0 : filesize($fullPath),
            'mtime' => filemtime($fullPath) ?: 0,
        ];

        if ($isDir) {
            $item['children'] = list_tree($fullPath);
            $item['empty'] = count($item['children']) === 0;
        }

        $items[] = $item;
    }

    usort($items, static function (array $left, array $right): int {
        if ($left['type'] !== $right['type']) {
            return $left['type'] === 'dir' ? -1 : 1;
        }
        return strnatcasecmp($left['name'], $right['name']);
    });

    return $items;
}

function list_directory(string $absolutePath): array
{
    $items = [];
    $entries = @scandir($absolutePath);
    if ($entries === false) {
        return $items;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = $absolutePath . DIRECTORY_SEPARATOR . $entry;
        if (!file_exists($fullPath) || is_link($fullPath) || is_blocked_file($fullPath)) {
            continue;
        }

        $isDir = is_dir($fullPath);
        $item = [
            'path' => relative_from_root($fullPath),
            'name' => $entry,
            'type' => $isDir ? 'dir' : 'file',
            'size' => $isDir ? 0 : filesize($fullPath),
            'mtime' => filemtime($fullPath) ?: 0,
        ];

        if ($isDir) {
            $item['empty'] = count(list_directory($fullPath)) === 0;
        }

        $items[] = $item;
    }

    usort($items, static function (array $left, array $right): int {
        if ($left['type'] !== $right['type']) {
            return $left['type'] === 'dir' ? -1 : 1;
        }
        return strnatcasecmp($left['name'], $right['name']);
    });

    return $items;
}

function directory_signature(string $absolutePath): array
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $maxMtime = is_dir($absolutePath) ? (filemtime($absolutePath) ?: time()) : time();
    $count = 0;
    $hash = hash_init('sha256');

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        if (is_link($path) || is_blocked_file($path)) {
            continue;
        }

        $relative = relative_from_root($path);
        $mtime = $item->getMTime();
        $size = $item->isDir() ? 0 : $item->getSize();
        $maxMtime = max($maxMtime, $mtime);
        $count++;
        hash_update($hash, $relative . '|' . $mtime . '|' . $size . '|' . ($item->isDir() ? 'd' : 'f'));
    }

    return [
        'hash' => hash_final($hash),
        'mtime' => $maxMtime,
        'count' => $count,
    ];
}

function task_file_path(string $taskId): string
{
    $config = app_config();
    return $config['task_root'] . DIRECTORY_SEPARATOR . $taskId . '.json';
}

function write_task(string $taskId, array $payload): void
{
    $payload['updated_at'] = time();
    file_put_contents(
        task_file_path($taskId),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function read_task(string $taskId): ?array
{
    $file = task_file_path($taskId);
    if (!is_file($file)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function log_message(string $message): void
{
    $config = app_config();
    ensure_directories();
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($config['logs_root'] . DIRECTORY_SEPARATOR . 'app.log', $line, FILE_APPEND);
}

function csrf_token(bool $refresh = false): string
{
    $now = time();
    if (
        $refresh ||
        empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_expire']) ||
        $_SESSION['csrf_token_expire'] < $now
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        $_SESSION['csrf_token_expire'] = $now + app_config()['form_token_ttl'];
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token)
        && !empty($_SESSION['csrf_token_expire'])
        && $_SESSION['csrf_token_expire'] >= time();
}

function login_token(bool $refresh = false): string
{
    $now = time();
    if (
        $refresh ||
        empty($_SESSION['login_token']) ||
        empty($_SESSION['login_token_expire']) ||
        $_SESSION['login_token_expire'] < $now
    ) {
        $_SESSION['login_token'] = bin2hex(random_bytes(16));
        $_SESSION['login_token_expire'] = $now + app_config()['form_token_ttl'];
    }

    return $_SESSION['login_token'];
}

function consume_login_token(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['login_token'])
        && hash_equals($_SESSION['login_token'], $token)
        && !empty($_SESSION['login_token_expire'])
        && $_SESSION['login_token_expire'] >= time();
}

function rate_limit_file(string $ip): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $ip);
    return app_config()['data_root'] . DIRECTORY_SEPARATOR . 'rate_' . $safe . '.json';
}

function rate_limit_state(string $ip): array
{
    $file = rate_limit_file($ip);
    if (!is_file($file)) {
        return ['failures' => 0, 'locked_until' => 0];
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        return ['failures' => 0, 'locked_until' => 0];
    }
    return [
        'failures' => (int) ($data['failures'] ?? 0),
        'locked_until' => (int) ($data['locked_until'] ?? 0),
    ];
}

function save_rate_limit_state(string $ip, array $state): void
{
    file_put_contents(rate_limit_file($ip), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
}

function require_guest_access(): void
{
    if (!empty($_SESSION['authenticated'])) {
        header('Location: /');
        exit;
    }
}

function require_auth(): void
{
    if (empty($_SESSION['authenticated'])) {
        if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            json_response(['error' => 'UNAUTHORIZED', 'message' => '请先登录'], 401);
        }
        header('Location: /');
        exit;
    }
}

function handle_login(string $password, string $token): array
{
    $config = app_config();
    $ip = client_ip();
    $state = rate_limit_state($ip);
    $now = time();

    if ($state['locked_until'] > $now) {
        return [
            'ok' => false,
            'message' => '失败次数过多，请在 ' . ceil(($state['locked_until'] - $now) / 60) . ' 分钟后重试',
        ];
    }

    if (!consume_login_token($token)) {
        return ['ok' => false, 'message' => '令牌无效，请刷新页面后重试'];
    }

    if (!hash_equals($config['login_password'], $password)) {
        $state['failures']++;
        if ($state['failures'] >= $config['max_failed_attempts']) {
            $state['locked_until'] = $now + ($config['lock_minutes'] * 60);
            $state['failures'] = 0;
        }
        save_rate_limit_state($ip, $state);
        return ['ok' => false, 'message' => '密码错误'];
    }

    save_rate_limit_state($ip, ['failures' => 0, 'locked_until' => 0]);
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['login_at'] = $now;
    csrf_token(true);

    return ['ok' => true];
}

function send_file_with_range(string $absolutePath, string $downloadName): void
{
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        http_response_code(404);
        echo '文件不存在';
        exit;
    }

    $size = filesize($absolutePath);
    $start = 0;
    $end = $size - 1;
    $status = 200;

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($downloadName) . '"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0');

    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $matches)) {
        if ($matches[1] !== '') {
            $start = (int) $matches[1];
        }
        if ($matches[2] !== '') {
            $end = (int) $matches[2];
        }
        if ($start > $end || $start >= $size || $end >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }
        $status = 206;
    }

    $length = $end - $start + 1;
    http_response_code($status);
    header('Content-Length: ' . $length);
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $handle = fopen($absolutePath, 'rb');
    if ($handle === false) {
        http_response_code(500);
        echo '文件读取失败';
        exit;
    }

    ignore_user_abort(true);
    set_time_limit(0);
    fseek($handle, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(1024 * 1024, $remaining));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        flush();
        $remaining -= strlen($chunk);
    }
    fclose($handle);
    exit;
}

function shell_argument(string $value): string
{
    return escapeshellarg($value);
}
