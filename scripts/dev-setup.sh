#!/usr/bin/env bash
# 一键配置开发环境（见 CLAUDE.md §7.5 / §10）
#   1) 启用 git pre-commit hook（提交门禁）
#   2) 校验 PHP / Composer
#   3) 安装测试依赖（vendor/bin/phpunit）
#
# 用法： bash scripts/dev-setup.sh

cd "$(git rev-parse --show-toplevel 2>/dev/null || echo .)" || exit 1

echo "==> 1/3 配置 git hooks 路径 (.githooks)"
git config core.hooksPath .githooks
chmod +x .githooks/pre-commit 2>/dev/null || true   # Windows Git 不需要，忽略报错
echo "    core.hooksPath = $(git config core.hooksPath)"

echo "==> 2/3 检查 PHP (>=7.4)"
if command -v php >/dev/null 2>&1; then
    php -v | head -1
else
    echo "    [警告] 未找到 php。请安装 PHP >=7.4 并加入 PATH。"
    echo "           否则 pre-commit 门禁会阻止 git commit（可用 --no-verify 紧急跳过）。"
fi

echo "==> 3/3 检查测试依赖"
if [ -f vendor/bin/phpunit ]; then
    echo "    vendor/bin/phpunit 已就绪。"
elif command -v composer >/dev/null 2>&1; then
    echo "    运行 composer install ..."
    if ! composer install; then
        echo "    [错误] composer install 失败，请手动排查后重跑本脚本。"
        exit 1
    fi
else
    echo "    [警告] 未找到 composer 且 vendor/ 未安装。请先运行 composer install。"
fi

cat <<'TIP'

✅ 开发环境配置完成。
   提交时将自动运行: vendor/bin/phpunit -c tests/phpunit.xml.dist
   紧急跳过(须 CI 补跑): git commit --no-verify
TIP
