# 核心组件清单 & 设计规范

> 本项目无前端框架，组件 = **ThinkPHP 模板 include**（服务端）+ **约定 class 命名的 CSS**（表现）+ **行为 JS 挂 data-\* 钩子**（交互）。
> 组件 API 即 include 变量（服务端）或 `data-*` 属性（客户端）。P3 起每个 widget 头部必须有契约注释。

## 0. 组件契约模板（P3 强制）

```html
{/*
  组件：VodCard 视频卡片
  依赖变量：$vo（vod 行记录，必填）；$mac_vod_playlink（全局）；$tplconfig.theme.badge.*（可选）
  消费 class：.vodlist_item/.vodlist_thumb/.vodlist_titbox（定义于 components/vod-card.css）
  行为钩子：无（纯展示；hover 由 CSS 完成）
  可访问性：a[title]；封面图 aria-hidden；状态徽标为文本
*/}
```

## 1. 原子组件（10 个）

| 组件 | 现状落点 | API（关键变量/属性） | 状态 | 可访问性 |
|---|---|---|---|---|
| **Button** | `theme-refresh.css`（pc_more/refresh/submit 三处分散 → P2 合并为 `.btn`） | 变体 `.btn-primary/.btn-ghost/.btn-text` + 尺寸 `.btn-sm` | hover/focus-visible/disabled | 焦点环 ✅；禁用用 `disabled` 属性而非只变灰 |
| **Badge 徽标** | `mac_badge_top_right.html` / `mac_badge_bottom_right.html` | `$mac_points_value`、`$vo.type_is_vip_exclusive`、`$vo.vod_class`（取首词） | — | 纯文本 ✅；VIP 用 `--tpl-accent-warm` |
| **SerialBadge 连载状态** | 同上 | `$vo.vod_isend` → 已完结/连载中 | — | 文本自带语义 |
| **Input 输入框** | `head_search_capsule_pc.html` 等 | `placeholder`、`name` | focus/filled/disabled | `label`/`aria-label` P3 补 |
| **Tabs 选项卡** | `index.html` latest-tab、`filter.html` 筛选 | `data-tab` + `setTab()`；P3 改 `role=tablist` | current/hover | 键盘左右切换 P3 补 |
| **Pagination 分页** | `public/paging.html` | `$page`、总页数 | current/disabled | `aria-current="page"` P3 补 |
| **Skeleton 骨架屏** | `index.css` home-skeleton ✅ | `data-section` + 渲染 JS 自动替换 | loading → content | `aria-busy` P3 补 |
| **Modal 模态** | `mac-pop-sheets.css`、site_card/recharge 两套 → P2 合一 | 打开：`data-mac-modal="id"` | open/close/加载失败 | 焦点圈禁 + Esc 关闭 P3 |
| **EmptyState 空态** | `public/notempty.html` ✅ | 无 | — | 文案给"下一步动作"指引（skill：空屏是行动邀请） |
| **Rating 评分** | `rating_svg.html` | `$vo.vod_score` | — | `aria-label="评分 X"` P3 补 |

## 2. 业务组件（8 个）

### 2.1 VodCard 视频卡片（最高频，两套渲染路径收敛的核心）

- **服务端**：`widget/vod_box.html` ✅；**客户端**：`index-home-parts.js` 内拼 DOM（P3 收敛，见下）
- 结构：封面（背景图 + 右上分类/VIP 徽标 + 左下连载徽标）→ 标题 → 副标题（主演/备注，`--tpl-text-muted` ✅）
- 交互：hover 时封面 `brightness(1.06) saturate(1.05)` + 标题切 accent ✅（禁 transform 缩放，测试断言守护）
- 尺寸档：竖版海报 2:3（默认）/ 横版 `mac-poster--v`
- **P3 收敛方案**：把卡片 DOM 定义抽为一份 `widget/vod_card_dom.html`，服务端直接 include；客户端渲染改为从隐藏容器 `#js-vodcard-tpl` 读取该 DOM 做模板（`cloneNode` + 字段填充），一处改两处生效

### 2.2 RankList 排行榜

- 现状：`widget/rank_box_{day,week,month,all,text}.html` 5 份高度雷同 + JS 渲染一份 → P3 参数化为单 `rank_box.html`（`$period` 变量）
- 结构：三列 `.ranklist_items`；条目 = 封面 + 序号（前三 podium 红橙黄 ✅）+ 标题 + **评分（`--tpl-accent-warm` 金色 ✅）** + 元信息
- 序号是真实序列信息，保留数字不加装饰（skill：结构装置必须编码信息）

### 2.3 FilterBar 筛选条

- `module/filter.html` + `mac_catalog_filter_*.html`
- 链接式选项（SEO 需要，保持 `<a>`）；选中态 = `--tpl-accent-soft` 底 + accent 文字 ✅
- P4：断点收敛后删除 819/821 绕边界 hack

### 2.4 BannerHero 首页主视觉（页面的"记忆点"，boldness 唯一投放处）

- `module/banner.html` + `home-banner.css`（新写层，质量最高）
- 4 种风格模板已参数化（`style_pc`/`style_h5`），维持现状；仅将星色/分数接 `--tpl-accent-warm`
- 全屏 scrim 中性黑渐变保持（skill 认可的 Netflix 式手法）

### 2.5 EpisodeList 剧集列表

- `widget/play_list_con.html`；当前集 `current`、hover accent
- P3：剧集按钮补 `aria-current`；长列表（>100 集）分折叠组

### 2.6 SearchCapsule 搜索胶囊

- `widget/head_search_capsule_{pc,mobile}.html`；提交按钮 = **全页唯一主按钮** ✅
- 联想下拉走 `--tpl-z-dropdown`；键盘 ↑↓ 选择 P3 补

### 2.7 UserNav 用户中心侧栏

- `user/left_nav.html`；选中态 accent + 左侧 3px 指示条（P2 实现）
- P4 响应式下抽屉化，替代双 header DOM 冗余方案

### 2.8 ActorCard 演员卡

- `actor/vod_actor.html`；头像圆 `50%`（唯一允许的圆形，语义=人物）

## 3. 通用规范

1. **一个组件一份 CSS**（`components/{name}.css`），只允许消费 token；页面差异用页面类作用域（`.page-vod-detail`）覆盖，禁止跨组件改别人样式。
2. **状态命名**：`is-*`（JS 切换）/ `has-*`（内容有无）/ 无前缀（结构）；禁用 `current/on/act` 混写（存量 P2 统一为 `is-active`）。
3. **交互反馈三件套**：hover 视觉变化 + `:focus-visible` 焦点环 ✅ + 点击后 200ms 内有响应（骨架或 loading 态）。
4. **空态/失败态是组件的一部分**：每个数据组件必须定义 empty（复用 `notempty.html`）与 error（"加载失败 + 重试按钮"）两态文案。
5. **文案**（skill writing 原则）：按钮写动作结果（"保存修改"非"提交"）；错误信息说明发生了什么怎么修；全流程同一动作同一名称。
