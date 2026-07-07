#!/usr/bin/env bash
# MacCMS10 接口冒烟：起服务后跑，真实调用关键前端/接口路径，记录 HTTP 状态码。
#
# 用法：
#   BASE=http://localhost:8080 bash tests/regression/api-smoke.sh
#
# 输出每行： <HTTP码>  <路径>
# 非 2xx/3xx 的行会在末尾标记 ✗，便于定位 4xx/5xx。
#
# 注：API 方法名为 get_list/get_detail（非 list/info），route_status=0 时用 ?s= 风格 URL。

set -u
BASE="${BASE:-http://localhost:8080}"
FAIL=0

check() {
    local path="$1"
    local code
    code=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 8 --max-time 20 "${BASE}${path}" 2>/dev/null || echo "000")
    local mark=""
    if ! echo "$code" | grep -qE '^(2|3)'; then
        mark=" ✗"
        FAIL=$((FAIL+1))
    fi
    printf "%s  %s%s\n" "$code" "$path" "$mark"
}

echo "=== MacCMS10 接口冒烟（BASE=$BASE）==="
echo "  前台/页面"
check "/index.php"
check "/index.php?s=vod/show/id/1.html"
check "/index.php?s=art/show/id/1.html"
check "/index.php?s=map/index.html"
check "/index.php?type=rss"
check "/index.php?s=rss/baidu.html"

echo "  API"
check "/api.php"
check "/api.php?s=type/get_list"
check "/api.php?s=vod/get_list"
check "/api.php?s=vod/get_detail&vod_id=1"

echo "  资源（静态资源 404 也是信息）"
check "/static/player/index.html"
check "/static/js/playerconfig.js"

echo ""
echo "=== 非预期状态数: $FAIL ==="
[ "$FAIL" -eq 0 ] && echo "全部 2xx/3xx" || echo "存在 4xx/5xx，详见上方 ✗ 标记。"
exit 0
