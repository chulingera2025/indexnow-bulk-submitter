# IndexNow Bulk Submitter

WordPress插件，用于批量提交sitemap中的所有URL到IndexNow。

## 功能特性

- **批量提交**：一键批量提交sitemap中的所有URL到IndexNow
- **自动解析**：支持sitemap索引文件，自动递归解析所有子sitemap
- **进度显示**：实时显示解析和提交进度
- **分批处理**：支持自定义批次大小，避免超时
- **集成IndexNow**：复用IndexNow插件的API密钥和提交逻辑

## 使用场景

如果你在安装IndexNow插件之前已经发布了很多文章，这些老文章不会被自动提交到IndexNow。使用本插件可以一键批量提交所有历史文章。

## 安装要求

- WordPress 5.0 或更高版本
- PHP 7.2 或更高版本
- **必须先安装并激活 [IndexNow Plugin](https://wordpress.org/plugins/indexnow/)**

## 安装步骤

1. 确保已安装并激活 IndexNow 插件
2. 将本插件文件夹上传到 `/wp-content/plugins/` 目录
3. 在WordPress后台"插件"页面激活插件
4. 在WordPress后台左侧菜单"IndexNow > 批量提交"中使用

## 使用方法

1. 进入 **IndexNow > 批量提交** 页面
2. 输入你的Sitemap URL（插件会自动检测常见的sitemap地址）
3. 设置批次大小（默认100，建议不超过1000）
4. 点击"开始批量提交"按钮
5. 等待提交完成，查看提交结果

## 插件结构

```
indexnow-bulk-submitter/
├── indexnow-bulk-submitter.php  # 主插件文件
├── includes/
│   └── class-sitemap-parser.php # Sitemap解析类
├── admin/
│   ├── class-admin-page.php     # 后台管理页面
│   ├── css/
│   │   └── admin.css            # 后台样式
│   └── js/
│       └── admin.js             # 后台JavaScript
└── README.md                    # 文档
```

## 技术说明

### Sitemap解析

- 支持标准的XML sitemap格式
- 支持sitemap索引文件（自动递归解析）
- 自动去重URL
- 验证URL有效性

### IndexNow提交

- 使用IndexNow插件的API密钥
- 提交到 `https://api.indexnow.org/indexnow/`
- 支持批量提交（每批最多10,000个URL）
- 自动处理HTTP响应

### AJAX处理

- 使用WordPress AJAX API
- Nonce验证确保安全
- 权限检查（需要 `manage_options` 权限）
- 使用transient临时存储URL列表

## 常见问题

**Q: 为什么提示"此插件需要先安装并激活 IndexNow 插件"？**

A: 本插件依赖IndexNow插件的API密钥和配置，必须先安装并激活IndexNow插件。

**Q: 批次大小设置多少合适？**

A: 建议设置100-500之间。太小会增加请求次数，太大可能导致超时。IndexNow API单次最多支持10,000个URL。

**Q: 提交失败怎么办？**

A: 检查：
1. IndexNow插件是否正确配置了API密钥
2. Sitemap URL是否可访问
3. 网站服务器是否能访问 `api.indexnow.org`
4. WordPress错误日志中是否有相关错误信息

**Q: 提交的URL会重复吗？**

A: IndexNow API会自动处理重复提交，不会造成问题。新发布的文章会由IndexNow插件自动提交，不需要重复使用本工具。

**Q: 支持哪些类型的Sitemap？**

A: 支持标准的XML sitemap格式，包括：
- 单个sitemap文件
- Sitemap索引文件（会自动解析所有子sitemap）
- WordPress默认的 `wp-sitemap.xml`
- 常见SEO插件生成的sitemap

## 更新日志

### 1.0.0
- 首次发布
- 支持批量提交sitemap中的URL
- 支持sitemap索引文件解析
- 实时进度显示
- 集成IndexNow插件

## 作者

Your Name

## 许可证

GPL-2.0+