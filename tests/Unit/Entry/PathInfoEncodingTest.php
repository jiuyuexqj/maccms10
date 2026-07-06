<?php
/**
 * P2-4 测试：入口 PATH_INFO 编码归一逻辑（镜像 admin.php / api.php / index.php）。
 *
 * 这三个入口在框架加载前执行，无法直接单测；本测试镜像其逻辑，
 * 改入口时务必与 normalizePathInfo 保持一致。
 */

namespace MaccmsTest\Unit\Entry;

use PHPUnit\Framework\TestCase;

class PathInfoEncodingTest extends TestCase
{
    /**
     * 与 admin.php/api.php/index.php 中的 PATH_INFO 处理逻辑保持一致。
     */
    private static function normalizePathInfo($pi)
    {
        $pi = isset($pi) ? $pi : '';
        if ($pi !== '' && !mb_check_encoding($pi, 'utf-8')) {
            $pi = mb_convert_encoding($pi, 'UTF-8', 'GBK');
        }
        return $pi;
    }

    public function test_undefined_or_empty_yields_empty()
    {
        $this->assertSame('', self::normalizePathInfo(null));
        $this->assertSame('', self::normalizePathInfo(''));
    }

    public function test_utf8_path_unchanged()
    {
        $this->assertSame('/vod/play/id/1', self::normalizePathInfo('/vod/play/id/1'));
    }

    public function test_gbk_path_converted_to_utf8()
    {
        $gbk = mb_convert_encoding('/中文', 'GBK', 'UTF-8');
        $res = self::normalizePathInfo($gbk);
        $this->assertSame('/中文', $res);
    }
}
