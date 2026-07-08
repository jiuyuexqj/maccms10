#!/usr/bin/env bash
# 前端资源/页面回归（真实 HTTP，需运行中的服务）。
#
# 守护：
#   - 资源路径无双斜杠（path_tpl 恒以 / 结尾，模板不应再补 /）
#   - 关键静态资源 200
#   - 关键页面可渲染（非 4xx/5xx）
#
# 用法：BASE=http://127.0.0.1:8080 bash tests/regression/frontend-assets.sh

set -u
BASE="${BASE:-http://127.0.0.1:8080}"
PASS=0; FAIL=0
ok(){ echo "  ✅ $1"; PASS=$((PASS+1)); }
no(){ echo "  ❌ $1" >&2; FAIL=$((FAIL+1)); }

echo "=== MacCMS10 前端资源回归 (BASE=$BASE) ==="

# 1) 关键页面无 //asset、//images 双斜杠
for p in "/index.php" "/index.php?s=vod/show/id/1.html" "/index.php?s=vod/detail/id/1.html" "/index.php?s=vod/play/id/1/sid/1/nid/1.html" "/index.php?s=user/index.html"; do
    n=$(curl -sS --max-time 15 "$BASE$p" 2>/dev/null | grep -oE "/template/default//|//asset/|//images/" | wc -l)
    if [ "$n" -eq 0 ]; then ok "无双斜杠: $p"; else no "$p 仍有 $n 处双斜杠"; fi
done

# 2) 关键静态资源 200
for a in "/template/default/asset/js/jquery.js" "/template/default/asset/css/mac-pop-sheets.css" "/template/default/asset/js/mac-logo-lottie.js" "/template/default/asset/img/favicon.ico"; do
    code=$(curl -sS -o /dev/null -w "%{http_code}" --max-time 10 "$BASE$a" 2>/dev/null)
    if [ "$code" = "200" ]; then ok "200 $a"; else no "$code $a"; fi
done

# 3) Lottie logo img 不再把 .json 当图片抓取（应为透明占位 + data-mac-logo-lottie-url）
html=$(curl -sS --max-time 15 "$BASE/index.php" 2>/dev/null)
if echo "$html" | grep -q 'data-mac-logo-lottie-url=' && ! echo "$html" | grep -qE 'src="[^"]*lottie/logo[^"]*\.json"'; then
    ok "Lottie logo 用占位+data 属性，无 .json 图片抓取"
else
    no "Lottie logo 仍以 .json 为 img src（应改占位+data 属性）"
fi

# 4) 分类页 <noscript> SSR 首屏卡片（无 JS 爬虫/读屏器可见详情链接，JS 客户端仍走 AJAX）
show=$(curl -sS --max-time 15 "$BASE/index.php?s=vod/show/id/1.html" 2>/dev/null)
n=$(echo "$show" | grep -oE '<a class="vodlist_thumb" href="[^"]*vod/detail[^"]*"' | wc -l)
if [ "$n" -ge 1 ]; then ok "分类页 noscript SSR 卡片 ($n 条)"; else no "分类页无 noscript SSR 卡片"; fi

echo ""
echo "=== 通过 $PASS / 失败 $FAIL ==="
[ "$FAIL" -eq 0 ] && { echo "✅ 前端资源不变量保持"; exit 0; } || { echo "❌ 存在前端资源回归"; exit 1; }
