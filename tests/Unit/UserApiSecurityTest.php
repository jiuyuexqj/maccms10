<?php
/**
 * 用户/留言 API 安全修复单测。
 *
 * 复现：api/gbook/submit 游客路径在控制器显式置 user_id=0，但校验规则
 * between:1,PHP_INT_MAX 拒绝 0 → 游客留言永远 1001 失败（接口形同虚设）。
 * 修复：application/api/validate/Gbook.php 改 between:0, 允许游客。
 *
 * 关联的真实链路校验（隐藏视频不泄露、PII 不下发、POST-only、orderby 注入等）
 * 见 tests/regression/api-security.sh（需要运行中的服务）。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class UserApiSecurityTest extends TestCase
{
    /**
     * 游客 user_id=0 必须通过校验（修复后）；负数仍拒绝；正常 uid 通过。
     */
    public function test_gbook_validate_allows_guest_user_id_zero()
    {
        $v = new \app\api\validate\Gbook();

        $this->assertTrue($v->check(['user_id' => 0]),  '游客 user_id=0 应通过（修复点）');
        $this->assertTrue($v->check(['user_id' => 5]),  '已登录 user_id=5 应通过');

        // 边界：负数 / 非法仍拒绝（不能因为放行 0 而放开越界值）
        $this->assertFalse($v->check(['user_id' => -1]), '负数 user_id 仍应拒绝');
        $this->assertFalse($v->check(['limit' => 9999]), 'limit 越界仍应拒绝（校验未被破坏）');
    }
}
