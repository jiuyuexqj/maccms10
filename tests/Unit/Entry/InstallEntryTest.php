<?php
/**
 * P2-7 回归保护：install.php 在已安装（install.lock 存在）时应返回 403，
 * 且不回显 install.lock 的具体路径。
 *
 * 入口文件在框架加载前执行，无法直接单测；此处对入口源码做静态断言，
 * 防止加固被无意回退。
 */

namespace MaccmsTest\Unit\Entry;

use PHPUnit\Framework\TestCase;

class InstallEntryTest extends TestCase
{
    public function test_install_returns_403_when_locked()
    {
        $src = file_get_contents(ROOT_PATH . 'install.php');
        $this->assertStringContainsString('http_response_code(403)', $src, 'install.php 锁定时必须返回 403');
        $this->assertStringContainsString('already installed', $src);
    }
}
