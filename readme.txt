=== 九流 - AI智能文章摘要特效插件 ===
Contributors: jiuliu
Tags: ai, summary, openai, gemini, deepseek, claude, qwen, kimi, doubao, glm, animation, typewriter, cache
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

自动在文章顶部插入 AI 智能摘要，支持 16 家主流厂商、三级联动模型选择、10 种文字动画特效、完整缓存系统与暗黑极简卡片风格。

== Description ==

* 自动在文章顶部（标题下方、正文上方）插入 AI 智能摘要
* 三级联动：API 服务商 → 模型 → API Key（OpenAI / Gemini / DeepSeek / Claude / Kimi / 通义千问 / 文心一言 / 豆包 / 火山方舟 / 星火 / GLM / 360 / Mistral / Grok / OpenRouter / 自定义接口）
* 内置 10 种文字动画特效（打字机、淡入、滑入、缩放、弹跳、逐行渐入、霓虹呼吸 ...）
* 全站缓存 + 单文章缓存 + 编辑自动清缓存 + 后台精细化管理
* 暗黑极简卡片风格，自适应移动端，兼容 Zibll、Astra、Divi 等主流主题
* 安全规范：Nonce 校验 / 权限校验 / 输入过滤 / 输出转义 / 卸载自动清理

== Installation ==

1. 把整个 `wp-ai-article-summary` 目录上传到 `/wp-content/plugins/`
2. 在 WP 后台 → 插件 中启用
3. 在左侧菜单「首页与加载开屏」中配置 API 与样式

== Changelog ==

= 1.0.2 =
* 🎯 主题兼容性大幅增强：新增「注入模式」设置（自动 / 仅 the_content / 仅 JS 注入 / 仅短代码 / 完全手动）。
* 🎯 新增 wp_footer 模板 + JS DOM 智能注入，兼容 Zibll / Astra / Divi / Elementor / 块编辑器主题 / FSE 等绕过 the_content 的商用主题。
* 🎯 新增 MutationObserver 监听，自动适配 SPA / 懒加载 / 延迟渲染主题。
* 🎯 新增 `[wpaias_summary]` 短代码与 `wpaias_render_summary()` 模板函数，便于主题作者手动放置。
* 🎯 新增 CSS 选择器与注入位置（prepend/append/before/after）可配置。
* 🛡️ 内置 20+ 主流主题文章容器选择器作为兜底（.entry-content、.post-content、.typo、.elementor-widget-theme-post-content 等）。

= 1.0.1 =
* 修复：分 Tab 提交时其它 Tab 设置被意外清空的问题（保存 API 设置不会再关闭全局开关等）。
* 修复：自定义接口仅填写 Base URL 时连通性测试失败 / 返回内容为空的问题（OpenAI 协议下自动追加 /chat/completions，Claude 自动追加 /v1/messages）。

= 1.0.0 =
* 初版发布。
