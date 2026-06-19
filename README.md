# You Markdown

一个基于 PHP 语言开发的轻量、优雅、简洁的 Markdown 在线阅读器。

无需数据库，无需复杂配置，上传即用。将 `.md` 文件放入 `data` 目录，即可获得一个精美的在线阅读页面。

## ✨ 特性

- 📖 **Markdown 渲染** — 完整支持 GFM 语法，标题、列表、表格、代码块一应俱全
- 🎨 **代码高亮** — 基于 highlight.js，支持 JavaScript、Python、PHP、CSS、Bash 等多种语言
- 🔍 **全文搜索** — 支持按标题、摘要、标签搜索文档
- 📑 **文档目录** — 自动提取文章标题生成目录，支持快速跳转
- 🎨 **主题色调整** — 通过调色盘自定义主题色
- ✏️ **字体设置** — 支持多种字体风格，字号可调
- 📤 **文档管理** — 独立管理界面，支持上传、编辑、删除文章

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

## 📂 目录结构

```
you-markdown/
├── index.php          # 主页面（阅读器 + API）
├── sc.php             # 文档管理页面
├── go.php             # 短链接跳转
├── .htaccess          # URL 重写规则
├── LICENSE            # MIT 许可证
├── README.md          # 项目说明
├── css/
│   └── style.css      # 主样式表
├── js/
│   └── main.js        # 主脚本
├── data/              # Markdown 文档目录
│   └── getting-started.md
└── fonts/             # 自定义字体
    ├── luoliti.ttf
    └── roundfont.ttf
```

## 🚀 使用

### 添加文章

将 `.md` 文件放入 `data/` 目录即可。文件支持以下 META 元数据：

```markdown
<!--META{"category":"分类","tags":"标签1, 标签2","excerpt":"摘要","author":"作者","license":"CC BY-NC-SA 4.0","licenseUrl":"https://creativecommons.org/licenses/by-nc-sa/4.0/"}-->

# 文章标题

正文内容...
```

### 文档管理

访问 `你的域名/sc.php` 进入管理界面。

**默认密码：`youyou`**

首次登录后系统会强制要求修改密码，请设置一个新密码。密码修改后，后续登录均使用新密码。

管理界面支持：

- 上传新文档（文件上传 / URL 抓取 / 直接粘贴）
- 编辑已有文章
- 删除文章
- 设置分类、标签、作者、许可证书

### 功能面板

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
- [Mizuki](https://github.com/LyraVoid/Mizuki) — 代码块和暗色模式设计灵感
