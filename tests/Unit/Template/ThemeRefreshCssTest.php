<?php
/**
 * 前端视觉改版（theme-refresh）回归测试。
 *
 * 背景：default 模板是"三代设计系统叠加"（旧玫瑰红/蓝 + 薄荷绿 token 层 + 新版 banner），
 * 2026-09 改版在 token 层收敛为单一体系。本测试把改版的关键约束固化为断言，
 * 防止后续改动再次引入以下已修复问题：
 *   1. 主题色对比度不足（旧 #40cc92 白底仅 2.04:1，低于 WCAG AA 4.5:1）
 *   2. 25px 大药丸圆角污染 401 处
 *   3. body 字体栈回退宋体、正文 12px 过小
 *   4. 消费了未定义的 CSS 变量（--tpl-line-height-body 拼写错误、--tpl-bg-accent 未定义）
 *   5. viewport 禁用用户缩放（user-scalable=0）
 *   6. theme-refresh.css 未接线（foot.html）
 */
namespace MaccmsTest\Unit\Template;

use PHPUnit\Framework\TestCase;

class ThemeRefreshCssTest extends TestCase
{
    const CSS_DIR  = 'template/default/asset/css/';
    const HTML_DIR = 'template/default/html/';

    private function readTpl($rel)
    {
        $full = ROOT_PATH . $rel;
        $this->assertFileExists($full);
        return file_get_contents($full);
    }

    /** 解析 :root{...} / .bstem,body.bstem{...} 里的 --token:值 映射 */
    private function parseTokenBlock($css, $selectorRegex)
    {
        $out = [];
        if (preg_match($selectorRegex, $css, $m)) {
            foreach (explode(';', $m[1]) as $decl) {
                if (preg_match('/^\s*(--[\w-]+)\s*:\s*([^;]+)$/', $decl, $d)) {
                    $out[$d[1]] = trim($d[2]);
                }
            }
        }
        return $out;
    }

    private function hexLuminance($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $this->assertSame(6, strlen($hex), "非法颜色值: {$hex}");
        $c = [];
        for ($i = 0; $i < 3; $i++) {
            $v = hexdec(substr($hex, $i * 2, 2)) / 255;
            $c[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private function contrastRatio($a, $b)
    {
        $l1 = $this->hexLuminance($a);
        $l2 = $this->hexLuminance($b);
        $hi = max($l1, $l2);
        $lo = min($l1, $l2);
        return ($hi + 0.05) / ($lo + 0.05);
    }

    // ---------------------------------------------------------
    // 1) 主题色对比度（可用性回归，P 级问题）
    // ---------------------------------------------------------

    public function test_light_accent_passes_wcag_aa_on_white()
    {
        $css = $this->readTpl(self::CSS_DIR . 'public-head-main.css');
        $tokens = $this->parseTokenBlock($css, '/^:root\{([^}]*)\}/');
        $this->assertArrayHasKey('--tpl-accent', $tokens, ':root 缺少 --tpl-accent');
        $ratio = $this->contrastRatio($tokens['--tpl-accent'], '#ffffff');
        $this->assertGreaterThanOrEqual(
            4.5,
            $ratio,
            "亮色主题色 {$tokens['--tpl-accent']} 白底对比度 " . round($ratio, 2) . " 低于 WCAG AA 4.5"
        );
    }

    public function test_dark_accent_passes_wcag_aa_on_dark_base()
    {
        $css = $this->readTpl(self::CSS_DIR . 'public-head-main.css');
        $tokens = $this->parseTokenBlock($css, '/\.bstem,body\.bstem\{([^}]*)\}/');
        $this->assertArrayHasKey('--tpl-accent', $tokens, '.bstem 缺少 --tpl-accent');
        $this->assertArrayHasKey('--tpl-bg-base', $tokens);
        $ratio = $this->contrastRatio($tokens['--tpl-accent'], $tokens['--tpl-bg-base']);
        $this->assertGreaterThanOrEqual(
            4.5,
            $ratio,
            "暗色主题色 {$tokens['--tpl-accent']} 在 {$tokens['--tpl-bg-base']} 上对比度 " . round($ratio, 2) . " 低于 WCAG AA 4.5"
        );
    }

    // ---------------------------------------------------------
    // 2) 圆角与排版尺度
    // ---------------------------------------------------------

    public function test_radius_tokens_are_compact()
    {
        $css = $this->readTpl(self::CSS_DIR . 'public-head-main.css');
        foreach ([':root', '.bstem,body.bstem'] as $sel) {
            $re = $sel === ':root' ? '/^:root\{([^}]*)\}/' : '/\.bstem,body\.bstem\{([^}]*)\}/';
            $tokens = $this->parseTokenBlock($css, $re);
            foreach (['--tpl-radius', '--tpl-radius-sm', '--tpl-radius-cover'] as $t) {
                if (!isset($tokens[$t])) {
                    continue;
                }
                $this->assertLessThanOrEqual(
                    12,
                    (int)$tokens[$t],
                    "{$sel} 的 {$t}={$tokens[$t]} 超过 12px（旧版 25px 大药丸不得回归）"
                );
            }
        }
    }

    public function test_body_font_size_at_least_14_and_drops_simsun()
    {
        $css = $this->readTpl(self::CSS_DIR . 'public-head-main.css');
        $tokens = $this->parseTokenBlock($css, '/^:root\{([^}]*)\}/');
        $this->assertArrayHasKey('--tpl-font-body', $tokens);
        $this->assertGreaterThanOrEqual(14, (int)$tokens['--tpl-font-body'], '正文字号不得小于 14px');
        $this->assertArrayHasKey('--tpl-font-family', $tokens, '必须提供统一字体栈 token');
        $this->assertStringNotContainsStringIgnoringCase('simsun', $tokens['--tpl-font-family'], '字体栈不得回退宋体');
    }

    // ---------------------------------------------------------
    // 3) 变量拼写/未定义回归（曾导致行高失效、充值弹窗背景透明）
    // ---------------------------------------------------------

    public function test_all_consumed_tpl_vars_are_defined()
    {
        $dir = ROOT_PATH . self::CSS_DIR;
        $this->assertIsReadable($dir);
        // 定义集合 = 全部 CSS 文件里出现过的 `--name:` 声明
        // （全局 token 在 public-head-main.css 两个块；各页面 CSS 允许定义局部变量）
        $defined = [];
        foreach (glob($dir . '*.css') as $file) {
            $css = file_get_contents($file);
            foreach (preg_match_all('/(--[\w-]+)\s*:/', $css, $m) ? $m[1] : [] as $def) {
                $defined[$def] = true;
            }
        }
        $undefined = [];
        foreach (glob($dir . '*.css') as $file) {
            $css = file_get_contents($file);
            foreach (preg_match_all('/var\((--[\w-]+)/', $css, $m) ? $m[1] : [] as $var) {
                if (!isset($defined[$var])) {
                    // 有 fallback 的消费不视为错误
                    if (!preg_match('/var\(' . preg_quote($var, '/') . '\s*,/', $css)) {
                        $undefined[basename($file) . ' → ' . $var] = true;
                    }
                }
            }
        }
        $this->assertSame(
            [],
            array_keys($undefined),
            "存在消费了未定义且无 fallback 的 CSS 变量：" . implode(', ', array_keys($undefined))
        );
    }

    public function test_known_var_typos_stay_fixed()
    {
        $this->assertStringNotContainsString(
            '--tpl-line-height-body',
            $this->readTpl(self::CSS_DIR . 'user-head-early.css'),
            '拼写错误的 --tpl-line-height-body 不得回归（真名 --tpl-line-body）'
        );
        $this->assertStringNotContainsString(
            'var(--tpl-bg-accent)',
            $this->readTpl(self::CSS_DIR . 'widget-recharge_modal.css'),
            '未定义的 --tpl-bg-accent 不得回归（充值弹窗会因此透明）'
        );
        $this->assertStringNotContainsString(
            'var(--tpl-bg-elevated,#fff)',
            $this->readTpl(self::CSS_DIR . 'actor-index.css'),
            '演员页不得用 #fff 硬编码 fallback（暗色主题漏网点）'
        );
    }

    // ---------------------------------------------------------
    // 4) 可达性与接线
    // ---------------------------------------------------------

    public function test_viewport_allows_user_scaling()
    {
        $html = $this->readTpl(self::HTML_DIR . 'public/include.html');
        $this->assertStringNotContainsString(
            'user-scalable=0',
            $html,
            '不得禁用用户缩放（无障碍）'
        );
        $this->assertStringNotContainsStringIgnoringCase('X-UA-Compatible', $html, 'IE 兼容 meta 应移除');
    }

    public function test_theme_refresh_sheet_is_linked_in_foot()
    {
        $html = $this->readTpl(self::HTML_DIR . 'public/foot.html');
        $this->assertStringContainsString('theme-refresh.css?v=', $html, '覆盖样式表必须在 foot.html 接线');
        $this->assertFileExists(ROOT_PATH . self::CSS_DIR . 'theme-refresh.css');
    }

    // ---------------------------------------------------------
    // 5) 结构完整性：改版触碰过的文件括号必须平衡
    // ---------------------------------------------------------

    public function test_edited_css_braces_balance()
    {
        $edited = [
            'public-head-main.css', 'public-head-early.css', 'user-head-early.css',
            'user-head-main.css', 'index.css', 'head.css', 'foot.css', 'friendLink.css',
            'actor-index.css', 'art-type.css', 'home-banner.css',
            'widget-recharge_modal.css', 'theme-refresh.css',
        ];
        foreach ($edited as $f) {
            $css = $this->readTpl(self::CSS_DIR . $f);
            $open = substr_count($css, '{');
            $close = substr_count($css, '}');
            $this->assertSame(
                $open,
                $close,
                "{$f} 括号不平衡（{$open} 开 / {$close} 闭）"
            );
        }
    }

    public function test_legacy_clash_colors_are_gone_from_core_sheets()
    {
        foreach (['public-head-main.css', 'user-head-main.css'] as $f) {
            $css = $this->readTpl(self::CSS_DIR . $f);
            $this->assertStringNotContainsString('#e12160', $css, $f . ' 仍含遗留玫瑰红 #e12160');
            $this->assertStringNotContainsString('#4c8fe8', $css, $f . ' 仍含遗留蓝 #4c8fe8');
            $this->assertStringNotContainsString('#40cc92', $css, $f . ' 仍含旧薄荷绿 #40cc92');
        }
    }

    // ---------------------------------------------------------
    // 6) 第二轮（frontend-design skill 引导）：双强调色与层级
    // ---------------------------------------------------------

    public function test_warm_accent_defined_and_passes_contrast()
    {
        $css = $this->readTpl(self::CSS_DIR . 'public-head-main.css');
        $light = $this->parseTokenBlock($css, '/^:root\{([^}]*)\}/');
        $dark = $this->parseTokenBlock($css, '/\.bstem,body\.bstem\{([^}]*)\}/');
        $this->assertArrayHasKey('--tpl-accent-warm', $light, ':root 缺少影院金 --tpl-accent-warm');
        $this->assertArrayHasKey('--tpl-accent-warm', $dark, '.bstem 缺少影院金 --tpl-accent-warm');
        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrastRatio($light['--tpl-accent-warm'], '#ffffff'),
            "亮色影院金 {$light['--tpl-accent-warm']} 白底对比度低于 WCAG AA 4.5"
        );
        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrastRatio($dark['--tpl-accent-warm'], $dark['--tpl-bg-base']),
            "暗色影院金 {$dark['--tpl-accent-warm']} 在 {$dark['--tpl-bg-base']} 上对比度低于 WCAG AA 4.5"
        );
    }

    public function test_round2_component_layer_present()
    {
        $css = $this->readTpl(self::CSS_DIR . 'theme-refresh.css');
        // 标题层级：700 字重
        $this->assertMatchesRegularExpression(
            '/\.hu-section-head h2[^{]*\{[^}]*font-weight:\s*700/',
            $css,
            '区块标题必须有 700 字重（层级）'
        );
        // 卡片 hover 只做亮度/饱和微调，禁止 transform 缩放（零布局位移）
        $this->assertStringContainsString('.vodlist_item:hover .vodlist_thumb', $css);
        $this->assertStringContainsString('filter: brightness(1.06) saturate(1.05)', $css);
        $this->assertStringNotContainsString(
            '.vodlist_item:hover .vodlist_thumb' . '{' . 'transform',
            '卡片 hover 不得使用 transform 缩放（会产生布局位移）'
        );
        // 次级按钮为幽灵样式
        $this->assertMatchesRegularExpression(
            '/\.hu-section-head \.pc_more\s*\{[^}]*background:\s*transparent/',
            $css,
            '「更多」按钮必须为幽灵样式（透明底）'
        );
        // 搜索按钮为主按钮（主题色底 + 对比色文字）
        $this->assertMatchesRegularExpression(
            '/\.head_search_capsule_submit\s*\{[^}]*background:\s*var\(--tpl-accent\)/',
            $css,
            '搜索按钮必须是页面唯一主按钮（主题色底）'
        );
        // 评分走影院金
        $this->assertStringContainsString('color: var(--tpl-accent-warm)', $css, '排行榜评分必须使用影院金');
    }

    public function test_no_leftover_old_mint_rgba_in_home_sheets()
    {
        // 旧薄荷绿的 rgba 形式（token 换色时只换了 hex）必须从首页加载表中清除
        foreach (['head.css', 'public-head-early.css', 'black.css', 'home-banner.css', 'site-card-modal.css'] as $f) {
            $css = $this->readTpl(self::CSS_DIR . $f);
            $this->assertStringNotContainsString(
                'rgba(64,204,146',
                $css,
                $f . ' 仍含旧薄荷绿 rgba(64,204,146,...)，应改为 color-mix + token'
            );
            $this->assertStringNotContainsString('rgba(34,197,94', $css, $f . ' 仍含遗留 Tailwind 绿 rgba');
        }
    }

    public function test_frontend_design_skill_installed_in_project()
    {
        // skill 装在项目 .claude/skills/，保证后续会话原生可用
        $skill = ROOT_PATH . '.claude/skills/frontend-design/SKILL.md';
        $this->assertFileExists($skill, 'frontend-design skill 应安装在项目 .claude/skills/');
        $head = file_get_contents($skill, false, null, 0, 400);
        $this->assertStringContainsString('name: frontend-design', $head, 'SKILL.md 元数据不完整');
    }
}
