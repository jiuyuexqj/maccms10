#!/usr/bin/env bash
# 登录 CSRF 回归（真实 HTTP，需运行中的服务 + MariaDB）。
#
# 复现：前端登录(#fm-login 主页/模态)经 serialize() POST 到 api/user/login_or_register。
# 修复：表单 {:token()} + 控制器非消费式 hash_equals 校验（TP5 内置 Validate::token 单次有效，
# 会让 AJAX 登录密码错→重试失败，故自定义不销毁）。
#
# 守护：
#   - 正确密码 + token → 登录成功
#   - 同 token 错密码重试 → 密码错（非 token 错，验证非消费）
#   - 无 token（CSRF）→ 被拒
#
# 用法：BASE=http://127.0.0.1:8080 MDB_PASS=root bash tests/regression/login-csrf.sh
# 依赖：能连 MariaDB 建测试用户（绕开注册频率限制）。

set -u
BASE="${BASE:-http://127.0.0.1:8080}"
MDB="${MDB:-/c/Users/ztw/maccms-tools/mariadb/bin/mariadb.exe}"
MDB_PASS="${MDB_PASS:-root}"
UNAME="csrf_regression_user"
UPWD="regpass123"
PASS=0; FAIL=0
ok(){ echo "  ✅ $1"; PASS=$((PASS+1)); }
no(){ echo "  ❌ $1" >&2; FAIL=$((FAIL+1)); }

echo "=== 登录 CSRF 回归 (BASE=$BASE) ==="

# 建测试用户
"$MDB" -h127.0.0.1 -P3306 -uroot -p"$MDB_PASS" maccms10 -e "INSERT INTO mac_user (user_name,user_pwd,user_random,user_status,user_nick_name,user_points,user_reg_time) VALUES ('$UNAME', MD5('$UPWD'), MD5('randcsrfr'), 1, 'CSRF回归', 0, UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE user_pwd=MD5('$UPWD'), user_status=1;" >/dev/null 2>&1

# GET 登录页，拿 session cookie + 表单 __token__
curl -sS -c /tmp/cj_csrf -b /tmp/cj_csrf --max-time 15 "$BASE/index.php?s=user/login.html" -o /tmp/lp_csrf.html 2>/dev/null
TOK=$(grep -oE 'name="__token__" value="[a-f0-9]+"' /tmp/lp_csrf.html 2>/dev/null | head -1 | grep -oE '[a-f0-9]{20,}')

if [ -z "$TOK" ]; then no "登录页未渲染 __token__（{:token()} 缺失？）"; else ok "登录页含 __token__"; fi

# 1) 正确密码 + token → 成功
if [ -n "$TOK" ]; then
  r=$(curl -sS -c /tmp/cj_csrf -b /tmp/cj_csrf --max-time 15 -X POST "$BASE/api.php?s=user/login_or_register" -d "user_name=$UNAME&user_pwd=$UPWD&__token__=$TOK" 2>/dev/null)
  echo "$r" | grep -q '"code":1' && ok "正确密码+token 登录成功" || no "正确密码+token 登录失败: $r"
fi

# 2) 同 token 错密码重试 → 密码错（非 token 错）
r=$(curl -sS -c /tmp/cj_csrf -b /tmp/cj_csrf --max-time 15 -X POST "$BASE/api.php?s=user/login_or_register" -d "user_name=$UNAME&user_pwd=WRONGPWD&__token__=$TOK" 2>/dev/null)
if echo "$r" | grep -qE '"code":1003|密码错误'; then ok "同 token 重试报密码错（非消费，UX 正常）"; else no "重试返回非密码错: $r"; fi

# 3) 无 token（CSRF）→ 被拒
r=$(curl -sS -c /tmp/cj_csrf2 -b /tmp/cj_csrf2 --max-time 15 -X POST "$BASE/api.php?s=user/login_or_register" -d "user_name=$UNAME&user_pwd=$UPWD" 2>/dev/null)
if echo "$r" | grep -q '"code":1001'; then ok "无 token(CSRF) 被拒"; else no "无 token 未被拒: $r"; fi

echo ""
echo "=== 通过 $PASS / 失败 $FAIL ==="
[ "$FAIL" -eq 0 ] && { echo "✅ 登录 CSRF 不变量保持"; exit 0; } || { echo "❌ 存在登录 CSRF 回归"; exit 1; }
