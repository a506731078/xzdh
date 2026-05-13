<?php

require __DIR__ . '/../lib/bootstrap.php';

require_auth();
ensure_directories();

try {
    $root = source_root_realpath();
    $requestedPath = (string) ($_GET['path'] ?? '');
    $current = resolve_source_path($requestedPath, true);
    if (!is_dir($current)) {
        json_response(['error' => 'NOT_DIRECTORY', 'message' => '目标不是目录'], 400);
    }

    $relativePath = relative_from_root($current);
    $parentPath = '';
    if ($relativePath !== '') {
        $segments = explode('/', $relativePath);
        array_pop($segments);
        $parentPath = implode('/', $segments);
    }

    json_response([
        'root' => basename($root),
        'path' => $relativePath,
        'parent_path' => $parentPath,
        'type' => 'dir',
        'size' => 0,
        'mtime' => filemtime($current) ?: time(),
        'items' => list_directory($current),
        'csrf_token' => csrf_token(true),
        'generated_at' => time(),
    ]);
} catch (Throwable $e) {
    log_message('list error: ' . $e->getMessage());
    json_response(['error' => 'LIST_FAILED', 'message' => '目录读取失败'], 500);
}
