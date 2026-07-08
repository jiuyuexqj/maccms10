<?php
/**
 * 评分自增并发安全单测。
 *
 * 背景：api/Vod::update_score、api/Art::update_score 旧实现 read-modify-write（先读 score_num/
 * score_all，PHP 算 +1/+score/avg 再整体写回），多 worker 并发丢更新（与 update_hits 同类，
 * 3 worker×20 并发实测丢更新）。修复：mac_score_atomic_update() 返回 Db::raw 原子表达式，
 * 单条 UPDATE（行锁 + 原子自增），avg 用 (all+score)/(num+1) 显式计算不依赖 SET 求值顺序。
 * 真实并发由 tests/regression/hits-concurrency.sh 同口径复现（update_score 20 并发 num=20/all=160/score=8.0）。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class ScoreConcurrencyTest extends TestCase
{
    public function test_vod_score_atomic_expressions()
    {
        $u = mac_score_atomic_update(8, 'vod_score_num', 'vod_score_all', 'vod_score');
        $this->assertSame('vod_score_num+1', $u['vod_score_num']->getValue());
        $this->assertSame('vod_score_all+8', $u['vod_score_all']->getValue());
        $this->assertSame('ROUND((vod_score_all+8)/(vod_score_num+1),1)', $u['vod_score']->getValue());
    }

    public function test_art_score_atomic_expressions()
    {
        $u = mac_score_atomic_update(9, 'art_score_num', 'art_score_all', 'art_score');
        $this->assertSame('art_score_num+1', $u['art_score_num']->getValue());
        $this->assertSame('art_score_all+9', $u['art_score_all']->getValue());
        $this->assertSame('ROUND((art_score_all+9)/(art_score_num+1),1)', $u['art_score']->getValue());
    }

    public function test_score_int_inline_no_injection_surface()
    {
        // score 经 intval，必为整数内联，无引号/拼接注入面
        $u = mac_score_atomic_update(7, 'vod_score_num', 'vod_score_all', 'vod_score');
        foreach (['vod_score_num', 'vod_score_all', 'vod_score'] as $k) {
            $this->assertStringNotContainsString("'", $u[$k]->getValue(), "$k 表达式不应含引号");
        }
    }
}
