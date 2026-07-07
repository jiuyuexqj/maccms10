# 本地真实运行指南

> 目标：在你自己的机器上把 MacCMS10 完整跑起来——真实 HTTP 访问、真实播放、真实接口调用。
> 这是验证错误诊断与全维度优化的前提（本仓库的分析环境无 PHP/MySQL，只能跑测试层）。

## 方式 A：Docker 一键起全栈（推荐）

前置：安装 [Docker Desktop](https://www.docker.com/products/docker-desktop)。

```bash
cd maccms10
docker compose up -d --build      # 首次会装 gd/mysqli 扩展，约 2-5 分钟
docker compose ps                 # 确认 maccms-web / maccms-db 都是 running
```

浏览器打开：

1. **http://localhost:8080/install.php** — 首次安装向导
   - 数据库主机：`db`（docker 服务名）
   - 用户名：`root`　密码：`root`　数据库：`maccms10`
   - 表前缀：`mac_`
2. 安装完成后，**立即删除或改名 `install.php`**（见 AUDIT_REPORT P2-7）
3. **http://localhost:8080/index.php** — 前台首页
4. **http://localhost:8080/admin.php** — 后台（部署时务必改名入口，见 admin.php:36）

跑接口复现脚本（真实调用，记录 HTTP 码）：

```bash
bash tests/regression/api-smoke.sh
```

真实跑 PHPUnit（容器内）：

```bash
docker compose exec web bash -c "curl -sS https://getcomposer.org/installer | php && php composer.phar install && vendor/bin/phpunit -c tests/phpunit.xml.dist"
```

## 方式 B：本地 LAMP / WAMP / phpStudy

1. PHP **8.0–8.3**（扩展：mbstring, mysqli, pdo_mysql, gd, curl, openssl, fileinfo）
2. MySQL 5.7+
3. Web 根目录指向本项目根（admin.php/index.php/api.php 所在目录）
4. 访问 `/install.php` 安装，其余同上。

## 起来后该做什么（对应优化目标）

| 目标 | 怎么真实复现 |
|---|---|
| 接口 5xx/4xx | 跑 `tests/regression/api-smoke.sh`，看非 200 的路径 |
| 播放器卡顿/黑屏 | 后台先采集或手动添加一条带真实 m3u8/mp4 地址的视频，浏览器（+移动模拟）打开播放页，开 DevTools Network/Console |
| 跨域 | iframe 播放器 `src` 与父页同源校验；弱网用 DevTools throttling |
| 数据库慢查询 | 开 `database.debug=true`，看 SQL 日志；对慢的加索引 |
| 并发 | `ab -n 200 -c 20 http://localhost:8080/index.php` 或 wrk |

## 把结果回传给我

跑完后把以下任意内容贴回来，我据此做**真实、可验证**的修复（不靠静态推测）：

- `api-smoke.sh` 输出的非 200 行
- 浏览器 Console / Network 报错截图或文本
- `runtime/log/` 下日志
- 慢查询 / ab 压测结果
