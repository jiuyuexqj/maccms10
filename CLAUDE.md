# CLAUDE.md — MacCMS10 协作文档

> 本文档供所有协作者（含 AI 助手）在改动本项目前必读。它记录了项目的非显然机制、规范与红线。

## 1. 项目简介

苹果CMS V10（MacCMS10），基于 **ThinkPHP 5.0.24** 内核的视频/内容建站系统。模块：

- `application/admin` — 后台（约 50 个控制器）
- `application/index` — 前台
- `application/api` — 接口（供 App/前端调用）
- `application/common` — 公共模型/工具/行为
- `application/command` — 命令行任务
- `addons` — 插件
- `thinkphp/` — 框架内核（**一般不修改**；如必须改，需在 PR 中说明理由）

## 2. 环境要求

- PHP **7.4+**（建议 8.0+；项目已做 PHP8 兼容处理，详见 §4）
- MySQL 5.7+ / MariaDB 10.3+
- **生产部署不依赖 Composer**（`thinkphp/` 已打包）；Composer 仅用于本地/CI 测试

## 3. 常用命令

```bash
# 本地装测试依赖（仅开发/CI，不影响生产）
composer install

# 跑测试
vendor/bin/phpunit -c tests/phpunit.xml.dist

# 跑单个测试文件
vendor/bin/phpunit -c tests/phpunit.xml.dist tests/Unit/CommonFunctionsTest.php
```

## 4. 非显然机制（必读，否则会误判行为）

### 4.1 error_reporting 被收窄 → warning 被静默
`application/common.php:12` 设置 `error_reporting(E_ERROR | E_PARSE)`，配合 `thinkphp/library/think/Error.php:71` 的 `if (error_reporting() & $errno) throw`，使得：

- **E_WARNING / E_NOTICE 不会抛异常、不会显示**。
- 后果：未定义变量/键、`foreach(null)`、给字符串函数传 `null` 等问题**不会报错**，而是**静默产生错误数据**（典型案例：`Index.php:852` 导致仪表盘统计恒为 0）。

**编码启示**：
- 不能依赖"warning 会报错"来发现问题，**必须主动判空、判键、判类型**。
- 数组取值前用 `isset()` / `??`；`explode()` / `Db::find()` 结果用前先判空。
- 测试环境下 `tests/bootstrap.php` 会把 `error_reporting` 恢复为 `E_ALL`，让静默问题在测试中暴露为失败。

### 4.2 统一安全 behavior（`application/tags.php`）
以下行为在请求生命周期内全局生效，**新增接口自动受保护**：

| 钩子 | 行为 | 作用 |
|---|---|---|
| `app_init` | `SessionSameSite` | 应用 PHP7.3+ SameSite cookie 参数 |
| `app_init` | `RequestSecurity` | 请求层安全检查 |
| `app_begin` | `CsrfGuard` | 后台 POST 校验 `__token__`/`X-CSRF-Token`（需 `security_csrf_admin=1`） |
| `app_begin` | `AntiScrape` | 防采集 |
| `app_end` | `SecurityHeaders` | 输出安全响应头 |
| `app_end` | `AdminAudit` | 管理员操作审计 |

### 4.3 鉴权链
- **后台**：`admin/controller/Base.php` 构造 → `model/Admin.php::checkLogin()`（基于 **session**）→ `check_auth()` 按 `admin_auth` 列表校验权限。
- **前台/API 用户**：`model/User.php::checkLogin()` —— **JWT 优先**（`Authorization: Bearer`，`hash_equals` 校验 `user_random`），否则回落到 cookie（`user_id`/`user_name`/`user_check`，`md5(user_random+user_name+user_id)` 校验）。
- 新增涉及用户隐私的 API 接口，必须调用 `checkLogin()` 或在控制器内校验 `$GLOBALS['user']['user_id'] > 0`。

## 5. 编码规范

- 遵循项目现有风格（ThinkPHP5 风格：`var` 属性、`input()` 取参、`model()` 助手）。
- **用户输入一律过滤**：`mac_filter_xss()`、`htmlspecialchars()`、`intval()`。
- **SQL**：
  - 参数化优先：`Db::query('SELECT … WHERE id=?', [$id])` 或模型 `where('字段','eq',$id)`。
  - **禁止**把用户输入字符串拼进 SQL。表名/字段名必须走白名单（参考 `Database.php` 的 `isValidTable()`/`isValidField()`、`DataReplace.php` 的 `$allowed_tables`）。
  - `order()`/`orderRaw()` 的字段名必须白名单，不可直接接用户输入。
- **数组操作前判空**（因 warning 被静默，否则静默 bug）。
- **多字节字符串**用 `mb_*` 系列，不要用 `substr`/`strlen` 处理中文。

## 6. 安全规范（红线）

- **鉴权**：后台方法默认需登录；公开方法必须在 `Base::__construct` 显式放行。API 敏感接口必须校验登录态。
- **SQL 注入**：参数化 + 白名单（见 §5）。
- **XSS**：模板输出用 `{$var}`（自动转义），**不要**用 `{:var}`（不转义）；控制器回显用户输入必须 `htmlspecialchars`。
- **文件上传**：扩展名白名单 + MIME 校验 + 随机文件名 + 存储目录不可执行。
- **CSRF**：后台 POST 依赖 `CsrfGuard`，确保 `security_csrf_admin=1`；不便带表单的端点用 `X-CSRF-Token` 头。
- **不引入** `eval`/`assert`/`create_function`/`unserialize(用户输入)`。

## 7. ⚠️ 测试要求（硬性，强制）

> **任何代码改动（新增功能、修复 bug、重构、安全加固）必须同时补充或更新测试用例，否则不予评审合并。无测试的 PR 直接打回。**

### 7.1 测试位置与结构
```
tests/
├── README.md                 # 测试规范与运行说明
├── phpunit.xml.dist          # PHPUnit 配置
├── bootstrap.php             # 测试引导（加载框架常量+公共函数，恢复 E_ALL）
└── Unit/                     # 单元测试（纯逻辑，不连库）
    ├── CommonFunctionsTest.php
    ├── IndexDashboardDataTest.php
    ├── Auth/
    │   └── LoginTokenTest.php
    ├── Security/
    │   ├── CsrfGuardConfigTest.php
    │   ├── OrderInjectionTest.php
    │   └── DatabaseSqlGuardTest.php
    └── Config/
        └── CookieSecurityTest.php
```

### 7.2 命名约定
- 文件：`{被测类/主题}Test.php`，放在与源码对应的子目录。
- 方法：`test_{场景}_{预期}`，例如 `test_filter_xss_strips_script_tag`。

### 7.3 覆盖要求
- **修 bug**：必须有一个能复现原 bug 的测试（先红后绿）。
- **新功能**：核心逻辑路径 + 边界（空、超长、非法字符）至少各一例。
- **安全修复**：构造攻击 payload，断言被拒。

### 7.4 运行
```bash
composer install                              # 首次
vendor/bin/phpunit -c tests/phpunit.xml.dist  # 全量
```
提交前本地必须全绿。CI 同样执行该命令。

### 7.5 提交门禁（强制：每个 commit 必须带 UT + 全绿才能提交）

**规则：**

1. **每个 commit 都必须包含相关的单元测试**——不允许「先提交代码、后补测试」。
   - 修 bug 的 commit：含能复现该 bug 的测试（先红后绿）。
   - 新功能 / 重构 / 安全加固的 commit：含对应覆盖（见 7.3）。
   - 纯文档 / 注释 / 配置默认值改动除外。
2. **提交前必须跑完全部 UT 且全绿**——不只是跑与本次改动相关的测试，而是整套 `tests/`。
3. 测试失败时修复代码或测试后再提交；**不得用「暂时跳过」绕过**。

**强制执行（已内置工具）：** 仓库提供 `.githooks/pre-commit`，每位开发者本地执行一次启用：

```bash
git config core.hooksPath .githooks
# Unix 系追加：chmod +x .githooks/pre-commit
```

或一键配置（启用 hook + 校验 PHP + 安装测试依赖）：

```bash
bash scripts/dev-setup.sh
```

此后每次 `git commit` 自动运行全部 PHPUnit，失败即阻止提交；CI 以同一命令作为门禁。

## 8. 已知问题与修复优先级

见 [`docs/AUDIT_REPORT.md`](docs/AUDIT_REPORT.md)。摘要：

- **P1**：`Index.php:852` 统计 bug、cookie HttpOnly 未开、CSRF 开关确认。
- **P2**：md5 鉴权 token、日志驱动 `test`、数据库默认弱口令、`orderRaw` 来源、`PATH_INFO` 判空等。

修复时严格遵循 §7 的测试要求。

## 9. 常见陷阱（踩坑记录）

1. **改了代码没反应**：检查 `runtime/cache/` 与 `runtime/temp/`，必要时后台「清除缓存」或删 `runtime/`。
2. **"为什么不报错却结果不对"**：多半是 §4.1 的静默 warning。临时在入口把 `error_reporting(E_ALL)` 打开排查。
3. **模板变量没转义**：用 `{$var}` 而非 `{:var}`。
4. **PHP8 下白屏**：先确认是否触发了真正的 `E_ERROR`（如调未定义函数），而非 warning（warning 已被吞）。
5. **后台改名提示**：`admin.php` 必须改名部署（`admin.php:36` 会拦截默认名）。

## 10. Git 与提交

**提交前检查清单（必须全部满足，详见 §7.5）：**

- [ ] 本次改动已补充 / 更新对应单元测试
- [ ] 测试文件与代码**在同一个 commit** 中提交（不允许拆到后续 commit「后补」）
- [ ] 本地 `vendor/bin/phpunit -c tests/phpunit.xml.dist` **整套全绿**
- [ ] pre-commit hook 已启用并通过（`.githooks/pre-commit`，启用方式见 §7.5）
- [ ] 一个 commit 只做一件事；安全修复与功能改动分开提交

**提交信息规范：**

- 格式：`<类型>: <概述>`，类型用 `fix` / `feat` / `security` / `refactor` / `test` / `docs`。
  示例：`security(P2-6): orderRaw 参数白名单净化 + 注入测试`。
- 中文描述为主，可带必要英文关键词。
- PR 描述须包含：改了什么、为什么、**新增/更新了哪些测试**、如何本地验证。

**跳过门禁（仅限紧急）：** `git commit --no-verify` 可绕过 hook，但必须在同 PR 的 CI 补跑全绿；**不得作为常态**。
