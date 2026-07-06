<?php
/**
 * PHPUnit 测试引导。
 *
 * 目标：在不连接数据库、不进入 Web 请求生命周期的情况下，加载框架常量、
 * 自动装载与公共函数，使纯逻辑（工具函数、鉴权 token 校验、SQL 守卫等）可被单测。
 *
 * 关键：测试环境在最后把 error_reporting 恢复为 E_ALL，让 application/common.php:12
 * 收窄后本会被静默的 warning/notice 在测试中重新抛出 → 暴露为测试失败。
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . 'application/');
}
if (!defined('THINK_PATH')) {
    define('THINK_PATH', ROOT_PATH . 'thinkphp/');
}
if (!defined('MAC_PATH')) {
    define('MAC_PATH', ROOT_PATH);
}

// 1) 加载框架：注册 autoload + Error handler（内部会设 error_reporting(E_ALL)）
require THINK_PATH . 'base.php';

// 2) 加载应用公共函数（application/common.php 顶部会把 error_reporting 收窄为 E_ERROR|E_PARSE）
require APP_PATH . 'common.php';

// 3) 测试环境恢复 E_ALL —— 让静默问题暴露为失败（这是测试存在的核心价值之一）
error_reporting(E_ALL);
ini_set('display_errors', '1');
