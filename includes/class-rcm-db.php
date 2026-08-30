<?php

defined( 'ABSPATH' ) || exit;

final class RCM_DB {
	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'rcm_' . $name;
	}

	public static function now(): string {
		return current_time( 'mysql', true );
	}

	public static function get_settings(): array {
		$settings = get_option( 'rcm_settings', array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), RCM_Activator::default_settings() );
	}

	public static function update_settings( array $settings ): bool {
		return update_option( 'rcm_settings', $settings, false );
	}

	public static function user_display( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$settings      = self::get_settings();
		$last_seen     = (string) get_user_meta( $user->ID, '_rcm_last_seen', true );
		$presence      = (string) get_user_meta( $user->ID, '_rcm_presence', true ) ?: 'online';
		$last_seen_ts  = $last_seen ? strtotime( $last_seen . ' UTC' ) : 0;

		// A stored presence preference must not make an inactive browser look online forever.
		if ( 'offline' !== $presence && ( ! $last_seen_ts || $last_seen_ts < time() - 120 ) ) {
			$presence = 'offline';
		}

		return array(
			'id'           => (int) $user->ID,
			'name'         => $user->display_name ?: $user->user_login,
			'username'     => $user->user_login,
			'avatar'       => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
			'roles'        => array_values( (array) $user->roles ),
			'last_seen'    => ! empty( $settings['show_last_seen'] ) ? $last_seen : '',
			'presence'     => ! empty( $settings['show_presence'] ) ? $presence : '',
			'is_suspended' => RCM_Permissions::is_suspended( $user->ID ),
		);
	}

	public static function audit( int $actor_id, string $action, string $object_type = '', int $object_id = 0, array $details = array() ): void {
		global $wpdb;
		$wpdb->insert(
			self::table( 'audit_log' ),
			array(
				'actor_id'    => $actor_id,
				'action'      => sanitize_key( $action ),
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => $object_id,
				'details'     => wp_json_encode( $details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
				'ip_address'  => self::client_ip(),
				'created_at'  => self::now(),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return substr( $ip, 0, 45 );
	}

	public static function member_row( int $conversation_id, int $user_id ): ?object {
		global $wpdb;
		$table = self::table( 'members' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE conversation_id = %d AND user_id = %d LIMIT 1", $conversation_id, $user_id )
		);
		return $row ?: null;
	}

	public static function conversation_row( int $conversation_id ): ?object {
		global $wpdb;
		$table = self::table( 'conversations' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $conversation_id ) );
		return $row ?: null;
	}

	public static function conversation_members( int $conversation_id ): array {
		global $wpdb;
		$table = self::table( 'members' );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM {$table} WHERE conversation_id = %d ORDER BY joined_at ASC", $conversation_id ) );
		return array_map( 'intval', $ids );
	}

	public static function is_member( int $conversation_id, int $user_id ): bool {
		return null !== self::member_row( $conversation_id, $user_id );
	}

	public static function direct_key( int $user_a, int $user_b ): string {
		$ids = array( $user_a, $user_b );
		sort( $ids, SORT_NUMERIC );
		return $ids[0] . ':' . $ids[1];
	}

	public static function find_direct_conversation( int $user_a, int $user_b ): int {
		global $wpdb;
		$c = self::table( 'conversations' );
		$key = self::direct_key( $user_a, $user_b );
		$by_key = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$c} WHERE direct_key = %s AND type = 'direct' AND status = 'active' LIMIT 1", $key ) );
		if ( $by_key ) {
			return $by_key;
		}
		$m = self::table( 'members' );
		$sql = $wpdb->prepare(
			"SELECT c.id
			 FROM {$c} c
			 INNER JOIN {$m} m1 ON m1.conversation_id = c.id AND m1.user_id = %d
			 INNER JOIN {$m} m2 ON m2.conversation_id = c.id AND m2.user_id = %d
			 WHERE c.type = 'direct' AND c.status = 'active'
			 LIMIT 1",
			$user_a,
			$user_b
		);
		return (int) $wpdb->get_var( $sql );
	}

	public static function unread_total( int $user_id ): int {
		global $wpdb;
		$m = self::table( 'members' );
		$g = self::table( 'messages' );
		$sql = $wpdb->prepare(
			"SELECT COUNT(*)
			 FROM {$g} msg
			 INNER JOIN {$m} mem ON mem.conversation_id = msg.conversation_id AND mem.user_id = %d
			 WHERE msg.id > mem.last_read_message_id
			   AND msg.sender_id <> %d
			   AND msg.deleted_at IS NULL",
			$user_id,
			$user_id
		);
		return (int) $wpdb->get_var( $sql );
	}
}
