<?php
/**
 * P2-8 测试：页面规范 URL 构造（Host 头与 REQUEST_URI 加固）。
 */

namespace MaccmsTest\Unit\Common;

use PHPUnit\Framework\TestCase;

class HttpUrlBuildTest extends TestCase
{
    public function test_standard_https_omits_port()
    {
        $this->assertSame('https://example.com/vod/1', mac_build_http_url('https://', 'example.com', 443, '/vod/1'));
    }

    public function test_standard_http_omits_port()
    {
        $this->assertSame('http://example.com/vod/1', mac_build_http_url('http://', 'example.com', 80, '/vod/1'));
    }

    public function test_non_standard_port_included()
    {
        $this->assertSame('http://example.com:8080/vod/1', mac_build_http_url('http://', 'example.com', 8080, '/vod/1'));
    }

    /**
     * 关键：REQUEST_URI 中的 HTML 注入载荷必须被转义
     */
    public function test_request_uri_xss_is_escaped()
    {
        $url = mac_build_http_url('https://', 'example.com', 443, '/vod/1" onmouseover="alert(1)');
        $this->assertStringNotContainsString('" onmouseover', $url);
        $this->assertStringContainsString('&quot;', $url);
    }

    public function test_non_scalar_host_is_safe()
    {
        // 数组/对象 host 归一为空字符串，不抛错也不反射
        $this->assertSame('http:///x', mac_build_http_url('http://', ['evil'], 80, '/x'));
    }
}
