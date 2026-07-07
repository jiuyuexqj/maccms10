<?php
/**
 * 播放量自增并发安全单测。
 *
 * 复现背景：api/vod/update_hits 旧实现是 read-modify-write，多 worker 并发丢更新
 * （3 worker × 30 并发实测 30 次播放只 +25）。现抽取 mac_vod_hits_atomic_update()
 * 构造单条原子 UPDATE（Db::raw() 自增 + InnoDB 行锁），本测试守护其正确形态与窗口语义。
 *
 * 注：真实并发丢更新由 tests/regression/hits-concurrency.sh 在多 worker 下回归；
 *     本 PHPUnit 用例守护“构造出的 UPDATE 必须是原子自增、窗口重置逻辑正确”。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class VodHitsConcurrencyTest extends TestCase
{
    /**
     * 守护核心修复：UPDATE 必须用原子自增表达式，而非“读到的旧值 +1”。
     * 一旦有人改回 read-modify-write（写字面量），本断言即失败。
     */
    public function test_update_uses_atomic_increment_not_read_modify_write()
    {
        $built = mac_vod_hits_atomic_update(123456, 1700000000, ['vod_hits' => 99]);

        $this->assertSame('vod_hits+1', $built['sql']['vod_hits']);
        $this->assertStringStartsWith('IF(vod_time_hits>=', $built['sql']['vod_hits_day']);
        $this->assertStringContainsString('vod_hits_day+1,1)', $built['sql']['vod_hits_day']);
        $this->assertStringStartsWith('IF(vod_time_hits>=', $built['sql']['vod_hits_week']);
        $this->assertStringStartsWith('IF(vod_time_hits>=', $built['sql']['vod_hits_month']);
        $this->assertSame(1700000000, $built['vod_time_hits']);
    }

    /**
     * 同一天内再次播放：今日/本周/本月计数都应自增（不重置）。
     */
    public function test_same_day_increment_all_counters()
    {
        $now = mktime(12, 0, 0, 7, 7, 2026); // 2026-07-07 周二 正午
        $built = mac_vod_hits_atomic_update($now - 3600, $now, [
            'vod_hits' => 100, 'vod_hits_day' => 5, 'vod_hits_week' => 20, 'vod_hits_month' => 80,
        ]);
        $e = $built['expect'];
        $this->assertSame(101, $e['vod_hits']);
        $this->assertSame(6, $e['vod_hits_day']);
        $this->assertSame(21, $e['vod_hits_week']);
        $this->assertSame(81, $e['vod_hits_month']);
    }

    /**
     * 上次播放距今很久（跨日/跨周/跨月）：日/周/月计数都应重置为 1。
     */
    public function test_long_ago_resets_day_week_month_to_one()
    {
        $now = mktime(12, 0, 0, 7, 7, 2026);
        $built = mac_vod_hits_atomic_update($now - 86400 * 400, $now, [
            'vod_hits' => 1000, 'vod_hits_day' => 5, 'vod_hits_week' => 20, 'vod_hits_month' => 80,
        ]);
        $e = $built['expect'];
        $this->assertSame(1001, $e['vod_hits']);   // 总播放永远 +1
        $this->assertSame(1, $e['vod_hits_day']);
        $this->assertSame(1, $e['vod_hits_week']);
        $this->assertSame(1, $e['vod_hits_month']);
    }

    /**
     * 跨日但仍在同一自然周内（周二 vs 上周一）：日重置为 1，周/月自增。
     * 选 2026-07-07(周二) 与 2026-07-06(周一，同周)，但跨日 → 日重置、周自增。
     */
    public function test_different_day_same_week_resets_day_only()
    {
        $now = mktime(12, 0, 0, 7, 7, 2026);     // 周二
        $last = mktime(23, 0, 0, 7, 6, 2026);    // 周一（同一自然周，跨日）
        $built = mac_vod_hits_atomic_update($last, $now, [
            'vod_hits' => 50, 'vod_hits_day' => 9, 'vod_hits_week' => 33, 'vod_hits_month' => 70,
        ]);
        $e = $built['expect'];
        $this->assertSame(1, $e['vod_hits_day']);    // 跨日 → 重置
        $this->assertSame(34, $e['vod_hits_week']);  // 同周 → 自增
        $this->assertSame(71, $e['vod_hits_month']); // 同月 → 自增
    }

    /**
     * IF 表达式内联的时间边界必须是整数（无注入面），且与 PHP 计算的窗口起点一致。
     */
    public function test_window_boundaries_are_int_and_consistent()
    {
        $now = mktime(12, 0, 0, 7, 7, 2026);
        $built = mac_vod_hits_atomic_update(0, $now);
        $w = $built['window'];
        $this->assertIsInt($w['day']);
        $this->assertIsInt($w['week']);
        $this->assertIsInt($w['month']);
        // 边界出现在对应 IF 表达式里
        $this->assertStringContainsString('>=' . $w['day'] . ',', $built['sql']['vod_hits_day']);
        $this->assertStringContainsString('>=' . $w['week'] . ',', $built['sql']['vod_hits_week']);
        $this->assertStringContainsString('>=' . $w['month'] . ',', $built['sql']['vod_hits_month']);
    }
}
