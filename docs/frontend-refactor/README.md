# MacCMS10 前端重构总体方案

> 版本：v1.0（2026-09-23）｜范围：`template/default` 前台模板及其静态资源
> 配套文档：[design-tokens.md](design-tokens.md)｜[components.md](components.md)｜[directory-structure.md](directory-structure.md)｜[task-checklist.md](task-checklist.md)｜[acceptance.md](acceptance.md)

---

## 1. 背景与范围

本项目前台是 **ThinkPHP 5.0 服务端渲染模板**（非 SPA）：236 个模板文件（`.html`，含 ThinkPHP 模板语法）、96 个 CSS（共 1.3MB）、84 个 JS（共 1.7MB）、14 个字体文件。生产部署**不依赖 Composer/Node 构建链**，这是硬约束——重构方案不得引入必须在线编译的资产形态。

2026-09 已完成两轮止血性改版（分支 `worktree-frontend-visual-refresh`，commit `b0fe035` + `d7c1697`）：

- **P0-a 设计 token 收敛**：重写 `public-head-main.css` / `user-head-main.css` 亮暗两套 `:root`/`.bstem` 块——主题绿 `#0e7f57`（白底 5.0:1，原 2.04:1）、圆角 25→12px（收敛 401 个消费点）、正文 12→14px、宋体回退移除、新增层级 token；清剿玫瑰红 `#e12160`（34 处）与遗留蓝 `#4c8fe8`（24 处）；修复暗色白底面板 5 类、CSS 变量拼写/未定义 bug 3 个、`user-scalable=0` 禁缩放。
- **P0-b frontend-design skill 打磨轮**：双强调色（绿=交互、影院金=价值，均达 WCAG AA）、标题 700 字重层级、卡片 hover 收敛（filter 微调、零位移）、按钮主次分级（搜索=唯一主按钮，其余幽灵化）、首页 5 表 28 处旧绿 rgba 清剿为 `color-mix` + token。
- **配套基建**：`theme-refresh.css` 覆盖层（焦点环/选区/暗色滚动条/reduced-motion）；`tests/Unit/Template/ThemeRefreshCssTest.php` 14 用例（对比度阈值、token 完整性、旧色清零、括号平衡）；`.claude/skills/frontend-design/` 项目级 skill。

本方案在此之上规划 P1–P5 的**结构性重构**。

---

## 2. 现状诊断（按痛点分类）

### 2.1 架构混乱：三代设计系统叠加，无单一事实源

| 层 | 来源 | 特征 | 现状 |
|---|---|---|---|
| 底层 | 2013–2021 MacCMS 原版 | 玫瑰红/宋体/25px 药丸圆角/`!important` 军备竞赛（head.css 196 处、black.css 91 处） | 大部分已 token 化，`!important` 尚未拆 |
| 中层 | 后补 token 层 | 仅 20 个变量，覆盖率 <5% | **已重写为完整 token**（P0 完成） |
| 上层 | 2026 新写的 banner/详情页 | 质量高但自成体系 | 已并入统一 token |

衍生问题：`user-head-main.css` 与 `public-head-main.css` 是 589 条规则级的近似镜像（用户中心 vs 前台各养一份）；`black.css` 是 24KB 暗色补丁表，靠 300 条覆盖规则逐条打补丁，曾出现 13 种近黑背景。

### 2.2 组件复用差：两套渲染路径、无组件契约

- **服务端渲染**：`html/widget/` 下 40+ 碎片（`vod_box.html`、`rank_box_week.html`…），但无契约文档——每个 widget 依赖哪些变量、哪些全局 class，只能读源码反推。
- **客户端渲染**：首页 banner/分类区/排行榜由 `index-home-parts.js`（35KB 压缩）拉 `api.php` 动态拼 DOM——**同一张卡片，服务端 widget 和 JS 模板各写一份**，样式靠 class 名约等同步，改一处漏一处（历史上"封面副标题开关"修过两轮）。
- **PC/移动双 header**：`head_block_pc.html` 与 `head_block_mobile_default.html` 同时渲染、CSS 二选一，DOM 全量冗余下发。

### 2.3 样式污染：!important 军备竞赛 + 图标字体抢注

- `@font-face` 有 **6 处声明、4 个同名 `font-family:iconfont`**，按加载顺序最后者胜——任何 CSS 顺序调整都可能让 45 个硬编码 PUA 码位变成豆腐块；`.svg`/`.eot` 字体是已废弃格式。
- 23 处指向不存在文件的死 `url()`（`images/icons/` 整目录不存在、`paly.png` 拼写错误等）。
- 全站 16 种 `font-family`（P0 已收敛主栈，页面级散写仍在）。

### 2.4 交互不一致

- 8 种 transition 时长并存、`transition:all` ×17、缓动函数混用（P0 起新增代码已统一，存量未清）。
- 同屏 4 种以上圆角、21 种字号、54 种间距值（token 化后新增代码已收敛，存量在 P2 消化）。

### 2.5 响应式缺陷：断点碎片化 + rem 双轨制

- 实际断点 10 个（819/820/821 互相绕边界、768/769、991、1199/1200、1280、480）。
- rem 基准双轨：PC 端 `html{font-size:40px!important}` 固定；移动端 `head-sync.js` 内嵌 2015 版 `lib.flexible` 动态计算——同一 rem 值两端比例不一致。
- 布局非流式：`.container` 固定 1200px、`.header` min-width 1360px、`.marg` 1300px 三个魔数并存，1920 屏两侧留白 >300px。

### 2.6 代码冗余

- `public-home-stack.js` 与 `user-home-stack.js` 各 135KB、同尺寸不同哈希——同一栈的两份近似副本，应合并为一份参数化产物。
- `vendor-lottie.js` 305KB 全站加载（仅 logo 动画使用）；`vod-down.js` 124KB 只在下载页用。
- 死代码：`qire-*`/`ui-dialog`/`acLoading` 等老插件选择器在模板中零引用。

### 2.7 可维护性问题（最要命的一条）

**存量 CSS/JS 全部是单行压缩产物，仓库里没有可读源文件、无 sourcemap**：改一行要用脚本做字节级替换，diff 不可 review，冲突无法手工合并。P0 两轮已经这么干，不可持续——P1 必须先解决"源文件形态"问题，后续一切重构才有意义。

---

## 3. 重构总体策略

### 3.1 三条原则

1. **增量绞杀，不做全量重写**：以页面/资源为单元逐块替换，任意时刻主干可发布。引入新体系时新旧共存（覆盖层在前、逐文件迁移在后），迁移完一个删一个旧的。
2. **每阶段独立可回滚**：一个阶段 = 一组 commit + CSS `?v=` 版本号跳变。回滚 = `git revert` + 版本号回退；模板级灰度可用现有 `tplconfig` 配置按页切换新旧资源。
3. **门禁全程**：沿用已建立的 PHPUnit 文件断言基建（token 完整性、旧色清零、括号平衡）+ 新增 stylelint + 静态预览截图 diff（管线已建成，见 P5）。

### 3.2 阶段划分

| 阶段 | 主题 | 核心产出 | 依赖 |
|---|---|---|---|
| **P0（已完成）** | 止血：token 收敛 + 暗色修复 + a11y 底线 | 见 §1 | — |
| **P1** | 源文件形态 + 单一事实源 | tokens 层独立成文件、可读化首批核心 CSS、stylelint 立规 | P0 |
| **P2** | CSS 架构分层 + 暗色补丁拆解 | `black.css` 补丁退役为 token-scoped 规则、CSS 分层目录、页面级瘦身 | P1 |
| **P3** | 组件契约 + 双渲染路径合一 | widget 契约注释头、JS 卡片模板与服务端 widget 收敛为一份 DOM 定义 | P2 |
| **P4** | 响应式统一 | 断点收敛至 3 个、rem 双轨退役、流式容器 | P2 |
| **P5** | 工程化与性能 | lint/CI 门禁、视觉回归、资源分级加载、Lighthouse 基线 | P1–P4 |

详细任务与工作量见 [task-checklist.md](task-checklist.md)。

### 3.3 风险与回滚

| 风险 | 概率 | 缓解 | 回滚 |
|---|---|---|---|
| 压缩文件可读化过程中引入语法错误 | 中 | 每文件括号/长度断言 + 3 家浏览器冒烟（预览截图管线） | revert 单文件 commit |
| CSS 分层迁移改变加载顺序导致覆盖失效 | 高 | 迁移前后截图 diff 逐页对比；新层永远排在被替代文件之后 | 恢复原 `<link>` 顺序 |
| iconfont 家族合并后丢字形 | 高 | 先建 45 码位 → 字形映射表（测试断言），再合并 | 恢复 4 家族声明 |
| rem 双轨退役影响移动端缩放 | 中 | 新断点体系下灰度 `tplconfig` 按端切换 | 回退 head-sync.js |
| JS 合并参数化引入行为差异 | 中 | 合并前后以同一静态预览 + mock 数据对比 DOM | 双文件并存期内随时切回 |

---

## 4. 交付物索引

| 文档 | 内容 |
|---|---|
| [design-tokens.md](design-tokens.md) | 设计 Token 清单（色彩/字体/间距/圆角/阴影/层级/动效）+ 单源策略 |
| [components.md](components.md) | 原子与业务组件清单、组件 API（include 变量契约）、状态与可访问性规范 |
| [directory-structure.md](directory-structure.md) | 目标目录结构、命名规则、迁移映射 |
| [task-checklist.md](task-checklist.md) | P1–P5 分阶段任务清单（含工作量与验收钩子） |
| [acceptance.md](acceptance.md) | 重构验收标准（量化指标） |
