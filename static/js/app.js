const app = document.getElementById('app');

if (app) {
  let csrfToken = app.dataset.csrfToken;
  const pollSeconds = Number(app.dataset.listPoll || '5');
  const refreshBtn = document.getElementById('refreshBtn');
  const backBtn = document.getElementById('backBtn');
  const currentPathLabel = document.getElementById('currentPathLabel');
  const listLoading = document.getElementById('listLoading');
  const treeRoot = document.getElementById('treeRoot');
  const emptyState = document.getElementById('emptyState');
  const syncState = document.getElementById('syncState');
  const messageBox = document.getElementById('messageBox');
  const currentTarget = document.getElementById('currentTarget');
  const progressText = document.getElementById('progressText');
  const progressBar = document.getElementById('progressBar');
  const speedText = document.getElementById('speedText');
  const remainingText = document.getElementById('remainingText');
  const zipDownloadLink = document.getElementById('zipDownloadLink');
  const taskHint = document.getElementById('taskHint');

  let activeStream = null;
  let activePollTimer = null;
  let lastTreeJson = '';
  let currentPath = '';
  let parentPath = '';
  let rootName = '/';

  const showMessage = (text, type = 'info') => {
    messageBox.textContent = text;
    messageBox.className = `alert ${type}`;
    messageBox.classList.remove('hidden');
  };

  const hideMessage = () => {
    messageBox.classList.add('hidden');
  };

  const extractResponseSnippet = (rawText) => {
    if (!rawText) {
      return '(空响应)';
    }
    const clean = String(rawText).replace(/\s+/g, ' ').trim();
    if (clean.length <= 180) {
      return clean;
    }
    return `${clean.slice(0, 180)}...`;
  };

  const parseApiResponse = async (response, fallbackMessage) => {
    const rawText = await response.text();
    let data = null;

    try {
      data = rawText ? JSON.parse(rawText) : {};
    } catch (error) {
      const snippet = extractResponseSnippet(rawText);
      throw new Error(`${fallbackMessage} | 后端响应片段: ${snippet}`);
    }

    if (!response.ok) {
      const message = data?.message || data?.error || fallbackMessage;
      throw new Error(message);
    }

    return data;
  };

  const formatSize = (size) => {
    if (!size) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = size;
    let index = 0;
    while (value >= 1024 && index < units.length - 1) {
      value /= 1024;
      index += 1;
    }
    return `${value.toFixed(index === 0 ? 0 : 2)} ${units[index]}`;
  };

  const formatTime = (mtime) => new Date(mtime * 1000).toLocaleString('zh-CN');

  const setProgress = (percent, speed = '--', remaining = '--') => {
    progressText.textContent = `${percent}%`;
    progressBar.style.width = `${percent}%`;
    speedText.textContent = `速度: ${speed}`;
    remainingText.textContent = `剩余: ${remaining}`;
  };

  const resetProgress = () => {
    setProgress(0, '--', '--');
    zipDownloadLink.classList.add('hidden');
    zipDownloadLink.removeAttribute('href');
  };

  const stopTaskWatch = () => {
    if (activeStream) {
      activeStream.close();
      activeStream = null;
    }
    if (activePollTimer) {
      window.clearInterval(activePollTimer);
      activePollTimer = null;
    }
  };

  const applyTaskState = (data) => {
    setProgress(Number(data.percent || 0), data.speed || '--', data.remaining || '--');

    if (data.status === 'done' && data.download_url) {
      zipDownloadLink.href = data.download_url;
      zipDownloadLink.classList.remove('hidden');
      taskHint.textContent = '打包完成，可直接下载 ZIP';
      hideMessage();
      stopTaskWatch();
      return;
    }

    if (data.status === 'error') {
      showMessage(data.error || '打包失败', 'error');
      taskHint.textContent = '任务失败';
      stopTaskWatch();
    }
  };

  const startTaskPolling = (taskId) => {
    if (activePollTimer) {
      window.clearInterval(activePollTimer);
    }

    const poll = async () => {
      try {
        const response = await fetch(`/api/zip?task=${encodeURIComponent(taskId)}`, {
          cache: 'no-store',
        });
        const data = await parseApiResponse(response, '任务状态获取失败');
        applyTaskState(data);
      } catch (error) {
        showMessage(error.message || '任务状态获取失败', 'error');
        taskHint.textContent = '任务状态异常';
        stopTaskWatch();
      }
    };

    poll();
    activePollTimer = window.setInterval(poll, 1000);
  };

  const updateBrowserBar = () => {
    currentPathLabel.textContent = currentPath ? `/${currentPath}` : `/${rootName}`;
    backBtn.disabled = currentPath === '';
  };

  const enterFolder = (path) => {
    currentPath = path || '';
    fetchList(false);
  };

  const renderTreeNode = (node) => {
    const item = document.createElement('div');
    item.className = 'tree-item';

    const row = document.createElement('div');
    row.className = 'tree-row';

    const info = document.createElement('div');
    info.className = 'tree-info';

    const title = document.createElement('button');
    title.type = 'button';
    title.className = `tree-title ${node.type}`;
    title.textContent = node.name;
    title.dataset.path = node.path;
    title.dataset.type = node.type;
    title.addEventListener('click', () => {
      currentTarget.textContent = node.path || node.name;
      if (node.type === 'file') {
        taskHint.textContent = '文件将直接触发浏览器下载';
        hideMessage();
        window.location.href = `/download.php?path=${encodeURIComponent(node.path)}`;
      } else {
        taskHint.textContent = '已进入目录';
        enterFolder(node.path);
      }
    });

    const meta = document.createElement('span');
    meta.className = 'tree-meta';
    meta.textContent = `${node.type === 'dir' ? '目录' : formatSize(node.size)} | ${formatTime(node.mtime)}`;

    info.appendChild(title);
    info.appendChild(meta);

    const actions = document.createElement('div');
    actions.className = 'tree-actions';

    if (node.type === 'dir') {
      const enterBtn = document.createElement('button');
      enterBtn.type = 'button';
      enterBtn.className = 'mini-btn';
      enterBtn.textContent = '进入';
      enterBtn.addEventListener('click', () => {
        currentTarget.textContent = node.path || node.name;
        taskHint.textContent = '已进入目录';
        enterFolder(node.path);
      });

      const downloadBtn = document.createElement('button');
      downloadBtn.type = 'button';
      downloadBtn.className = 'mini-btn primary-mini-btn';
      downloadBtn.textContent = '下载 ZIP';
      downloadBtn.addEventListener('click', () => {
        currentTarget.textContent = node.path || node.name;
        taskHint.textContent = '目录将进入打包任务';
        startZipTask(node.path, node.empty);
      });

      actions.appendChild(enterBtn);
      actions.appendChild(downloadBtn);
    }

    row.appendChild(info);
    row.appendChild(actions);
    item.appendChild(row);

    return item;
  };

  const renderTree = (payload) => {
    const json = JSON.stringify({
      path: payload.path || '',
      items: payload.items || [],
    });
    if (json === lastTreeJson) {
      syncState.textContent = `已同步 ${new Date().toLocaleTimeString('zh-CN')}`;
      return;
    }

    lastTreeJson = json;
    treeRoot.innerHTML = '';

    rootName = payload.root || '/';
    currentPath = payload.path || '';
    parentPath = payload.parent_path || '';
    updateBrowserBar();

    if (!(payload.items || []).length) {
      emptyState.classList.remove('hidden');
      treeRoot.classList.add('hidden');
    } else {
      emptyState.classList.add('hidden');
      treeRoot.classList.remove('hidden');
      payload.items.forEach((item) => treeRoot.appendChild(renderTreeNode(item)));
    }

    syncState.textContent = `已同步 ${new Date().toLocaleTimeString('zh-CN')}`;
  };

  const fetchList = async (silent = false) => {
    if (!silent) {
      listLoading.classList.remove('hidden');
    }
    try {
      const query = currentPath ? `?path=${encodeURIComponent(currentPath)}` : '';
      const response = await fetch(`/api/list${query}`, {
        headers: { 'X-Requested-With': 'fetch' },
        cache: 'no-store',
      });
      const data = await parseApiResponse(response, '目录读取失败');
      if (data.csrf_token) {
        csrfToken = data.csrf_token;
      }
      renderTree(data);
      hideMessage();
    } catch (error) {
      showMessage(error.message || '目录读取失败', 'error');
      syncState.textContent = '同步失败';
    } finally {
      listLoading.classList.add('hidden');
    }
  };

  const bindStream = (taskId) => {
    stopTaskWatch();
    activeStream = new EventSource(`/api/zip?stream=${encodeURIComponent(taskId)}`);
    startTaskPolling(taskId);

    activeStream.addEventListener('progress', (event) => {
      try {
        const data = JSON.parse(event.data);
        applyTaskState(data);
      } catch (error) {
        const snippet = extractResponseSnippet(event.data);
        showMessage(`进度数据解析失败 | 后端响应片段: ${snippet}`, 'error');
      }
    });

    activeStream.onerror = () => {
      if (activeStream) {
        activeStream.close();
        activeStream = null;
      }
      taskHint.textContent = '进度流中断，已切换状态轮询';
    };
  };

  const startZipTask = async (path, isEmpty) => {
    resetProgress();
    currentTarget.textContent = path;

    if (isEmpty) {
      showMessage('目录为空', 'error');
      taskHint.textContent = '空目录无法打包';
      return;
    }

    taskHint.textContent = '正在创建打包任务';
    showMessage('正在创建打包任务，请稍候...', 'info');

    try {
      const response = await fetch('/api/zip', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          path,
          csrf_token: csrfToken,
        }),
      });
      const data = await parseApiResponse(response, '打包任务创建失败');

      if (data.status === 'done' && data.download_url) {
        setProgress(100, '--', '0 s');
        zipDownloadLink.href = data.download_url;
        zipDownloadLink.classList.remove('hidden');
        taskHint.textContent = data.cached ? '已复用缓存 ZIP' : '打包完成';
        hideMessage();
        return;
      }

      taskHint.textContent = '正在打包，请等待进度更新';
      hideMessage();
      bindStream(data.task_id);
    } catch (error) {
      showMessage(error.message || '打包失败', 'error');
      taskHint.textContent = '任务创建失败';
    }
  };

  backBtn?.addEventListener('click', () => {
    if (currentPath === '') {
      return;
    }
    currentPath = parentPath;
    fetchList(false);
  });

  refreshBtn?.addEventListener('click', () => fetchList(false));

  updateBrowserBar();
  fetchList(false);
  window.setInterval(() => fetchList(true), Math.max(3, pollSeconds) * 1000);
}
