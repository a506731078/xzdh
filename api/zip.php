<?php

require __DIR__ . '/../lib/bootstrap.php';

require_auth();
ensure_directories();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'POST') {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            json_response(['error' => 'BAD_REQUEST', 'message' => '请求体必须为 JSON'], 400);
        }
        if (!validate_csrf((string) ($payload['csrf_token'] ?? ''))) {
            json_response(['error' => 'INVALID_TOKEN', 'message' => '令牌校验失败'], 403);
        }

        $relativePath = (string) ($payload['path'] ?? '');
        $source = resolve_source_path($relativePath, true);
        if (!is_dir($source)) {
            json_response(['error' => 'NOT_DIRECTORY', 'message' => '目标不是目录'], 400);
        }

        $signature = directory_signature($source);
        if ($signature['count'] === 0) {
            json_response(['error' => 'EMPTY_DIR', 'message' => '目录为空'], 400);
        }

        $taskId = hash('sha256', relative_from_root($source) . '|' . $signature['hash']);
        $zipName = basename($source) . '-' . substr($signature['hash'], 0, 12) . '.zip';
        $target = app_config()['tmp_root'] . DIRECTORY_SEPARATOR . $zipName;
        $publicUrl = '/tmp/' . rawurlencode($zipName);

        $existing = read_task($taskId);
        if (
            is_array($existing) &&
            ($existing['status'] ?? '') === 'done' &&
            !empty($existing['zip_path']) &&
            is_file($existing['zip_path']) &&
            (time() - (int) ($existing['updated_at'] ?? 0)) <= app_config()['zip_cache_ttl']
        ) {
            json_response([
                'task_id' => $taskId,
                'status' => 'done',
                'cached' => true,
                'download_url' => $publicUrl,
                'signature' => $signature,
            ]);
        }

        $task = [
            'task_id' => $taskId,
            'status' => 'queued',
            'source' => $source,
            'relative_path' => relative_from_root($source),
            'zip_path' => $target,
            'download_url' => $publicUrl,
            'signature' => $signature,
            'percent' => 0,
            'speed' => '0 MB/s',
            'remaining' => '--',
            'started_at' => time(),
            'updated_at' => time(),
            'error' => '',
            'worker_started' => false,
        ];
        write_task($taskId, $task);

        if (is_file($target)) {
            @unlink($target);
        }

        $pythonBin = app_config()['python_bin'];
        $zipScript = app_config()['zip_script'];
        $taskPayload = json_encode([
            'source' => $source,
            'target' => $target,
            'task_id' => $taskId,
            'compresslevel' => app_config()['zip_compress_level'],
            'memory_limit_mb' => app_config()['python_memory_limit_mb'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!function_exists('exec')) {
            json_response([
                'error' => 'EXEC_DISABLED',
                'message' => '服务器未开启 exec，无法启动 Python 打包进程',
            ], 500);
        }
        if (!is_file($zipScript)) {
            json_response([
                'error' => 'SCRIPT_MISSING',
                'message' => 'Python 打包脚本不存在',
            ], 500);
        }
        if ($taskPayload === false) {
            json_response([
                'error' => 'PAYLOAD_FAILED',
                'message' => '任务参数生成失败',
            ], 500);
        }
        if (!is_writable(app_config()['tmp_root']) || !is_writable(app_config()['task_root'])) {
            json_response([
                'error' => 'PATH_NOT_WRITABLE',
                'message' => 'tmp 或任务目录不可写',
            ], 500);
        }

        $command = sprintf(
            '%s %s %s > /dev/null 2>&1 &',
            shell_argument($pythonBin),
            shell_argument($zipScript),
            shell_argument($taskPayload)
        );

        if (DIRECTORY_SEPARATOR === '\\') {
            $command = sprintf(
                'start /B "" %s %s %s',
                shell_argument($pythonBin),
                shell_argument($zipScript),
                shell_argument($taskPayload)
            );
        }

        exec($command, $output, $exitCode);
        if (DIRECTORY_SEPARATOR !== '\\' && $exitCode !== 0) {
            json_response([
                'error' => 'PROCESS_START_FAILED',
                'message' => 'Python 打包进程启动失败',
            ], 500);
        }

        $workerStarted = false;
        for ($i = 0; $i < 10; $i++) {
            usleep(200000);
            $latestTask = read_task($taskId);
            if (!is_array($latestTask)) {
                continue;
            }
            if (!empty($latestTask['worker_started'])) {
                $workerStarted = true;
                break;
            }
            if (($latestTask['status'] ?? '') === 'error') {
                json_response([
                    'error' => 'PROCESS_RUNTIME_FAILED',
                    'message' => (string) ($latestTask['error'] ?? 'Python 打包进程启动后立即失败'),
                ], 500);
            }
        }

        if (!$workerStarted) {
            write_task($taskId, [
                'task_id' => $taskId,
                'status' => 'error',
                'error' => 'Python 进程未接管任务，请检查 Python 路径、依赖、权限或 open_basedir 限制',
                'worker_started' => false,
            ]);
            json_response([
                'error' => 'PROCESS_NOT_CONFIRMED',
                'message' => 'Python 进程未接管任务，请检查 Python 路径、依赖、权限或 open_basedir 限制',
            ], 500);
        }

        json_response([
            'task_id' => $taskId,
            'status' => 'running',
            'cached' => false,
            'download_url' => $publicUrl,
            'signature' => $signature,
        ], 202);
    }

    if ($method === 'GET' && isset($_GET['task'])) {
        $taskId = preg_replace('/[^a-f0-9]/', '', (string) $_GET['task']);
        $task = read_task($taskId);
        if (!$task) {
            json_response(['error' => 'NOT_FOUND', 'message' => '任务不存在'], 404);
        }
        json_response($task);
    }

    if ($method === 'GET' && isset($_GET['stream'])) {
        $taskId = preg_replace('/[^a-f0-9]/', '', (string) $_GET['stream']);
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        echo ':' . str_repeat(' ', 2048) . "\n\n";
        flush();

        $startTime = time();
        while (true) {
            $task = read_task($taskId);
            if (!$task) {
                echo "event: error\n";
                echo 'data: ' . json_encode(['message' => '任务不存在'], JSON_UNESCAPED_UNICODE) . "\n\n";
                flush();
                break;
            }

            echo "event: progress\n";
            echo 'data: ' . json_encode($task, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
            flush();

            if (in_array($task['status'] ?? '', ['done', 'error'], true)) {
                break;
            }

            if ((time() - $startTime) > 3600 || connection_aborted()) {
                break;
            }
            usleep(200000);
        }
        exit;
    }

    json_response(['error' => 'METHOD_NOT_ALLOWED', 'message' => '不支持的请求'], 405);
} catch (Throwable $e) {
    log_message('zip api error: ' . $e->getMessage());
    json_response([
        'error' => 'ZIP_FAILED',
        'message' => '打包任务创建失败：' . $e->getMessage(),
    ], 500);
}
