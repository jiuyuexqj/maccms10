<?php
/**
 * P1-1 回归测试：mac_array_sum_column 替代易错的 foreach 求和。
 *
 * 复现 Index.php:852 的原始 bug 场景：键名笔误 / 缺失键 不应导致总量为 0。
 */

namespace MaccmsTest\Unit;

use PHPUnit\Framework\TestCase;

class ArraySumColumnTest extends TestCase
{
    public function test_sums_named_column_across_rows()
    {
        $rows = [
            ['days' => '2026-07-01', 'count' => 10],
            ['days' => '2026-07-02', 'count' => 20],
            ['days' => '2026-07-03', 'count' => 5],
        ];
        $this->assertSame(35, mac_array_sum_column($rows, 'count'));
    }

    public function test_returns_zero_for_empty_or_non_array()
    {
        $this->assertSame(0, mac_array_sum_column(null, 'count'));
        $this->assertSame(0, mac_array_sum_column([], 'count'));
        $this->assertSame(0, mac_array_sum_column('not-array', 'count'));
    }

    /**
     * 关键：部分行缺失该列时，不应整体失败或得到错误结果（原 bug 的根因）
     */
    public function test_skips_rows_missing_the_column()
    {
        $rows = [
            ['count' => 1],
            ['other' => 2],   // 缺 count
            ['count' => 2],
        ];
        $this->assertSame(3, mac_array_sum_column($rows, 'count'));
    }

    public function test_treats_non_numeric_values_as_zero()
    {
        $rows = [
            ['count' => 5],
            ['count' => 'abc'],
            ['count' => '7'],
        ];
        $this->assertSame(12, mac_array_sum_column($rows, 'count'));
    }
}
