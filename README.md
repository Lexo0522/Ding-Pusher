# Ding Pusher 说明文档

[English](readme.txt)
![Version](https://img.shields.io/badge/version-v1.0.3-2563eb)
![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)

> 当前稳定版本：`v1.0.3`

Ding Pusher 是一款 WordPress 插件，用于自动检测新文章并通过钉钉机器人推送，同时支持新用户注册提示、去重、记录管理与导出。

## 功能
- 钉钉机器人配置：Webhook 与安全校验（关键词/加签/IP 白名单）。
- 触发场景：新文章发布、更新、新用户注册。
- 模板与占位符：文本/链接/Markdown 模板。
- 去重与记录管理：标记、清理、导出 CSV / XLSX。
- 导出自动清理：默认保留 24 小时。
- 失败重试与日志提示。
- 帮助页与中英文界面支持。

## 安装
1. 后台上传：进入“插件” → “安装插件” → “上传插件”，选择压缩包并启用。
2. FTP 上传：解压后上传到 `wp-content/plugins/` 并在后台启用。

## 配置
1. 创建钉钉群机器人并复制 Webhook。
2. 在后台 “Ding Pusher” → “设置” 中填入 Webhook。
3. 选择安全方式并填写关键词/加签/IP 白名单。
4. 选择消息类型与模板并保存。
5. 发送测试消息验证配置。

## 使用
- 在“推送记录”中查看已推送文章。
- 对记录进行标记、取消标记或清理。
- 通过记录页导出 CSV / XLSX。

## 导出说明
- 支持 CSV 与 XLSX 两种格式。
- XLSX 依赖 ZipArchive 或 PclZip，若不可用请使用 CSV。
- 导出文件默认保留 24 小时后自动清理。

## 常见问题
**Q:** 推送失败怎么办？
**A:** 请检查 Webhook 是否正确、安全校验是否匹配，以及服务器网络是否可访问钉钉。

**Q:** 为什么没有推送记录？
**A:** 记录仅在成功推送后生成，请先触发一次推送并查看日志。

**Q:** XLSX 导出不可用？
**A:** 请确认服务器已启用 ZipArchive 或 PclZip，或改用 CSV 导出。

## 更新日志



### v1.0.3

- 修复了主入口文件中的默认中文文案乱码
- 加强了核心层 Webhook 校验逻辑
- 改造导出下载链路为受控下载

### v1.0.2
- XLSX 导出加入 ZipArchive/PclZip 兼容与可用性提示。
- 语言加载更稳定，完善英文翻译与帮助页面。
- 帮助页补充快速开始、配置清单与排查说明。

### v1.0.1
- 重构插件主入口，拆分核心类与更新器类。
- 补充卸载清理逻辑并优化后台文案。
- 修复部分推送流程细节。

### v1.0.0
- 初始版本发布。
- 支持新文章推送、去重与新用户提示。
- 支持多种消息类型。

## 技术支持
- 插件文档：https://github.com/Lexo0522/Ding-Pusher
- 问题反馈：https://github.com/Lexo0522/Ding-Pusher/issues
- 联系作者：kate522@88.com

## 许可证
本插件采用 GPLv2 或更高版本许可证，详见 `LICENSE`。
