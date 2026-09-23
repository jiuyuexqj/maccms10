# 分阶段重构任务清单

> 工作量为单人净投入估算；每项完成即合入主干（带测试），不等阶段整体完工。
> 门禁：全程 PHPUnit 全绿 + 改动文件带 `?v=` 跳版 + 涉及视觉的项附预览截图对比。

## P1 源文件形态与单一事实源（约 3–4 人日）

- [ ] **P1-1 拆出 `theme/tokens.css`**：从 `public-head-main.css` / `user-head-main.css` 抽出两套 token 块合一，`include.html` 在最前引入；同步删除两 main 表内的 token 块（避免三处定义）。验收：`ThemeRefreshCssTest` 改为从 tokens.css 解析。
- [ ] **P1-2 补全 token**：功能色 4 个、字号阶梯 4 档、间距 8 档、z-index 3 档、动效 4 档（见 design-tokens.md）。
- [ ] **P1-3 stylelint 立规**：`npm devDependency`（仅本地/CI，生产不受影响）：禁裸 hex、禁 `transition:all`、禁新增 `!important`、缩进/换行强制（新文件可读化的机械保障）。
- [ ] **P1-4 iconfont 统一**：盘点 45 个 PUA 码位实际字形使用 → 生成码位映射表 `docs/frontend-refactor/iconfont-map.md` → 合并 4 个同名 `@font-face` 为 `fonts/iconfont.css`（保留含 class 映射的 ccq3 版字体文件），删除 `.eot/.svg` 废格式。验收：全站图标截图 diff 无豆腐块。
- [ ] **P1-5 核心文件可读化**：`tokens.css`、`base.css`、`theme-refresh.css` ✅、`components/*` 一律多行可读格式；建立"压缩文件只减不增"约定写入 CLAUDE.md §5。

## P2 CSS 架构分层与暗色补丁拆解（约 8–10 人日，最重阶段）

- [ ] **P2-1 `user-head-main.css` 并入公共层**：与 public 镜像 diff，差异规则进 `pages/user-*`；文件删除。验收：用户中心 5 页截图 diff。
- [ ] **P2-2 `black.css` 退役**：91 条 `!important` 规则逐条改写为 token 消费（`.bstem` 作用域内大多数可直接删——tokens 已承担）；删一条跑一次暗色截图。目标：文件清空移入 legacy/ 删除。
- [ ] **P2-3 `public-head-early.css` 拆迁**（127KB → 按页面）：先以"页面引用计数"排序，从引用最少的页面开始（live/plot/website…），每页抽出该页规则建 `pages/{page}.css`，原文件删除已迁规则。**每迁 3–5 页合入一次**。
- [ ] **P2-4 `head.css` 拆分**：header-nav / search-capsule / head-user 三组件文件。
- [ ] **P2-5 组件 CSS 建立**：components/ 目录落 button/badge/vod-card/rank-list/filter-bar/modal/skeleton 七件（从现有规则迁移+按 components.md 规范补状态）。
- [ ] **P2-6 `!important` 存量清理**：head.css 196 处随 P2-4 处理；全站目标 <20 处（确有覆盖优先级需求的保留并注释原因）。
- [ ] **P2-7 死代码清除**：23 处死 `url()`、`qire-*`/`ui-dialog`/`acLoading` 等零引用选择器（模板 grep 验证零引用后删）。

## P3 组件契约与双渲染路径合一（约 5–6 人日）

- [ ] **P3-1 widget 契约注释头**：40+ widget 补齐（模板见 components.md §0），重点是数据依赖与消费 class。
- [ ] **P3-2 VodCard 双路径收敛**：服务端 `vod_box.html` 输出同时挂 `#js-vodcard-tpl` 隐藏模板；`index-home-parts.js` 改 `cloneNode` 填充。验收：改 DOM 结构一处，SSR/CSR 两处同步变化（截图对比）。
- [ ] **P3-3 rank_box 5 合 1**：`$period` 参数化，label/rank 页与首页共用。
- [ ] **P3-4 JS 合并**：public/user 两个 home-stack 合一（diff 后参数化）；`vendor-lottie.js` 305KB 改为仅 logo 页按需加载；`vod-down.js` 移入 pages/ 按页加载。
- [ ] **P3-5 可访问性补齐**：Tabs `role=tablist` + 键盘导航、分页 `aria-current`、模态焦点圈禁 + Esc、评分 `aria-label`。
- [ ] **P3-6 `layout/` 布局壳**：显式 pc-default / mobile-default 壳模板，为 P4 单 header 做准备。

## P4 响应式统一（约 4–5 人日）

- [ ] **P4-1 断点收敛**：10 个 → 3 个（`768px` 手机 / `1024px` 平板 / `1280px` 桌面），删除 819/821 绕边界 hack；`show-pc/show-mobile` 语义重检。
- [ ] **P4-2 rem 双轨退役**：新断点体系下全站统一基准（建议 `html{font-size:clamp(...)}` 或直接 px + container query），`lib.flexible` 仅保留给未迁移存量页面，加开关。
- [ ] **P4-3 流式容器**：`.container` 改 `max-width:1280px; margin-inline:auto; padding-inline:var(--tpl-space-4)`，删除 1200/1300/1360 三魔数。验收：1280/1440/1920 三宽度截图。
- [ ] **P4-4 单 header**：`layout/` 壳 + 一份 header DOM，替代 PC/mobile 双份下发。

## P5 工程化与性能（约 4–5 人日，可与 P2-P4 穿插）

- [ ] **P5-1 CI 门禁**：stylelint + PHPUnit（已有）+ 新增"前端资产预算"测试（单页 CSS 引用总 KB 上限，防回归性膨胀）。
- [ ] **P5-2 视觉回归管线产品化**：把本次建立的静态预览截图方案固化为脚本（mock 数据 → Edge headless → 亮/暗 × 关键 5 页），diff 像素差异超阈值报警。
- [ ] **P5-3 加载优化**：`public-head-early` 拆完后首页关键 CSS < 60KB；非关键 CSS `media="print" onload` 异步化；字体 `font-display:swap` + preload。
- [ ] **P5-4 性能基线**：Lighthouse 移动端 Perf ≥ 80、A11y ≥ 95 作为验收线（acceptance.md），记录基线报告进 docs。
- [ ] **P5-5 文档收尾**：CLAUDE.md §5 增补前端规范索引；删除 legacy/ 清空的文件。
