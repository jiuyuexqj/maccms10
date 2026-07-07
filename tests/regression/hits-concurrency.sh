#!/usr/bin/env bash
# 播放量自增并发安全回归（真实多 worker 并发复现）。
#
# 背景：api/vod/update_hits 旧实现 read-modify-write，多 worker 并发丢更新
# （3 worker × 30 并发实测 30 次播放只 +25，丢 5 次）。
# 修复后改为单条原子 UPDATE（['exp'] 自增 + InnoDB 行锁），N 次并发应精确 +N。
#
# 前置：起 >=2 个 PHP worker（单进程 php -S 无法复现并发，会串行化）：
#   php -S 127.0.0.1:8080 -t .  &  php -S 127.0.0.1:8081 -t .  &  php -S 127.0.0.1:8082 -t .  &
# 用法：
#   WORKERS="http://127.0.0.1:8080 http://127.0.0.1:8081 http://127.0.0.1:8082" \
#   VID=1 N=30 bash tests/regression/hits-concurrency.sh
#
# 退出码：0 = 并发自增精确无丢更新；1 = 仍丢更新（回退/回归）。

set -u
WORKERS="${WORKERS:-http://127.0.0.1:8080 http://127.0.0.1:8081 http://127.0.0.1:8082}"
VID="${VID:-1}"
N="${N:-30}"

read -r -a W <<< "$WORKERS"
NW=${#W[@]}
if [ "$NW" -lt 2 ]; then
    echo "❌ 需要至少 2 个 worker（单进程 php -S 会串行化，无法复现并发）。当前: $WORKERS" >&2
    exit 1
fi

# 读当前播放量（type 不传 = 只读，返回 {data.hits}）
hits(){ curl -sS --max-time 10 "http://127.0.0.1:8080/api.php?s=vod/update_hits&id=${VID}" 2>/dev/null \
    | sed -n 's/.*"hits":\([0-9]\+\).*/\1/p'; }

BEFORE="$(hits)"
if [ -z "$BEFORE" ]; then echo "❌ 无法读取 vod_id=${VID} 的当前播放量（服务是否启动？VID 是否存在？）" >&2; exit 1; fi

echo "=== 并发自增回归：$NW worker × $N 并发 (vod_id=${VID}) ==="
echo "before hits = ${BEFORE}"

i=0
while [ "$i" -lt "$N" ]; do
    url="${W[$((i % NW))]}/api.php?s=vod/update_hits&id=${VID}&type=update"
    curl -sS -o /dev/null --max-time 15 "$url" &
    i=$((i+1))
done
wait

AFTER="$(hits)"
DELTA=$((AFTER - BEFORE))
LOST=$((N - DELTA))
echo "after  hits = ${AFTER}"
echo "delta  = ${DELTA} / ${N}  (丢失 ${LOST})"

if [ "$DELTA" -eq "$N" ]; then
    echo "✅ 并发自增精确无丢更新（原子 UPDATE 生效）。"
    exit 0
fi
echo "❌ 丢更新 ${LOST} 次：update_hits 非原子（read-modify-write 回归？）" >&2
exit 1
