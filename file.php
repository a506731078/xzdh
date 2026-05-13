<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$path = isset($_GET['path']) ? $_GET['path'] : '';

if (empty($path)) {
    http_response_code(400);
    echo '参数 path 不能为空';
    exit;
}

$path = str_replace(array('\\', "\0", '..'), array('/', '', ''), $path);
$path = trim($path, '/');

$file1 = __DIR__ . '/1/' . $path;
$file2 = __DIR__ . '/2/' . $path;

$fullPath = '';
if (file_exists($file1) && is_file($file1)) {
    $fullPath = $file1;
} elseif (file_exists($file2) && is_file($file2)) {
    $fullPath = $file2;
}

if (empty($fullPath)) {
    http_response_code(404);
    echo '文件不存在';
    exit;
}

$dl = isset($_GET['dl']) ? $_GET['dl'] : '';
$fileName = basename($fullPath);

if ($dl === '1') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=0');
    readfile($fullPath);
} else {
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeTypes = array(
        'txt' => 'text/plain; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'xml' => 'application/xml; charset=utf-8',
        'md' => 'text/markdown; charset=utf-8',
        'csv' => 'text/csv; charset=utf-8',
        'yaml' => 'text/yaml; charset=utf-8',
        'yml' => 'text/yaml; charset=utf-8',
        'php' => 'text/plain; charset=utf-8',
        'py' => 'text/plain; charset=utf-8',
        'java' => 'text/plain; charset=utf-8',
        'c' => 'text/plain; charset=utf-8',
        'cpp' => 'text/plain; charset=utf-8',
        'h' => 'text/plain; charset=utf-8',
        'go' => 'text/plain; charset=utf-8',
        'rs' => 'text/plain; charset=utf-8',
        'ts' => 'text/plain; charset=utf-8',
        'sh' => 'text/plain; charset=utf-8',
        'bat' => 'text/plain; charset=utf-8',
        'conf' => 'text/plain; charset=utf-8',
        'ini' => 'text/plain; charset=utf-8',
        'log' => 'text/plain; charset=utf-8',
        'sql' => 'text/plain; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'pdf' => 'application/pdf',
    );
    $mime = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=0');
    readfile($fullPath);
}
