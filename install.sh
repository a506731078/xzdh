#!/bin/bash
set -euo pipefail

SITE_ROOT="/www/wwwroot/cs"
PY_DIR="$SITE_ROOT/py"
VENV_DIR="$PY_DIR/venv"
REQ_FILE="$SITE_ROOT/requirements.txt"

SUDO=""
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  if command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  else
    echo "错误: 需要 root 权限安装系统依赖，且未找到 sudo。"
    exit 1
  fi
fi

install_python_runtime() {
  echo "[2/8] 检查并安装 Python 运行环境 (python3-venv/python3-full)..."

  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    $SUDO apt-get update -y
    $SUDO apt-get install -y python3 python3-venv python3-full python3-pip
    return
  fi

  echo "警告: 未检测到 apt-get，无法自动安装 python3-venv/python3-full。"
  echo "请手动安装 Python venv 相关依赖后重试。"
}

echo "[1/8] 创建目录..."
mkdir -p "$SITE_ROOT/api" "$SITE_ROOT/py" "$SITE_ROOT/tmp" "$SITE_ROOT/static/css" "$SITE_ROOT/static/js" \
  "$SITE_ROOT/lib" "$SITE_ROOT/docs" "$SITE_ROOT/data/tasks" "$SITE_ROOT/logs"

install_python_runtime

echo "[3/8] 设置权限..."
find "$SITE_ROOT" -type d -exec chmod 755 {} \;
chmod 777 "$SITE_ROOT/tmp"
chmod 755 "$SITE_ROOT/install.sh"

echo "[4/8] 初始化 Python 虚拟环境..."
python3 -m venv "$VENV_DIR"

echo "[5/8] 升级 pip..."
"$VENV_DIR/bin/pip" install --upgrade pip

echo "[6/8] 安装 Python 依赖..."
if [[ -f "$REQ_FILE" ]]; then
  "$VENV_DIR/bin/pip" install -r "$REQ_FILE"
else
  "$VENV_DIR/bin/pip" install tqdm
fi

echo "[7/8] 校验虚拟环境..."
"$VENV_DIR/bin/python3" -V
"$VENV_DIR/bin/python3" -m pip show tqdm >/dev/null 2>&1 || true

echo "[8/8] 写入 crontab 清理任务提示..."
echo "0 3 * * * find $SITE_ROOT/tmp -type f -mtime +1 -delete"

echo "安装完成。"
echo "请确认环境变量："
echo "  export CS_APP_PASSWORD='请改成强密码'"
echo "  export CS_SOURCE_ROOT='/www/wwwroot/cs/1'"
echo "  export CS_PYTHON_BIN='$VENV_DIR/bin/python3'"
