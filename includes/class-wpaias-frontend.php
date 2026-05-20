<?php
/**
 * 前端展示：自动插入文章顶部 AI 摘要卡片。
 *
 * @package WP_AI_Article_Summary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPAIAS_Frontend
 */
class WPAIAS_Frontend {

	/**
	 * 注册 hooks。
	 */
	public function register() {
		add_filter( 'the_content', array( $this, 'inject_summary' ), 9 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'print_inline_styles' ), 99 );

		// 前端 Ajax 兜底（首次访问自动生成；非登录用户也可使用）。
		add_action( 'wp_ajax_wpaias_front_generate', array( $this, 'ajax_front_generate' ) );
		add_action( 'wp_ajax_nopriv_wpaias_front_generate', array( $this, 'ajax_front_generate' ) );
	}

	/**
	 * 是否应该展示。
	 *
	 * @return bool
	 */
	public function should_show() {
		if ( is_admin() || is_feed() || is_search() || is_archive() || is_home() || is_front_page() ) {
			return false;
		}
		if ( ! is_singular() ) {
			return false;
		}

		$settings = WPAIAS_Plugin::get_settings();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		// 移动端开关。
		if ( wp_is_mobile() && empty( $settings['mobile_enable'] ) ) {
			return false;
		}

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		if ( ! in_array( $post->post_type, (array) $settings['post_types'], true ) ) {
			return false;
		}

		// 排除文章 ID。
		$exclude_ids = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', (string) $settings['exclude_post_ids'] ) ) ) );
		if ( in_array( (int) $post->ID, $exclude_ids, true ) ) {
			return false;
		}

		// 排除分类。
		if ( ! empty( $settings['exclude_categories'] ) ) {
			$cats = wp_get_post_categories( $post->ID );
			if ( array_intersect( array_map( 'intval', $cats ), array_map( 'intval', (array) $settings['exclude_categories'] ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * 注入文章顶部摘要。
	 *
	 * @param string $content 文章内容。
	 * @return string
	 */
	public function inject_summary( $content ) {
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( ! $this->should_show() ) {
			return $content;
		}

		$post     = get_post();
		$settings = WPAIAS_Plugin::get_settings();
		$cached   = WPAIAS_Cache::get( $post->ID );

		$html = $this->build_card_html( $post, $cached === false ? '' : (string) $cached, $settings );

		$position = isset( $settings['position'] ) ? $settings['position'] : 'before_content';

		switch ( $position ) {
			case 'after_first_paragraph':
				$pos = stripos( $content, '</p>' );
				if ( false !== $pos ) {
					return substr( $content, 0, $pos + 4 ) . $html . substr( $content, $pos + 4 );
				}
				return $html . $content;

			case 'after_title':
			case 'before_content':
			default:
				return $html . $content;
		}
	}

	/**
	 * 构建卡片 HTML。
	 *
	 * @param WP_Post $post     文章。
	 * @param string  $summary  已有缓存（空表示需要前端 ajax 拉取）。
	 * @param array   $settings 设置。
	 * @return string
	 */
	protected function build_card_html( $post, $summary, $settings ) {
		$title    = $settings['title'] !== '' ? $settings['title'] : __( 'AI 智能摘要', 'wp-ai-article-summary' );
		$anim     = $settings['animation'];
		$duration = (int) $settings['anim_duration'];
		$speed    = (int) $settings['type_speed'];
		$delay    = (int) $settings['anim_delay'];
		$cursor   = (int) $settings['cursor_enable'];
		$color    = $settings['cursor_color'];

		$state = ( '' === $summary ) ? 'loading' : 'ready';

		$data_attrs = sprintf(
			'data-post-id="%d" data-anim="%s" data-duration="%d" data-speed="%d" data-delay="%d" data-cursor="%d" data-color="%s" data-state="%s"',
			(int) $post->ID,
			esc_attr( $anim ),
			$duration,
			$speed,
			$delay,
			$cursor,
			esc_attr( $color ),
			esc_attr( $state )
		);

		ob_start();
		?>
		<aside class="wpaias-summary wpaias-anim-<?php echo esc_attr( $anim ); ?>" <?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="wpaias-summary__header">
				<span class="wpaias-summary__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 14.39 8.26 21 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.61-1.01z"/></svg>
				</span>
				<span class="wpaias-summary__title"><?php echo esc_html( $title ); ?></span>
				<span class="wpaias-summary__badge"><?php esc_html_e( '由 AI 生成', 'wp-ai-article-summary' ); ?></span>
			</div>
			<div class="wpaias-summary__body">
				<?php if ( '' === $summary ) : ?>
					<div class="wpaias-summary__placeholder">
						<span class="wpaias-dot"></span><span class="wpaias-dot"></span><span class="wpaias-dot"></span>
						<span class="wpaias-summary__loading-text"><?php esc_html_e( 'AI 摘要生成中…', 'wp-ai-article-summary' ); ?></span>
					</div>
					<div class="wpaias-summary__text" data-pending="1"></div>
				<?php else : ?>
					<div class="wpaias-summary__text"><?php echo esc_html( $summary ); ?></div>
				<?php endif; ?>
			</div>
		</aside>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * 入队前端资源。
	 */
	public function enqueue_assets() {
		if ( ! $this->should_show() ) {
			return;
		}

		wp_enqueue_style(
			'wpaias-frontend',
			WPAIAS_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			WPAIAS_VERSION
		);

		wp_enqueue_script(
			'wpaias-frontend',
			WPAIAS_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			WPAIAS_VERSION,
			true
		);

		$post = get_post();
		wp_localize_script(
			'wpaias-frontend',
			'WPAIAS_FRONT',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpaias_front_nonce' ),
				'post_id'  => $post ? (int) $post->ID : 0,
			)
		);
	}

	/**
	 * 内联自定义 CSS / 光标变量。
	 */
	public function print_inline_styles() {
		if ( ! $this->should_show() ) {
			return;
		}
		$settings = WPAIAS_Plugin::get_settings();
		$color    = $settings['cursor_color'];
		$duration = max( 100, (int) $settings['anim_duration'] );
		$delay    = max( 0, (int) $settings['anim_delay'] );

		$css  = ':root{--wpaias-cursor-color:' . esc_attr( $color ) . ';--wpaias-anim-duration:' . $duration . 'ms;--wpaias-anim-delay:' . $delay . 'ms;}';
		$css .= "\n" . (string) $settings['custom_css'];

		echo '<style id="wpaias-inline-css">' . wp_strip_all_tags( $css ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Ajax：前端首次访问生成摘要。
	 */
	public function ajax_front_generate() {
		check_ajax_referer( 'wpaias_front_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( '无效文章。', 'wp-ai-article-summary' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			wp_send_json_error( array( 'message' => __( '文章不存在或未发布。', 'wp-ai-article-summary' ) ) );
		}

		// 命中缓存直接返回。
		$cached = WPAIAS_Cache::get( $post_id );
		if ( false !== $cached ) {
			wp_send_json_success(
				array(
					'summary' => $cached,
					'cached'  => true,
				)
			);
		}

		$settings = WPAIAS_Plugin::get_settings();

		if ( empty( $settings['enabled'] ) ) {
			wp_send_json_error( array( 'message' => __( '插件未开启。', 'wp-ai-article-summary' ) ) );
		}

		$content = wp_strip_all_tags( (string) $post->post_content );
		$result  = WPAIAS_API::generate_summary( $content, $settings );

		if ( ! empty( $result['success'] ) ) {
			$ttl = WPAIAS_Cache::ttl_from_key( $settings['cache_ttl'] );
			WPAIAS_Cache::set( $post_id, $result['data'], $ttl );
			wp_send_json_success(
				array(
					'summary' => $result['data'],
					'cached'  => false,
				)
			);
		}

		// 失败不缓存。
		wp_send_json_error( array( 'message' => $result['message'] ) );
	}
}
