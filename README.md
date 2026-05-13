# 部署手册

## 1. 环境要求

- 宝塔面板：9.x 或以上
- Nginx：1.22 或以上
- PHP：8.1 / 8.2 / 8.3
- Python：3.9 或以上
- 站点根目录：`/www/wwwroot/cs`
- 源文件目录：`/www/wwwroot/cs/1`

## 2. 目录结构

```text
/www/wwwroot/cs
├─ index.php
├─ download.php
├─ config.php
├─ api/
│  ├─ list.php
│  └─ zip.php
├─ lib/
│  ├─ bootstrap.php
│  └─ helpers.php
├─ py/
│  └─ zipper.py
├─ static/
│  ├─ css/app.css
│  └─ js/app.js
├─ tmp/
├─ data/
│  └─ tasks/
├─ logs/
├─ docs/
└─ install.sh
```

## 3. 宝塔建站步骤

1. 在宝塔面板创建站点，站点目录设置为 `/www/wwwroot/cs`。
2. PHP 版本选择 `PHP-8.2` 或更高。
3. 上传本项目全部文件到站点目录。
4. 将待展示文件放入 `/www/wwwroot/cs/1`。
5. SSH 登录服务器后执行：

```bash
cd /www/wwwroot/cs
chmod +x install.sh
./install.sh
```

## 4. 环境变量

推荐在站点的 `php-fpm` 池配置、`/etc/profile.d/cs.sh` 或宝塔站点运行环境中设置：

```bash
export CS_APP_PASSWORD='请改成长度不少于16位的强密码'
export CS_SOURCE_ROOT='/www/wwwroot/cs/1'
export CS_PYTHON_BIN='/www/wwwroot/cs/py/venv/bin/python3'
```

设置后重载 `php-fpm` 与 `nginx`。

## 5. Nginx 伪静态

将以下内容加入站点配置的 `location /` 或伪静态规则中：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

rewrite ^/api/list$ /api/list.php last;
rewrite ^/api/zip$ /api/zip.php last;

location ^~ /tmp/ {
    types { application/zip zip; }
    add_header X-Content-Type-Options nosniff;
    add_header Cache-Control "private, max-age=3600";
}

location ~* \.(php|phtml)$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/tmp/php-cgi-82.sock;
    fastcgi_read_timeout 3600;
}

location ~ /\.(?!well-known).* {
    deny all;
}
```

说明：

- `rewrite ^/api/list$` 和 `rewrite ^/api/zip$` 对应前端使用的无扩展名接口地址。
- `fastcgi_read_timeout 3600` 用于目录打包进度流与大文件下载场景。

## 6. PHP 禁用函数调整

系统需要使用 `exec()` 启动 Python 压缩进程，请确认 `disable_functions` 中未禁用以下函数：

- `exec`
- `shell_exec`
- `proc_open`
- `proc_close`
- `popen`
- `passthru`

若当前环境有禁用项，至少要移除 `exec`；建议在宝塔 `PHP 设置 -> 禁用函数` 中按站点实际情况调整。

## 7. Python 虚拟环境

若未执行 `install.sh`，可手动执行：

```bash
cd /www/wwwroot/cs
python3 -m venv py/venv
source py/venv/bin/activate
pip install --upgrade pip
pip install tqdm
```

## 8. 文件权限

执行后应满足：

- 站点目录与代码目录：`755`
- `tmp/`：`777`
- `logs/`：`755`
- `data/`、`data/tasks/`：`755`

可执行命令：

```bash
find /www/wwwroot/cs -type d -exec chmod 755 {} \;
chmod 777 /www/wwwroot/cs/tmp
chmod 755 /www/wwwroot/cs/install.sh
```

## 9. crontab 清理任务

每日凌晨 03:00 删除 `tmp/` 中超过 24 小时的 ZIP：

```cron
0 3 * * * find /www/wwwroot/cs/tmp -type f -mtime +1 -delete
```

在宝塔计划任务中新增 `Shell 脚本` 或 `清理文件` 均可。

## 10. SSL 证书配置

1. 在宝塔站点设置中申请 Let's Encrypt 证书。
2. 开启 `强制 HTTPS`。
3. 启用 `HTTP/2`。
4. 确认下载接口与 `EventSource` 都通过同域 HTTPS 访问。

推荐额外开启：

- `TLS 1.2`
- `TLS 1.3`
- HSTS

## 11. 防火墙端口

至少开放：

- `80/tcp`
- `443/tcp`
- `22/tcp` 仅管理端使用，建议限制来源 IP

若启用宝塔安全组或云厂商防火墙，同步放行上述端口。

## 12. 宝塔截图清单

当前仓库无法直接生成宝塔面板实机截图，部署时请按以下页面各截一张，插入到本手册对应章节：

- 站点列表页：显示域名、根目录、PHP 版本
- 站点配置页：显示 Nginx 伪静态与 SSL
- PHP 设置页：显示 `disable_functions` 调整结果
- 计划任务页：显示凌晨 03:00 清理任务
- 安全页或云防火墙页：显示 `80/443` 端口放行

## 13. 上线验证

```bash
curl -I https://你的域名/
curl -I https://你的域名/api/list
curl -I "https://你的域名/download.php?path=示例文件.zip"
```

浏览器侧应验证：

- 首次访问先显示密码页
- 登录后目录自动轮询刷新
- 文件点击后浏览器直接下载
- 目录点击后进度条持续更新
- 打包完成后可下载 `tmp/` 中生成的 ZIP
