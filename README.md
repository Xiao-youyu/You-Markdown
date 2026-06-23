# You Markdown

轻量、优雅的 PHP Markdown 在线阅读器。无需数据库，上传即用。

## 特性

- Markdown 渲染 + 代码高亮 + 一键复制
- 全文搜索 + 文档目录 + 短链接分享
- 评论系统（多级回复、QQ 头像、访客评论）
- 用户系统（注册/登录/权限管理）
- 安全防护（IP 封禁、异常检测、频率限制）
- 文档管理（上传/编辑/删除/ZIP 批量导入）
- 版本更新（GitHub 自动检测、一键更新）
- 明暗模式 + 主题色 + 字体切换
- 响应式布局，移动端适配

## 环境要求

- PHP 7.4+
- Apache（启用 `mod_rewrite`）或 Nginx

## 部署

1. 上传文件到 Web 目录
2. 设置 `data/` 目录可写：`chmod -R 755 data/`
3. Nginx 需添加配置：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ /\. { deny all; }
location ~ ^/data/ { deny all; }
```

## 首次使用

1. 登录后台 `/youyou/`（默认账号：`youyou` / `youyou`）
2. 首次登录后修改密码
3. 删除「站长必看！！！」文章
4. 开始上传你的文档

## 添加文章

- 将 `.md` 文件放入 `data/articles/` 目录
- 或通过后台「文档上传」界面上传（支持本地文件、ZIP、URL 抓取、粘贴）

文章支持 META 元数据：

```markdown
<!--META{"category":"分类","tags":"标签1,标签2","excerpt":"摘要","author":"作者"}-->
# 标题
正文...
```

## 管理后台

入口：`/youyou/`

- 文档上传：管理文章，设置分类/标签/置顶
- 网站配置：标题、注册、评论、自动封禁、频率限制
- 版本更新：检查更新、切换通道（正式版/测试版）、一键更新
- 异常日志：查看异常行为记录
- 封禁管理：管理 IP 封禁

## 许可证

[MIT](LICENSE)

## 致谢

- [marked](https://github.com/markedjs/marked) — Markdown 解析器
- [highlight.js](https://github.com/highlightjs/highlight.js) — 语法高亮
- [QRCode.js](https://github.com/davidshimjs/qrcodejs) — 二维码生成
- [Mizuki](https://github.com/LyraVoid/Mizuki) — 项目 UI 设计灵感
