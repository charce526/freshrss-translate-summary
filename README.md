# FreshRSS 翻译与摘要

这是一个 FreshRSS 用户扩展，可通过 OpenAI 兼容 API 对文章内容进行中文翻译或生成中文摘要。

## 功能

- 一键翻译文章内容
- 一键生成中文摘要
- 支持 OpenAI 兼容接口
- 可自定义模型、翻译提示词和摘要提示词
- 适配 FreshRSS 明暗主题
- 界面与操作提示中文化

## 安装

1. 从 Releases 下载扩展压缩包。
2. 解压后确认目录名为 `freshrss-translate-summary`。
3. 将目录放入 FreshRSS 的扩展目录：

   ```text
   /var/www/FreshRSS/extensions/
   ```

4. 进入 FreshRSS 的“设置 → 扩展”，启用“FreshRSS 翻译与摘要”。

## 配置

进入扩展设置页，填写：

- API 基础地址，例如 `https://api.openai.com/v1`
- API 密钥
- 模型名称，例如 `gpt-4o`
- 翻译提示词
- 摘要提示词

保存后，打开任意文章即可使用“翻译”和“摘要”按钮。

## 版本 0.2.0

- 修复扩展设置保存时出现的 FreshRSS CSRF 403 错误
- 修复翻译与摘要请求因 JSON 提交导致的 CSRF 403 错误
- 将设置页、文章按钮和状态提示翻译为中文
- 改用 FreshRSS 官方会话 CSRF 接口

## 致谢

本扩展最初参考了 [xExtension-ArticleSummary](https://github.com/LiangWei88/xExtension-ArticleSummary)。
