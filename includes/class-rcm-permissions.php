<?php

defined( 'ABSPATH' ) || exit;

final class RCM_Permissions {
	public static function settings(): array {
		return RCM_DB::get_settings();
	}

	public static function user_roles( int $user_id ): array {
		$user = get_userdata( $user_id );
		return $user ? array_values( (array) $user->roles ) : array();
	}

	public static function is_suspended( int $user_id ): bool {
		$until = (string) get_user_meta( $user_id, '_rcm_suspended_until', true );
		if ( '' === $until ) {
			return false;
		}
		if ( 'forever' === $until ) {
			return true;
		}
		$timestamp = strtotime( $until . ' UTC' );
		if ( ! $timestamp || $timestamp <= time() ) {
			delete_user_meta( $user_id, '_rcm_suspended_until' );
			delete_user_meta( $user_id, '_rcm_suspension_reason' );
			return false;
		}
		return true;
	}

	public static function can_use_chat( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! $user_id || self::is_suspended( $user_id ) ) {
			return false;
		}
		$settings = self::settings();
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}
		$roles = self::user_roles( $user_id );
		return (bool) array_intersect( $roles, (array) $settings['enabled_roles'] );
	}

	public static function can_use_backend( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! self::can_use_chat( $user_id ) ) {
			return false;
		}
		$settings = self::settings();
		if ( empty( $settings['admin_chat'] ) ) {
			return false;
		}
		$roles = self::user_roles( $user_id );
		if ( in_array( 'administrator', $roles, true ) ) {
			return true;
		}
		return ! (bool) array_intersect( $roles, (array) $settings['frontend_roles'] );
	}

	public static function can_use_frontend( int $user_id = 0 ): bool {
		$user_id = $user_id ?: get_current_user_id();
		if ( ! self::can_use_chat( $user_id ) ) {
			return false;
		}
		$settings = self::settings();
		if ( empty( $settings['frontend_widget'] ) ) {
			return false;
		}
		return (bool) array_intersect( self::user_roles( $user_id ), (array) $settings['frontend_roles'] );
	}

	public static function matrix_allows( int $sender_id, int $recipient_id, string $ability ): bool {
		if ( $sender_id <= 0 || $recipient_id <= 0 || $sender_id === $recipient_id ) {
			return false;
		}
		if ( ! self::can_use_chat( $sender_id ) || ! self::can_use_chat( $recipient_id ) ) {
			return false;
		}
		if ( self::is_blocked_either_way( $sender_id, $recipient_id ) ) {
			return false;
		}

		$settings        = self::settings();
		$sender_roles    = self::user_roles( $sender_id );
		$recipient_roles = self::user_roles( $recipient_id );
		$matrix          = (array) $settings['role_matrix'];

		foreach ( $sender_roles as $sender_role ) {
			foreach ( $recipient_roles as $recipient_role ) {
				if ( ! empty( $matrix[ $sender_role ][ $recipient_role ][ $ability ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function can_initiate( int $sender_id, int $recipient_id ): bool {
		return self::matrix_allows( $sender_id, $recipient_id, 'initiate' );
	}

	public static function can_reply_to_user( int $sender_id, int $recipient_id ): bool {
		return self::matrix_allows( $sender_id, $recipient_id, 'reply' );
	}

	public static function can_attach_to_user( int $sender_id, int $recipient_id ): bool {
		$settings = self::settings();
		return ! empty( $settings['allow_attachments'] ) && self::matrix_allows( $sender_id, $recipient_id, 'attach' );
	}

	public static function can_create_group( int $user_id ): bool {
		$settings = self::settings();
		if ( empty( $settings['allow_groups'] ) || ! self::can_use_chat( $user_id ) ) {
			return false;
		}
		return (bool) array_intersect( self::user_roles( $user_id ), (array) $settings['group_creator_roles'] );
	}

	public static function can_send_to_conversation( int $user_id, int $conversation_id, bool $attachment = false ): bool {
		if ( ! self::can_use_chat( $user_id ) || ! RCM_DB::is_member( $conversation_id, $user_id ) ) {
			return false;
		}
		$conversation = RCM_DB::conversation_row( $conversation_id );
		if ( ! $conversation || 'active' !== $conversation->status ) {
			return false;
		}
		$others = array_values( array_diff( RCM_DB::conversation_members( $conversation_id ), array( $user_id ) ) );
		if ( empty( $others ) ) {
			return false;
		}
		foreach ( $others as $other_id ) {
			$allowed = $attachment ? self::can_attach_to_user( $user_id, (int) $other_id ) : self::can_reply_to_user( $user_id, (int) $other_id );
			if ( ! $allowed ) {
				return false;
			}
		}
		return true;
	}

	public static function can_manage_group( int $user_id, int $conversation_id ): bool {
		$member = RCM_DB::member_row( $conversation_id, $user_id );
		if ( ! $member ) {
			return false;
		}
		return in_array( $member->member_role, array( 'owner', 'admin' ), true );
	}

	public static function is_blocked_either_way( int $a, int $b ): bool {
		global $wpdb;
		$table = RCM_DB::table( 'blocks' );
		$sql   = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE (user_id = %d AND blocked_user_id = %d) OR (user_id = %d AND blocked_user_id = %d)",
			$a,
			$b,
			$b,
			$a
		);
		return (int) $wpdb->get_var( $sql ) > 0;
	}

	public static function rest_can_use(): bool|WP_Error {
		if ( ! is_user_logged_in() || ! self::can_use_chat() ) {
			return new WP_Error( 'rcm_forbidden', __( 'You are not allowed to use chat.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public static function rest_manage(): bool|WP_Error {
		return current_user_can( 'rcm_manage_chat' ) || current_user_can( 'manage_options' )
			? true
			: new WP_Error( 'rcm_forbidden', __( 'You are not allowed to manage chat.', 'rolechat-messenger' ), array( 'status' => 403 ) );
	}

	public static function rest_moderate(): bool|WP_Error {
		return current_user_can( 'rcm_moderate_chat' ) || current_user_can( 'manage_options' )
			? true
			: new WP_Error( 'rcm_forbidden', __( 'You are not allowed to moderate chat.', 'rolechat-messenger' ), array( 'status' => 403 ) );
	}

	public static function rate_limit_ok( int $user_id ): bool {
		$settings = self::settings();
		$limit    = max( 1, (int) $settings['rate_limit_per_minute'] );
		$key      = 'rcm_rate_' . $user_id;
		$data     = get_transient( $key );
		$now      = time();
		if ( ! is_array( $data ) || empty( $data['start'] ) || ( $now - (int) $data['start'] ) >= 60 ) {
			set_transient( $key, array( 'start' => $now, 'count' => 1 ), 70 );
			return true;
		}
		$count = (int) $data['count'] + 1;
		set_transient( $key, array( 'start' => (int) $data['start'], 'count' => $count ), 70 );
		return $count <= $limit;
	}
}
