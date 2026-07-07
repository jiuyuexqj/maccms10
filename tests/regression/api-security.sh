#!/usr/bin/env bash
# API 安全修复回归（真实 HTTP 调用，需运行中的服务）。
#
# 覆盖本次修复的安全不变量（详见 commit）：
#   B1 user/get_detail   不再 500（user_exp 列移除）+ 不下发 PII(user_phone)
#   B2 vod/get_detail    隐藏(vod_status=0)视频不再泄露详情/播放地址
#   B3 comment/submit    必须 POST（GET 拒绝，防 CSRF 植入评论）
#   B4 gbook/submit      游客(user_id=0)留言可成功
#   B5 user/get_list     不下发 user_phone（PII）
#   B6 vod/get_class     不含隐藏视频的分类元数据
#   B7 user/get_list     orderby 注入不再 500（白名单）
#
# 用法：BASE=http://127.0.0.1:8080 bash tests/regression/api-security.sh
# 退出码：0 全部通过；1 存在回归。
# 依赖：至少 1 个正常用户(user_id>=1)、vod_id=5 为隐藏状态(vod_status=0)。

set -u
BASE="${BASE:-http://127.0.0.1:8080}"
PASS=0; FAIL=0
ok(){ echo "  ✅ $1"; PASS=$((PASS+1)); }
no(){ echo "  ❌ $1" >&2; FAIL=$((FAIL+1)); }

get(){ curl -sS --max-time 15 "$BASE$1"; }
code(){ curl -sS -o /tmp/api_sec -w "%{http_code}" --max-time 15 "$BASE$1"; }

echo "=== MacCMS10 API 安全回归 (BASE=$BASE) ==="

# --- B1: user/get_detail 不再 500 + 无 PII ---
b=$(get "/api.php?s=User/get_detail&id=1")
if echo "$b" | grep -q '"code":1' && ! echo "$b" | grep -q 'user_phone'; then ok "B1 get_detail 正常且无 user_phone"; else no "B1 get_detail 异常或泄露 user_phone: $b"; fi

# --- B2: 隐藏视频 get_detail 不泄露 ---
b=$(get "/api.php?s=vod/get_detail&vod_id=5")
if echo "$b" | grep -q '"code":1001' && ! echo "$b" | grep -q 'vod_play_from'; then ok "B2 隐藏 vod 5 不泄露详情"; else no "B2 隐藏 vod 泄露: $b"; fi

# --- B3: comment/submit GET 拒绝 / POST 放行 ---
b=$(get "/api.php?s=Comment/submit&comment_mid=1&comment_rid=1&comment_content=x")
echo "$b" | grep -q '请使用 POST' && ok "B3 comment GET 被拒" || no "B3 comment GET 未拒: $b"
b=$(curl -sS --max-time 15 -X POST "$BASE/api.php?s=Comment/submit" -d "comment_mid=1&comment_rid=1&comment_content=regressionok")
echo "$b" | grep -q '"code":1' && ok "B3 comment POST 放行" || no "B3 comment POST 失败: $b"

# --- B4: 游客留言 ---
b=$(curl -sS --max-time 15 -X POST "$BASE/api.php?s=gbook/submit" -d "gbook_content=regguest&gbook_name=游客")
echo "$b" | grep -q '"code":1' && ok "B4 游客留言成功" || no "B4 游客留言失败: $b"

# --- B5: user/get_list 无 user_phone ---
b=$(get "/api.php?s=User/get_list")
echo "$b" | grep -q 'user_phone' && no "B5 get_list 泄露 user_phone" || ok "B5 get_list 无 user_phone"

# --- B6: get_class 不含隐藏视频分类（隐藏 vod 5 的 '喜剧' 单值不再出现）---
b=$(get "/api.php?s=vod/get_class&type_id_1=1")
# 正常视频分类均为“X,Y”复值；隐藏 vod 5 提供单独 '喜剧'。修复后不应再含单独 "喜剧"。
echo "$b" | grep -q '"喜剧"' && no "B6 get_class 含隐藏视频单独分类: $b" || ok "B6 get_class 无隐藏视频分类"

# --- B7: orderby 注入不再 500 ---
c=$(code "/api.php?s=User/get_list&orderby=reg_time,(select%20sleep(1))")
[ "$c" = "200" ] && ok "B7 orderby 注入返回 200（白名单兜底）" || no "B7 orderby 注入返回 $c（应 200）"

echo ""
echo "=== 通过 $PASS / 失败 $FAIL ==="
[ "$FAIL" -eq 0 ] && { echo "✅ 全部安全不变量保持"; exit 0; } || { echo "❌ 存在安全回归，详见上方"; exit 1; }
