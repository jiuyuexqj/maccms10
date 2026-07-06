<?php
/**
 * P1-3 回归测试：后台 CSRF 防护（security_csrf_admin）必须默认开启。
 *
 * CsrfGuard behavior 仅在 security_csrf_admin 为真且非 '0' 时生效；
 * 默认值写在 application/extra/maccms.php，曾为 '0'（关闭）。
 */

namespace MaccmsTest\Unit\Security;

use PHPUnit\Framework\TestCase;

class CsrfGuardConfigTest extends TestCase
{
    private function loadMaccmsConfig()
    {
        return require ROOT_PATH . 'application/extra/maccms.php';
    }

    /**
     * 不依赖具体层级（可能在 app 段），递归查找键值。
     */
    private function findKey(array $arr, $key)
    {
        foreach ($arr as $k => $v) {
            if ($k === $key) {
                return $v;
            }
            if (is_array($v)) {
                $r = $this->findKey($v, $key);
                if ($r !== null) {
                    return $r;
                }
            }
        }
        return null;
    }

    public function test_csrf_admin_is_enabled_by_default()
    {
        $cfg = $this->loadMaccmsConfig();
        $val = $this->findKey($cfg, 'security_csrf_admin');
        $this->assertNotNull($val, 'security_csrf_admin 配置项必须存在');
        $this->assertNotSame('0', (string)$val, '后台 CSRF 防护不得默认关闭');
        $this->assertSame('1', (string)$val, '后台 CSRF 防护应默认开启');
    }

    public function test_csrf_exempt_keeps_upload()
    {
        $cfg = $this->loadMaccmsConfig();
        $exempt = $this->findKey($cfg, 'security_csrf_admin_exempt');
        $this->assertNotNull($exempt);
        $this->assertStringContainsString('upload/*', (string)$exempt);
    }
}
