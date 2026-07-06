<?php
/**
 * 公共工具函数单测 —— 可立即运行，作为测试基础设施的冒烟样例。
 *
 * 覆盖 application/common.php 中无副作用（不依赖 $GLOBALS['config'] / 数据库）的纯函数。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class CommonFunctionsTest extends TestCase
{
    /**
     * mac_filter_xss：script 标签被剥离 + 引号被转义
     */
    public function test_mac_filter_xss_strips_tags_and_escapes_quotes()
    {
        $this->assertSame('alert(1)', mac_filter_xss('<script>alert(1)</script>'));
        $this->assertSame('&quot;x', mac_filter_xss('"x'));
        $this->assertSame('', mac_filter_xss(''));
    }

    /**
     * mac_filter_xss：URL（http/https/ mac:）只去标签，不转义 &/
     */
    public function test_mac_filter_xss_preserves_url_form()
    {
        $this->assertSame('https://example.com/a?b=1&c=2', mac_filter_xss('https://example.com/a?b=1&c=2'));
        $this->assertSame('//cdn.example.com/x', mac_filter_xss('//cdn.example.com/x'));
    }

    /**
     * mac_substring：按多字节字符（中文）截取
     */
    public function test_mac_substring_handles_multibyte_chinese()
    {
        $this->assertSame('中文', mac_substring('中文字符串测试', 2));
        $this->assertSame('中', mac_substring('中文字符串测试', 1));
    }

    /**
     * mac_get_mid：控制器名 → 模块编号映射
     */
    public function test_mac_get_mid_maps_known_controllers()
    {
        $this->assertSame(1, mac_get_mid('vod'));
        $this->assertSame(2, mac_get_mid('art'));
        $this->assertSame(8, mac_get_mid('actor'));
        $this->assertSame(12, mac_get_mid('manga'));
    }

    /**
     * mac_format_count：按逗号分段计数
     */
    public function test_mac_format_count_counts_comma_parts()
    {
        $this->assertSame(3, mac_format_count('a,b,c'));
        $this->assertSame(1, mac_format_count('only'));
    }

    /**
     * mac_scalar_string：非字符串类型安全归一
     */
    public function test_mac_scalar_string_normalizes_input()
    {
        $this->assertSame('abc', mac_scalar_string('abc'));
        $this->assertSame('', mac_scalar_string(null));
        $this->assertSame('', mac_scalar_string(['x']));
        $this->assertSame('123', mac_scalar_string(123));
    }
}
