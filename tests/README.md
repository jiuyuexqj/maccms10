# 测试规范

> 配合 [`../CLAUDE.md` §7](../CLAUDE.md#7--测试要求硬性强制) 阅读本项目测试要求。

## 红线

**任何代码改动（新功能 / 修 bug / 重构 / 安全加固）必须同时补充或更新测试，否则不予合并。**

## 快速开始

```bash
# 首次：安装测试依赖（仅本地/CI，不影响生产部署）
composer install

# 跑全部测试
vendor/bin/phpunit -c tests/phpunit.xml.dist

# 跑单个文件
vendor/bin/phpunit -c tests/phpunit.xml.dist tests/Unit/CommonFunctionsTest.php

# 跑单个方法
vendor/bin/phpunit -c tests/phpunit.xml.dist --filter test_mac_filter_xss
```

提交前本地必须全绿。

## 目录结构

```
tests/
├── README.md             本文件
├── phpunit.xml.dist      PHPUnit 配置
├── bootstrap.php         测试引导（加载框架常量+公共函数，恢复 E_ALL）
└── Unit/                 单元测试（纯逻辑，不连库）
    ├── CommonFunctionsTest.php   ← 已有样例，可运行
    ├── IndexDashboardDataTest.php
    ├── Auth/…
    ├── Security/…
    └── Config/…
```

测试命名空间 `MaccmsTest\`（见 `composer.json` 的 `autoload-dev`）。

## 设计要点

1. **bootstrap.php 不连数据库**：只加载框架常量、autoloader 与 `application/common.php` 的工具函数。可测对象 = 纯函数 / 可 mock 的逻辑。
2. **error_reporting 恢复 E_ALL**：`application/common.php:12` 在生产把级别收窄为 `E_ERROR|E_PARSE` 以避免老代码 warning 白屏；测试环境重新放开，**让静默 warning 暴露为失败**——这是本项目测试的核心价值（专治 `Index.php:852` 那类静默 bug）。
3. 需要数据库/请求上下文的集成测试，后续可在 `tests/Feature/` 下扩展（用 SQLite 内存库或独立测试库），暂不强制。

## 命名约定

- 文件：`{被测主题}Test.php`，放对应子目录（`Auth/`、`Security/`、`Config/`）。
- 方法：`test_{场景}_{预期}`，全小写下划线，例如 `test_filter_xss_strips_script_tag`、`test_login_rejects_forged_check`。

## 各类改动应加的测试

| 改动类型 | 必须的测试 |
|---|---|
| 修 bug | 一个能复现原 bug 的用例（先红后绿） |
| 新功能 | 主路径 + 边界（空、超长、非法字符、并发安全相关） |
| 安全加固 | 构造攻击 payload，断言被拒（注入、越权、XSS、CSRF） |
| 重构 | 行为不变的回归测试 |

## 范例：为 `Index.php:852` 统计 bug 修复加测试

被测逻辑（修复后应纯函数化或可单独调用）：

```php
// tests/Unit/IndexDashboardDataTest.php
<?php
namespace MaccmsTest\Unit;
use PHPUnit\Framework\TestCase;

class IndexDashboardDataTest extends TestCase
{
    /**
     * 复现 P1-1：七日访问总量不应恒为 0。
     * 触发条件：当 Db::query 返回多条记录时，总量 = 各天 count 之和。
     */
    public function test_seven_day_visit_total_sums_each_day()
    {
        // 修复前：foreach ($result['seven_day_visit_data'])  → 键不存在 → 总量 0
        // 修复后：foreach ($tmp_arr) → 正确求和
        $tmp_arr = [
            ['days' => '2026-07-01', 'count' => 10],
            ['days' => '2026-07-02', 'count' => 20],
            ['days' => '2026-07-03', 'count' => 5],
        ];
        $total = 0;
        foreach ($tmp_arr as $value) {           // ← 修复后的写法
            $total += (int)$value['count'];
        }
        $this->assertSame(35, $total);           // 修复前会失败（得到 0）
    }
}
```

> 当 `Index::getAdminDashboardData()` 的求和逻辑被提取为可单测的纯函数后，
> 把上例的本地循环替换为对该函数的调用即可。
