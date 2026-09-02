<?php

defined( 'ABSPATH' ) || exit;

final class RCM_REST {
	private const NS = 'rolechat/v1';
	private const MAX_MESSAGE_LENGTH = 10000;
	private const MAX_GROUP_TITLE_LENGTH = 190;
	private const MAX_CATEGORY_NAME_LENGTH = 120;
	private const MAX_REPORT_REASON_LENGTH = 1000;
	private const MAX_GROUP_MEMBERS = 200;

	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_ajax_rcm_download_attachment', array( __CLASS__, 'download_attachment' ) );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/bootstrap', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'bootstrap' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'conversations' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations/direct', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'create_direct' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			'args'                => array( 'user_id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ) ),
		) );

		register_rest_route( self::NS, '/conversations/group', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'create_group' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations/(?P<id>\d+)/messages', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'messages' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'send_message' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
		) );

		register_rest_route( self::NS, '/conversations/(?P<id>\d+)/search', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'search_messages' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations/(?P<id>\d+)/read', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'mark_read' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations/(?P<id>\d+)/state', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( __CLASS__, 'update_conversation_state' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations/(?P<id>\d+)/typing', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'typing' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations/(?P<id>\d+)/members', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'add_group_member' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/conversations/(?P<id>\d+)/members/(?P<user_id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'remove_group_member' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/messages/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'edit_message' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_message' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
		) );

		register_rest_route( self::NS, '/messages/(?P<id>\d+)/forward', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'forward_message' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/messages/(?P<id>\d+)/reaction', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'react' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/messages/(?P<id>\d+)/report', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'report_message' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/upload', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'upload' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/users', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'users' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/contacts', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'contacts' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_contact' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
		) );

		register_rest_route( self::NS, '/contacts/(?P<user_id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_contact' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );

		register_rest_route( self::NS, '/contact-categories', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'categories' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_category' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
		) );

		register_rest_route( self::NS, '/contact-categories/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_category' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_category' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
		) );

		register_rest_route( self::NS, '/blocks/(?P<user_id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'block_user' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'unblock_user' ),
				'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
			),
		) );

		register_rest_route( self::NS, '/presence', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'presence' ),
			'permission_callback' => array( 'RCM_Permissions', 'rest_can_use' ),
		) );
	}

	public static function bootstrap(): WP_REST_Response {
		$user_id  = get_current_user_id();
		$settings = RCM_Permissions::settings();
		self::touch_presence( $user_id );
		return rest_ensure_response( array(
			'user'          => RCM_DB::user_display( $user_id ),
			'conversations' => self::conversation_list( $user_id ),
			'contacts'      => self::contact_list( $user_id ),
			'categories'    => self::category_list( $user_id ),
			'blocked_users' => self::blocked_users( $user_id ),
			'features'      => array(
				'groups'                => ! empty( $settings['allow_groups'] ),
				'can_create_group'      => RCM_Permissions::can_create_group( $user_id ),
				'attachments'           => ! empty( $settings['allow_attachments'] ),
				'reactions'             => ! empty( $settings['allow_reactions'] ),
				'edit'                  => ! empty( $settings['allow_edit'] ),
				'delete'                => ! empty( $settings['allow_delete'] ),
				'forward'               => ! empty( $settings['allow_forward'] ),
				'mentions'              => ! empty( $settings['allow_mentions'] ),
				'blocking'              => ! empty( $settings['allow_user_blocking'] ),
				'presence'              => ! empty( $settings['show_presence'] ),
				'last_seen'             => ! empty( $settings['show_last_seen'] ),
				'browser_notifications' => ! empty( $settings['browser_notifications'] ),
				'sounds'                => ! empty( $settings['sounds'] ),
			),
			'limits'        => array(
				'max_attachment_mb' => (int) $settings['max_attachment_mb'],
				'poll_interval_ms'  => max( 2000, (int) $settings['poll_interval_ms'] ),
			),
		) );
	}

	public static function conversations(): WP_REST_Response {
		$user_id = get_current_user_id();
		self::touch_presence( $user_id );
		return rest_ensure_response( array( 'conversations' => self::conversation_list( $user_id ) ) );
	}

	private static function conversation_list( int $user_id ): array {
		global $wpdb;
		$c = RCM_DB::table( 'conversations' );
		$m = RCM_DB::table( 'members' );
		$g = RCM_DB::table( 'messages' );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$m} mem
			 INNER JOIN {$c} conv ON conv.id = mem.conversation_id
			 INNER JOIN {$g} lm ON lm.id = conv.last_message_id
			 SET mem.last_delivered_message_id = conv.last_message_id
			 WHERE mem.user_id = %d AND conv.status = 'active'
			   AND conv.last_message_id > mem.last_delivered_message_id AND lm.sender_id <> %d",
			$user_id,
			$user_id
		) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, mem.member_role, mem.last_read_message_id, mem.last_delivered_message_id, mem.is_pinned, mem.is_archived, mem.muted_until,
			        (SELECT COUNT(*) FROM {$g} unread_msg
			         WHERE unread_msg.conversation_id = c.id AND unread_msg.id > mem.last_read_message_id
			           AND unread_msg.sender_id <> %d AND unread_msg.deleted_at IS NULL) AS unread,
			        lm.sender_id AS last_sender_id, lm.content AS last_content, lm.type AS last_type, lm.deleted_at AS last_deleted_at
			 FROM {$c} c
			 INNER JOIN {$m} mem ON mem.conversation_id = c.id AND mem.user_id = %d
			 LEFT JOIN {$g} lm ON lm.id = c.last_message_id
			 WHERE c.status = 'active'
			 ORDER BY mem.is_pinned DESC, COALESCE(c.last_message_at,c.created_at) DESC, c.id DESC
			 LIMIT 200",
			$user_id,
			$user_id
		) );

		$member_map = array();
		if ( $rows ) {
			$conversation_ids = array_map( static fn( $row ) => (int) $row->id, $rows );
			$ids_sql          = implode( ',', array_map( 'absint', $conversation_ids ) );
			$member_rows      = $wpdb->get_results( "SELECT conversation_id,user_id FROM {$m} WHERE conversation_id IN ({$ids_sql}) ORDER BY joined_at ASC,id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			foreach ( $member_rows as $member_row ) {
				$member_map[ (int) $member_row->conversation_id ][] = (int) $member_row->user_id;
			}
		}

		$user_cache = array();
		foreach ( $member_map as $member_ids ) {
			foreach ( $member_ids as $member_id ) {
				if ( ! array_key_exists( $member_id, $user_cache ) ) {
					$user_cache[ $member_id ] = RCM_DB::user_display( $member_id );
				}
			}
		}

		$out = array();
		foreach ( $rows as $row ) {
			$conversation_id = (int) $row->id;
			$members         = $member_map[ $conversation_id ] ?? array();
			$other_ids       = array_values( array_diff( $members, array( $user_id ) ) );
			$member_data     = array_map( static fn( $member_id ) => $user_cache[ $member_id ] ?? array(), $members );

			if ( 'direct' === $row->type && ! empty( $other_ids ) ) {
				$other  = $user_cache[ (int) $other_ids[0] ] ?? array();
				$title  = $other['name'] ?? __( 'User', 'rolechat-messenger' );
				$avatar = $other['avatar'] ?? '';
			} else {
				$title  = $row->title ?: __( 'Group', 'rolechat-messenger' );
				$avatar = $row->avatar_id ? wp_get_attachment_image_url( (int) $row->avatar_id, 'thumbnail' ) : '';
			}

			$out[] = array(
				'id'             => $conversation_id,
				'type'           => $row->type,
				'title'          => $title,
				'avatar'         => $avatar,
				'created_by'     => (int) $row->created_by,
				'last_message_at'=> self::gmt_mysql_to_iso( (string) ( $row->last_message_at ?: $row->created_at ) ),
				'last_message_id'=> (int) $row->last_message_id,
				'last_message'   => $row->last_message_id ? array(
					'sender_id' => (int) $row->last_sender_id,
					'content'   => $row->last_deleted_at ? __( 'Message deleted', 'rolechat-messenger' ) : self::preview( (string) $row->last_content, (string) $row->last_type ),
					'type'      => $row->last_type,
				) : null,
				'unread'         => (int) $row->unread,
				'is_pinned'      => (bool) $row->is_pinned,
				'is_archived'    => (bool) $row->is_archived,
				'muted_until'    => $row->muted_until,
				'members'        => array_values( array_filter( $member_data ) ),
				'can_manage'     => 'group' === $row->type && in_array( $row->member_role, array( 'owner', 'admin' ), true ),
				'typing'         => self::typing_users( $conversation_id, $user_id, $members ),
			);
		}
		return $out;
	}

	private static function preview( string $content, string $type ): string {
		if ( '' !== trim( $content ) ) {
			return wp_html_excerpt( $content, 80, '…' );
		}
		return 'attachment' === $type ? __( 'Attachment', 'rolechat-messenger' ) : __( 'Message', 'rolechat-messenger' );
	}

	public static function create_direct( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$sender_id    = get_current_user_id();
		$recipient_id = absint( $request['user_id'] );
		if ( ! get_userdata( $recipient_id ) || ! RCM_Permissions::can_initiate( $sender_id, $recipient_id ) ) {
			return new WP_Error( 'rcm_not_allowed', __( 'You are not allowed to start a conversation with this user.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$existing = RCM_DB::find_direct_conversation( $sender_id, $recipient_id );
		if ( $existing ) {
			// Repair membership if a previous concurrent/incomplete creation left the conversation row behind.
			global $wpdb;
			$m   = RCM_DB::table( 'members' );
			$now = RCM_DB::now();
			$sender_added    = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$m} (conversation_id,user_id,member_role,joined_at) VALUES (%d,%d,'member',%s)", $existing, $sender_id, $now ) );
			$recipient_added = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$m} (conversation_id,user_id,member_role,joined_at) VALUES (%d,%d,'member',%s)", $existing, $recipient_id, $now ) );
			if ( false === $sender_added || false === $recipient_added || ! RCM_DB::is_member( $existing, $sender_id ) || ! RCM_DB::is_member( $existing, $recipient_id ) ) {
				return new WP_Error( 'rcm_db_error', __( 'Could not restore conversation membership.', 'rolechat-messenger' ), array( 'status' => 500 ) );
			}
			return rest_ensure_response( array( 'conversation_id' => $existing, 'existing' => true ) );
		}

		global $wpdb;
		$c   = RCM_DB::table( 'conversations' );
		$m   = RCM_DB::table( 'members' );
		$now = RCM_DB::now();
		$direct_key = RCM_DB::direct_key( $sender_id, $recipient_id );
		$wpdb->insert( $c, array( 'type' => 'direct', 'direct_key' => $direct_key, 'created_by' => $sender_id, 'created_at' => $now, 'updated_at' => $now ), array( '%s', '%s', '%d', '%s', '%s' ) );
		$conversation_id = (int) $wpdb->insert_id;
		if ( ! $conversation_id ) {
			$conversation_id = RCM_DB::find_direct_conversation( $sender_id, $recipient_id );
			if ( ! $conversation_id ) {
				return new WP_Error( 'rcm_db_error', __( 'Could not create conversation.', 'rolechat-messenger' ), array( 'status' => 500 ) );
			}
		}
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$m} (conversation_id,user_id,member_role,joined_at) VALUES (%d,%d,'member',%s)", $conversation_id, $sender_id, $now ) );
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$m} (conversation_id,user_id,member_role,joined_at) VALUES (%d,%d,'member',%s)", $conversation_id, $recipient_id, $now ) );
		if ( ! RCM_DB::is_member( $conversation_id, $sender_id ) || ! RCM_DB::is_member( $conversation_id, $recipient_id ) ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not create conversation membership.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		do_action( 'rcm_conversation_created', $conversation_id, $sender_id );
		return rest_ensure_response( array( 'conversation_id' => $conversation_id, 'existing' => false ) );
	}

	public static function create_group( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		if ( ! RCM_Permissions::can_create_group( $user_id ) ) {
			return new WP_Error( 'rcm_not_allowed', __( 'You are not allowed to create groups.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$title      = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$member_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'member_ids' ) ) ) ) );
		$member_ids = array_values( array_diff( $member_ids, array( $user_id ) ) );
		if ( '' === $title || empty( $member_ids ) ) {
			return new WP_Error( 'rcm_invalid_group', __( 'A group title and at least one participant are required.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		if ( self::text_length( $title ) > self::MAX_GROUP_TITLE_LENGTH ) {
			return new WP_Error( 'rcm_group_title_too_long', __( 'The group title is too long.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		if ( count( $member_ids ) >= self::MAX_GROUP_MEMBERS ) {
			return new WP_Error( 'rcm_group_too_large', __( 'A group can contain at most 200 members.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		foreach ( $member_ids as $member_id ) {
			if ( ! RCM_Permissions::can_initiate( $user_id, $member_id ) ) {
				return new WP_Error( 'rcm_member_not_allowed', __( 'One or more selected users cannot be added because of the role permission matrix.', 'rolechat-messenger' ), array( 'status' => 403 ) );
			}
		}
		if ( ! self::group_members_compatible( array_merge( array( $user_id ), $member_ids ) ) ) {
			return new WP_Error( 'rcm_group_matrix_conflict', __( 'The selected group contains role combinations that are not mutually allowed to exchange messages.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		if ( ! RCM_Permissions::rate_limit_ok( $user_id, 'group_create', 5 ) ) {
			return new WP_Error( 'rcm_rate_limited', __( 'Too many groups were created. Please wait before trying again.', 'rolechat-messenger' ), array( 'status' => 429 ) );
		}
		global $wpdb;
		$c   = RCM_DB::table( 'conversations' );
		$m   = RCM_DB::table( 'members' );
		$now = RCM_DB::now();
		$wpdb->insert( $c, array( 'type' => 'group', 'title' => $title, 'created_by' => $user_id, 'created_at' => $now, 'updated_at' => $now ), array( '%s', '%s', '%d', '%s', '%s' ) );
		$conversation_id = (int) $wpdb->insert_id;
		if ( ! $conversation_id ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not create group.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		$owner_added = $wpdb->insert( $m, array( 'conversation_id' => $conversation_id, 'user_id' => $user_id, 'member_role' => 'owner', 'joined_at' => $now ), array( '%d', '%d', '%s', '%s' ) );
		if ( false === $owner_added ) {
			$wpdb->delete( $c, array( 'id' => $conversation_id ), array( '%d' ) );
			return new WP_Error( 'rcm_db_error', __( 'Could not create group membership.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		foreach ( $member_ids as $member_id ) {
			if ( false === $wpdb->insert( $m, array( 'conversation_id' => $conversation_id, 'user_id' => $member_id, 'member_role' => 'member', 'joined_at' => $now ), array( '%d', '%d', '%s', '%s' ) ) ) {
				$wpdb->delete( $m, array( 'conversation_id' => $conversation_id ), array( '%d' ) );
				$wpdb->delete( $c, array( 'id' => $conversation_id ), array( '%d' ) );
				return new WP_Error( 'rcm_db_error', __( 'Could not add all group members.', 'rolechat-messenger' ), array( 'status' => 500 ) );
			}
		}
		RCM_DB::audit( $user_id, 'group_created', 'conversation', $conversation_id, array( 'title' => $title, 'members' => $member_ids ) );
		do_action( 'rcm_group_created', $conversation_id, $user_id );
		return rest_ensure_response( array( 'conversation_id' => $conversation_id ) );
	}

	private static function group_members_compatible( array $member_ids ): bool {
		$member_ids = array_values( array_unique( array_filter( array_map( 'absint', $member_ids ) ) ) );
		foreach ( $member_ids as $sender_id ) {
			foreach ( $member_ids as $recipient_id ) {
				if ( $sender_id === $recipient_id ) { continue; }
				if ( ! RCM_Permissions::can_reply_to_user( $sender_id, $recipient_id ) ) { return false; }
			}
		}
		return true;
	}

	public static function messages( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		if ( ! RCM_DB::is_member( $conversation_id, $user_id ) ) {
			return new WP_Error( 'rcm_forbidden_conversation', __( 'You cannot access this conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$limit  = min( 100, max( 1, absint( $request->get_param( 'limit' ) ?: 50 ) ) );
		$before = absint( $request->get_param( 'before_id' ) );
		global $wpdb;
		$g = RCM_DB::table( 'messages' );
		if ( $before ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$g} WHERE conversation_id = %d AND id < %d ORDER BY id DESC LIMIT %d", $conversation_id, $before, $limit ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$g} WHERE conversation_id = %d ORDER BY id DESC LIMIT %d", $conversation_id, $limit ) );
		}
		$rows = array_reverse( $rows );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = self::serialize_message( $row, $user_id );
		}
		self::touch_presence( $user_id );
		return rest_ensure_response( array(
			'messages' => $out,
			'has_more' => count( $rows ) === $limit,
			'typing'   => self::typing_users( $conversation_id, $user_id ),
		) );
	}

	public static function search_messages( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		if ( ! RCM_DB::is_member( $conversation_id, $user_id ) ) {
			return new WP_Error( 'rcm_forbidden_conversation', __( 'You cannot access this conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$q = sanitize_text_field( (string) $request->get_param( 'q' ) );
		if ( self::text_length( $q ) < 2 ) {
			return rest_ensure_response( array( 'messages' => array() ) );
		}
		global $wpdb;
		$g    = RCM_DB::table( 'messages' );
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$g} WHERE conversation_id = %d AND deleted_at IS NULL AND content LIKE %s ORDER BY id DESC LIMIT 100", $conversation_id, $like ) );
		return rest_ensure_response( array( 'messages' => array_map( fn( $row ) => self::serialize_message( $row, $user_id ), $rows ) ) );
	}

	public static function send_message( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		$content         = sanitize_textarea_field( (string) $request->get_param( 'content' ) );
		$reply_to_id     = absint( $request->get_param( 'reply_to_id' ) );
		$attachment_ids  = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $request->get_param( 'attachment_ids' ) ) ) ) ), 0, 10 );
		$has_attachment  = ! empty( $attachment_ids );
		$has_text        = '' !== trim( $content );

		if ( ! $has_text && ! $has_attachment ) {
			return new WP_Error( 'rcm_empty_message', __( 'Message cannot be empty.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		if ( self::text_length( $content ) > self::MAX_MESSAGE_LENGTH ) {
			return new WP_Error( 'rcm_message_too_long', __( 'The message is too long.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		if ( ! RCM_Permissions::can_send_to_conversation( $user_id, $conversation_id, $has_attachment, $has_text ) ) {
			return new WP_Error( 'rcm_send_forbidden', __( 'Your role is not allowed to send this message to all participants in this conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		if ( ! RCM_Permissions::rate_limit_ok( $user_id ) ) {
			return new WP_Error( 'rcm_rate_limited', __( 'Too many messages. Please wait before sending again.', 'rolechat-messenger' ), array( 'status' => 429 ) );
		}

		global $wpdb;
		$g = RCM_DB::table( 'messages' );
		$c = RCM_DB::table( 'conversations' );
		$a = RCM_DB::table( 'attachments' );

		if ( $reply_to_id ) {
			$valid_reply = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$g} WHERE id = %d AND conversation_id = %d", $reply_to_id, $conversation_id ) );
			if ( ! $valid_reply ) {
				$reply_to_id = 0;
			}
		}

		$valid_attachments = array();
		if ( $attachment_ids ) {
			$ids_sql = implode( ',', array_map( 'absint', $attachment_ids ) );
			$valid_attachments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$a} WHERE id IN ({$ids_sql}) AND uploader_id = %d AND conversation_id = %d AND message_id = 0", $user_id, $conversation_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( '' === trim( $content ) && empty( $valid_attachments ) ) {
			return new WP_Error( 'rcm_invalid_attachment', __( 'The message has no valid content or authorized attachment.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}

		$type = ! empty( $valid_attachments ) ? 'attachment' : 'text';
		$now  = RCM_DB::now();
		do_action( 'rcm_before_send_message', $conversation_id, $user_id, $content );
		$wpdb->insert( $g, array(
			'conversation_id' => $conversation_id,
			'sender_id'       => $user_id,
			'reply_to_id'     => $reply_to_id,
			'type'            => $type,
			'content'         => $content,
			'created_at'      => $now,
		), array( '%d', '%d', '%d', '%s', '%s', '%s' ) );
		$message_id = (int) $wpdb->insert_id;
		if ( ! $message_id ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not save the message.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}

		$claimed_attachment_ids = array();
		foreach ( $valid_attachments as $attachment ) {
			$attachment_id = (int) $attachment->id;
			$claimed = $wpdb->update( $a, array( 'message_id' => $message_id ), array( 'id' => $attachment_id, 'uploader_id' => $user_id, 'conversation_id' => $conversation_id, 'message_id' => 0 ), array( '%d' ), array( '%d', '%d', '%d', '%d' ) );
			if ( 1 !== $claimed ) {
				if ( $claimed_attachment_ids ) {
					$claimed_sql = implode( ',', array_map( 'absint', $claimed_attachment_ids ) );
					$wpdb->query( $wpdb->prepare( "UPDATE {$a} SET message_id = 0 WHERE message_id = %d AND id IN ({$claimed_sql})", $message_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
				$wpdb->delete( $g, array( 'id' => $message_id ), array( '%d' ) );
				return new WP_Error( 'rcm_attachment_conflict', __( 'One or more attachments were already used or could not be attached. Please upload them again.', 'rolechat-messenger' ), array( 'status' => 409 ) );
			}
			$claimed_attachment_ids[] = $attachment_id;
		}

		$conversation_updated = $wpdb->update( $c, array( 'updated_at' => $now, 'last_message_at' => $now, 'last_message_id' => $message_id ), array( 'id' => $conversation_id ), array( '%s', '%s', '%d' ), array( '%d' ) );
		if ( false === $conversation_updated ) {
			if ( $claimed_attachment_ids ) {
				$claimed_sql = implode( ',', array_map( 'absint', $claimed_attachment_ids ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$a} SET message_id = 0 WHERE message_id = %d AND id IN ({$claimed_sql})", $message_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			$wpdb->delete( $g, array( 'id' => $message_id ), array( '%d' ) );
			return new WP_Error( 'rcm_db_error', __( 'Could not update the conversation after sending the message.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		do_action( 'rcm_after_send_message', $message_id, $conversation_id, $user_id );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$g} WHERE id = %d", $message_id ) );
		return rest_ensure_response( array( 'message' => self::serialize_message( $row, $user_id ) ) );
	}

	private static function message_mentions( object $row ): array {
		$settings = RCM_Permissions::settings();
		if ( empty( $settings['allow_mentions'] ) || ! empty( $row->deleted_at ) || empty( $row->content ) || 'group' !== ( RCM_DB::conversation_row( (int) $row->conversation_id )->type ?? '' ) ) {
			return array();
		}
		if ( ! preg_match_all( '/@([A-Za-z0-9._-]{1,60})/u', (string) $row->content, $matches ) ) {
			return array();
		}
		$member_ids = RCM_DB::conversation_members( (int) $row->conversation_id );
		$ids        = array();
		foreach ( array_unique( $matches[1] ) as $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user && in_array( (int) $user->ID, $member_ids, true ) ) {
				$ids[] = (int) $user->ID;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private static function gmt_mysql_to_iso( ?string $value ): ?string {
		if ( ! $value ) {
			return null;
		}
		$timestamp = strtotime( $value . ' UTC' );
		return $timestamp ? gmdate( DATE_ATOM, $timestamp ) : null;
	}

	private static function text_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}
		return preg_match_all( '/./us', $value, $matches ) ?: 0;
	}

	private static function serialize_message( object $row, int $viewer_id ): array {
		global $wpdb;
		$a = RCM_DB::table( 'attachments' );
		$r = RCM_DB::table( 'reactions' );
		$m = RCM_DB::table( 'members' );
		$g = RCM_DB::table( 'messages' );

		$attachments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$a} WHERE message_id = %d ORDER BY id ASC", (int) $row->id ) );
		$attachment_data = array();
		foreach ( $attachments as $att ) {
			$url = RCM_Attachments::download_url( (int) $att->id );
			$is_image = str_starts_with( (string) $att->mime_type, 'image/' );
			$attachment_data[] = array(
				'id'            => (int) $att->id,
				'name'          => $att->file_name,
				'mime'          => $att->mime_type,
				'size'          => (int) $att->file_size,
				'url'           => $url,
				'is_image'      => $is_image,
				'thumbnail_url' => $is_image ? $url : '',
			);
		}

		$reactions = $wpdb->get_results( $wpdb->prepare( "SELECT reaction, COUNT(*) AS total, GROUP_CONCAT(user_id) AS users FROM {$r} WHERE message_id = %d GROUP BY reaction ORDER BY total DESC", (int) $row->id ) );
		$reaction_data = array_map( static function ( $item ) use ( $viewer_id ) {
			$users = array_map( 'intval', explode( ',', (string) $item->users ) );
			return array( 'reaction' => $item->reaction, 'count' => (int) $item->total, 'mine' => in_array( $viewer_id, $users, true ) );
		}, $reactions );

		$reply = null;
		if ( ! empty( $row->reply_to_id ) ) {
			$parent = $wpdb->get_row( $wpdb->prepare( "SELECT id,sender_id,content,deleted_at FROM {$g} WHERE id = %d", (int) $row->reply_to_id ) );
			if ( $parent ) {
				$sender = RCM_DB::user_display( (int) $parent->sender_id );
				$reply  = array(
					'id'      => (int) $parent->id,
					'sender'  => $sender['name'] ?? __( 'User', 'rolechat-messenger' ),
					'content' => $parent->deleted_at ? __( 'Message deleted', 'rolechat-messenger' ) : self::preview( (string) $parent->content, 'text' ),
				);
			}
		}

		$members = $wpdb->get_results( $wpdb->prepare( "SELECT user_id,last_read_message_id,last_delivered_message_id FROM {$m} WHERE conversation_id = %d AND user_id <> %d", (int) $row->conversation_id, (int) $row->sender_id ) );
		$delivered = 0;
		$read      = 0;
		foreach ( $members as $member ) {
			if ( (int) $member->last_delivered_message_id >= (int) $row->id ) { $delivered++; }
			if ( (int) $member->last_read_message_id >= (int) $row->id ) { $read++; }
		}
		$status = 'sent';
		if ( $read > 0 ) { $status = 'read'; }
		elseif ( $delivered > 0 ) { $status = 'delivered'; }

		$sender = RCM_DB::user_display( (int) $row->sender_id );
		return array(
			'id'             => (int) $row->id,
			'conversation_id'=> (int) $row->conversation_id,
			'sender_id'      => (int) $row->sender_id,
			'sender'         => $sender,
			'own'            => (int) $row->sender_id === $viewer_id,
			'type'           => $row->type,
			'content'        => $row->deleted_at ? '' : (string) $row->content,
			'created_at'     => self::gmt_mysql_to_iso( (string) $row->created_at ),
			'edited_at'      => self::gmt_mysql_to_iso( $row->edited_at ? (string) $row->edited_at : null ),
			'deleted'        => ! empty( $row->deleted_at ),
			'status'         => $status,
			'delivered_count'=> $delivered,
			'read_count'     => $read,
			'attachments'    => $attachment_data,
			'reactions'      => $reaction_data,
			'reply_to'       => $reply,
			'mention_user_ids'=> self::message_mentions( $row ),
		);
	}

	public static function mark_read( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		if ( ! RCM_DB::is_member( $conversation_id, $user_id ) ) {
			return new WP_Error( 'rcm_forbidden_conversation', __( 'You cannot access this conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$message_id = absint( $request->get_param( 'message_id' ) );
		global $wpdb;
		$g = RCM_DB::table( 'messages' );
		$m = RCM_DB::table( 'members' );
		$max_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(id) FROM {$g} WHERE conversation_id = %d", $conversation_id ) );
		$message_id = $message_id ? min( $message_id, $max_id ) : $max_id;
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$m} SET last_read_message_id = GREATEST(last_read_message_id,%d), last_delivered_message_id = GREATEST(last_delivered_message_id,%d) WHERE conversation_id = %d AND user_id = %d", $message_id, $message_id, $conversation_id, $user_id ) );
		if ( false === $updated ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not update the read state.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'read' => $message_id ) );
	}

	public static function update_conversation_state( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		if ( ! RCM_DB::is_member( $conversation_id, $user_id ) ) {
			return new WP_Error( 'rcm_forbidden_conversation', __( 'You cannot access this conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$data = array();
		$formats = array();
		if ( null !== $request->get_param( 'is_pinned' ) ) { $data['is_pinned'] = rest_sanitize_boolean( $request->get_param( 'is_pinned' ) ) ? 1 : 0; $formats[] = '%d'; }
		if ( null !== $request->get_param( 'is_archived' ) ) { $data['is_archived'] = rest_sanitize_boolean( $request->get_param( 'is_archived' ) ) ? 1 : 0; $formats[] = '%d'; }
		if ( null !== $request->get_param( 'muted_minutes' ) ) {
			$minutes = min( 525600, max( 0, absint( $request->get_param( 'muted_minutes' ) ) ) );
			$data['muted_until'] = $minutes ? gmdate( 'Y-m-d H:i:s', time() + $minutes * MINUTE_IN_SECONDS ) : null;
			$formats[] = '%s';
		}
		if ( empty( $data ) ) { return rest_ensure_response( array( 'updated' => false ) ); }
		global $wpdb;
		$updated = $wpdb->update( RCM_DB::table( 'members' ), $data, array( 'conversation_id' => $conversation_id, 'user_id' => $user_id ), $formats, array( '%d', '%d' ) );
		if ( false === $updated ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not update the conversation state.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'updated' => true ) );
	}

	public static function typing( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		if ( ! RCM_DB::is_member( $conversation_id, $user_id ) || ! RCM_Permissions::can_send_to_conversation( $user_id, $conversation_id, false ) ) {
			return new WP_Error( 'rcm_forbidden_conversation', __( 'You cannot send activity to this conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$is_typing = rest_sanitize_boolean( $request->get_param( 'typing' ) );
		$key = 'rcm_typing_' . $conversation_id . '_' . $user_id;
		if ( $is_typing ) { set_transient( $key, 1, 8 ); } else { delete_transient( $key ); }
		return rest_ensure_response( array( 'typing' => $is_typing ) );
	}

	private static function typing_users( int $conversation_id, int $viewer_id, ?array $member_ids = null ): array {
		$out = array();
		foreach ( $member_ids ?? RCM_DB::conversation_members( $conversation_id ) as $member_id ) {
			if ( $member_id === $viewer_id ) { continue; }
			if ( get_transient( 'rcm_typing_' . $conversation_id . '_' . $member_id ) ) {
				$user = RCM_DB::user_display( $member_id );
				if ( $user ) { $out[] = $user; }
			}
		}
		return $out;
	}

	public static function add_group_member( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		$target_id       = absint( $request->get_param( 'user_id' ) );
		$conversation    = RCM_DB::conversation_row( $conversation_id );
		if ( ! $conversation || 'group' !== $conversation->type || 'active' !== $conversation->status || ! RCM_Permissions::can_manage_group( $user_id, $conversation_id ) ) {
			return new WP_Error( 'rcm_group_forbidden', __( 'You cannot manage this group.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		if ( ! RCM_Permissions::can_initiate( $user_id, $target_id ) ) {
			return new WP_Error( 'rcm_member_not_allowed', __( 'The role permission matrix does not allow adding this user.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$current_members = RCM_DB::conversation_members( $conversation_id );
		if ( in_array( $target_id, $current_members, true ) ) {
			return rest_ensure_response( array( 'added' => false, 'existing' => true ) );
		}
		if ( count( $current_members ) >= self::MAX_GROUP_MEMBERS ) {
			return new WP_Error( 'rcm_group_too_large', __( 'A group can contain at most 200 members.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		$prospective_members = array_merge( $current_members, array( $target_id ) );
		if ( ! self::group_members_compatible( $prospective_members ) ) {
			return new WP_Error( 'rcm_group_matrix_conflict', __( 'This user cannot join the group because one or more role-to-role messaging directions are disabled.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		global $wpdb;
		$m = RCM_DB::table( 'members' );
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$m} (conversation_id,user_id,member_role,joined_at) VALUES (%d,%d,'member',%s)", $conversation_id, $target_id, RCM_DB::now() ) );
		if ( false === $inserted ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not add the group member.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		if ( 0 === $inserted ) {
			return rest_ensure_response( array( 'added' => false, 'existing' => true ) );
		}
		RCM_DB::audit( $user_id, 'group_member_added', 'conversation', $conversation_id, array( 'user_id' => $target_id ) );
		return rest_ensure_response( array( 'added' => true ) );
	}

	public static function remove_group_member( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request['id'] );
		$target_id       = absint( $request['user_id'] );
		$conversation    = RCM_DB::conversation_row( $conversation_id );
		if ( ! $conversation || 'group' !== $conversation->type ) {
			return new WP_Error( 'rcm_invalid_group', __( 'Invalid group.', 'rolechat-messenger' ), array( 'status' => 404 ) );
		}
		$allowed = $target_id === $user_id || RCM_Permissions::can_manage_group( $user_id, $conversation_id );
		if ( ! $allowed ) {
			return new WP_Error( 'rcm_group_forbidden', __( 'You cannot remove this group member.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$target_member = RCM_DB::member_row( $conversation_id, $target_id );
		if ( ! $target_member || ( 'owner' === $target_member->member_role && $target_id !== $user_id ) ) {
			return new WP_Error( 'rcm_owner_protected', __( 'The group owner cannot be removed by another member.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		global $wpdb;
		$members_table = RCM_DB::table( 'members' );
		$conversations_table = RCM_DB::table( 'conversations' );
		$wpdb->query( 'START TRANSACTION' );
		$operation_ok = true;
		if ( 'owner' === $target_member->member_role && $target_id === $user_id ) {
			$replacement = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$members_table} WHERE conversation_id = %d AND user_id <> %d ORDER BY (member_role='admin') DESC, joined_at ASC LIMIT 1 FOR UPDATE", $conversation_id, $target_id ) );
			if ( $replacement ) {
				$operation_ok = false !== $wpdb->update( $members_table, array( 'member_role' => 'owner' ), array( 'conversation_id' => $conversation_id, 'user_id' => $replacement ), array( '%s' ), array( '%d', '%d' ) );
				$operation_ok = $operation_ok && false !== $wpdb->update( $conversations_table, array( 'created_by' => $replacement, 'updated_at' => RCM_DB::now() ), array( 'id' => $conversation_id ), array( '%d', '%s' ), array( '%d' ) );
			} else {
				$operation_ok = false !== $wpdb->update( $conversations_table, array( 'status' => 'deleted', 'updated_at' => RCM_DB::now() ), array( 'id' => $conversation_id ), array( '%s', '%s' ), array( '%d' ) );
			}
		}
		$removed = $operation_ok ? $wpdb->delete( $members_table, array( 'conversation_id' => $conversation_id, 'user_id' => $target_id ), array( '%d', '%d' ) ) : false;
		if ( false === $removed || 0 === $removed ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'rcm_db_error', __( 'Could not remove the group member.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		$wpdb->query( 'COMMIT' );
		RCM_DB::audit( $user_id, 'group_member_removed', 'conversation', $conversation_id, array( 'user_id' => $target_id ) );
		return rest_ensure_response( array( 'removed' => true ) );
	}

	private static function get_message_row( int $message_id ): ?object {
		global $wpdb;
		$g = RCM_DB::table( 'messages' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$g} WHERE id = %d LIMIT 1", $message_id ) );
		return $row ?: null;
	}

	public static function edit_message( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$message_id = absint( $request['id'] );
		$row        = self::get_message_row( $message_id );
		$settings   = RCM_Permissions::settings();
		if ( ! $row || (int) $row->sender_id !== $user_id || ! empty( $row->deleted_at ) || empty( $settings['allow_edit'] ) || ! RCM_DB::is_member( (int) $row->conversation_id, $user_id ) || ! RCM_Permissions::can_send_to_conversation( $user_id, (int) $row->conversation_id, false ) ) {
			return new WP_Error( 'rcm_edit_forbidden', __( 'You cannot edit this message.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$window = max( 0, (int) $settings['edit_window_minutes'] );
		if ( $window && strtotime( $row->created_at . ' UTC' ) < time() - ( $window * MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'rcm_edit_expired', __( 'The message editing window has expired.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$content = sanitize_textarea_field( (string) $request->get_param( 'content' ) );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'rcm_empty_message', __( 'Message cannot be empty.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		if ( self::text_length( $content ) > self::MAX_MESSAGE_LENGTH ) {
			return new WP_Error( 'rcm_message_too_long', __( 'The message is too long.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		global $wpdb;
		$updated = $wpdb->update( RCM_DB::table( 'messages' ), array( 'content' => $content, 'edited_at' => RCM_DB::now() ), array( 'id' => $message_id ), array( '%s', '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not update the message.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		RCM_DB::audit( $user_id, 'message_edited', 'message', $message_id );
		return rest_ensure_response( array( 'message' => self::serialize_message( self::get_message_row( $message_id ), $user_id ) ) );
	}

	public static function purge_message_attachments( int $message_id ): bool {
		global $wpdb;
		$table = RCM_DB::table( 'attachments' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE message_id = %d", $message_id ) );
		if ( empty( $rows ) ) { return true; }
		if ( false === $wpdb->delete( $table, array( 'message_id' => $message_id ), array( '%d' ) ) ) {
			return false;
		}
		foreach ( $rows as $row ) {
			$still_referenced = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE storage_key = %s", (string) $row->storage_key ) );
			if ( 0 === $still_referenced ) {
				RCM_Attachments::delete_storage( (string) $row->storage_key );
			}
		}
		return true;
	}

	public static function delete_message( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$message_id = absint( $request['id'] );
		$row        = self::get_message_row( $message_id );
		$settings   = RCM_Permissions::settings();
		if ( ! $row || ! RCM_DB::is_member( (int) $row->conversation_id, $user_id ) ) {
			return new WP_Error( 'rcm_message_not_found', __( 'Message not found.', 'rolechat-messenger' ), array( 'status' => 404 ) );
		}
		$is_moderator = current_user_can( 'rcm_moderate_chat' ) || current_user_can( 'manage_options' );
		if ( ! $is_moderator ) {
			if ( (int) $row->sender_id !== $user_id || empty( $settings['allow_delete'] ) ) {
				return new WP_Error( 'rcm_delete_forbidden', __( 'You cannot delete this message.', 'rolechat-messenger' ), array( 'status' => 403 ) );
			}
			$window = max( 0, (int) $settings['delete_for_everyone_minutes'] );
			if ( $window && strtotime( $row->created_at . ' UTC' ) < time() - ( $window * MINUTE_IN_SECONDS ) ) {
				return new WP_Error( 'rcm_delete_expired', __( 'The deletion window has expired.', 'rolechat-messenger' ), array( 'status' => 403 ) );
			}
		}
		global $wpdb;
		$deleted = $wpdb->update( RCM_DB::table( 'messages' ), array( 'content' => '', 'deleted_at' => RCM_DB::now(), 'deleted_by' => $user_id ), array( 'id' => $message_id ), array( '%s', '%s', '%d' ), array( '%d' ) );
		if ( false === $deleted ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not delete the message.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		if ( ! self::purge_message_attachments( $message_id ) ) {
			return new WP_Error( 'rcm_attachment_cleanup', __( 'The message was deleted, but its attachment metadata could not be removed. Please try again.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		RCM_DB::audit( $user_id, 'message_deleted', 'message', $message_id, array( 'moderator' => $is_moderator ) );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function forward_message( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$message_id = absint( $request['id'] );
		$target_conversation_id = absint( $request->get_param( 'conversation_id' ) );
		$row = self::get_message_row( $message_id );
		$settings = RCM_Permissions::settings();
		if ( empty( $settings['allow_forward'] ) || ! $row || ! RCM_DB::is_member( (int) $row->conversation_id, $user_id ) || ! empty( $row->deleted_at ) ) {
			return new WP_Error( 'rcm_forward_forbidden', __( 'You cannot forward this message.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		global $wpdb;
		$a = RCM_DB::table( 'attachments' );
		$g = RCM_DB::table( 'messages' );
		$c = RCM_DB::table( 'conversations' );
		$attachments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$a} WHERE message_id = %d ORDER BY id ASC", $message_id ) );
		$has_attachments = ! empty( $attachments );
		$has_text        = '' !== trim( (string) $row->content );
		if ( ! RCM_Permissions::can_send_to_conversation( $user_id, $target_conversation_id, $has_attachments, $has_text ) ) {
			return new WP_Error( 'rcm_forward_target_forbidden', __( 'You cannot forward this message to that conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		if ( ! RCM_Permissions::rate_limit_ok( $user_id ) ) {
			return new WP_Error( 'rcm_rate_limited', __( 'Too many messages. Please wait before sending again.', 'rolechat-messenger' ), array( 'status' => 429 ) );
		}
		$now = RCM_DB::now();
		$wpdb->insert( $g, array(
			'conversation_id' => $target_conversation_id,
			'sender_id' => $user_id,
			'reply_to_id' => 0,
			'type' => empty( $attachments ) ? 'text' : 'attachment',
			'content' => (string) $row->content,
			'created_at' => $now,
		), array( '%d','%d','%d','%s','%s','%s' ) );
		$new_id = (int) $wpdb->insert_id;
		if ( ! $new_id ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not forward the message.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		foreach ( $attachments as $att ) {
			$copied = $wpdb->insert( $a, array(
				'message_id' => $new_id,
				'conversation_id' => $target_conversation_id,
				'attachment_id' => 0,
				'uploader_id' => $user_id,
				'storage_key' => (string) $att->storage_key,
				'file_name' => $att->file_name,
				'mime_type' => $att->mime_type,
				'file_size' => (int) $att->file_size,
				'created_at' => $now,
			), array( '%d','%d','%d','%d','%s','%s','%s','%d','%s' ) );
			if ( false === $copied ) {
				$wpdb->delete( $a, array( 'message_id' => $new_id ), array( '%d' ) );
				$wpdb->delete( $g, array( 'id' => $new_id ), array( '%d' ) );
				return new WP_Error( 'rcm_db_error', __( 'Could not copy the forwarded attachments.', 'rolechat-messenger' ), array( 'status' => 500 ) );
			}
		}
		$updated = $wpdb->update( $c, array( 'updated_at' => $now, 'last_message_at' => $now, 'last_message_id' => $new_id ), array( 'id' => $target_conversation_id ), array( '%s','%s','%d' ), array( '%d' ) );
		if ( false === $updated ) {
			$wpdb->delete( $a, array( 'message_id' => $new_id ), array( '%d' ) );
			$wpdb->delete( $g, array( 'id' => $new_id ), array( '%d' ) );
			return new WP_Error( 'rcm_db_error', __( 'Could not update the destination conversation.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		RCM_DB::audit( $user_id, 'message_forwarded', 'message', $message_id, array( 'to_conversation' => $target_conversation_id, 'new_message_id' => $new_id ) );
		return rest_ensure_response( array( 'message' => self::serialize_message( self::get_message_row( $new_id ), $user_id ) ) );
	}

	public static function react( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$message_id = absint( $request['id'] );
		$row        = self::get_message_row( $message_id );
		$settings   = RCM_Permissions::settings();
		if ( empty( $settings['allow_reactions'] ) || ! $row || ! empty( $row->deleted_at ) || ! RCM_DB::is_member( (int) $row->conversation_id, $user_id ) || ! RCM_Permissions::can_send_to_conversation( $user_id, (int) $row->conversation_id, false ) ) {
			return new WP_Error( 'rcm_reaction_forbidden', __( 'You cannot react to this message.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$reaction = (string) $request->get_param( 'reaction' );
		$allowed  = array( '👍', '❤️', '😂', '😮', '😢', '👏', '👎' );
		if ( ! in_array( $reaction, $allowed, true ) ) {
			return new WP_Error( 'rcm_invalid_reaction', __( 'Invalid reaction.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		global $wpdb;
		$r = RCM_DB::table( 'reactions' );
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$r} WHERE message_id = %d AND user_id = %d AND reaction = %s", $message_id, $user_id, $reaction ) );
		if ( $exists ) {
			$changed = $wpdb->delete( $r, array( 'id' => $exists ), array( '%d' ) );
			$active = false;
		} else {
			$changed = $wpdb->insert( $r, array( 'message_id' => $message_id, 'user_id' => $user_id, 'reaction' => $reaction, 'created_at' => RCM_DB::now() ), array( '%d', '%d', '%s', '%s' ) );
			$active = true;
		}
		if ( false === $changed ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not update the reaction.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'active' => $active, 'message' => self::serialize_message( self::get_message_row( $message_id ), $user_id ) ) );
	}

	public static function report_message( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$message_id = absint( $request['id'] );
		$row        = self::get_message_row( $message_id );
		if ( ! $row || ! empty( $row->deleted_at ) || (int) $row->sender_id === $user_id || ! RCM_DB::is_member( (int) $row->conversation_id, $user_id ) ) {
			return new WP_Error( 'rcm_report_forbidden', __( 'You cannot report this message.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$reason = sanitize_textarea_field( (string) $request->get_param( 'reason' ) );
		if ( self::text_length( $reason ) > self::MAX_REPORT_REASON_LENGTH ) {
			return new WP_Error( 'rcm_report_reason_too_long', __( 'The report reason is too long.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		global $wpdb;
		$reports = RCM_DB::table( 'reports' );
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$reports} WHERE reporter_id = %d AND message_id = %d AND status = 'open' LIMIT 1", $user_id, $message_id ) );
		if ( $existing ) {
			return rest_ensure_response( array( 'reported' => true, 'existing' => true ) );
		}
		if ( ! RCM_Permissions::rate_limit_ok( $user_id, 'report', 10 ) ) {
			return new WP_Error( 'rcm_rate_limited', __( 'Too many reports were submitted. Please wait before trying again.', 'rolechat-messenger' ), array( 'status' => 429 ) );
		}
		$inserted = $wpdb->insert( $reports, array( 'reporter_id' => $user_id, 'message_id' => $message_id, 'reason' => $reason, 'status' => 'open', 'created_at' => RCM_DB::now() ), array( '%d', '%d', '%s', '%s', '%s' ) );
		if ( false === $inserted ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not submit the report.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		RCM_DB::audit( $user_id, 'message_reported', 'message', $message_id );
		return rest_ensure_response( array( 'reported' => true ) );
	}

	public static function upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id         = get_current_user_id();
		$conversation_id = absint( $request->get_param( 'conversation_id' ) );
		$settings        = RCM_Permissions::settings();
		if ( empty( $settings['allow_attachments'] ) ) {
			return new WP_Error( 'rcm_upload_disabled', __( 'Attachments are disabled.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		if ( ! RCM_Attachments::encryption_available() ) {
			return new WP_Error( 'rcm_secure_upload_unavailable', __( 'Secure attachments require the PHP Sodium extension on this server.', 'rolechat-messenger' ), array( 'status' => 503 ) );
		}
		if ( ! $conversation_id || ! RCM_Permissions::can_send_to_conversation( $user_id, $conversation_id, true, false ) ) {
			return new WP_Error( 'rcm_upload_forbidden', __( 'You are not allowed to upload an attachment to this conversation.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$upload_limit = max( 1, (int) $settings['upload_rate_limit_per_minute'] );
		if ( ! RCM_Permissions::rate_limit_ok( $user_id, 'upload', $upload_limit ) ) {
			return new WP_Error( 'rcm_rate_limited', __( 'Too many attachments were uploaded. Please wait before trying again.', 'rolechat-messenger' ), array( 'status' => 429 ) );
		}
		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			return new WP_Error( 'rcm_upload_missing', __( 'No file was uploaded.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below before any persistence.
		if ( (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'rcm_upload_invalid', __( 'The file upload was not valid.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		$max = max( 1, (int) $settings['max_attachment_mb'] ) * MB_IN_BYTES;
		if ( empty( $file['size'] ) || (int) $file['size'] > $max ) {
			return new WP_Error( 'rcm_upload_too_large', __( 'The uploaded file is empty or exceeds the configured size limit.', 'rolechat-messenger' ), array( 'status' => 413 ) );
		}
		$file_name    = sanitize_file_name( (string) $file['name'] );
		if ( '' === $file_name || self::text_length( $file_name ) > 240 ) {
			return new WP_Error( 'rcm_upload_name', __( 'The attachment file name is invalid or too long.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		$allowed_exts = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', strtolower( (string) $settings['allowed_extensions'] ) ) ) ) );
		$ext          = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return new WP_Error( 'rcm_upload_type', __( 'This file extension is not allowed.', 'rolechat-messenger' ), array( 'status' => 415 ) );
		}
		$all_mimes = get_allowed_mime_types( $user_id );
		$mimes     = array();
		foreach ( $all_mimes as $extensions => $mime ) {
			if ( array_intersect( explode( '|', $extensions ), $allowed_exts ) ) {
				$mimes[ $extensions ] = $mime;
			}
		}
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file_name, $mimes );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new WP_Error( 'rcm_upload_mime', __( 'The file content does not match an allowed MIME type.', 'rolechat-messenger' ), array( 'status' => 415 ) );
		}
		$storage_key = RCM_Attachments::new_storage_key();
		$encrypted   = RCM_Attachments::encrypt_uploaded_file( $file['tmp_name'], $storage_key );
		if ( is_wp_error( $encrypted ) ) {
			return $encrypted;
		}
		global $wpdb;
		$table = RCM_DB::table( 'attachments' );
		$inserted = $wpdb->insert( $table, array(
			'message_id'    => 0,
			'conversation_id' => $conversation_id,
			'attachment_id' => 0,
			'uploader_id'   => $user_id,
			'storage_key'   => $storage_key,
			'file_name'     => $file_name,
			'mime_type'     => sanitize_mime_type( (string) $checked['type'] ),
			'file_size'     => (int) $file['size'],
			'created_at'    => RCM_DB::now(),
		), array( '%d','%d','%d','%d','%s','%s','%s','%d','%s' ) );
		if ( false === $inserted ) {
			RCM_Attachments::delete_storage( $storage_key );
			return new WP_Error( 'rcm_upload_db', __( 'The encrypted attachment metadata could not be saved.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		$row_id   = (int) $wpdb->insert_id;
		$mime     = sanitize_mime_type( (string) $checked['type'] );
		$is_image = str_starts_with( $mime, 'image/' );
		$url      = RCM_Attachments::download_url( $row_id );
		return rest_ensure_response( array( 'attachment' => array(
			'id'            => $row_id,
			'name'          => $file_name,
			'url'           => $url,
			'mime'          => $mime,
			'size'          => (int) $file['size'],
			'is_image'      => $is_image,
			'thumbnail_url' => $is_image ? $url : '',
		) ) );
	}

	public static function download_attachment(): void {
		if ( ! is_user_logged_in() ) {
			status_header( 401 );
			exit;
		}
		$id    = absint( $_GET['id'] ?? 0 );
		$nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) );
		if ( ! $id || ! wp_verify_nonce( $nonce, 'rcm_download_attachment_' . $id ) ) {
			status_header( 403 );
			exit;
		}
		global $wpdb;
		$table = RCM_DB::table( 'attachments' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
		if ( ! $row ) {
			status_header( 404 );
			exit;
		}
		$user_id = get_current_user_id();
		$allowed = false;
		if ( 0 === (int) $row->message_id ) {
			$allowed = (int) $row->uploader_id === $user_id;
		} else {
			$message = self::get_message_row( (int) $row->message_id );
			$allowed = $message && RCM_DB::is_member( (int) $message->conversation_id, $user_id );
		}
		if ( ! $allowed || ! RCM_Permissions::can_use_chat( $user_id ) ) {
			status_header( 403 );
			exit;
		}
		$path = RCM_Attachments::path( (string) $row->storage_key );
		if ( '' === $path || ! is_file( $path ) ) {
			status_header( 404 );
			exit;
		}
		$stream = RCM_Attachments::decrypted_stream( (string) $row->storage_key );
		if ( false === $stream ) {
			status_header( 500 );
			exit;
		}
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		$mime = sanitize_mime_type( (string) $row->mime_type ) ?: 'application/octet-stream';
		$disposition = str_starts_with( $mime, 'image/' ) ? 'inline' : 'attachment';
		$fallback = sanitize_file_name( (string) $row->file_name ) ?: 'attachment';
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . (int) $row->file_size );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . str_replace( '"', '', $fallback ) . '"; filename*=UTF-8\'\'' . rawurlencode( (string) $row->file_name ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
		fpassthru( $stream );
		fclose( $stream );
		exit;
	}

	public static function users( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$search  = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$limit   = min( 50, max( 5, absint( $request->get_param( 'limit' ) ?: 25 ) ) );
		$settings = RCM_Permissions::settings();
		$sender_roles = RCM_Permissions::user_roles( $user_id );
		$target_roles = array();
		foreach ( $sender_roles as $sender_role ) {
			foreach ( (array) ( $settings['role_matrix'][ $sender_role ] ?? array() ) as $recipient_role => $abilities ) {
				if ( ! empty( $abilities['initiate'] ) ) { $target_roles[] = $recipient_role; }
			}
		}
		$target_roles = array_values( array_unique( $target_roles ) );
		if ( empty( $target_roles ) ) { return rest_ensure_response( array( 'users' => array() ) ); }

		$args = array(
			'number'     => min( 200, $limit * 4 ),
			'exclude'    => array( $user_id ),
			'role__in'   => $target_roles,
			'orderby'    => 'display_name',
			'order'      => 'ASC',
			'fields'     => 'all',
		);
		if ( '' !== $search ) {
			$args['search'] = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_nicename', 'display_name' );
		}
		$query = new WP_User_Query( $args );
		$out   = array();
		foreach ( $query->get_results() as $user ) {
			if ( RCM_Permissions::can_initiate( $user_id, (int) $user->ID ) ) {
				$out[] = RCM_DB::user_display( (int) $user->ID );
				if ( count( $out ) >= $limit ) { break; }
			}
		}
		return rest_ensure_response( array( 'users' => $out ) );
	}


	private static function blocked_users( int $user_id ): array {
		global $wpdb;
		$t = RCM_DB::table( 'blocks' );
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT blocked_user_id FROM {$t} WHERE user_id = %d", $user_id ) ) );
	}

	private static function contact_list( int $user_id ): array {
		global $wpdb;
		$t = RCM_DB::table( 'contacts' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d ORDER BY id DESC", $user_id ) );
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! RCM_Permissions::can_initiate( $user_id, (int) $row->contact_user_id ) ) {
				continue;
			}
			$user = RCM_DB::user_display( (int) $row->contact_user_id );
			if ( $user ) {
				$user['contact_id'] = (int) $row->id;
				$user['category_id'] = (int) $row->category_id;
				$out[] = $user;
			}
		}
		return $out;
	}

	public static function contacts(): WP_REST_Response {
		return rest_ensure_response( array( 'contacts' => self::contact_list( get_current_user_id() ) ) );
	}

	public static function save_contact( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id    = get_current_user_id();
		$contact_id = absint( $request->get_param( 'user_id' ) );
		$category_id= absint( $request->get_param( 'category_id' ) );
		if ( ! RCM_Permissions::can_initiate( $user_id, $contact_id ) ) {
			return new WP_Error( 'rcm_contact_forbidden', __( 'You cannot add this user as a contact.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		global $wpdb;
		if ( $category_id ) {
			$owns = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . RCM_DB::table( 'contact_categories' ) . " WHERE id = %d AND user_id = %d", $category_id, $user_id ) );
			if ( ! $owns ) { $category_id = 0; }
		}
		$t = RCM_DB::table( 'contacts' );
		$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE user_id = %d AND contact_user_id = %d", $user_id, $contact_id ) );
		if ( $existing ) {
			$saved = $wpdb->update( $t, array( 'category_id' => $category_id ), array( 'id' => $existing ), array( '%d' ), array( '%d' ) );
		} else {
			$saved = $wpdb->insert( $t, array( 'user_id' => $user_id, 'contact_user_id' => $contact_id, 'category_id' => $category_id, 'created_at' => RCM_DB::now() ), array( '%d', '%d', '%d', '%s' ) );
		}
		if ( false === $saved ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not save the contact.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'contacts' => self::contact_list( $user_id ) ) );
	}

	public static function delete_contact( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;
		$deleted = $wpdb->delete( RCM_DB::table( 'contacts' ), array( 'user_id' => get_current_user_id(), 'contact_user_id' => absint( $request['user_id'] ) ), array( '%d', '%d' ) );
		if ( false === $deleted ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not delete the contact.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	private static function category_list( int $user_id ): array {
		global $wpdb;
		$t = RCM_DB::table( 'contact_categories' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,name,sort_order FROM {$t} WHERE user_id = %d ORDER BY sort_order ASC,name ASC", $user_id ), ARRAY_A );
		return array_map( static fn( $row ) => array( 'id' => (int) $row['id'], 'name' => $row['name'], 'sort_order' => (int) $row['sort_order'] ), $rows );
	}

	public static function categories(): WP_REST_Response {
		return rest_ensure_response( array( 'categories' => self::category_list( get_current_user_id() ) ) );
	}

	public static function create_category( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) { return new WP_Error( 'rcm_category_name', __( 'Category name is required.', 'rolechat-messenger' ), array( 'status' => 400 ) ); }
		if ( self::text_length( $name ) > self::MAX_CATEGORY_NAME_LENGTH ) { return new WP_Error( 'rcm_category_name', __( 'The category name is too long.', 'rolechat-messenger' ), array( 'status' => 400 ) ); }
		global $wpdb;
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . RCM_DB::table( 'contact_categories' ) . " WHERE user_id = %d", $user_id ) );
		if ( $count >= 100 ) { return new WP_Error( 'rcm_category_limit', __( 'You cannot create more than 100 contact categories.', 'rolechat-messenger' ), array( 'status' => 400 ) ); }
		if ( false === $wpdb->insert( RCM_DB::table( 'contact_categories' ), array( 'user_id' => $user_id, 'name' => $name, 'sort_order' => 0, 'created_at' => RCM_DB::now() ), array( '%d', '%s', '%d', '%s' ) ) ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not create the category.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'categories' => self::category_list( $user_id ) ) );
	}

	public static function update_category( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$id = absint( $request['id'] );
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) { return new WP_Error( 'rcm_category_name', __( 'Category name is required.', 'rolechat-messenger' ), array( 'status' => 400 ) ); }
		if ( self::text_length( $name ) > self::MAX_CATEGORY_NAME_LENGTH ) { return new WP_Error( 'rcm_category_name', __( 'The category name is too long.', 'rolechat-messenger' ), array( 'status' => 400 ) ); }
		global $wpdb;
		if ( false === $wpdb->update( RCM_DB::table( 'contact_categories' ), array( 'name' => $name ), array( 'id' => $id, 'user_id' => $user_id ), array( '%s' ), array( '%d', '%d' ) ) ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not update the category.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'categories' => self::category_list( $user_id ) ) );
	}

	public static function delete_category( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$id = absint( $request['id'] );
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$contacts_updated = $wpdb->update( RCM_DB::table( 'contacts' ), array( 'category_id' => 0 ), array( 'user_id' => $user_id, 'category_id' => $id ), array( '%d' ), array( '%d', '%d' ) );
		$category_deleted = false !== $contacts_updated ? $wpdb->delete( RCM_DB::table( 'contact_categories' ), array( 'id' => $id, 'user_id' => $user_id ), array( '%d', '%d' ) ) : false;
		if ( false === $category_deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'rcm_db_error', __( 'Could not delete the category.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		$wpdb->query( 'COMMIT' );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function block_user( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$settings = RCM_Permissions::settings();
		if ( empty( $settings['allow_user_blocking'] ) ) {
			return new WP_Error( 'rcm_blocking_disabled', __( 'User blocking is disabled.', 'rolechat-messenger' ), array( 'status' => 403 ) );
		}
		$user_id = get_current_user_id();
		$target  = absint( $request['user_id'] );
		if ( $target === $user_id || ! get_userdata( $target ) ) {
			return new WP_Error( 'rcm_invalid_user', __( 'Invalid user.', 'rolechat-messenger' ), array( 'status' => 400 ) );
		}
		global $wpdb;
		$t = RCM_DB::table( 'blocks' );
		$blocked = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t} (user_id,blocked_user_id,created_at) VALUES (%d,%d,%s)", $user_id, $target, RCM_DB::now() ) );
		if ( false === $blocked ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not block the user.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		RCM_DB::audit( $user_id, 'user_blocked', 'user', $target );
		return rest_ensure_response( array( 'blocked' => true ) );
	}

	public static function unblock_user( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$user_id = get_current_user_id();
		$target  = absint( $request['user_id'] );
		global $wpdb;
		if ( false === $wpdb->delete( RCM_DB::table( 'blocks' ), array( 'user_id' => $user_id, 'blocked_user_id' => $target ), array( '%d', '%d' ) ) ) {
			return new WP_Error( 'rcm_db_error', __( 'Could not unblock the user.', 'rolechat-messenger' ), array( 'status' => 500 ) );
		}
		RCM_DB::audit( $user_id, 'user_unblocked', 'user', $target );
		return rest_ensure_response( array( 'blocked' => false ) );
	}

	public static function presence( WP_REST_Request $request ): WP_REST_Response {
		$user_id = get_current_user_id();
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$allowed = array( 'online', 'away', 'busy', 'dnd', 'offline' );
		if ( in_array( $status, $allowed, true ) ) { update_user_meta( $user_id, '_rcm_presence', $status ); }
		self::touch_presence( $user_id );
		return rest_ensure_response( array( 'presence' => (string) get_user_meta( $user_id, '_rcm_presence', true ) ?: 'online' ) );
	}

	private static function touch_presence( int $user_id ): void {
		$last = (int) get_user_meta( $user_id, '_rcm_presence_touch', true );
		if ( time() - $last >= 30 ) {
			update_user_meta( $user_id, '_rcm_last_seen', gmdate( 'Y-m-d H:i:s' ) );
			update_user_meta( $user_id, '_rcm_presence_touch', time() );
		}
	}
}
