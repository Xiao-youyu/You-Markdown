# You Markdown

一个基于 PHP 语言开发的轻量、优雅、简洁的 Markdown 在线阅读器。

无需数据库，无需复杂配置，上传即用。将 `.md` 文件放入 `data/articles/` 目录，即可获得一个精美的在线阅读页面。

## ✨ 特性

- 📖 **Markdown 渲染** — 完整支持 GFM 语法，标题、列表、表格、代码块一应俱全
- 🎨 **代码高亮** — 基于 highlight.js，支持 JavaScript、Python、PHP、CSS、Bash 等多种语言
- 🔍 **全文搜索** — 支持按标题、摘要、标签搜索文档
- 📑 **文档目录** — 自动提取文章标题生成目录，支持快速跳转
- 💬 **评论系统** — 支持多级嵌套回复，QQ 头像自动获取，管理员后台管理
- 👤 **用户系统** — 注册 / 登录 / 个人资料编辑，支持站长管理
- 🔒 **安全防护** — IP 封禁、异常行为检测、自动封禁、评论频率限制
- 🎨 **主题色调整** — 通过调色盘自定义主题色
- ✏️ **字体设置** — 支持多种字体风格，字号可调
- 📤 **文档管理** — 独立管理界面，支持上传、编辑、删除文章
- 🌙 **明暗模式** — 支持亮色 / 暗色主题切换
- 📱 **响应式** — 桌面端侧边栏 + 移动端浮动按钮，自适应布局

## 📦 安装

### 环境要求

- PHP 7.4+
- Apache（需启用 `mod_rewrite`）或 Nginx

### 部署步骤

1. 下载或克隆本项目

```bash
git clone https://github.com/your-username/you-markdown.git
```

2. 将文件上传到你的 Web 服务器目录

3. 确保 `data/` 目录可写

```bash
chmod -R 755 data/
```

4. 确保 Apache 已启用 `mod_rewrite`，`.htaccess` 文件生效

5. 访问你的网站，开始使用！

### Nginx 配置

如果你使用 Nginx，需要在 `server` 块中添加：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## 🚀 首次使用

部署后首页会显示一篇 **「站长必看！！！」** 的置顶文章，请按以下步骤操作：

1. 使用默认账号登录后台（账号：`youyou`，密码：`youyou`）
2. 首次登录后系统会要求修改账号信息，请设置新密码
3. 在「文档上传」界面删除「站长必看！！！」文章
4. 开始上传你自己的文档

> 💡 管理后台入口：`/youyou/`

## 📂 目录结构

```
you-markdown/
├── index.php              # 主页面（阅读器 + API）
├── sc.php                 # 文档管理页面
├── api.php                # 评论 / 用户 API
├── comment.php            # 评论系统（旧版兼容）
├── check.php              # 自检工具（用完建议删除）
├── .htaccess              # URL 重写规则
├── LICENSE                # MIT 许可证
├── README.md              # 项目说明
├── css/
│   └── style.css          # 主样式表
├── js/
│   └── main.js            # 主脚本
├── data/
│   ├── articles/          # Markdown 文档目录
│   │   ├── 站长必看！！！.md
│   │   └── getting-started.md
│   ├── .config.json       # 站点配置
│   ├── .users.json        # 用户数据
│   ├── .comments/         # 评论数据
│   ├── .bans.json         # 封禁数据
│   ├── .logs.json         # 异常日志
│   └── .pinned.json       # 置顶文章列表
├── youyou/                # 管理后台
│   ├── index.php          # 后台首页
│   ├── config.php         # 网站配置
│   ├── bans.php           # 封禁管理
│   └── logs.php           # 异常日志
└── fonts/                 # 自定义字体
    ├── luoliti.ttf
    └── roundfont.ttf
```

## 📝 添加文章

将 `.md` 文件放入 `data/articles/` 目录即可。文件支持以下 META 元数据：

```markdown
<!--META{"category":"分类","tags":"标签1, 标签2","excerpt":"摘要","author":"作者","license":"CC BY-NC-SA 4.0","licenseUrl":"https://creativecommons.org/licenses/by-nc-sa/4.0/"}-->

# 文章标题

正文内容...
```

也可以通过管理后台的「文档上传」界面上传，支持：

- 📁 本地文件上传
- 🔗 URL 链接抓取
- 📋 直接粘贴内容

## 🔧 管理后台

管理后台入口：`/youyou/`

| 功能 | 说明 |
|------|------|
| 📄 文档上传 | 上传、编辑、删除文章，设置分类 / 标签 / 置顶 |
| ⚙️ 网站配置 | 修改网站标题、注册限制、评论开关、自动封禁 |
| ⚠️ 异常日志 | 查看频繁登录、刷评论、刷注册等异常行为 |
| 🚫 封禁管理 | 管理被封禁的 IP，支持按功能（注册 / 评论 / 登录）封禁 |

## 🎨 功能面板

顶栏提供以下功能：

| 按钮 | 功能 |
|------|------|
| 🔍 搜索 | 全文搜索文档 |
| ☰ 目录 | 文档列表 / 文章目录 |
| 🔤 字体 | 切换字体风格和大小 |
| 🌙 明暗 | 切换亮色/暗色模式 |
| 🎨 主题色 | 自定义主题色调 |

## 🎨 代码块

代码块支持：

- 语法高亮（highlight.js）
- 行号显示
- 语言标签
- 一键复制
- 亮色/暗色配色（One Light / Catppuccin Mocha）

## 📄 许可证

本项目基于 [MIT 许可证](LICENSE) 开源。

## 🙏 致谢

- [marked](https://github.com/markedjs/marked) — Markdown 解析器
- [highlight.js](https://github.com/highlightjs/highlight.js) — 语法高亮
- [QRCode.js](https://github.com/davidshimjs/qrcodejs) — 二维码生成
- [Mizuki](https://github.com/LyraVoid/Mizuki) — 项目 UI 设计灵感
