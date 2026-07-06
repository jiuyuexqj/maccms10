<?php
/**
 * P2-1 测试：cookie 登录凭据的生成与校验。
 *
 * 覆盖：正确 token 通过、伪造/空 token 拒绝、错误 random 拒绝、admin 的 IP 绑定。
 */

namespace MaccmsTest\Unit\Auth;

use PHPUnit\Framework\TestCase;

class LoginCheckTokenTest extends TestCase
{
    public function test_user_check_roundtrip()
    {
        $token = mac_build_login_check('rnd-secret', 'alice', 5);
        $this->assertTrue(mac_verify_login_check($token, 'rnd-secret', 'alice', 5));
    }

    public function test_user_check_rejects_forged_or_empty()
    {
        $this->assertFalse(mac_verify_login_check('forged', 'rnd', 'alice', 5));
        $this->assertFalse(mac_verify_login_check('', 'rnd', 'alice', 5));
        // 正确 token 但 random 不匹配 → 必须拒绝（账户接管防线）
        $token = mac_build_login_check('rnd-correct', 'alice', 5);
        $this->assertFalse(mac_verify_login_check($token, 'rnd-wrong', 'alice', 5));
    }

    public function test_admin_check_binds_client_ip()
    {
        $ip = '203.0.113.10';
        $token = mac_build_admin_check('arnd', 'admin', 1, $ip);
        $this->assertTrue(mac_verify_admin_check($token, 'arnd', 'admin', 1, $ip));
        // 换 IP 后 cookie 失效（防 cookie 跨机盗用）
        $this->assertFalse(mac_verify_admin_check($token, 'arnd', 'admin', 1, '198.51.100.1'));
    }

    public function test_admin_check_rejects_empty()
    {
        $this->assertFalse(mac_verify_admin_check('', 'arnd', 'admin', 1, '1.2.3.4'));
    }
}
