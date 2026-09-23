# 目录结构设计

> 原则：**不引入构建链**（生产无 Node），分层靠目录约定 + `<link>` 顺序规范；新文件一律**可读格式**（多行、有注释），存量压缩文件随迁移逐个退役。

## 1. 目标结构（`template/default/`）

```
template/default/
├── asset/
│   ├── css/
│   │   ├── theme/                     # ① 设计系统层（全站加载，顺序固定）
│   │   │   ├── tokens.css             #    所有 --tpl-* 变量（唯一事实源，亮+暗两块）
│   │   │   ├── base.css               #    reset/元素默认/字体应用（自 public-head-main 拆出）
│   │   │   └── theme-refresh.css      #    覆盖层/渐进增强（已存在 ✅）
│   │   ├── components/                # ② 组件层（一组件一文件，按需 <link>）
│   │   │   ├── vod-card.css
│   │   │   ├── rank-list.css
│   │   │   ├── filter-bar.css
│   │   │   ├── button.css
│   │   │   ├── modal.css
│   │   │   └── ...
│   │   ├── pages/                     # ③ 页面层（重命名自现有散文件）
│   │   │   ├── home.css               #    ← index.css
│   │   │   ├── home-banner.css
│   │   │   ├── vod-detail.css
│   │   │   ├── vod-play.css
│   │   │   ├── type-show.css
│   │   │   └── ...
│   │   ├── legacy/                    # ④ 待退役区（只减不增，迁走一个删一个引用）
│   │   │   ├── public-head-early.css  #    127KB 大盘，P2 按页面拆迁
│   │   │   ├── user-head-early.css
│   │   │   ├── public-head-main.css   #    token 拆出后剩余规则并入 base/components
│   │   │   ├── user-head-main.css     #    与 public 镜像合并后删除
│   │   │   └── black.css              #    暗色补丁，规则逐条回迁 theme 层后删除
│   │   └── fonts/
│   │       └── iconfont.css           # 唯一 @font-face（P1 合并 4 家族后）
│   ├── js/
│   │   ├── vendor/                    # jquery、swiper、lottie（按页加载）
│   │   ├── core/                      # head-sync、mac 基础库、公共工具
│   │   ├── components/                # 对应 css/components 的行为（vod-card.js...）
│   │   └── pages/                     # index-home-parts、vod-play 等页面脚本
│   └── images/
├── html/
│   ├── public/    # 页面骨架（include/head/foot/jsvar/错误页）
│   ├── widget/    # 原子+业务组件（P3 起带契约注释头）
│   ├── module/    # 页面区块（banner、filter、hom_type…）
│   ├── vod/ art/ manga/ actor/ user/ …   # 路由页面（维持 ThinkPHP 约定，不动）
│   └── layout/    # P3 新增：显式布局壳（pc-default/mobile-default）
└── config.defaults.json.example
```

## 2. `<link>` 加载顺序规范（级联即架构）

```
① theme/tokens.css → ② theme/base.css → ③ fonts/iconfont.css
→ ④ components/*.css（本页所用） → ⑤ pages/*.css（本页）
→ ⑥ theme/theme-refresh.css（永远最后，收口覆盖）
```

- 全站公共部分（①②③⑥）在 `public/include.html` + `public/foot.html` 引入；④⑤ 由各页面模板引入（现状已如此，规范的是**层内不再互引**）。
- 每个文件引用必须带 `?v=` 日期版本号（YYYYMMDD + 序号），改动即跳版 ✅（已有实践）。

## 3. 命名规则

| 对象 | 规则 | 示例 |
|---|---|---|
| CSS/JS 文件 | kebab-case，`{域}-{组件}.css` | `vod-card.css`、`filter-bar.js` |
| CSS 类 | 前缀分域：布局无前缀 / 组件 `mac-{name}__{part}--{mod}`（新代码 BEM，存量不强改） | `.mac-rank__item--top1` |
| 状态类 | `is-*` / `has-*` | `.is-active`、`.has-error` |
| JS 钩子 | 只挂 `data-*`，禁用 class 当 JS 选择器（行为与样式解耦） | `data-mac-modal="login"` |
| 模板文件 | 现有 ThinkPHP 约定不变（`{controller}/{action}.html`） | `vod/type.html` |

## 4. 迁移映射（存量 → 目标）

| 现文件 | 去向 | 阶段 |
|---|---|---|
| `public-head-main.css` token 块 | `theme/tokens.css` | P1 |
| `public-head-main.css` 其余规则 | base + 各 components 拆分 | P2 |
| `user-head-main.css` | 与 public 镜像合并，差异部分进 `pages/user-*.css` | P2 |
| `public-head-early.css`（127KB） | 按页面拆迁到 `pages/`，全局存活规则进 base | P2（最重） |
| `black.css` | 规则逐条改为 token 消费后并入各层（.bstem 作用域重定义已由 tokens.css 承担大半） | P2 |
| `index.css` | `pages/home.css`（重命名 + 拆 skeleton 进 components/skeleton.css） | P2 |
| `iconfont-alicdn.css` + 3 处同名 `@font-face` | 合并为 `fonts/iconfont.css` + 码位映射表 | P1 |
| `head.css`（61KB） | 拆 `components/header-nav.css` + `components/search-capsule.css` | P2 |
| `public-home-stack.js` / `user-home-stack.js` | 合并参数化单文件 | P3 |
| 双 header 模板 | `layout/` 壳 + 响应式单 header | P4 |

## 5. 禁止事项（stylelint/code-review 强制）

1. `legacy/` 目录新增文件。
2. 新增单行压缩文件（无 sourcemap 的不可读资产一律不收）。
3. 页面层样式反向覆盖组件层内部选择器。
4. `z-index`、裸色值、非 token 间距/圆角（白名单：reset 内 0/1px 等结构性值）。
