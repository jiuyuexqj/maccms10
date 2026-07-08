<?php
/**
 * API 入口异常响应映射单测。
 *
 * 背景：api.php 旧 catch 对所有异常返回 500 service error；但 ThinkPHP 对“方法/路由不存在”
 * 抛 HttpException(404)。修复后按状态码透传（404/405/...），其余 500。
 * 决策逻辑抽到 mac_api_exception_response()，本用例守护其映射；真实 HTTP 由
 * tests/regression/api-security.sh 的 B11 用例复现（未知方法 → 404）。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class ApiExceptionResponseTest extends TestCase
{
    public function test_http_exception_404_maps_to_not_found()
    {
        $r = mac_api_exception_response(new \think\exception\HttpException(404, 'method not exists:app\\api\\controller\\Type->list()'));
        $this->assertSame(404, $r['status']);
        $this->assertSame('not found', $r['msg']);
    }

    public function test_http_exception_405_maps_to_method_not_allowed()
    {
        $r = mac_api_exception_response(new \think\exception\HttpException(405, 'x'));
        $this->assertSame(405, $r['status']);
        $this->assertSame('method not allowed', $r['msg']);
    }

    public function test_other_http_status_passes_through()
    {
        // 非 404/405 的 HttpException：状态码如实透传，msg 走通用 service error
        $r = mac_api_exception_response(new \think\exception\HttpException(401, 'unauth'));
        $this->assertSame(401, $r['status']);
    }

    public function test_plain_exception_maps_to_500_and_hides_detail_in_prod()
    {
        $r = mac_api_exception_response(new \Exception('boom'));
        $this->assertSame(500, $r['status']);
        $this->assertSame('service error', $r['msg']); // 生产关闭 debug，不泄露 boom
    }

    public function test_debug_mode_appends_detail_for_500()
    {
        $r = mac_api_exception_response(new \Exception('boom-detail'), true);
        $this->assertSame(500, $r['status']);
        $this->assertStringContainsString('boom-detail', $r['msg']);
    }
}
