<?php
/**
 * 管理/定时(cron)接口鉴权单测。
 *
 * 背景：api/timming/index、api/analytics/aggregate 旧实现无鉴权，公网 GET 即可强制执行
 * 管理员定时任务。修复：mac_require_cron_auth() 仅放行本机(REMOTE_ADDR 不可伪造)或携带
 * 正确 api_key 的请求。本机(cron)路径由真实 HTTP 验证（仍可执行）；远程拒绝路径在此单测覆盖。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class CronAuthTest extends TestCase
{
    private $savedAddr;
    private $savedKey;
    private $savedReq;

    protected function setUp(): void
    {
        $this->savedAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->savedKey  = $GLOBALS['config']['app']['api_key'] ?? null;
        $this->savedReq  = $_REQUEST['key'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->savedAddr === null) unset($_SERVER['REMOTE_ADDR']); else $_SERVER['REMOTE_ADDR'] = $this->savedAddr;
        if ($this->savedKey === null) unset($GLOBALS['config']['app']['api_key']); else $GLOBALS['config']['app']['api_key'] = $this->savedKey;
        if ($this->savedReq === null) unset($_REQUEST['key']); else $_REQUEST['key'] = $this->savedReq;
    }

    public function test_localhost_allowed()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertTrue(mac_require_cron_auth());
        $_SERVER['REMOTE_ADDR'] = '::1';
        $this->assertTrue(mac_require_cron_auth());
    }

    public function test_remote_denied_when_no_api_key_configured()
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        unset($GLOBALS['config']['app']['api_key']);
        $this->assertFalse(mac_require_cron_auth(), '未配置 api_key 时远程必须拒绝');
    }

    public function test_remote_allowed_with_correct_key()
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $GLOBALS['config']['app']['api_key'] = 'topsecret';
        $_REQUEST['key'] = 'topsecret';
        $this->assertTrue(mac_require_cron_auth());
    }

    public function test_remote_denied_with_wrong_or_missing_key()
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $GLOBALS['config']['app']['api_key'] = 'topsecret';
        $_REQUEST['key'] = 'wrong';
        $this->assertFalse(mac_require_cron_auth());
        unset($_REQUEST['key']);
        $this->assertFalse(mac_require_cron_auth(), '远程不带 key 必须拒绝');
    }
}
