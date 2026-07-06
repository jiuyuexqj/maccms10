<?php
/**
 * P2-5 测试：后台「直接执行 SQL」的危险性判断。
 */

namespace MaccmsTest\Unit\Security;

use PHPUnit\Framework\TestCase;

class DatabaseSqlGuardTest extends TestCase
{
    public function test_normal_select_is_safe()
    {
        $this->assertFalse(mac_is_sql_dangerous('SELECT * FROM mac_vod WHERE vod_id=1', 'mac_'));
        $this->assertFalse(mac_is_sql_dangerous('select 1', 'mac_'));
        $this->assertFalse(mac_is_sql_dangerous('UPDATE mac_vod SET vod_hits=vod_hits+1', 'mac_'));
    }

    public function test_danger_functions_blocked()
    {
        $this->assertTrue(mac_is_sql_dangerous("SELECT load_file('/etc/passwd')", 'mac_'));
        $this->assertTrue(mac_is_sql_dangerous('SELECT a INTO OUTFILE "/tmp/x"', 'mac_'));
        $this->assertTrue(mac_is_sql_dangerous('SELECT sleep(10)', 'mac_'));
        $this->assertTrue(mac_is_sql_dangerous('SELECT benchmark(1000000, md5(1))', 'mac_'));
    }

    public function test_drop_sensitive_table_blocked()
    {
        $this->assertTrue(mac_is_sql_dangerous('DROP TABLE mac_admin', 'mac_'));
        $this->assertTrue(mac_is_sql_dangerous('DROP TABLE `mac_user`', 'mac_'));
        $this->assertTrue(mac_is_sql_dangerous('TRUNCATE TABLE mac_role', 'mac_'));
        $this->assertTrue(mac_is_sql_dangerous('truncate table mac_group', 'mac_'));
    }

    public function test_drop_non_sensitive_table_allowed()
    {
        // 仅敏感表（admin/user/role/group）受保护；业务表清理由管理员自行负责
        $this->assertFalse(mac_is_sql_dangerous('DROP TABLE mac_tmp_cleanup', 'mac_'));
        $this->assertFalse(mac_is_sql_dangerous('TRUNCATE TABLE mac_visit', 'mac_'));
    }
}
