<?php
/**
 * 插件主类（单例 + 默认配置）。
 *
 * @package WP_AI_Article_Summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPAIAS_Plugin
 */
class WPAIAS_Plugin {

	/**
	 * 单例。
	 *
	 * @var WPAIAS_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * 后台实例。
	 *
	 * @var WPAIAS_Admin
	 */
	public $admin;

	/**
	 * 前端实例。
	 *
	 * @var WPAIAS_Frontend
	 */
	public $frontend;

	/**
	 * 获取单例。
	 *
	 * @return WPAIAS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * 启动。
	 */
	protected function boot() {
		load_plugin_textdomain( 'wp-ai-article-summary', false, dirname( WPAIAS_PLUGIN_BASENAME ) . '/languages' );

		if ( is_admin() ) {
			$this->admin = new WPAIAS_Admin();
			$this->admin->register();
		}

		$this->frontend = new WPAIAS_Frontend();
		$this->frontend->register();
	}

	/**
	 * 获取设置（合并默认值）。
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved   = get_option( WPAIAS_OPTION_KEY, array() );
		$defaults = self::get_default_settings();
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * 默认设置。
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			// Tab1.
			'enabled'            => 1,
			'mobile_enable'      => 1,
			'title'              => 'AI 智能摘要',
			'word_limit'         => 150,
			'position'           => 'before_content',
			'post_types'         => array( 'post' ),
			'exclude_categories' => array(),
			'exclude_post_ids'   => '',

			// 注入模式（兼容各种主题）。
			'insert_method'      => 'auto',
			'js_selector'        => '.entry-content, .post-content, .article-content, .single-content, .article__content, .post__content, .post-single .post-content, article .content-area, article.post .content, main article .entry-content, main .post-content, #content article, .single .article-content, .typo, .single-content-wrap, .post .content',
			'js_position'        => 'prepend',

			// Tab2.
			'provider'           => 'openai',
			'model'              => 'gpt-4o-mini',
			'custom_endpoint'    => '',
			'custom_model'       => '',
			'api_key'            => '',
			'temperature'        => 0.7,
			'max_tokens'         => 512,
			'prompt'             => '你是一位专业的中文文章编辑助手，请用简洁、客观、流畅的中文为以下文章生成一段摘要，字数控制在 {WORDS} 字以内，不要使用 Markdown 标记，不要重复标题，直接输出摘要正文：\n\n{CONTENT}',

			// Tab3.
			'animation'          => 'typewriter',
			'anim_duration'      => 800,
			'type_speed'         => 35,
			'anim_delay'         => 0,
			'cursor_enable'      => 1,
			'cursor_color'       => '#ffffff',
			'custom_css'         => '',

			// Tab4.
			'cache_ttl'          => 'forever',
		);
	}
}
