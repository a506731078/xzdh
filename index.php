<?php

require __DIR__ . '/lib/bootstrap.php';

ensure_directories();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$selfPath = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = handle_login((string) ($_POST['password'] ?? ''), (string) ($_POST['csrf_token'] ?? ''));
    if ($result['ok']) {
        header('Location: ' . $selfPath);
        exit;
    }
    $_SESSION['login_error'] = $result['message'];
    header('Location: ' . $selfPath);
    exit;
}

if (!empty($_SESSION['login_error'])) {
    $error = (string) $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

$isAuthed = !empty($_SESSION['authenticated']);
$csrfToken = csrf_token();
$loginToken = login_token();
$config = app_config();
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>在线文件浏览与下载</title>
    <link rel="stylesheet" href="/static/css/app.css?v=1">
</head>
<body>
<div class="page-shell">
    <header class="topbar">
        <div>
            <h1>在线文件浏览与下载</h1>
            <p>实时同步目录，支持单文件下载与目录打包。</p>
        </div>
        <?php if ($isAuthed): ?>
            <button class="ghost-btn" id="refreshBtn" type="button">立即同步</button>
        <?php endif; ?>
    </header>

    <?php if (!$isAuthed): ?>
        <main class="auth-card">
            <form method="post" class="login-form" autocomplete="off">
                <h2>输入访问密码</h2>
                <p>连续 5 次失败将封禁 IP 10 分钟。</p>
                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= h($error) ?></div>
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?= h($loginToken) ?>">
                <label for="password">访问密码</label>
                <input id="password" name="password" type="password" required maxlength="128" placeholder="请输入密码">
                <button type="submit" class="primary-btn">进入系统</button>
            </form>
        </main>
    <?php else: ?>
        <main class="app-layout" id="app"
              data-csrf-token="<?= h($csrfToken) ?>"
              data-list-poll="<?= (int) $config['list_poll_seconds'] ?>">
            <section class="panel left-panel">
                <div class="panel-head">
                    <h2>文件列表</h2>
                    <span id="syncState">等待同步</span>
                </div>
                <div class="browser-bar">
                    <button class="ghost-btn browser-back" id="backBtn" type="button" disabled>返回上一级</button>
                    <div class="path-badge" id="currentPathLabel">/</div>
                </div>
                <div class="loading-wrap" id="listLoading">
                    <div class="spinner"></div>
                    <span>正在读取目录...</span>
                </div>
                <div id="emptyState" class="empty-state hidden">目录为空</div>
                <div id="treeRoot" class="tree-root hidden"></div>
            </section>

            <section class="panel right-panel">
                <div class="panel-head">
                    <h2>任务与状态</h2>
                    <span id="taskHint">选择文件或目录开始下载</span>
                </div>
                <div id="messageBox" class="alert hidden"></div>
                <div class="task-card">
                    <div class="task-row">
                        <span>当前目录</span>
                        <strong id="currentTarget">未选择</strong>
                    </div>
                    <div class="task-row">
                        <span>打包进度</span>
                        <strong id="progressText">0%</strong>
                    </div>
                    <div class="progress-bar">
                        <div id="progressBar" class="progress-value"></div>
                    </div>
                    <div class="task-row meta-row">
                        <span id="speedText">速度: --</span>
                        <span id="remainingText">剩余: --</span>
                    </div>
                    <a id="zipDownloadLink" class="primary-btn hidden" href="#" download>下载 ZIP</a>
                </div>

                <div class="tips-card">
                    <h3>说明</h3>
                    <ul>
                        <li>点击文件可直接下载，支持断点续传。</li>
                        <li>点击文件夹名称进入目录，点击“下载 ZIP”按钮打包当前目录。</li>
                        <li>目录下载会自动打包，若源目录未变化则直接复用缓存 ZIP。</li>
                        <li>目录结构默认每 <?= (int) $config['list_poll_seconds'] ?> 秒自动同步一次。</li>
                    </ul>
                </div>
            </section>
        </main>
        <script src="/static/js/app.js?v=3" defer></script>
    <?php endif; ?>
</div>
</body>
</html>
