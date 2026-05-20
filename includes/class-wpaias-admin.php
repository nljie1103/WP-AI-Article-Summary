<?php
/**
 * 后台菜单、设置页面、Ajax、文章列表标记、编辑器侧边栏。
 *
 * @package WP_AI_Article_Summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPAIAS_Admin
 */
class WPAIAS_Admin {

	/**
	 * 菜单 slug。
	 */
	const MENU_SLUG = 'wpaias-settings';

	/**
	 * 注册 hooks。
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Ajax。
		add_action( 'wp_ajax_wpaias_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wpaias_generate_summary', array( $this, 'ajax_generate_summary' ) );
		add_action( 'wp_ajax_wpaias_clear_post_cache', array( $this, 'ajax_clear_post_cache' ) );
		add_action( 'wp_ajax_wpaias_clear_all_cache', array( $this, 'ajax_clear_all_cache' ) );
		add_action( 'wp_ajax_wpaias_clear_cache_by_id', array( $this, 'ajax_clear_cache_by_id' ) );

		// 文章列表标记列。
		add_filter( 'manage_posts_columns', array( $this, 'add_summary_column' ) );
		add_action( 'manage_posts_custom_column', array( $this, 'render_summary_column' ), 10, 2 );
		add_filter( 'manage_pages_columns', array( $this, 'add_summary_column' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_summary_column' ), 10, 2 );

		// 编辑器侧边栏（经典 meta box，兼容所有编辑器）。
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// 文章更新后清缓存。
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );

		// 插件页操作链接。
		add_filter( 'plugin_action_links_' . WPAIAS_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * 插件操作链接。
	 *
	 * @param array $links 链接数组。
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( '设置', 'wp-ai-article-summary' ) . '</a>' );
		return $links;
	}

	/**
	 * 注册顶级菜单（位置在 外观 与 插件 之间）。
	 */
	public function add_menu() {
		add_menu_page(
			__( 'AI智能文章摘要特效插件', 'wp-ai-article-summary' ),
			__( 'AI智能文章摘要特效插件', 'wp-ai-article-summary' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-animation',
			63 // 外观=60, 插件=65，63 居中。
		);
	}

	/**
	 * 注册设置（白名单）。
	 */
	public function register_settings() {
		register_setting(
			'wpaias_settings_group',
			WPAIAS_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => WPAIAS_Plugin::get_default_settings(),
			)
		);
	}

	/**
	 * 后台资源。
	 *
	 * @param string $hook 当前页面 hook。
	 */
	public function enqueue_assets( $hook ) {
		// 设置页加载。
		$is_settings_page = ( false !== strpos( (string) $hook, self::MENU_SLUG ) );
		$is_post_edit     = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		wp_register_style(
			'wpaias-admin',
			WPAIAS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPAIAS_VERSION
		);

		wp_register_script(
			'wpaias-admin',
			WPAIAS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WPAIAS_VERSION,
			true
		);

		if ( $is_settings_page || $is_post_edit ) {
			wp_enqueue_style( 'wpaias-admin' );
			wp_enqueue_script( 'wpaias-admin' );

			wp_localize_script(
				'wpaias-admin',
				'WPAIAS_ADMIN',
				array(
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'wpaias_admin_nonce' ),
					'providers'  => WPAIAS_Providers::js_map(),
					'i18n'       => array(
						'testing'    => __( '正在测试…', 'wp-ai-article-summary' ),
						'test_ok'    => __( '连通成功！', 'wp-ai-article-summary' ),
						'test_fail'  => __( '测试失败：', 'wp-ai-article-summary' ),
						'generating' => __( '生成中，请稍候…', 'wp-ai-article-summary' ),
						'gen_ok'     => __( '已生成并缓存。', 'wp-ai-article-summary' ),
						'gen_fail'   => __( '生成失败：', 'wp-ai-article-summary' ),
						'cleared'    => __( '已清空。', 'wp-ai-article-summary' ),
						'confirm'    => __( '确定执行该操作？', 'wp-ai-article-summary' ),
					),
				)
			);
		}
	}

	/**
	 * Sanitize 全部设置。
	 *
	 * 设置页采用分 Tab 渲染，单个 Tab 的表单只包含本 Tab 的字段。
	 * 为避免提交时把其它 Tab 的值清空：
	 *  - 从已保存的设置出发；
	 *  - 仅按当前 Tab 范围内的字段做覆盖；
	 *  - 复选框等"未提交即关闭"的字段也只在当前 Tab 范围内置 0。
	 *
	 * @param array $input 原始输入。
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = WPAIAS_Plugin::get_default_settings();
		$saved    = get_option( WPAIAS_OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		// 以"已保存值 + 默认值"为基线，避免任何字段被意外丢失。
		$out = wp_parse_args( $saved, $defaults );

		if ( ! is_array( $input ) ) {
			return $out;
		}

		// 当前提交来源的 Tab，确定哪些字段允许被覆盖。
		$current_tab = isset( $input['_current_tab'] ) ? sanitize_key( $input['_current_tab'] ) : '';
		$valid_tabs  = array( 'basic', 'api', 'anim', 'cache' );
		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = ''; // 兼容旧表单：覆盖全部字段。
		}

		$apply_basic = ( '' === $current_tab || 'basic' === $current_tab );
		$apply_api   = ( '' === $current_tab || 'api'   === $current_tab );
		$apply_anim  = ( '' === $current_tab || 'anim'  === $current_tab );
		$apply_cache = ( '' === $current_tab || 'cache' === $current_tab );

		// Tab1 — 基础设置。
		if ( $apply_basic ) {
			$out['enabled']       = ! empty( $input['enabled'] ) ? 1 : 0;
			$out['mobile_enable'] = ! empty( $input['mobile_enable'] ) ? 1 : 0;
			if ( isset( $input['title'] ) ) {
				$out['title'] = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['word_limit'] ) ) {
				$out['word_limit'] = max( 30, min( 1000, (int) $input['word_limit'] ) );
			}
			if ( isset( $input['position'] ) && in_array( $input['position'], array( 'before_content', 'after_title', 'after_first_paragraph' ), true ) ) {
				$out['position'] = $input['position'];
			}
			$out['post_types'] = array();
			if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
				foreach ( $input['post_types'] as $pt ) {
					$out['post_types'][] = sanitize_key( $pt );
				}
			}
			if ( empty( $out['post_types'] ) ) {
				$out['post_types'] = array( 'post' );
			}
			$out['exclude_categories'] = array();
			if ( isset( $input['exclude_categories'] ) && is_array( $input['exclude_categories'] ) ) {
				foreach ( $input['exclude_categories'] as $cid ) {
					$out['exclude_categories'][] = (int) $cid;
				}
			}
			if ( isset( $input['exclude_post_ids'] ) ) {
				$ids = preg_replace( '/[^0-9,\s]/', '', (string) $input['exclude_post_ids'] );
				$out['exclude_post_ids'] = trim( (string) $ids );
			}
		}

		// Tab2 — AI 接口设置。
		if ( $apply_api ) {
			$providers = WPAIAS_Providers::all();
			if ( isset( $input['provider'] ) && isset( $providers[ $input['provider'] ] ) ) {
				$out['provider'] = $input['provider'];
			}
			if ( isset( $input['model'] ) ) {
				$out['model'] = sanitize_text_field( $input['model'] );
			}
			if ( isset( $input['custom_endpoint'] ) ) {
				$out['custom_endpoint'] = esc_url_raw( trim( (string) $input['custom_endpoint'] ) );
			}
			if ( isset( $input['custom_model'] ) ) {
				$out['custom_model'] = sanitize_text_field( $input['custom_model'] );
			}
			if ( isset( $input['api_key'] ) ) {
				$out['api_key'] = trim( (string) $input['api_key'] );
			}
			if ( isset( $input['temperature'] ) ) {
				$out['temperature'] = max( 0, min( 2, (float) $input['temperature'] ) );
			}
			if ( isset( $input['max_tokens'] ) ) {
				$out['max_tokens'] = max( 32, min( 8192, (int) $input['max_tokens'] ) );
			}
			if ( isset( $input['prompt'] ) ) {
				$out['prompt'] = wp_kses_post( $input['prompt'] );
			}
		}

		// Tab3 — 动画。
		if ( $apply_anim ) {
			$valid_anim = array( 'none', 'typewriter', 'fade', 'slide-up', 'slide-down', 'zoom', 'bounce', 'line-fade', 'neon' );
			if ( isset( $input['animation'] ) && in_array( $input['animation'], $valid_anim, true ) ) {
				$out['animation'] = $input['animation'];
			}
			if ( isset( $input['anim_duration'] ) ) {
				$out['anim_duration'] = max( 100, min( 5000, (int) $input['anim_duration'] ) );
			}
			if ( isset( $input['type_speed'] ) ) {
				$out['type_speed'] = max( 5, min( 300, (int) $input['type_speed'] ) );
			}
			if ( isset( $input['anim_delay'] ) ) {
				$out['anim_delay'] = max( 0, min( 5000, (int) $input['anim_delay'] ) );
			}
			$out['cursor_enable'] = ! empty( $input['cursor_enable'] ) ? 1 : 0;
			if ( isset( $input['cursor_color'] ) ) {
				$c = sanitize_hex_color( $input['cursor_color'] );
				$out['cursor_color'] = $c ? $c : '#ffffff';
			}
			if ( isset( $input['custom_css'] ) ) {
				$out['custom_css'] = wp_strip_all_tags( (string) $input['custom_css'] );
			}
		}

		// Tab4 — 缓存。
		if ( $apply_cache ) {
			$valid_ttl = array( 'forever', '1day', '7days', '30days' );
			if ( isset( $input['cache_ttl'] ) && in_array( $input['cache_ttl'], $valid_ttl, true ) ) {
				$out['cache_ttl'] = $input['cache_ttl'];
			}
		}

		return $out;
	}

	/**
	 * 渲染设置页面。
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = WPAIAS_Plugin::get_settings();
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'basic'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs     = array(
			'basic'    => __( '基础设置', 'wp-ai-article-summary' ),
			'api'      => __( 'AI接口设置', 'wp-ai-article-summary' ),
			'anim'     => __( '动画特效', 'wp-ai-article-summary' ),
			'cache'    => __( '缓存管理', 'wp-ai-article-summary' ),
		);

		$cache_count = WPAIAS_Cache::count();
		$providers   = WPAIAS_Providers::all();
		?>
		<div class="wrap wpaias-wrap">
			<h1 class="wpaias-title">
				<span class="dashicons dashicons-animation"></span>
				<?php esc_html_e( '九流 — AI智能文章摘要特效插件', 'wp-ai-article-summary' ); ?>
				<span class="wpaias-ver">v<?php echo esc_html( WPAIAS_VERSION ); ?></span>
			</h1>
			<p class="wpaias-author">
				<?php esc_html_e( '作者：九流', 'wp-ai-article-summary' ); ?>
				· <a href="https://www.jiuliu.org" target="_blank" rel="noopener">www.jiuliu.org</a>
			</p>

			<h2 class="nav-tab-wrapper wpaias-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $key ) ); ?>"
						class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php" id="wpaias-form">
				<?php settings_fields( 'wpaias_settings_group' ); ?>
				<?php $opt = WPAIAS_OPTION_KEY; ?>
				<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_current_tab]" value="<?php echo esc_attr( $tab ); ?>">

				<?php if ( 'basic' === $tab ) : ?>
					<table class="form-table wpaias-table">
						<tr>
							<th><label><?php esc_html_e( '全局开关', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<label class="wpaias-switch">
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( 1, (int) $settings['enabled'] ); ?>>
									<span class="wpaias-slider"></span>
								</label>
								<span class="description"><?php esc_html_e( '是否启用 AI 摘要自动插入。', 'wp-ai-article-summary' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '摘要标题', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[title]" value="<?php echo esc_attr( $settings['title'] ); ?>">
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '摘要字数限制', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="number" min="30" max="1000" name="<?php echo esc_attr( $opt ); ?>[word_limit]" value="<?php echo esc_attr( $settings['word_limit'] ); ?>">
								<span class="description"><?php esc_html_e( '建议 80 ~ 300 字之间。', 'wp-ai-article-summary' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '显示位置', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<select name="<?php echo esc_attr( $opt ); ?>[position]">
									<option value="before_content" <?php selected( 'before_content', $settings['position'] ); ?>><?php esc_html_e( '文章正文上方（标题下方）', 'wp-ai-article-summary' ); ?></option>
									<option value="after_first_paragraph" <?php selected( 'after_first_paragraph', $settings['position'] ); ?>><?php esc_html_e( '第一段之后', 'wp-ai-article-summary' ); ?></option>
									<option value="after_title" <?php selected( 'after_title', $settings['position'] ); ?>><?php esc_html_e( '紧贴标题下方（与上者相同）', 'wp-ai-article-summary' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '应用文章类型', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<?php
								$post_types = get_post_types( array( 'public' => true ), 'objects' );
								foreach ( $post_types as $pt ) {
									if ( 'attachment' === $pt->name ) {
										continue;
									}
									$checked = in_array( $pt->name, (array) $settings['post_types'], true );
									echo '<label style="margin-right:12px;"><input type="checkbox" name="' . esc_attr( $opt ) . '[post_types][]" value="' . esc_attr( $pt->name ) . '" ' . checked( $checked, true, false ) . '> ' . esc_html( $pt->labels->singular_name ) . '</label>';
								}
								?>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '排除分类', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<?php
								$cats = get_categories( array( 'hide_empty' => false ) );
								if ( $cats ) :
									?>
									<div class="wpaias-cats">
										<?php foreach ( $cats as $cat ) :
											$cid = (int) $cat->term_id;
											$ck  = in_array( $cid, (array) $settings['exclude_categories'], true );
											?>
											<label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[exclude_categories][]" value="<?php echo esc_attr( $cid ); ?>" <?php checked( $ck ); ?>> <?php echo esc_html( $cat->name ); ?></label>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<span class="description"><?php esc_html_e( '暂无分类。', 'wp-ai-article-summary' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '排除文章 ID', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[exclude_post_ids]" value="<?php echo esc_attr( $settings['exclude_post_ids'] ); ?>" placeholder="例如：12,34,56">
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '移动端启用', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<label class="wpaias-switch">
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[mobile_enable]" value="1" <?php checked( 1, (int) $settings['mobile_enable'] ); ?>>
									<span class="wpaias-slider"></span>
								</label>
							</td>
						</tr>
					</table>

				<?php elseif ( 'api' === $tab ) : ?>
					<table class="form-table wpaias-table">
						<tr>
							<th><label><?php esc_html_e( 'API 服务商', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<select id="wpaias-provider" name="<?php echo esc_attr( $opt ); ?>[provider]">
									<?php foreach ( $providers as $pk => $pv ) : ?>
										<option value="<?php echo esc_attr( $pk ); ?>" <?php selected( $pk, $settings['provider'] ); ?>><?php echo esc_html( $pv['label'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<span class="description"><?php esc_html_e( '选择后下方自动加载对应模型清单。', 'wp-ai-article-summary' ); ?></span>
							</td>
						</tr>
						<tr id="wpaias-model-row">
							<th><label><?php esc_html_e( '模型', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<select id="wpaias-model" name="<?php echo esc_attr( $opt ); ?>[model]" data-current="<?php echo esc_attr( $settings['model'] ); ?>"></select>
							</td>
						</tr>
						<tr id="wpaias-custom-endpoint-row" style="display:none;">
							<th><label><?php esc_html_e( '自定义接口地址', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="wpaias-custom-endpoint" name="<?php echo esc_attr( $opt ); ?>[custom_endpoint]" value="<?php echo esc_attr( $settings['custom_endpoint'] ); ?>" placeholder="https://openrouter.ai/api/v1/chat/completions">
								<p class="description"><?php esc_html_e( '只填 Base URL（如 https://openrouter.ai/api/v1）也可以，系统会自动补全 /chat/completions。', 'wp-ai-article-summary' ); ?></p>
							</td>
						</tr>
						<tr id="wpaias-custom-model-row" style="display:none;">
							<th><label><?php esc_html_e( '自定义模型名', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="text" class="regular-text" id="wpaias-custom-model" name="<?php echo esc_attr( $opt ); ?>[custom_model]" value="<?php echo esc_attr( $settings['custom_model'] ); ?>" placeholder="gpt-3.5-turbo">
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'API Key', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="password" class="regular-text" id="wpaias-api-key" name="<?php echo esc_attr( $opt ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off">
								<button type="button" class="button" id="wpaias-toggle-key"><?php esc_html_e( '显示/隐藏', 'wp-ai-article-summary' ); ?></button>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '温度', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="number" step="0.1" min="0" max="2" name="<?php echo esc_attr( $opt ); ?>[temperature]" value="<?php echo esc_attr( $settings['temperature'] ); ?>">
								<span class="description"><?php esc_html_e( '0 ~ 2，越大越发散。', 'wp-ai-article-summary' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '最大 Token', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="number" min="32" max="8192" name="<?php echo esc_attr( $opt ); ?>[max_tokens]" value="<?php echo esc_attr( $settings['max_tokens'] ); ?>">
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '自定义 Prompt', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<textarea class="large-text" rows="6" name="<?php echo esc_attr( $opt ); ?>[prompt]"><?php echo esc_textarea( $settings['prompt'] ); ?></textarea>
								<p class="description"><?php esc_html_e( '占位符：{WORDS} = 字数限制；{CONTENT} = 文章正文。', 'wp-ai-article-summary' ); ?></p>
							</td>
						</tr>
						<tr>
							<th></th>
							<td>
								<button type="button" class="button button-secondary" id="wpaias-test-conn">
									<span class="dashicons dashicons-admin-network"></span>
									<?php esc_html_e( '一键测试连通性', 'wp-ai-article-summary' ); ?>
								</button>
								<span id="wpaias-test-result" class="wpaias-test-result"></span>
							</td>
						</tr>
					</table>

				<?php elseif ( 'anim' === $tab ) : ?>
					<table class="form-table wpaias-table">
						<tr>
							<th><label><?php esc_html_e( '入场动画', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<?php
								$anims = array(
									'none'       => __( '1. 普通直接显示（无动画）', 'wp-ai-article-summary' ),
									'typewriter' => __( '2/3. 打字机逐字 + 完成后光标消失', 'wp-ai-article-summary' ),
									'fade'       => __( '4. 全局淡入', 'wp-ai-article-summary' ),
									'slide-up'   => __( '5. 从下向上滑入', 'wp-ai-article-summary' ),
									'slide-down' => __( '6. 从上向下滑入', 'wp-ai-article-summary' ),
									'zoom'       => __( '7. 缩放淡入', 'wp-ai-article-summary' ),
									'bounce'     => __( '8. 轻微弹跳入场', 'wp-ai-article-summary' ),
									'line-fade'  => __( '9. 逐行渐入', 'wp-ai-article-summary' ),
									'neon'       => __( '10. 霓虹微光呼吸', 'wp-ai-article-summary' ),
								);
								foreach ( $anims as $val => $label ) {
									$checked = ( $settings['animation'] === $val );
									echo '<label class="wpaias-anim-item"><input type="radio" name="' . esc_attr( $opt ) . '[animation]" value="' . esc_attr( $val ) . '" ' . checked( $checked, true, false ) . '> ' . esc_html( $label ) . '</label>';
								}
								?>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '动画时长 (ms)', 'wp-ai-article-summary' ); ?></label></th>
							<td><input type="number" min="100" max="5000" name="<?php echo esc_attr( $opt ); ?>[anim_duration]" value="<?php echo esc_attr( $settings['anim_duration'] ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '打字速度 (ms/字)', 'wp-ai-article-summary' ); ?></label></th>
							<td><input type="number" min="5" max="300" name="<?php echo esc_attr( $opt ); ?>[type_speed]" value="<?php echo esc_attr( $settings['type_speed'] ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '动画延迟 (ms)', 'wp-ai-article-summary' ); ?></label></th>
							<td><input type="number" min="0" max="5000" name="<?php echo esc_attr( $opt ); ?>[anim_delay]" value="<?php echo esc_attr( $settings['anim_delay'] ); ?>"></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '光标闪烁', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<label class="wpaias-switch">
									<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[cursor_enable]" value="1" <?php checked( 1, (int) $settings['cursor_enable'] ); ?>>
									<span class="wpaias-slider"></span>
								</label>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '光标颜色', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="color" name="<?php echo esc_attr( $opt ); ?>[cursor_color]" value="<?php echo esc_attr( $settings['cursor_color'] ); ?>">
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '自定义 CSS', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<textarea class="large-text code" rows="6" name="<?php echo esc_attr( $opt ); ?>[custom_css]" placeholder=".wpaias-summary { ... }"><?php echo esc_textarea( $settings['custom_css'] ); ?></textarea>
							</td>
						</tr>
					</table>

				<?php elseif ( 'cache' === $tab ) : ?>
					<table class="form-table wpaias-table">
						<tr>
							<th><label><?php esc_html_e( '当前缓存数量', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<strong id="wpaias-cache-count" style="font-size:18px;"><?php echo esc_html( $cache_count ); ?></strong>
								<span class="description"><?php esc_html_e( '篇文章已生成摘要并缓存。', 'wp-ai-article-summary' ); ?></span>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '缓存过期时间', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<select name="<?php echo esc_attr( $opt ); ?>[cache_ttl]">
									<option value="forever" <?php selected( 'forever', $settings['cache_ttl'] ); ?>><?php esc_html_e( '永久', 'wp-ai-article-summary' ); ?></option>
									<option value="1day"    <?php selected( '1day', $settings['cache_ttl'] ); ?>><?php esc_html_e( '1 天', 'wp-ai-article-summary' ); ?></option>
									<option value="7days"   <?php selected( '7days', $settings['cache_ttl'] ); ?>><?php esc_html_e( '7 天', 'wp-ai-article-summary' ); ?></option>
									<option value="30days"  <?php selected( '30days', $settings['cache_ttl'] ); ?>><?php esc_html_e( '30 天', 'wp-ai-article-summary' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '清空全部缓存', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<button type="button" class="button button-secondary" id="wpaias-clear-all">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( '清空全部', 'wp-ai-article-summary' ); ?>
								</button>
								<span id="wpaias-clear-all-result" class="wpaias-test-result"></span>
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( '清空指定文章缓存', 'wp-ai-article-summary' ); ?></label></th>
							<td>
								<input type="number" min="1" id="wpaias-clear-id" placeholder="文章 ID">
								<button type="button" class="button" id="wpaias-clear-by-id"><?php esc_html_e( '清空', 'wp-ai-article-summary' ); ?></button>
								<span id="wpaias-clear-id-result" class="wpaias-test-result"></span>
							</td>
						</tr>
					</table>
				<?php endif; ?>

				<?php submit_button( __( '保存设置', 'wp-ai-article-summary' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Ajax：测试接口连通性。
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'wpaias_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足。', 'wp-ai-article-summary' ) ) );
		}

		$settings = WPAIAS_Plugin::get_settings();

		// 允许临时表单参数覆盖。
		$overrides = array(
			'provider'    => isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : $settings['provider'],
			'model'       => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : $settings['model'],
			'api_key'     => isset( $_POST['api_key'] ) ? trim( (string) wp_unslash( $_POST['api_key'] ) ) : $settings['api_key'],
			'endpoint'    => isset( $_POST['endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['endpoint'] ) ) : $settings['custom_endpoint'],
			'temperature' => isset( $_POST['temperature'] ) ? (float) wp_unslash( $_POST['temperature'] ) : $settings['temperature'],
			'max_tokens'  => 32,
			'prompt'      => '请回复"ok"两个字符，用于连通性测试。',
		);

		if ( 'custom' === $overrides['provider'] && empty( $overrides['model'] ) ) {
			$overrides['model'] = isset( $_POST['custom_model'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_model'] ) ) : $settings['custom_model'];
		}

		$result = WPAIAS_API::generate_summary( '这是一段用于连通性测试的内容。', $settings, $overrides );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success(
				array(
					'message' => __( '连通成功！', 'wp-ai-article-summary' ),
					'sample'  => mb_substr( (string) $result['data'], 0, 80 ),
				)
			);
		}

		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Ajax：生成或重新生成摘要。
	 */
	public function ajax_generate_summary() {
		check_ajax_referer( 'wpaias_admin_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( '无效文章。', 'wp-ai-article-summary' ) ) );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足。', 'wp-ai-article-summary' ) ) );
		}

		$force = ! empty( $_POST['force'] );
		if ( $force ) {
			WPAIAS_Cache::delete( $post_id );
		} else {
			$cached = WPAIAS_Cache::get( $post_id );
			if ( false !== $cached ) {
				wp_send_json_success(
					array(
						'message' => __( '已存在缓存。', 'wp-ai-article-summary' ),
						'summary' => $cached,
						'cached'  => true,
					)
				);
			}
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( '文章不存在。', 'wp-ai-article-summary' ) ) );
		}

		$settings = WPAIAS_Plugin::get_settings();
		$content  = wp_strip_all_tags( (string) $post->post_content );

		$result = WPAIAS_API::generate_summary( $content, $settings );
		if ( ! empty( $result['success'] ) ) {
			$ttl = WPAIAS_Cache::ttl_from_key( $settings['cache_ttl'] );
			WPAIAS_Cache::set( $post_id, $result['data'], $ttl );
			wp_send_json_success(
				array(
					'message' => __( '生成成功。', 'wp-ai-article-summary' ),
					'summary' => $result['data'],
					'cached'  => false,
				)
			);
		}

		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Ajax：清当前文章缓存（编辑页用）。
	 */
	public function ajax_clear_post_cache() {
		check_ajax_referer( 'wpaias_admin_nonce', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足。', 'wp-ai-article-summary' ) ) );
		}
		WPAIAS_Cache::delete( $post_id );
		wp_send_json_success( array( 'message' => __( '已清除。', 'wp-ai-article-summary' ) ) );
	}

	/**
	 * Ajax：清空全部。
	 */
	public function ajax_clear_all_cache() {
		check_ajax_referer( 'wpaias_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足。', 'wp-ai-article-summary' ) ) );
		}
		$n = WPAIAS_Cache::flush_all();
		wp_send_json_success(
			array(
				'message' => sprintf( /* translators: %d is the number of cleared items */ __( '已清空 %d 篇。', 'wp-ai-article-summary' ), $n ),
				'count'   => 0,
			)
		);
	}

	/**
	 * Ajax：按 ID 清空。
	 */
	public function ajax_clear_cache_by_id() {
		check_ajax_referer( 'wpaias_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( '权限不足。', 'wp-ai-article-summary' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( '请输入文章 ID。', 'wp-ai-article-summary' ) ) );
		}
		WPAIAS_Cache::delete( $post_id );
		wp_send_json_success(
			array(
				'message' => __( '已清除该文章缓存。', 'wp-ai-article-summary' ),
				'count'   => WPAIAS_Cache::count(),
			)
		);
	}

	/**
	 * 增加文章列表标记列。
	 */
	public function add_summary_column( $columns ) {
		$columns['wpaias_summary'] = __( 'AI 摘要', 'wp-ai-article-summary' );
		return $columns;
	}

	/**
	 * 渲染列。
	 */
	public function render_summary_column( $column, $post_id ) {
		if ( 'wpaias_summary' !== $column ) {
			return;
		}
		$cached = WPAIAS_Cache::get( $post_id );
		if ( false !== $cached ) {
			echo '<span class="wpaias-flag yes" title="' . esc_attr__( '已生成', 'wp-ai-article-summary' ) . '">●</span>';
		} else {
			echo '<span class="wpaias-flag no" title="' . esc_attr__( '未生成', 'wp-ai-article-summary' ) . '">○</span>';
		}
	}

	/**
	 * 添加编辑页 meta box。
	 */
	public function add_meta_box() {
		$settings   = WPAIAS_Plugin::get_settings();
		$post_types = (array) $settings['post_types'];
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'wpaias_meta_box',
				__( '九流 - AI 摘要', 'wp-ai-article-summary' ),
				array( $this, 'render_meta_box' ),
				$pt,
				'side',
				'high'
			);
		}
	}

	/**
	 * 渲染 meta box。
	 */
	public function render_meta_box( $post ) {
		$cached = WPAIAS_Cache::get( $post->ID );
		?>
		<div class="wpaias-mb" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<p>
				<?php if ( false !== $cached ) : ?>
					<span class="wpaias-mb-status ok">● <?php esc_html_e( '已有缓存', 'wp-ai-article-summary' ); ?></span>
				<?php else : ?>
					<span class="wpaias-mb-status no">○ <?php esc_html_e( '暂无缓存', 'wp-ai-article-summary' ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<button type="button" class="button button-primary wpaias-mb-gen"><?php esc_html_e( '生成 AI 摘要', 'wp-ai-article-summary' ); ?></button>
				<button type="button" class="button wpaias-mb-regen"><?php esc_html_e( '重新生成', 'wp-ai-article-summary' ); ?></button>
			</p>
			<p>
				<button type="button" class="button-link-delete wpaias-mb-clear"><?php esc_html_e( '清除当前文章缓存', 'wp-ai-article-summary' ); ?></button>
			</p>
			<div class="wpaias-mb-result" style="display:none;"></div>
			<div class="wpaias-mb-preview">
				<?php if ( false !== $cached ) : ?>
					<small style="display:block;margin-top:8px;color:#888;"><?php esc_html_e( '当前缓存预览：', 'wp-ai-article-summary' ); ?></small>
					<div class="wpaias-mb-text"><?php echo esc_html( wp_trim_words( (string) $cached, 60, '...' ) ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * 文章保存时清缓存。
	 */
	public function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post || 'auto-draft' === $post->post_status ) {
			return;
		}
		WPAIAS_Cache::delete( $post_id );
	}

	/**
	 * 删除文章时清缓存。
	 */
	public function on_delete_post( $post_id ) {
		WPAIAS_Cache::delete( $post_id );
	}
}
