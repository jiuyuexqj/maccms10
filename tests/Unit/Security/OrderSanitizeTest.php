<?php
/**
 * P2-6 测试：ORDER BY 参数白名单净化。
 *
 * 用于 orderRaw() 前置过滤，防止 Website/Actor listData 等处的 order 注入。
 */

namespace MaccmsTest\Unit\Security;

use PHPUnit\Framework\TestCase;

class OrderSanitizeTest extends TestCase
{
    public function test_plain_field_and_direction_pass()
    {
        $this->assertSame('website_id desc', mac_sanitize_order('website_id desc'));
        $this->assertSame('vod_id', mac_sanitize_order('vod_id'));
        $this->assertSame('vod_id asc', mac_sanitize_order('VOD_ID ASC'));
    }

    public function test_multi_field_with_directions_pass()
    {
        $this->assertSame('vod_id desc,vod_time desc', mac_sanitize_order('vod_id desc, vod_time desc'));
        $this->assertSame('t.vod_id', mac_sanitize_order('t.vod_id'));
    }

    public function test_empty_or_null_returns_empty()
    {
        $this->assertSame('', mac_sanitize_order(''));
        $this->assertSame('', mac_sanitize_order(null));
        $this->assertSame('', mac_sanitize_order('   '));
    }

    /**
     * 关键：各类 SQL 注入 payload 必须被拒（返回空）
     */
    public function test_injection_payloads_rejected()
    {
        $this->assertSame('', mac_sanitize_order('vod_id,(select sleep(5))'));
        $this->assertSame('', mac_sanitize_order('1; DROP TABLE mac_admin'));
        $this->assertSame('', mac_sanitize_order("vod_id' OR '1'='1"));
        $this->assertSame('', mac_sanitize_order('vod_id--'));
        $this->assertSame('', mac_sanitize_order('vod_id;--'));
        $this->assertSame('', mac_sanitize_order('IF(1=1,vod_id,1)'));
        $this->assertSame('', mac_sanitize_order('vod_id INTO OUTFILE "/tmp/x"'));
    }
}
