<!--META{"category":"教程","tags":"入门, Markdown, 教程","excerpt":"欢迎来到 You Markdown！这篇文章将带你了解本站的所有功能，以及如何编写一篇漂亮的 Markdown 文档。","author":"You Markdown","license":"CC BY-NC-SA 4.0","licenseUrl":"https://creativecommons.org/licenses/by-nc-sa/4.0/"}-->

# 初次见面 👋

欢迎来到 **You Markdown**，一个轻量、优雅的 Markdown 在线阅读器。无论你是第一次接触 Markdown，还是经验丰富的写作者，这篇文章都会对你有所帮助。

---

## 🏠 关于本站

You Markdown 是一个极简的 Markdown 文档管理与阅读平台。你只需要上传 `.md` 文件，就能获得一个精美的在线阅读页面。

### 核心功能一览

| 功能 | 说明 |
|------|------|
| 📖 文档阅读 | 支持完整的 Markdown 渲染，标题、列表、表格、代码块一应俱全 |
| 🎨 代码高亮 | 支持 JavaScript、Python、PHP、CSS、Bash 等多种语言的语法高亮 |
| 📋 代码复制 | 每个代码块右上角都有复制按钮，一键复制代码 |
| 🔢 行号显示 | 代码块自动显示行号，方便阅读和引用 |
| 🔍 全文搜索 | 顶栏搜索框支持按标题、摘要、标签搜索文档 |
| 📑 文档目录 | 自动提取文章标题生成目录，支持快速跳转 |
| 🌓 明暗切换 | 支持亮色/暗色模式，浮动按钮区一键切换 |
| 🎨 主题色调整 | 通过调色盘自定义主题色，打造个性化阅读体验 |
| ✏️ 字体切换 | 支持默认字体和萝莉体两种风格，字号可调 |
| 📤 文档管理 | 独立的管理界面，支持上传、编辑、删除文章 |
| 🔗 短链接 | 每篇文章都有简洁的短链接，方便分享 |
| 📱 移动适配 | 完美适配手机端，随时随地阅读 |

---

## 📝 Markdown 基础语法

Markdown 是一种轻量级的标记语言，让你用纯文本就能写出格式丰富的文档。

### 标题

使用 `#` 号标记标题，一个 `#` 是一级标题，两个是二级标题，以此类推。

```markdown
# 一级标题
## 二级标题
### 三级标题
#### 四级标题
```

> 💡 建议一篇文章只使用一个一级标题（文章标题），正文从二级标题开始。

### 文本样式

```markdown
**这是粗体文字**
*这是斜体文字*
~~这是删除线~~
***这是粗斜体***
```

效果：
- **这是粗体文字**
- *这是斜体文字*
- ~~这是删除线~~

### 引用

使用 `>` 符号创建引用块：

```markdown
> 这是一段引用文字。
> 适合用来摘录名言或标注重要信息。
```

效果：
> 这是一段引用文字。
> 适合用来摘录名言或标注重要信息。

### 列表

**无序列表** 使用 `-`、`*` 或 `+`：

```markdown
- 第一项
- 第二项
  - 子项目
  - 子项目
- 第三项
```

**有序列表** 使用数字加点：

```markdown
1. 第一步
2. 第二步
3. 第三步
```

### 链接和图片

```markdown
[链接文字](https://example.com)
![图片描述](https://example.com/image.jpg)
```

### 表格

```markdown
| 列1 | 列2 | 列3 |
|-----|-----|-----|
| 数据1 | 数据2 | 数据3 |
| 数据4 | 数据5 | 数据6 |
```

---

## 💻 代码相关

You Markdown 对代码块有特别好的支持，包括语法高亮、行号显示和一键复制。

### 行内代码

使用反引号包裹：`console.log('Hello World')`

### 代码块

使用三个反引号包裹代码，并指定语言名称：

#### JavaScript 示例

```javascript
// 一个简单的异步函数
async function fetchData(url) {
    try {
        const response = await fetch(url);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('请求失败:', error);
    }
}

// 使用
fetchData('https://api.example.com/data')
    .then(data => console.log(data));
```

#### Python 示例

```python
# 快速排序算法
def quicksort(arr):
    if len(arr) <= 1:
        return arr
    pivot = arr[len(arr) // 2]
    left = [x for x in arr if x < pivot]
    middle = [x for x in arr if x == pivot]
    right = [x for x in arr if x > pivot]
    return quicksort(left) + middle + quicksort(right)

# 使用
numbers = [3, 6, 8, 10, 1, 2, 1]
print(quicksort(numbers))  # [1, 1, 2, 3, 6, 8, 10]
```

#### CSS 示例

```css
/* 毛玻璃效果 */
.glass-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}
```

#### PHP 示例

```php
<?php
// 读取 Markdown 文件并解析元数据
function parseMarkdownFile($filepath) {
    $content = file_get_contents($filepath);
    
    if (preg_match('/<!--META(.*?)-->/s', $content, $match)) {
        $meta = json_decode($match[1], true);
        $content = preg_replace('/<!--META.*?-->\n?/s', '', $content);
    }
    
    return [
        'meta' => $meta ?? [],
        'content' => $content
    ];
}
```

#### Bash 示例

```bash
#!/bin/bash
# 批量重命名文件
for file in *.txt; do
    new_name=$(echo "$file" | sed 's/old/new/g')
    mv "$file" "$new_name"
    echo "Renamed: $file -> $new_name"
done
```

---

## 🔧 进阶技巧

### 任务列表

```markdown
- [x] 已完成的任务
- [ ] 未完成的任务
- [ ] 另一个待办事项
```

### 分割线

使用三个或更多的 `-`、`*` 或 `_`：

```markdown
---
```

### 转义字符

如果需要显示 Markdown 语法符号本身，使用反斜杠 `\` 转义：

```markdown
\*这不是斜体\*
\[这不是链接\]
```

---

## 📌 写作建议

1. **结构清晰**：合理使用标题层级，让文章有明确的脉络
2. **善用代码块**：技术文章中，代码示例比文字描述更直观
3. **添加摘要**：上传时填写摘要，方便在首页快速预览内容
4. **合理使用标签**：标签帮助读者快速找到相关文章
5. **图文并茂**：适当添加图片和表格，提升阅读体验

---

## 🚀 开始创作

现在你已经了解了 You Markdown 的全部功能和 Markdown 的基本语法。

点击顶栏的 **上传按钮**，开始你的第一篇创作吧！

> 写作不难，难的是开始。—— 你已经在路上了 ✨
