<?php

require __DIR__ . '/lib/bootstrap.php';

require_auth();
ensure_directories();

try {
    $relative = (string) ($_GET['path'] ?? '');
    $absolute = resolve_source_path($relative, true);
    if (is_dir($absolute)) {
        http_response_code(400);
        echo '目录请使用打包接口下载';
        exit;
    }
    if (is_blocked_file($absolute)) {
        http_response_code(403);
        echo '禁止访问敏感文件';
        exit;
    }
    send_file_with_range($absolute, basename($absolute));
} catch (Throwable $e) {
    log_message('download error: ' . $e->getMessage());
    http_response_code(404);
    echo '文件不存在或不可访问';
}
