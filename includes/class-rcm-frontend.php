<?php

defined( 'ABSPATH' ) || exit;

final class RCM_Frontend {
	private static bool $show = false;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ) );
	}

	public static function enqueue(): void {
		if ( ! is_user_logged_in() || ! RCM_Permissions::can_use_frontend() || ! self::page_allowed() ) {
			return;
		}
		self::$show = true;
		$settings = RCM_Permissions::settings();
		wp_enqueue_style( 'rcm-chat', RCM_URL . 'assets/css/chat.css', array(), RCM_VERSION );
		wp_enqueue_style( 'rcm-frontend', RCM_URL . 'assets/css/frontend.css', array( 'rcm-chat' ), RCM_VERSION );
		wp_enqueue_script( 'rcm-chat-core', RCM_URL . 'assets/js/chat-core.js', array(), RCM_VERSION, true );
		wp_enqueue_script( 'rcm-frontend-chat', RCM_URL . 'assets/js/frontend.js', array( 'rcm-chat-core' ), RCM_VERSION, true );
		wp_localize_script( 'rcm-chat-core', 'RCM_CONFIG', array(
			'restUrl'       => esc_url_raw( rest_url( 'rolechat/v1/' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'mode'          => 'frontend',
			'currentUserId' => get_current_user_id(),
			'pollInterval'  => max( 2000, (int) $settings['poll_interval_ms'] ),
			'accent'        => sanitize_hex_color( $settings['frontend_widget_accent'] ) ?: '#229ED9',
			'labels'        => RCM_Admin::js_labels(),
		) );
		wp_add_inline_style( 'rcm-frontend', ':root{--rcm-accent:' . esc_attr( sanitize_hex_color( $settings['frontend_widget_accent'] ) ?: '#229ED9' ) . ';}' );
	}

	private static function page_allowed(): bool {
		$settings = RCM_Permissions::settings();
		$page_id  = is_singular() ? get_queried_object_id() : 0;
		$exclude  = array_filter( array_map( 'absint', explode( ',', (string) $settings['frontend_exclude_pages'] ) ) );
		if ( $page_id && in_array( $page_id, $exclude, true ) ) { return false; }
		if ( ! empty( $settings['frontend_show_everywhere'] ) ) { return true; }
		$include = array_filter( array_map( 'absint', explode( ',', (string) $settings['frontend_include_pages'] ) ) );
		return $page_id && in_array( $page_id, $include, true );
	}

	public static function render(): void {
		if ( ! self::$show ) { return; }
		$settings = RCM_Permissions::settings();
		$position = 'left' === $settings['frontend_widget_position'] ? 'left' : 'right';
		?>
		<div id="rcm-widget" class="rcm-widget rcm-widget-<?php echo esc_attr( $position ); ?>" data-position="<?php echo esc_attr( $position ); ?>">
			<button type="button" id="rcm-widget-toggle" class="rcm-widget-toggle" aria-expanded="false" aria-controls="rcm-widget-panel" aria-label="<?php esc_attr_e( 'Open chat', 'rolechat-messenger' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 3H4a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h3v3l4-3h9a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Zm-3 9H7v-2h10v2Zm0-4H7V6h10v2Z"/></svg>
				<span class="rcm-widget-badge" hidden>0</span>
			</button>
			<section id="rcm-widget-panel" class="rcm-widget-panel" hidden aria-label="<?php echo esc_attr( $settings['frontend_widget_title'] ); ?>">
				<header class="rcm-widget-topbar"><div><strong><?php echo esc_html( $settings['frontend_widget_title'] ); ?></strong><span><?php echo esc_html( $settings['frontend_widget_greeting'] ); ?></span></div><button type="button" class="rcm-widget-close" aria-label="<?php esc_attr_e( 'Close chat', 'rolechat-messenger' ); ?>">×</button></header>
				<div id="rcm-frontend-app" class="rcm-app rcm-app-frontend"><div class="rcm-loading-card"><span class="rcm-spinner"></span><span><?php esc_html_e( 'Loading messages…', 'rolechat-messenger' ); ?></span></div></div>
			</section>
		</div>
		<?php
	}
}
