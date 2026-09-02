<?php

defined( 'ABSPATH' ) || exit;

final class RCM_Activator {
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					self::activate_site();
				} finally {
					restore_current_blog();
				}
			}
			return;
		}

		self::activate_site();
	}

	private static function activate_site(): void {
		self::install_schema();
		self::install_capabilities();
		self::ensure_attachment_secret();

		if ( false === get_option( 'rcm_settings', false ) ) {
			add_option( 'rcm_settings', self::default_settings(), '', false );
		}
		update_option( 'rcm_db_version', RCM_DB_VERSION, false );

		self::ensure_cleanup_schedule();
	}

	public static function ensure_attachment_secret(): void {
		$existing = get_option( 'rcm_attachment_secret', '' );
		if ( is_string( $existing ) && '' !== $existing ) {
			return;
		}

		try {
			$secret = base64_encode( random_bytes( 32 ) );
		} catch ( Throwable $e ) {
			return;
		}
		// add_option() avoids overwriting a secret if concurrent activation requests race.
		add_option( 'rcm_attachment_secret', $secret, '', false );
	}

	public static function attachment_secret(): string {
		$encoded = get_option( 'rcm_attachment_secret', '' );
		if ( ! is_string( $encoded ) || '' === $encoded ) {
			self::ensure_attachment_secret();
			$encoded = get_option( 'rcm_attachment_secret', '' );
		}
		$decoded = is_string( $encoded ) ? base64_decode( $encoded, true ) : false;
		return is_string( $decoded ) && 32 === strlen( $decoded ) ? $decoded : '';
	}

	public static function deactivate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					self::deactivate_site();
				} finally {
					restore_current_blog();
				}
			}
			return;
		}

		self::deactivate_site();
	}

	private static function deactivate_site(): void {
		wp_clear_scheduled_hook( 'rcm_daily_cleanup' );
		wp_clear_scheduled_hook( 'rcm_cleanup_continuation' );
	}

	public static function ensure_cleanup_schedule(): void {
		if ( ! wp_next_scheduled( 'rcm_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'rcm_daily_cleanup' );
		}
	}

	public static function install_capabilities(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'rcm_manage_chat' );
			$admin->add_cap( 'rcm_moderate_chat' );
			$admin->add_cap( 'rcm_view_audit_log' );
		}
	}

	public static function install_schema(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$c = RCM_DB::table( 'conversations' );
		$m = RCM_DB::table( 'members' );
		$g = RCM_DB::table( 'messages' );
		$a = RCM_DB::table( 'attachments' );
		$r = RCM_DB::table( 'reactions' );
		$ct = RCM_DB::table( 'contacts' );
		$cc = RCM_DB::table( 'contact_categories' );
		$b = RCM_DB::table( 'blocks' );
		$rp = RCM_DB::table( 'reports' );
		$al = RCM_DB::table( 'audit_log' );

		$sql = array();
		$sql[] = "CREATE TABLE {$c} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			type varchar(20) NOT NULL DEFAULT 'direct',
			direct_key varchar(50) DEFAULT NULL,
			title varchar(190) NOT NULL DEFAULT '',
			avatar_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_message_at datetime DEFAULT NULL,
			last_message_id bigint(20) unsigned NOT NULL DEFAULT 0,
			meta longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY direct_key (direct_key),
			KEY type_status (type,status),
			KEY last_message_at (last_message_at),
			KEY created_by (created_by)
		) {$charset};";

		$sql[] = "CREATE TABLE {$m} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			member_role varchar(20) NOT NULL DEFAULT 'member',
			joined_at datetime NOT NULL,
			last_read_message_id bigint(20) unsigned NOT NULL DEFAULT 0,
			last_delivered_message_id bigint(20) unsigned NOT NULL DEFAULT 0,
			is_pinned tinyint(1) NOT NULL DEFAULT 0,
			is_archived tinyint(1) NOT NULL DEFAULT 0,
			muted_until datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY conversation_user (conversation_id,user_id),
			KEY user_id (user_id),
			KEY user_state (user_id,is_archived,is_pinned)
		) {$charset};";

		$sql[] = "CREATE TABLE {$g} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			sender_id bigint(20) unsigned NOT NULL,
			reply_to_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'text',
			content longtext NOT NULL,
			created_at datetime NOT NULL,
			edited_at datetime DEFAULT NULL,
			deleted_at datetime DEFAULT NULL,
			deleted_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id,id),
			KEY sender_id (sender_id),
			KEY created_at (created_at),
			KEY reply_to_id (reply_to_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$a} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			message_id bigint(20) unsigned NOT NULL DEFAULT 0,
			conversation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			uploader_id bigint(20) unsigned NOT NULL DEFAULT 0,
			storage_key varchar(64) NOT NULL DEFAULT '',
			file_name varchar(255) NOT NULL,
			mime_type varchar(120) NOT NULL DEFAULT '',
			file_size bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY message_id (message_id),
			KEY conversation_id (conversation_id),
			KEY uploader_pending (uploader_id,message_id),
			KEY storage_key (storage_key),
			KEY attachment_id (attachment_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$r} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			message_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			reaction varchar(20) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY message_user_reaction (message_id,user_id,reaction),
			KEY message_id (message_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$cc} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			name varchar(120) NOT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$ct} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			contact_user_id bigint(20) unsigned NOT NULL,
			category_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_contact (user_id,contact_user_id),
			KEY category_id (category_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$b} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			blocked_user_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_blocked (user_id,blocked_user_id),
			KEY blocked_user_id (blocked_user_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$rp} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reporter_id bigint(20) unsigned NOT NULL,
			message_id bigint(20) unsigned NOT NULL,
			reason varchar(1000) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'open',
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			reviewed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status_created (status,created_at),
			KEY message_id (message_id),
			KEY reporter_id (reporter_id)
		) {$charset};";

		$sql[] = "CREATE TABLE {$al} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(80) NOT NULL,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			details longtext DEFAULT NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY actor_id (actor_id),
			KEY action (action),
			KEY created_at (created_at),
			KEY object (object_type,object_id)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	public static function default_settings(): array {
		$roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		$matrix = array();
		$internal_roles = array( 'administrator', 'editor', 'author', 'contributor' );
		foreach ( $roles as $sender ) {
			foreach ( $roles as $recipient ) {
				// Least-privilege default: internal editorial roles can communicate mutually.
				// Frontend/subscriber routes are intentionally opt-in from the administrator matrix.
				$allow = in_array( $sender, $internal_roles, true ) && in_array( $recipient, $internal_roles, true );
				$matrix[ $sender ][ $recipient ] = array(
					'initiate' => $allow,
					'reply'    => $allow,
					'attach'   => $allow,
				);
			}
		}

		return array(
			'enabled'                        => true,
			'admin_chat'                     => true,
			'frontend_widget'                => true,
			'enabled_roles'                  => $roles,
			'frontend_roles'                 => array( 'subscriber' ),
			'group_creator_roles'            => array( 'administrator', 'editor', 'author', 'contributor' ),
			'role_matrix'                    => $matrix,
			'allow_groups'                   => true,
			'allow_attachments'              => true,
			'allow_reactions'                => true,
			'allow_edit'                     => true,
			'edit_window_minutes'            => 15,
			'allow_delete'                   => true,
			'delete_for_everyone_minutes'    => 60,
			'allow_forward'                  => true,
			'allow_mentions'                 => true,
			'allow_user_blocking'            => true,
			'show_presence'                  => true,
			'show_last_seen'                 => true,
			'browser_notifications'          => true,
			'sounds'                         => true,
			'max_attachment_mb'              => 10,
			'allowed_extensions'             => 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,txt,csv',
			'rate_limit_per_minute'          => 30,
			'upload_rate_limit_per_minute'   => 10,
			'retention_days'                 => 0,
			'admin_can_read_all'             => false,
			'frontend_widget_position'       => 'right',
			'frontend_widget_title'          => __( 'Messages', 'rolechat-messenger' ),
			'frontend_widget_greeting'       => __( 'How can we help?', 'rolechat-messenger' ),
			'frontend_widget_accent'         => '#229ED9',
			'frontend_show_everywhere'       => true,
			'frontend_include_pages'         => '',
			'frontend_exclude_pages'         => '',
			'poll_interval_ms'               => 3500,
			'delete_data_on_uninstall'       => false,
		);
	}
}
