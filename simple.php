<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo 'simple.php 启动！<br>';

$path = isset($_GET['path']) ? $_GET['path'] : '';
echo 'path: ' . htmlspecialchars($path) . '<br>';

if (empty($path)) {
    echo '请提供 path 参数';
    exit;
}

$file1 = __DIR__ . '/1/' . $path;
$file2 = __DIR__ . '/2/' . $path;

echo '尝试文件1: ' . htmlspecialchars($file1) . '<br>';
echo '尝试文件2: ' . htmlspecialchars($file2) . '<br>';

$fullPath = '';
if (file_exists($file1)) {
    $fullPath = $file1;
} elseif (file_exists($file2)) {
    $fullPath = $file2;
}

if (empty($fullPath)) {
    echo '文件不存在';
    exit;
}

echo '找到文件: ' . htmlspecialchars($fullPath) . '<br>';

$dl = isset($_GET['dl']) ? $_GET['dl'] : '';
if ($dl === '1') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode(basename($fullPath)) . '"');
    readfile($fullPath);
} else {
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = 'text/plain; charset=utf-8';
    if ($ext === 'json') $mime = 'application/json; charset=utf-8';
    if ($ext === 'js') $mime = 'text/javascript; charset=utf-8';
    if ($ext === 'html') $mime = 'text/html; charset=utf-8';
    if ($ext === 'css') $mime = 'text/css; charset=utf-8';
    if ($ext === 'png') $mime = 'image/png';
    if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
    if ($ext === 'gif') $mime = 'image/gif';
    header('Content-Type: ' . $mime);
    readfile($fullPath);
}
