# 设计 Token 清单

> 单一事实源策略：`template/default/asset/css/theme/tokens.css`（P1 从 `public-head-main.css` 的 `:root`/`.bstem` 块中独立出来）。所有新增代码**只允许消费 token，禁止裸写色值/圆角/间距魔法数**（stylelint 强制，见 task-checklist P1）。
> 值以 P0/P1 已落地的实现为基准，标注 ✅ 的已在 `master` 分支生效。

## 1. 色彩（语义 token）

### 1.1 品牌与强调

| Token | 亮色 | 暗色 | 用途 | WCAG |
|---|---|---|---|---|
| `--tpl-accent` ✅ | `#0e7f57` | `#3ecf8e` | 交互色：链接 hover、选中态、主按钮底 | 白底 5.9:1 / 暗底 8.4:1 |
| `--tpl-accent-hover` ✅ | `#0a6b48` | `#63dba4` | 交互色 hover | — |
| `--tpl-accent-soft` ✅ | `rgba(14,127,87,.08)` | `rgba(62,207,142,.10)` | 选中背景、幽灵按钮 hover 底 | — |
| `--tpl-accent-muted` ✅ | `rgba(14,127,87,.14)` | `rgba(62,207,142,.18)` | 边框级弱化 | — |
| `--tpl-accent-contrast` ✅ | `#fff` | `#06281b` | 强调色底上的文字 | — |
| `--tpl-accent-warm` ✅ | `#8a5a00` | `#f7bf33` | 第二强调色（影院金）：评分、VIP、榜单状元 | 白底 5.9:1 / 暗底 10.9:1 |

语义分工（来自 frontend-design skill 的双强调色判断）：**绿 = 可交互，金 = 有价值**。禁止混用。

### 1.2 文本

| Token | 亮色 | 暗色 | 用途 |
|---|---|---|---|
| `--tpl-text-strong` ✅ | `#14171c` | `#f5f7fa` | 标题、重点 |
| `--tpl-text` ✅ | `#2c3038` | `#d7dbe2` | 正文主色 |
| `--tpl-text-muted` ✅ | `#5c6672` | `#9aa3af` | 次级说明（卡片副标题） |
| `--tpl-text-subtle` ✅ | `#8b95a1` | `#79828e` | 弱化（时间戳、占位） |
| `--tpl-text-on-inverse` ✅ | `#fff` | `#fff` | 深色图/遮罩上的文字（banner 等） |

### 1.3 背景与边框（暗色即分层体系）

| Token | 亮色 | 暗色 | 层级语义 |
|---|---|---|---|
| `--tpl-bg-base` ✅ | `#fff` | `#131419` | 页面底 |
| `--tpl-bg-muted` ✅ | `#f6f7f9` | `#1a1c23` | 区块底（页脚、筛选条） |
| `--tpl-bg-elevated` ✅ | `#fff` | `#1f2129` | 卡片/浮层（比页面高一级） |
| `--tpl-bg-sunken` ✅ | `#f1f3f6` | `#0e0f13` | 凹陷（图片占位、输入底） |
| `--tpl-bg-login` ✅ | `#fff` | `#20222b` | 登录/表单区 |
| `--tpl-border` ✅ | `#e6e8ec` | `rgba(255,255,255,.10)` | 主边框 |
| `--tpl-border-soft` ✅ | `#eef0f3` | `rgba(255,255,255,.06)` | 弱边框/分隔线 |

> 暗色分层只允许这 5 档背景；原 13 种近黑（P0 已收敛）不得回归（有测试断言守护核心表）。

### 1.4 功能色（P1 新增）

| Token | 亮色 | 暗色 | 用途 |
|---|---|---|---|
| `--tpl-danger` | `#c0392b` | `#ff7871` | 错误、删除 |
| `--tpl-success` | `#0e7f57`（同 accent） | `#3ecf8e` | 成功（与品牌同源，不另造绿） |
| `--tpl-info` | `#2563a8` | `#7db3ea` | 提示 |
| `--tpl-overlay` | `rgba(16,24,40,.55)` | `rgba(0,0,0,.65)` | 遮罩 |

## 2. 字体

| Token | 值 | 说明 |
|---|---|---|
| `--tpl-font-family` ✅ | `system-ui,-apple-system,"Segoe UI",Roboto,"PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif` | 唯一主栈；**宋体不得作回退**（测试断言） |

类型阶梯（`--tpl-font-*` 为存量命名，P1 补全为阶梯）：

| 级 | Token | 值 | 用途 |
|---|---|---|---|
| display | `--tpl-font-display` | 28px / 行高 1.2 / 字重 700 ✅ | 区块标题（`hu-section-head h2`） |
| title | `--tpl-font-title` | 20px / 1.4 / 700 | 卡片标题、弹窗标题 |
| heading | `--tpl-font-heading` ✅ | 15px / 1.5 / 600 | 小标题、徽标文字 |
| body | `--tpl-font-body` ✅ | 14px / 1.57（22px） | 正文 |
| caption | `--tpl-font-caption` | 12px / 1.5 | 辅助说明（不低于 12px） |

## 3. 间距（4pt 网格，8 级）

| Token | 值 | 典型用途 |
|---|---|---|
| `--tpl-space-1` | 4px | 徽标内距、图标间距 |
| `--tpl-space-2` | 8px | 控件内距 |
| `--tpl-space-3` | 12px | 卡片内距、列表项间距 |
| `--tpl-space-4` | 16px | 卡片内距（大）、表单行距 |
| `--tpl-space-5` | 24px | 区块内距 |
| `--tpl-space-6` | 32px | 区块间距 |
| `--tpl-space-7` | 48px | 大区块分隔 |
| `--tpl-space-8` | 64px | 页面级分隔 |

> 存量 54 种间距值在 P2 随文件迁移就近吸附到最近档；不追求一次性全局替换。

## 4. 圆角

| Token | 值 | 用途 |
|---|---|---|
| `--tpl-radius-sm` ✅ | 8px | 徽标、小控件、输入框 |
| `--tpl-radius` ✅ | 12px | 卡片、按钮、面板（默认档） |
| `--tpl-radius-cover` ✅ | 8px | 封面图（与 sm 一致，语义独立便于单独调） |
| `--tpl-radius-pill` | 999px | 胶囊（仅限状态点/小标签，**大按钮禁止**——25px 药丸是历史包袱） |

## 5. 阴影（双主题双套）

| Token | 亮色 | 暗色 | 用途 |
|---|---|---|---|
| `--tpl-shadow-sm` ✅ | `0 1px 2px rgba(16,24,40,.05)` | `0 1px 2px rgba(0,0,0,.4)` | 卡片静态 |
| `--tpl-shadow-md` ✅ | `0 6px 16px -4px rgba(16,24,40,.10)` | `0 8px 20px -4px rgba(0,0,0,.5)` | hover/浮层 |
| `--tpl-shadow-lg` | `0 16px 40px -8px rgba(16,24,40,.16)` | `0 16px 44px -8px rgba(0,0,0,.6)` | 模态 |

## 6. 层级（z-index）

| Token | 值 | 用途 |
|---|---|---|
| `--tpl-z-dropdown` | 100 | 下拉、搜索联想 |
| `--tpl-z-sticky` | 200 | 吸顶 header、底部导航 |
| `--tpl-z-modal` | 1000 | 模态、toast |

> 禁止新增裸 z-index 魔法数（存量 P2 清理，现存最大值 ~9999）。

## 7. 动效

| Token | 值 | 用途 |
|---|---|---|
| `--tpl-motion-fast` | 120ms | 颜色/透明度过渡 |
| `--tpl-motion-base` | 200ms | hover、展开 |
| `--tpl-motion-slow` | 300ms | 弹层、轮播 |
| `--tpl-ease` | `cubic-bezier(.2,.7,.3,1)` | 统一缓出曲线 |

规则（frontend-design skill）：非用户触发的动效只允许一处（页面 hero）；hover 反馈属"响应用户操作"可保留但收敛（filter 微调，禁 transform 缩放布局）；全部受 `prefers-reduced-motion` 兜底 ✅（theme-refresh.css 全局规则）。

## 8. 命名与使用规则

1. token 命名 `--tpl-{类别}-{语义}`，暗色复用同名 token（`.bstem` 作用域重定义），**禁止** `--dark-xxx` 类命名。
2. 新增代码出现裸 `#hex`、裸 px 间距/圆角 → stylelint `declaration-property-value-disallowed-list` 报 error。
3. 带 alpha 的强调色一律 `color-mix(in srgb, var(--tpl-accent) N%, transparent)`，不写死 rgba。
4. token 变更必须同步更新 `tests/Unit/Template/ThemeRefreshCssTest.php` 对应断言（对比度、上限值）。
