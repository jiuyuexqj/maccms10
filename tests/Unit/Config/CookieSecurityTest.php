<?php
/**
 * P1-2 回归测试：cookie / session 必须 HttpOnly。
 *
 * 防止 XSS 通过 document.cookie 读取会话与凭据 cookie。
 */

namespace MaccmsTest\Unit\Config;

use PHPUnit\Framework\TestCase;

class CookieSecurityTest extends TestCase
{
    /**
     * 直接 require 配置文件，避免依赖框架完整运行时
     */
    private function loadAppConfig()
    {
        return require ROOT_PATH . 'application/config.php';
    }

    public function test_cookie_httponly_is_enabled()
    {
        $cfg = $this->loadAppConfig();
        $this->assertNotEmpty(
            $cfg['cookie']['httponly'],
            'application/config.php 的 cookie.httponly 必须为真值，否则 XSS 可读取 cookie'
        );
    }

    public function test_session_httponly_is_enabled()
    {
        $cfg = $this->loadAppConfig();
        $this->assertNotEmpty(
            $cfg['session']['httponly'],
            'application/config.php 的 session.httponly 必须为真值（SessionSameSite behavior 会读取）'
        );
    }

    public function test_session_samesite_is_set()
    {
        $cfg = $this->loadAppConfig();
        $this->assertNotEmpty($cfg['session']['samesite'], 'session.samesite 应已配置');
    }
}
