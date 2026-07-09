# MacCMS10 安全审计与处置清单

> 由真实运行环境（PHP 8.3 + MariaDB）+ 深度安全审计产生。每条注明【已修复】或【需运维/配置处置】。
> 代码层可修的已全部修复并随仓库提交；标注【需处置】的需在部署/运维侧落实（代码无法替代）。

## 一、已修复（随本仓库提交）

| 类别 | 问题 | 处置 |
|---|---|---|
| 严重-未授权操作 | Timming/Analytics 公网 GET 强制执行管理定时任务（采集/生成/分析/写 config） | 加 `mac_require_cron_auth()`：仅本机或 `app.api_key` |
| 高-越权/付费墙绕过 | Provide 采集接口 `ac=detail` 下发 `vod_pwd`/`vod_pwd_play` 等点播密码 | `vod_json` 剥离 6 个密码字段 |
| 高-开放重定向 | `User::visit` 比对伪造 Host 头 → 钓鱼中转 | 仅允许相对路径 |
| 高-SQL注入 | `Make` 的 `tab` 直拼字段名进 SQL（含 Db::raw） | tab 白名单，非法回落 vod |
| 中-明文密码回退 | 登录 `OR user_pwd=原文` 使明文凭证存活 | 移除回退，仅 md5 校验 |
| 中-会话固定 | 登录不换发 session ID | Admin 登录 `session_regenerate_id(true)` |
| 中-弱鉴权密钥 | `user_random`/`admin_random` 用 `md5(rand(8位))`≈26bit | 改 `bin2hex(random_bytes(16))`（128bit CSPRNG） |
| 中-SSRF | `mac_curl_get/post` 无协议限制，可 `file://`/`gopher://` | `CURLOPT_PROTOCOLS=HTTP\|HTTPS`（含重定向） |
| 中-资损并发 | 扣积分 check-then-write 双花；播放量/评分 read-modify-write 丢更新 | 全部改原子 UPDATE + 行锁 |
| 高-隐藏泄露 | Vod/Art/Topic get_detail/get_list、get_class/area/year 泄露下架内容与播放地址 | 加 status=1 + recycle 过滤 |
| 中-PII/枚举 | User get_list 公开枚举 + 下发 phone；可按 phone/qq/email 检索 | 去 phone、移除 PII 检索、orderby 白名单 |
| 高-播放器供应链 | 每个播放页强制加载第三方 `union.maccms.la` iframe + 全局吞 JS 错误 | 改本地资源 + 移除 `window.onerror` 抑制 |
| — | 未知 API 方法返回 500；集数越界黑屏；IP 入库类型错误（严格模式 500）；资源双斜杠；Lottie .json 当图片；登录 CSRF；分类页/搜索空白；RSS 占位域名；<title> 缺失 | 见各提交 |
| 部署加固 | `.git`/`*.sql`/`*.md`/`composer.*`/runtime/vendor/docs 可被公网抓取 | 根 `.htaccess` deny（Apache） |
| 部署加固 | `upload/` 无脚本执行守卫 | `upload/.htaccess`：`php_flag engine off` + 拒绝脚本后缀 |
| PHP8 兼容 | `Dir` 用 `create_function`（PHP8 已移除）+ 静态方法误用 `$this` | 闭包 + 实例方法 |

## 二、需运维/配置处置（代码无法替代，请按项落实）

### 🔴 必须
1. **DB 默认口令 `root/root`**（`application/database.php`、`docker-compose.yml`）：生产改成强随机口令。
2. **`.git` 入 webroot**：最稳妥是把应用代码移出文档根，仅 `index.php/admin.php/api.php` + `static/template/upload` 在 webroot；或在 nginx/Apache 加 `location ~ /\.git deny`（`.htaccess` 仅 Apache+AllowOverride 生效，docker `AllowOverride None` 不生效）。
3. **`install.php` 部署后删除/改名**（虽 install.lock 后返 403，仍建议物理移除）；`admin.php` 必须改名（`admin.php:36` 拦默认名）。
4. **nginx 部署**：补与根 `.htaccess` 等价的 `location` deny（`.git`、`\.(sql|md|bak|lock|log)$`、`/(runtime|vendor|docs|application|thinkphp)/`），并 `location ^~ /upload/ { location ~ \.php$ { deny all; } }`。
5. **密码哈希升级**：当前 `md5(明文)`（无盐、GPU 10^11/s）。建议迁移到 `password_hash`（bcrypt/argon2）：登录时 `password_verify`，对仍为 32 位 md5 的行用“md5 兼容校验通过后立即 `password_hash` 重新落库”平滑升级。（属大改，需回归测试，未在本次代码改动内。）

### 🟡 建议（管理面/中等）
6. **SSRF 深度加固**：本次仅限协议。`http://169.254.169.254` 等元数据/私网 IP 仍可被管理面采集/回链触达。建议把 `TemplateCloudService::validateRemoteUrl` 的强过滤（DNS 解析后比对 `FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE`、禁 `169.254.*`、`CURLOPT_FOLLOWLOCATION=0` + 校验 `CURLINFO_EFFECTIVE_URL`）下沉到 `mac_curl_get` 与 `Collect::checkCjUrl`。
7. **密码重置/绑定码可重放**（`mac_get_rndstr(6,'num')` 用 `mt_rand`，5 分钟窗口内可重放、无尝试次数上限）：改 CSPRNG + 校验成功后立即失效 + 每账号尝试上限。
8. **邮件模板存储型 eval**（`System::test_email`、用户验证邮件经 `View::display` → `eval`）：管理员/CSRF 可存 `<?php system(..)?>` 触发 RCE。建议邮件模板禁用 PHP 模板驱动，或对存储内容做 `token_get_all` 白名单。
9. **插件 zip 解压路径穿越 / Zip-Slip**（`Addon` 经 `karsonzhang/fastadmin-addons Service::unzip`，且下载 `CURLOPT_SSL_VERIFYPEER=false`）：加 `../` 校验（对照 `uninstall()` 已有逻辑）+ Zip-Slip 防护（对照 `TemplateCloudService`）+ 插件下载强制 HTTPS 校验。
10. **`datafilter` 二阶 SQL**（`Provide` 把管理配置的 `api[vod][datafilter]` 原样拼进 `_string`，匿名触发）：若启用该采集过滤，限定为预定义白名单条件而非裸 SQL。
11. **`crossdomain.xml` 通配**、`X-Powered-By: PHP/8.3.32`、`show_error_msg=true`、`*.bak`（`playerconfig.js.bak`）、S3 异常回显（`AwsException`）：生产关闭/清理。

### ⚪ 信息
- `mac_get_client_ip()` 信任 `X-Forwarded-For`/`Client-IP`/`CF-Connecting-IP`（已有 `FILTER_FLAG_NO_RES_RANGE`，仍可用于绕 IP 限频；部署侧可信代理白名单更稳）。
- 登录不绑定客户端 IP（`user_check` 无 IP）——配合第 5 项强哈希后风险可控。

## 三、已核实干净（否定结论）
命令注入（`shell_exec` 均硬编码；`OpenccConverter` 正确 `escapeshellarg`）、原生 `unserialize` RCE（仅框架内部、无 POP 链、phar 路径均 `./` 前缀）、公共搜索 SQLi（`wd` 均 `like` 绑定、orderby 白名单）、`Database/DataReplace`（`isValidTable/Field`+参数化）、addon 公开端点（404/需 admin+CSRF）、JWT（HMAC 固定、`hash_equals`、`exp` 校验、默认关、32+ 密钥）、前台文件写入/LFI（`Label`/`Annex` 拒斜杠）。
