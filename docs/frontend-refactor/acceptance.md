# 重构验收标准

> 三层验收：**硬门禁**（每次合入必须过）／**阶段验收**（每阶段完工度量）／**终态验收**（P5 收尾对照）。

## 1. 硬门禁（每次 commit）

| # | 项 | 判定 |
|---|---|---|
| G1 | PHPUnit 全量 | 全绿（含 `ThemeRefreshCssTest` 14+ 用例） |
| G2 | 改动 CSS 引用 | `?v=` 版本号跳变 |
| G3 | 视觉改动 | 附静态预览亮/暗截图对比（无对比不评审） |
| G4 | 新增 CSS | 可读格式（多行）；stylelint 0 error |
| G5 | 纯样式 commit | 不混入逻辑/模板结构变更（一 commit 一事） |

## 2. 阶段验收

### P1 验收
- [ ] 全站 token 定义只存在于 `theme/tokens.css` 一处（grep 断言）
- [ ] 45 个 iconfont 码位有映射表；4 同名 `@font-face` 合为 1；`.eot/.svg` 删除
- [ ] stylelint 在 CI 与 pre-commit 双通道生效
- [ ] 全站 10 页抽样截图与改造前 **diff ≤ 1% 像素差**（本阶段纯搬家不改样式）

### P2 验收
- [ ] `black.css`、`user-head-main.css` 文件删除；`legacy/` 为空或仅剩迁移中文件
- [ ] `!important` 总数：head.css 196 → **≤ 20**，black.css 91 → 0
- [ ] 首页 CSS 引用总量 ≤ 100KB（现状 ~350KB 公共层 + 页面层）
- [ ] 暗/亮双主题 13 页截图 diff 通过（无白底贴深色、无对比度回退）
- [ ] 死 `url()` 清零；零引用选择器清零（模板 grep 佐证）

### P3 验收
- [ ] 40+ widget 全部带契约注释头（抽查 10 个与实现一致）
- [ ] VodCard：改一处 DOM，SSR 首屏与 JS 懒加载区截图同步变化
- [ ] rank_box 5 → 1；home-stack 2 → 1；lottie 仅按需加载
- [ ] 键盘走查：Tabs/分页/模态/搜索 全程可操作、焦点可见

### P4 验收
- [ ] 断点 10 → 3（grep `@media` 归一统计）
- [ ] 1280 / 1440 / 1920 / 768 / 375 五宽度截图无横向滚动、无布局破碎
- [ ] 双击缩放可用（`user-scalable` 不受限）；rem 基准单轨
- [ ] header DOM 单份（grep `head_block_mobile_default` 仅存于 legacy）

### P5 验收（终态）
- [ ] Lighthouse（移动端模拟）：Performance ≥ 80，Accessibility ≥ 95，Best Practices ≥ 90
- [ ] 首屏关键 CSS ≤ 60KB；JS 首屏 ≤ 150KB（jQuery+swiper+core）
- [ ] 视觉回归管线纳入 CI 可 daily 跑
- [ ] CLAUDE.md 前端规范索引生效，legacy/ 清空

## 3. 设计质量验收（对照 frontend-design skill，人工评审）

- [ ] **对比度**：全站文本/交互色 ≥ 4.5:1（WCAG AA，机器断言已覆盖核心表）
- [ ] **双强调色纪律**：绿=交互、金=价值，抽查 20 处无混用
- [ ] **圆角/间距节奏**：同屏圆角 ≤ 3 种、间距吸附 4pt 网格（stylelint 保证新增，存量抽查）
- [ ] **动效克制**：非用户触发动画全站 ≤ 1 处；全部受 `prefers-reduced-motion` 兜底
- [ ] **无通用感特征**（skill 校准清单）：无 ALL-CAPS 装饰眉标、无 `A · B · C` 中点串滥用、链接不滥用 `→` 尾缀、无"一段 em-dash 命名"式标签
- [ ] **文案**：按钮=动作结果、错误信息含修复路径、全流程动作命名一致（抽 10 个流程）

## 4. 度量口径

- 截图 diff：静态预览管线（本仓库已建成）输出，像素差阈值 1%（P1 搬家期）/ 5%（正常迭代）
- 体积口径：`<link>`/`<script>` 声明体积（gzip 前），以首页与 vod/type、vod/detail 三页均值计
- 统计基线：P0 完成时快照已随 commit `b0fe035`/`d7c1697` 记录于测试断言与本文档
