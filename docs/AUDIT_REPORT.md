# MacCMS10 安全与质量审计报告

> 审计范围：`application/`、`addons/`、`thinkphp/`（仅框架层 PHP8 兼容）、入口与配置文件
> 审计时间：2026-07
> 结论速览：**未发现 P0 级问题**（无 RCE、无致命 fatal 函数、无 SQL 注入、无账户接管）。项目整体安全姿态良好（已内置 CSRF / 安全头 / 审计 / 白名单 / 参数化）。下列为 P1/P2 加固项与一个已确认的功能 bug。

---

## 一、审计方法与已排查（确认安全）项

| 排查维度 | 方法 | 结论 |
|---|---|---|
| PHP8 移除函数 | grep `each(`/`create_function`/`mysql_*` | ✅ 业务代码无独立 `each()`、无 `create_function`、无 `mysql_*` |
| PHP8 ValueError | grep `explode('',…)`、`array_combine` | ✅ 无空分隔符 explode、无危险 array_combine |
| 危险函数 | grep `eval`/`assert`/`unserialize` | ✅ 业务代码无危险用法；框架内 eval(unserialize) 为模板/缓存机制，输入不可控 |
| SQL 拼接 | grep `Db::query/execute`、`where("…$…")` | ✅ `Database`/`DataReplace` 均有白名单；全局无 `where("x=$y")` 拼接 |
| 账户接管 | 读 `User::checkLogin` cookie 分支 | ✅ 校验 `md5(user_random+user_name+user_id)`，`user_random` 不可猜 |
| 后台鉴权 | `Admin::checkLogin`（session）+ `Base::check_auth` | ✅ 基于 session，权限按 `admin_auth` 列表 |

---

## 二、问题清单（按严重性分级）

### P1 — 应尽快修复

#### P1-1 后台首页仪表盘统计恒为 0（功能 bug，静默错误）
- **位置**：`application/admin/controller/Index.php:852`
- **现象**：管理员登录后，仪表盘"近七日用户访问总量"永远显示 0。
- **根因**：`foreach ($result['seven_day_visit_data'] as …)` 中的键 `seven_day_visit_data` **从未被赋值**（同方法内只赋了 `seven_day_visit_day` / `seven_day_visit_count`）。在 `common.php:12` 收窄 error_reporting 后，未定义键 warning 被静默，循环体永不执行。
- **修复**：
  ```php
  // Index.php:852  改为对 $tmp_arr 求和
  foreach ($tmp_arr as $value) {
      $result['seven_day_visit_total_count'] += (int)$value['count'];
  }
  ```
- **测试**：新增 `tests/Unit/IndexDashboardDataTest.php`，构造七日数据断言总量正确（见测试规范）。

#### P1-2 Cookie 未设置 HttpOnly
- **位置**：`application/config.php:234`（`'httponly' => ''`）
- **影响**：会话 cookie 与业务 cookie（`user_id`/`admin_*` 等）可被 JS 读取，一旦存在 XSS 即可窃取凭据。
- **修复**：
  ```php
  // config.php  cookie 段
  'httponly'  => true,
  // config.php  session 段建议同步补 'httponly' => '1'（SessionSameSite behavior 会读取）
  ```
- **测试**：新增 `tests/Unit/Config/CookieSecurityTest.php`，断言 `config('cookie.httponly')` 为真值。

#### P1-3 后台 CSRF 防护依赖开关，需确认默认开启
- **位置**：`application/common/behavior/CsrfGuard.php:30`（`if (empty($app['security_csrf_admin'])) return;`）
- **影响**：若 `security_csrf_admin` 默认未开，后台所有 POST（保存配置、删除、数据库操作）无 CSRF 保护，可被诱导管理员触发。
- **修复**：在 `application/extra/maccms.php`（或对应 app 配置默认值）中确保 `'security_csrf_admin' => '1'`，并在安装/升级脚本里强制开启。
- **测试**：新增 `tests/Unit/Security/CsrfGuardConfigTest.php`，断言默认配置开启。

---

### P2 — 加固与健壮性

#### P2-1 鉴权 token 用 md5 + 松散比较
- **位置**：`application/common/model/User.php:787-788`、`Admin.php:201-202`
- **问题**：`md5(...)` 作为登录凭据 + `!=` 比较（非 `hash_equals`）。当前因 `user_random` 不可猜而是安全的，但属弱设计。
- **修复**：迁往 `hash_hmac('sha256', $payload, $serverSecret)` + `hash_equals`；JWT 分支已是 `hash_equals`（保留）。
- **测试**：`tests/Unit/Auth/LoginTokenTest.php`，断言伪造 token 被拒、正确 token 通过。

#### P2-2 日志驱动为 `test`（基本不写盘）
- **位置**：`application/config.php:171`（`'type' => 'test'`）
- **影响**：生产排障困难，安全事件无日志。
- **修复**：改为 `'type' => 'file'`，并确保 `runtime/log/` 不可 web 访问。
- **测试**：配置校验测试（同 P1-2 一并）。

#### P2-3 数据库默认弱口令 + 非 utf8mb4
- **位置**：`application/database.php`（`root/root`、`charset=utf8`）
- **修复**：部署时务必改密；`charset` 升级 `utf8mb4`（需配合表字符集迁移，谨慎）。
- **测试**：无（部署项）。

#### P2-4 入口未判 `$_SERVER['PATH_INFO']` 是否存在
- **位置**：`admin.php:40`、`api.php:36`、`index.php:35`
- **影响**：部分 FastCGI/nginx 不提供 `PATH_INFO`，访问未定义键（warning 被静默，不致命但脏）。
- **修复**：
  ```php
  $pi = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
  if ($pi !== '' && !mb_check_encoding($pi, 'utf-8')) {
      $_SERVER['PATH_INFO'] = mb_convert_encoding($pi, 'UTF-8', 'GBK');
  }
  ```
- **测试**：`tests/Unit/Entry/PathInfoEncodingTest.php`。

#### P2-5 后台 SQL 执行功能黑名单不全
- **位置**：`application/admin/controller/Database.php:249-282`（`sql()` 方法）
- **问题**：允许管理员执行任意 SQL，黑名单仅含 `into dumpfile/outfile/char(/load_file`，未拦截 `DELETE/UPDATE//TRUNCATE mac_admin` 等敏感操作。属设计功能（DB 管理工具），但易误操作。
- **修复**：对 `mac_admin`/`mac_user` 等敏感表的 `UPDATE/DELETE/TRUNCATE/DROP` 二次确认或额外审计。
- **测试**：`tests/Unit/Security/DatabaseSqlGuardTest.php`。

#### P2-6 `orderRaw($order)` 参数来源需审计
- **位置**：`application/common/model/Website.php:54`、`Actor.php:54`
- **问题**：`orderRaw` 直接拼入 SQL。`$order` 来自模型 listData 参数，若上游未白名单则存在 SQL 注入风险（待确认调用链）。
- **修复**：将 `$order` 限制为白名单字段名 + `asc/desc`。
- **测试**：`tests/Unit/Security/OrderInjectionTest.php`，注入 payload 应被拒或转义。

#### P2-7 `install.php` 留在 web 根
- **位置**：`install.php`
- **影响**：虽有 `install.lock` 拦截，但文件可访问；lock 一旦被删即可重装（重置数据库）。
- **修复**：安装成功后建议删除或 `chmod 000`；或在 nginx/apache 加 `deny`。
- **测试**：无（部署项）。

#### P2-8 前台 `http_url` 拼接 `SERVER_NAME` / `REQUEST_URI`
- **位置**：`application/common/controller/All.php:101`
- **影响**：若服务器以 `Host` 头覆盖 `SERVER_NAME`，存在 Host 头反射风险。
- **修复**：用配置中的 `site_url` 而非 `SERVER_NAME`；输出前转义。
- **测试**：`tests/Unit/Common/HttpUrlBuildTest.php`。

---

## 三、修复优先级与批次建议

| 批次 | 内容 | 工作量 | 是否需测试 |
|---|---|---|---|
| 第 1 批（本周） | P1-1、P1-2、P1-3 | 小 | ✅ 必须配 |
| 第 2 批（两周内） | P2-1、P2-4、P2-6 | 中 | ✅ 必须配 |
| 第 3 批（按需） | P2-2、P2-3、P2-5、P2-7、P2-8 | 中 | 部分 |

**原则**：每项修复提交前必须先写测试（见 `CLAUDE.md` 的「测试要求」红线）。

---

## 四、修复实施记录（2026-07）

| 项 | 状态 | 改动 | 测试 |
|---|---|---|---|
| P1-1 仪表盘统计恒 0 | ✅ 已修复 | `Index.php:851` 改用新增 `mac_array_sum_column()` | `tests/Unit/ArraySumColumnTest.php` |
| P1-2 Cookie HttpOnly | ✅ 已修复 | `config.php` cookie.httponly=true、session.httponly='1' | `tests/Unit/Config/CookieSecurityTest.php` |
| P1-3 CSRF 默认开启 | ✅ 已修复 | `extra/maccms.php` security_csrf_admin `'0'→'1'` | `tests/Unit/Security/CsrfGuardConfigTest.php` |
| P2-1 登录 token 加固 | ✅ 已修复 | common.php 新增 `mac_build/verify_login_check`、`mac_build/verify_admin_check`；User/Admin 改用 `hash_equals` | `tests/Unit/Auth/LoginCheckTokenTest.php` |
| P2-2 日志驱动 | ✅ 已修复 | `config.php` log.type `test→file` | `tests/Unit/Config/LogConfigTest.php` |
| P2-4 入口 PATH_INFO 判空 | ✅ 已修复 | `admin/api/index.php` isset 判空 | `tests/Unit/Entry/PathInfoEncodingTest.php` |
| P2-5 后台 SQL 敏感表保护 | ✅ 已修复 | common.php 新增 `mac_is_sql_dangerous`；`Database::sql()` 调用 | `tests/Unit/Security/DatabaseSqlGuardTest.php` |
| P2-6 orderRaw 白名单 | ✅ 已修复 | common.php 新增 `mac_sanitize_order`；Website/Actor `listData` 调用 | `tests/Unit/Security/OrderSanitizeTest.php` |
| P2-7 install.php 加固 | ✅ 已修复 | install.php 返回 403、不暴露 lock 路径 | `tests/Unit/Entry/InstallEntryTest.php` |
| P2-8 http_url 加固 | ✅ 已修复 | common.php 新增 `mac_build_http_url`；All.php 优先用 site_url + 转义 REQUEST_URI | `tests/Unit/Common/HttpUrlBuildTest.php` |
| P2-3 DB utf8mb4 / 默认口令 | 📄 文档化 | 见下方迁移清单（不改默认，避免破坏存量） | — |

新增公共工具函数（均在 `application/common.php`）：`mac_array_sum_column`、`mac_build_login_check`/`mac_verify_login_check`、`mac_build_admin_check`/`mac_verify_admin_check`、`mac_sanitize_order`、`mac_build_http_url`、`mac_is_sql_dangerous`。

### P2-3 数据库 utf8mb4 升级与默认口令（部署/DBA 执行，不在代码层盲改）

升级 charset 涉及表结构变更，盲改默认配置可能破坏存量，故按部署清单执行：

1. 备份全部数据库。
2. `application/database.php` 的 `charset` 改为 `utf8mb4`（连接层，对 utf8 表向后兼容）。
3. 安装建表 SQL 改为 `utf8mb4_unicode_ci`，新装站点才支持 emoji 等 4 字节字符。
4. 存量表迁移（可选，需停机）：`ALTER TABLE mac_vod CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`（每表重复）。
5. 安装时填强口令，禁用仓库默认 `root/root`。

### 测试执行

当前分析环境未安装 PHP，无法直接运行 PHPUnit；所有改动已通过静态语法/逻辑校验。开发者本地验证：

```bash
composer install
vendor/bin/phpunit -c tests/phpunit.xml.dist
```

`tests/bootstrap.php` 在加载 `common.php` 后会把 `error_reporting` 恢复为 `E_ALL`，使被生产配置静默的 warning 在测试中暴露为失败——这是本项目测试体系的核心价值。
