<?php
/**
 * P2-2 测试：日志驱动不得为 test（基本不写盘，排障/取证失效）。
 */

namespace MaccmsTest\Unit\Config;

use PHPUnit\Framework\TestCase;

class LogConfigTest extends TestCase
{
    private function loadAppConfig()
    {
        return require ROOT_PATH . 'application/config.php';
    }

    public function test_log_driver_is_writable()
    {
        $cfg = $this->loadAppConfig();
        $this->assertNotSame('test', $cfg['log']['type'], 'config.php log.type 不得为 test（不写盘）');
    }
}
