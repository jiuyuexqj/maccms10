<?php
/**
 * PHP 运行时兼容性扫描（真实 require 全部业务文件，捕获 DEPRECATED/WARNING/NOTICE）。
 *
 * 用法：  php tests/regression/scan-compat.php
 * 依赖：  已安装 PHP（>=8.0），无需数据库。
 *
 * 说明：  纯静态读代码无法系统性发现这些（如 ${var} 内插、可选参数顺序、
 *         JsonSerializable/ArrayAccess 返回类型），必须真实 require 才触发。
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__, 2)) . '/');
define('APP_PATH',  ROOT_PATH . 'application/');
define('THINK_PATH', ROOT_PATH . 'thinkphp/');
define('MAC_PATH',   ROOT_PATH);

require THINK_PATH . 'base.php';
require THINK_PATH . 'helper.php';   // lang()/config()/model() 等（App 启动时才自动加载，CLI 手动补）
require APP_PATH . 'common.php';

error_reporting(E_ALL);

$issues = [];
set_error_handler(function ($no, $str, $file, $line) use (&$issues) {
    static $map = [
        E_DEPRECATED => 'DEPRECATED', E_USER_DEPRECATED => 'DEPRECATED',
        E_NOTICE => 'NOTICE', E_USER_NOTICE => 'NOTICE',
        E_WARNING => 'WARNING', E_USER_WARNING => 'WARNING',
    ];
    $t = $map[$no] ?? 'OTHER';
    if (strpos($file, 'vendor') !== false) return true;   // 忽略 vendor
    $issues[] = "$t|" . str_replace(ROOT_PATH, '', $file) . ":$line|$str";
    return true;
});
register_shutdown_function(function () use (&$issues) {
    $e = error_get_last();
    if ($e && ($e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        $issues[] = "FATAL|" . str_replace(ROOT_PATH, '', $e['file']) . ":{$e['line']}|{$e['message']}";
    }
    $byType = [];
    foreach ($issues as $i) $byType[explode('|', $i, 2)[0]] = ($byType[explode('|', $i, 2)[0]] ?? 0) + 1;
    echo "\n=== 汇总 ===\n"; print_r($byType);
    echo "\n=== 去重后样本（最多 40 条）===\n";
    foreach (array_slice(array_unique($issues), 0, 40) as $i) echo "  $i\n";
    echo "\n总计 " . count($issues) . " 条，去重 " . count(array_unique($issues)) . " 条。\n";
});

$files = [];
foreach (['application/admin', 'application/index', 'application/api', 'application/common', 'application/command', 'addons'] as $d) {
    if (!is_dir(ROOT_PATH . $d)) continue;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH . $d, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (strtolower($f->getExtension()) === 'php') $files[] = $f->getRealPath();
    }
}
echo "待扫描 " . count(array_unique($files)) . " 个 PHP 文件...\n";
foreach (array_unique($files) as $path) @include_once $path;
echo "加载完成。\n";
