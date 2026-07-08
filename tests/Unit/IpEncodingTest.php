<?php
/**
 * IP 入库编码单测。
 *
 * 背景：mac_comment.comment_ip / mac_gbook.gbook_ip 等是 int unsigned（ip2long 整数存储），
 * 但 api/Comment、api/Gbook 的 submit 旧代码直接写 mac_get_client_ip()（点分字符串 "1.2.3.4"）：
 *  - MySQL 严格模式（8.0 默认）：1265 Data truncated → 抛错 → 评论/留言提交 500；
 *  - 非严格模式：静默截断为 0 → IP 审计/风控失效。
 * 修复：改写 mac_get_ip_long()（与 index 模块一致）。本用例守护该工具函数的编码正确性，
 * 真实提交链路由 tests/regression/api-security.sh 的 B3/B4 复现（严格模式下 POST 成功）。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class IpEncodingTest extends TestCase
{
    public function test_dotted_ipv4_encodes_to_unsigned_long()
    {
        $this->assertEquals(2130706433, mac_get_ip_long('127.0.0.1'));
        $this->assertEquals(3232235786, mac_get_ip_long('192.168.1.10'));
    }

    public function test_invalid_or_ipv6_falls_back_to_zero_not_fatal()
    {
        // 非法串 / IPv6（ip2long 不支持）必须安全回落 0，不得抛错（否则评论/留言提交崩溃）
        $this->assertEquals(0, mac_get_ip_long('not-an-ip'));
        $this->assertEquals(0, mac_get_ip_long('::1'));
    }

    public function test_empty_arg_uses_client_ip_without_fatal()
    {
        // 无参：内部取 mac_get_client_ip()，本地回环不可解析时回落 0，绝不抛错
        $v = mac_get_ip_long('');
        $this->assertTrue($v === 0 || $v === '0' || (int)$v > 0, '空参应返回数值，不得抛异常');
    }
}
