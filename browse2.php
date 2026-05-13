<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$allowedDirs = array(
    __DIR__ . DIRECTORY_SEPARATOR . '1',
    __DIR__ . DIRECTORY_SEPARATOR . '2'
);

$dl = isset($_GET['dl']) ? $_GET['dl'] : '';
$path = isset($_GET['path']) ? $_GET['path'] : '';

function normalizePath($path) {
    $path = str_replace(array('\\', "\0"), array('/', ''), $path);
    $path = trim($path, '/');
    if ($path === '') {
        return '';
    }
    $parts = array();
    $segments = explode('/', $path);
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            return null;
        }
        $parts[] = $segment;
    }
    return implode(DIRECTORY_SEPARATOR, $parts);
}

function isAllowedPath($fullPath, $allowedDirs) {
    $realPath = realpath($fullPath);
    if ($realPath === false) {
        return false;
    }
    foreach ($allowedDirs as $dir) {
        $realDir = realpath($dir);
        if ($realDir !== false) {
            $dirWithSep = $realDir . DIRECTORY_SEPARATOR;
            if (strpos($realPath, $dirWithSep) === 0) {
                return true;
            }
            if ($realPath === $realDir) {
                return true;
            }
        }
    }
    return false;
}

function getMimeType($filePath) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
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
    return isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream';
}

if (empty($path)) {
    http_response_code(400);
    echo '参数 path 不能为空';
    exit;
}

$normalizedPath = normalizePath($path);
if ($normalizedPath === null) {
    http_response_code(400);
    echo '非法路径';
    exit;
}

$found = false;
$fullPath = '';
foreach ($allowedDirs as $dir) {
    $candidate = $dir . DIRECTORY_SEPARATOR . $normalizedPath;
    if (file_exists($candidate) && isAllowedPath($candidate, $allowedDirs)) {
        $fullPath = $candidate;
        $found = true;
        break;
    }
}

if (!$found || !is_file($fullPath)) {
    http_response_code(404);
    echo '文件不存在';
    exit;
}

$fileName = basename($fullPath);

if ($dl === '1') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=0');
    readfile($fullPath);
    exit;
} else {
    $mimeType = getMimeType($fullPath);
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: private, max-age=0');
    readfile($fullPath);
    exit;
}
