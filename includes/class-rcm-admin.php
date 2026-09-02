<?php

defined( 'ABSPATH' ) || exit;

final class RCM_Admin {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_rcm_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_rcm_moderation_action', array( __CLASS__, 'moderation_action' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
	}

	public static function menu(): void {
		if ( RCM_Permissions::can_use_backend() ) {
			add_menu_page(
				__( 'Chat', 'rolechat-messenger' ),
				__( 'Chat', 'rolechat-messenger' ),
				'read',
				'rcm-chat',
				array( __CLASS__, 'messenger_page' ),
				'dashicons-format-chat',
				25
			);
			add_submenu_page( 'rcm-chat', __( 'Messenger', 'rolechat-messenger' ), __( 'Messenger', 'rolechat-messenger' ), 'read', 'rcm-chat', array( __CLASS__, 'messenger_page' ) );
		}
		if ( current_user_can( 'rcm_moderate_chat' ) || current_user_can( 'manage_options' ) ) {
			$parent = RCM_Permissions::can_use_backend() ? 'rcm-chat' : 'tools.php';
			$capability = current_user_can( 'rcm_moderate_chat' ) ? 'rcm_moderate_chat' : 'manage_options';
			add_submenu_page( $parent, __( 'Chat Moderation', 'rolechat-messenger' ), __( 'Moderation', 'rolechat-messenger' ), $capability, 'rcm-moderation', array( __CLASS__, 'moderation_page' ) );
		}
		if ( current_user_can( 'manage_options' ) ) {
			$parent = RCM_Permissions::can_use_backend() ? 'rcm-chat' : 'options-general.php';
			add_submenu_page( $parent, __( 'Chat Settings', 'rolechat-messenger' ), __( 'Settings', 'rolechat-messenger' ), 'manage_options', 'rcm-settings', array( __CLASS__, 'settings_page' ) );
		}
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'rcm-' ) ) {
			return;
		}
		wp_enqueue_style( 'rcm-chat', RCM_URL . 'assets/css/chat.css', array(), RCM_VERSION );
		wp_enqueue_style( 'rcm-admin', RCM_URL . 'assets/css/admin.css', array( 'rcm-chat' ), RCM_VERSION );

		if ( false !== strpos( $hook, 'rcm-chat' ) ) {
			wp_enqueue_script( 'rcm-chat-core', RCM_URL . 'assets/js/chat-core.js', array(), RCM_VERSION, true );
			wp_enqueue_script( 'rcm-admin-chat', RCM_URL . 'assets/js/admin.js', array( 'rcm-chat-core' ), RCM_VERSION, true );
			wp_localize_script( 'rcm-chat-core', 'RCM_CONFIG', self::js_config( 'admin' ) );
		}
	}

	private static function js_config( string $mode ): array {
		$settings = RCM_Permissions::settings();
		return array(
			'restUrl'      => esc_url_raw( rest_url( 'rolechat/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'mode'         => $mode,
			'currentUserId'=> get_current_user_id(),
			'pollInterval' => max( 2000, (int) $settings['poll_interval_ms'] ),
			'accent'       => sanitize_hex_color( $settings['frontend_widget_accent'] ) ?: '#229ED9',
			'labels'       => self::js_labels(),
		);
	}

	public static function js_labels(): array {
		return array(
			'loading'                  => __( 'Loading conversations…', 'rolechat-messenger' ),
			'noConversations'          => __( 'No conversations yet.', 'rolechat-messenger' ),
			'newMessage'               => __( 'New message', 'rolechat-messenger' ),
			'newChat'                  => __( 'New chat', 'rolechat-messenger' ),
			'newGroup'                 => __( 'New group', 'rolechat-messenger' ),
			'contacts'                 => __( 'Contacts', 'rolechat-messenger' ),
			'archived'                 => __( 'Archived', 'rolechat-messenger' ),
			'search'                   => __( 'Search', 'rolechat-messenger' ),
			'typeMessage'              => __( 'Write a message…', 'rolechat-messenger' ),
			'send'                     => __( 'Send', 'rolechat-messenger' ),
			'attach'                   => __( 'Attach', 'rolechat-messenger' ),
			'typing'                   => __( 'typing…', 'rolechat-messenger' ),
			'online'                   => __( 'Online', 'rolechat-messenger' ),
			'away'                     => __( 'Away', 'rolechat-messenger' ),
			'busy'                     => __( 'Busy', 'rolechat-messenger' ),
			'dnd'                      => __( 'Do not disturb', 'rolechat-messenger' ),
			'offline'                  => __( 'Offline', 'rolechat-messenger' ),
			'reply'                    => __( 'Reply', 'rolechat-messenger' ),
			'edit'                     => __( 'Edit', 'rolechat-messenger' ),
			'delete'                   => __( 'Delete', 'rolechat-messenger' ),
			'report'                   => __( 'Report', 'rolechat-messenger' ),
			'pin'                      => __( 'Pin', 'rolechat-messenger' ),
			'unpin'                    => __( 'Unpin', 'rolechat-messenger' ),
			'archive'                  => __( 'Archive', 'rolechat-messenger' ),
			'unarchive'                => __( 'Unarchive', 'rolechat-messenger' ),
			'mute'                     => __( 'Mute', 'rolechat-messenger' ),
			'unmute'                   => __( 'Unmute', 'rolechat-messenger' ),
			'groupInfo'                => __( 'Group info', 'rolechat-messenger' ),
			'addMember'                => __( 'Add member', 'rolechat-messenger' ),
			'leaveGroup'               => __( 'Leave group', 'rolechat-messenger' ),
			'addContact'               => __( 'Add contact', 'rolechat-messenger' ),
			'removeContact'            => __( 'Remove contact', 'rolechat-messenger' ),
			'block'                    => __( 'Block user', 'rolechat-messenger' ),
			'error'                    => __( 'Something went wrong.', 'rolechat-messenger' ),
			'messageDeleted'           => __( 'Message deleted', 'rolechat-messenger' ),
			'edited'                   => __( 'edited', 'rolechat-messenger' ),
			'selectConversation'       => __( 'Select a conversation to start messaging.', 'rolechat-messenger' ),
			'chats'                    => __( 'Chats', 'rolechat-messenger' ),
			'allContacts'              => __( 'All contacts', 'rolechat-messenger' ),
			'category'                 => __( 'Category', 'rolechat-messenger' ),
			'noContacts'               => __( 'No contacts', 'rolechat-messenger' ),
			'noMessages'               => __( 'No messages yet. Start the conversation.', 'rolechat-messenger' ),
			'loadEarlier'              => __( 'Load earlier messages', 'rolechat-messenger' ),
			'waitForUploads'           => __( 'Please wait for attachments to finish uploading.', 'rolechat-messenger' ),
			'attachment'               => __( 'Attachment', 'rolechat-messenger' ),
			'forward'                  => __( 'Forward', 'rolechat-messenger' ),
			'forwardMessage'           => __( 'Forward message', 'rolechat-messenger' ),
			'messageForwarded'         => __( 'Message forwarded.', 'rolechat-messenger' ),
			'searchMessages'           => __( 'Search messages', 'rolechat-messenger' ),
			'noMessagesFound'          => __( 'No messages found.', 'rolechat-messenger' ),
			'members'                  => __( 'Members', 'rolechat-messenger' ),
			'unblock'                  => __( 'Unblock user', 'rolechat-messenger' ),
			'createGroup'              => __( 'Create group', 'rolechat-messenger' ),
			'noPermittedUsers'         => __( 'No permitted users found.', 'rolechat-messenger' ),
			'notifications'            => __( 'Notifications', 'rolechat-messenger' ),
			'notificationEnabled'      => __( 'Browser notifications enabled.', 'rolechat-messenger' ),
			'notificationNotEnabled'   => __( 'Browser notifications were not enabled.', 'rolechat-messenger' ),
			'roleChatCouldNotStart'    => __( 'RoleChat could not start.', 'rolechat-messenger' ),
		);
	}

	public static function admin_bar( WP_Admin_Bar $bar ): void {
		if ( ! RCM_Permissions::can_use_backend() ) {
			return;
		}
		$unread = RCM_DB::unread_total( get_current_user_id() );
		$title  = __( 'Chat', 'rolechat-messenger' );
		if ( $unread > 0 ) {
			$title .= ' <span class="ab-label awaiting-mod">' . min( 99, $unread ) . '</span>';
		}
		$bar->add_node( array(
			'id'    => 'rcm-chat',
			'title' => $title,
			'href'  => admin_url( 'admin.php?page=rcm-chat' ),
			'meta'  => array( 'class' => 'rcm-admin-bar-chat' ),
		) );
	}

	public static function messenger_page(): void {
		if ( ! RCM_Permissions::can_use_backend() ) {
			wp_die( esc_html__( 'You are not allowed to use the wp-admin chat interface.', 'rolechat-messenger' ) );
		}
		?>
		<div class="wrap rcm-admin-wrap">
			<div class="rcm-page-heading">
				<div>
					<h1><?php esc_html_e( 'RoleChat Messenger', 'rolechat-messenger' ); ?></h1>
					<p><?php esc_html_e( 'Secure WordPress messaging with role-aware access control.', 'rolechat-messenger' ); ?></p>
				</div>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rcm-settings' ) ); ?>"><?php esc_html_e( 'Chat settings', 'rolechat-messenger' ); ?></a>
				<?php endif; ?>
			</div>
			<div id="rcm-admin-app" class="rcm-app rcm-app-admin" aria-live="polite">
				<div class="rcm-loading-card"><span class="rcm-spinner"></span><span><?php esc_html_e( 'Loading messenger…', 'rolechat-messenger' ); ?></span></div>
			</div>
		</div>
		<?php
	}

	public static function settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'rolechat-messenger' ) );
		}
		$settings = RCM_Permissions::settings();
		$wp_roles = wp_roles();
		$roles    = $wp_roles ? $wp_roles->roles : array();
		$saved    = isset( $_GET['updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['updated'] ) );
		?>
		<div class="wrap rcm-settings-wrap">
			<div class="rcm-page-heading rcm-settings-heading">
				<div>
					<h1><?php esc_html_e( 'RoleChat Settings', 'rolechat-messenger' ); ?></h1>
					<p><?php esc_html_e( 'Control role routing, messaging capabilities, privacy, frontend behavior, security, and lifecycle policies.', 'rolechat-messenger' ); ?></p>
				</div>
			</div>
			<?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Chat settings saved.', 'rolechat-messenger' ); ?></p></div><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcm-settings-form">
				<input type="hidden" name="action" value="rcm_save_settings">
				<?php wp_nonce_field( 'rcm_save_settings' ); ?>

				<div class="rcm-settings-grid">
					<section class="rcm-settings-card rcm-settings-card-wide">
						<div class="rcm-card-title"><span class="dashicons dashicons-admin-generic"></span><div><h2><?php esc_html_e( 'General & access', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Choose where chat is available and which WordPress roles participate.', 'rolechat-messenger' ); ?></p></div></div>
						<div class="rcm-switch-grid">
							<?php self::toggle( 'enabled', __( 'Enable RoleChat', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'admin_chat', __( 'Enable wp-admin messenger', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'frontend_widget', __( 'Enable frontend widget', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'browser_notifications', __( 'Browser notifications', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'sounds', __( 'Message sounds', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'show_presence', __( 'Show presence', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'show_last_seen', __( 'Show last seen', 'rolechat-messenger' ), $settings ); ?>
						</div>

						<div class="rcm-role-columns">
							<div><h3><?php esc_html_e( 'Chat-enabled roles', 'rolechat-messenger' ); ?></h3><?php self::role_checkboxes( 'enabled_roles', (array) $settings['enabled_roles'], $roles ); ?></div>
							<div><h3><?php esc_html_e( 'Frontend widget roles', 'rolechat-messenger' ); ?></h3><p class="description"><?php esc_html_e( 'These roles use the floating frontend interface instead of the wp-admin messenger.', 'rolechat-messenger' ); ?></p><?php self::role_checkboxes( 'frontend_roles', (array) $settings['frontend_roles'], $roles ); ?></div>
							<div><h3><?php esc_html_e( 'Group creator roles', 'rolechat-messenger' ); ?></h3><?php self::role_checkboxes( 'group_creator_roles', (array) $settings['group_creator_roles'], $roles ); ?></div>
						</div>
					</section>

					<section class="rcm-settings-card rcm-settings-card-wide">
						<div class="rcm-card-title"><span class="dashicons dashicons-networking"></span><div><h2><?php esc_html_e( 'Directional role permission matrix', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Permissions are directional. Sender → recipient is evaluated independently from recipient → sender.', 'rolechat-messenger' ); ?></p></div></div>
						<div class="rcm-matrix-legend"><span><b>I</b> <?php esc_html_e( 'Initiate', 'rolechat-messenger' ); ?></span><span><b>R</b> <?php esc_html_e( 'Reply/send', 'rolechat-messenger' ); ?></span><span><b>A</b> <?php esc_html_e( 'Attachments', 'rolechat-messenger' ); ?></span></div>
						<div class="rcm-matrix-scroll"><table class="rcm-matrix-table"><thead><tr><th><?php esc_html_e( 'Sender ↓ / Recipient →', 'rolechat-messenger' ); ?></th><?php foreach ( $roles as $recipient_key => $recipient ) : ?><th><?php echo esc_html( translate_user_role( $recipient['name'] ) ); ?></th><?php endforeach; ?></tr></thead><tbody>
						<?php foreach ( $roles as $sender_key => $sender ) : ?><tr><th><?php echo esc_html( translate_user_role( $sender['name'] ) ); ?></th><?php foreach ( $roles as $recipient_key => $recipient ) : $cell = (array) ( $settings['role_matrix'][ $sender_key ][ $recipient_key ] ?? array() ); ?><td><div class="rcm-matrix-cell">
							<label title="<?php esc_attr_e( 'Initiate conversation', 'rolechat-messenger' ); ?>"><input type="checkbox" name="role_matrix[<?php echo esc_attr( $sender_key ); ?>][<?php echo esc_attr( $recipient_key ); ?>][initiate]" value="1" <?php checked( ! empty( $cell['initiate'] ) ); ?>><span>I</span></label>
							<label title="<?php esc_attr_e( 'Reply/send messages', 'rolechat-messenger' ); ?>"><input type="checkbox" name="role_matrix[<?php echo esc_attr( $sender_key ); ?>][<?php echo esc_attr( $recipient_key ); ?>][reply]" value="1" <?php checked( ! empty( $cell['reply'] ) ); ?>><span>R</span></label>
							<label title="<?php esc_attr_e( 'Send attachments', 'rolechat-messenger' ); ?>"><input type="checkbox" name="role_matrix[<?php echo esc_attr( $sender_key ); ?>][<?php echo esc_attr( $recipient_key ); ?>][attach]" value="1" <?php checked( ! empty( $cell['attach'] ) ); ?>><span>A</span></label>
						</div></td><?php endforeach; ?></tr><?php endforeach; ?>
						</tbody></table></div>
					</section>

					<section class="rcm-settings-card">
						<div class="rcm-card-title"><span class="dashicons dashicons-format-chat"></span><div><h2><?php esc_html_e( 'Messaging features', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Enable or disable optional messenger capabilities globally.', 'rolechat-messenger' ); ?></p></div></div>
						<div class="rcm-switch-stack">
							<?php self::toggle( 'allow_groups', __( 'Group conversations', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'allow_attachments', __( 'File attachments', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'allow_reactions', __( 'Message reactions', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'allow_edit', __( 'Message editing', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'allow_delete', __( 'Delete for everyone', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'allow_forward', __( 'Message forwarding', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'allow_mentions', __( 'Group mentions', 'rolechat-messenger' ), $settings ); ?>
							<?php self::toggle( 'allow_user_blocking', __( 'User blocking', 'rolechat-messenger' ), $settings ); ?>
						</div>
						<div class="rcm-field-grid">
							<?php self::number_field( 'edit_window_minutes', __( 'Edit window (minutes)', 'rolechat-messenger' ), $settings, 0, 10080 ); ?>
							<?php self::number_field( 'delete_for_everyone_minutes', __( 'Delete window (minutes)', 'rolechat-messenger' ), $settings, 0, 10080 ); ?>
						</div>
					</section>

					<section class="rcm-settings-card">
						<div class="rcm-card-title"><span class="dashicons dashicons-shield-alt"></span><div><h2><?php esc_html_e( 'Security & lifecycle', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Limit abuse, restrict uploads, and define retention/privacy behavior.', 'rolechat-messenger' ); ?></p></div></div>
						<div class="rcm-field-grid">
							<?php self::number_field( 'rate_limit_per_minute', __( 'Messages per minute', 'rolechat-messenger' ), $settings, 1, 300 ); ?>
							<?php self::number_field( 'upload_rate_limit_per_minute', __( 'Attachments per minute', 'rolechat-messenger' ), $settings, 1, 60 ); ?>
							<?php self::number_field( 'max_attachment_mb', __( 'Max attachment size (MB)', 'rolechat-messenger' ), $settings, 1, 100 ); ?>
							<?php self::number_field( 'retention_days', __( 'Retention days (0 = forever)', 'rolechat-messenger' ), $settings, 0, 3650 ); ?>
							<?php self::number_field( 'poll_interval_ms', __( 'Polling interval (ms)', 'rolechat-messenger' ), $settings, 2000, 60000 ); ?>
						</div>
						<label class="rcm-field"><span><?php esc_html_e( 'Allowed attachment extensions', 'rolechat-messenger' ); ?></span><input type="text" name="allowed_extensions" value="<?php echo esc_attr( $settings['allowed_extensions'] ); ?>"><small><?php esc_html_e( 'Comma-separated. WordPress MIME validation still applies.', 'rolechat-messenger' ); ?></small></label>
						<div class="rcm-switch-stack rcm-security-switches">
							<?php self::toggle( 'admin_can_read_all', __( 'Allow administrators to inspect all conversations', 'rolechat-messenger' ), $settings, __( 'Privacy-sensitive. Access is audited and only available in Moderation.', 'rolechat-messenger' ) ); ?>
							<?php self::toggle( 'delete_data_on_uninstall', __( 'Delete all RoleChat data on uninstall', 'rolechat-messenger' ), $settings, __( 'Destructive. Leave disabled to preserve chat data when the plugin is removed.', 'rolechat-messenger' ) ); ?>
						</div>
					</section>

					<section class="rcm-settings-card rcm-settings-card-wide">
						<div class="rcm-card-title"><span class="dashicons dashicons-admin-appearance"></span><div><h2><?php esc_html_e( 'Frontend widget & appearance', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Configure the compact customer/support messenger shown to frontend roles.', 'rolechat-messenger' ); ?></p></div></div>
						<div class="rcm-field-grid rcm-field-grid-4">
							<label class="rcm-field"><span><?php esc_html_e( 'Widget title', 'rolechat-messenger' ); ?></span><input type="text" name="frontend_widget_title" value="<?php echo esc_attr( $settings['frontend_widget_title'] ); ?>"></label>
							<label class="rcm-field"><span><?php esc_html_e( 'Greeting', 'rolechat-messenger' ); ?></span><input type="text" name="frontend_widget_greeting" value="<?php echo esc_attr( $settings['frontend_widget_greeting'] ); ?>"></label>
							<label class="rcm-field"><span><?php esc_html_e( 'Accent color', 'rolechat-messenger' ); ?></span><input type="color" name="frontend_widget_accent" value="<?php echo esc_attr( sanitize_hex_color( $settings['frontend_widget_accent'] ) ?: '#229ED9' ); ?>"></label>
							<label class="rcm-field"><span><?php esc_html_e( 'Position', 'rolechat-messenger' ); ?></span><select name="frontend_widget_position"><option value="right" <?php selected( $settings['frontend_widget_position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'rolechat-messenger' ); ?></option><option value="left" <?php selected( $settings['frontend_widget_position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'rolechat-messenger' ); ?></option></select></label>
						</div>
						<div class="rcm-switch-stack"><?php self::toggle( 'frontend_show_everywhere', __( 'Show on all frontend pages', 'rolechat-messenger' ), $settings ); ?></div>
						<div class="rcm-field-grid">
							<label class="rcm-field"><span><?php esc_html_e( 'Include page IDs', 'rolechat-messenger' ); ?></span><input type="text" name="frontend_include_pages" value="<?php echo esc_attr( $settings['frontend_include_pages'] ); ?>"><small><?php esc_html_e( 'Comma-separated page IDs. Used when “show on all” is disabled.', 'rolechat-messenger' ); ?></small></label>
							<label class="rcm-field"><span><?php esc_html_e( 'Exclude page IDs', 'rolechat-messenger' ); ?></span><input type="text" name="frontend_exclude_pages" value="<?php echo esc_attr( $settings['frontend_exclude_pages'] ); ?>"><small><?php esc_html_e( 'Comma-separated page IDs where the widget must not appear.', 'rolechat-messenger' ); ?></small></label>
						</div>
					</section>
				</div>
				<div class="rcm-save-bar"><div><strong><?php esc_html_e( 'Security note:', 'rolechat-messenger' ); ?></strong> <?php esc_html_e( 'UI visibility never grants permission; all messaging actions are re-authorized on the server.', 'rolechat-messenger' ); ?></div><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save chat settings', 'rolechat-messenger' ); ?></button></div>
			</form>
		</div>
		<?php
	}

	private static function toggle( string $key, string $label, array $settings, string $description = '' ): void {
		?>
		<label class="rcm-switch-row"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?>><span class="rcm-switch-ui"></span><span class="rcm-switch-copy"><strong><?php echo esc_html( $label ); ?></strong><?php if ( $description ) : ?><small><?php echo esc_html( $description ); ?></small><?php endif; ?></span></label>
		<?php
	}

	private static function role_checkboxes( string $name, array $selected, array $roles ): void {
		echo '<div class="rcm-role-list">';
		foreach ( $roles as $key => $role ) {
			printf( '<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s><span>%4$s</span><code>%2$s</code></label>', esc_attr( $name ), esc_attr( $key ), checked( in_array( $key, $selected, true ), true, false ), esc_html( translate_user_role( $role['name'] ) ) );
		}
		echo '</div>';
	}

	private static function number_field( string $key, string $label, array $settings, int $min, int $max ): void {
		printf( '<label class="rcm-field"><span>%1$s</span><input type="number" name="%2$s" value="%3$d" min="%4$d" max="%5$d"></label>', esc_html( $label ), esc_attr( $key ), (int) $settings[ $key ], $min, $max );
	}

	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'rolechat-messenger' ), 403 );
		}
		check_admin_referer( 'rcm_save_settings' );
		$old = RCM_Permissions::settings();
		$bools = array( 'enabled','admin_chat','frontend_widget','browser_notifications','sounds','show_presence','show_last_seen','allow_groups','allow_attachments','allow_reactions','allow_edit','allow_delete','allow_forward','allow_mentions','allow_user_blocking','admin_can_read_all','delete_data_on_uninstall','frontend_show_everywhere' );
		$new = $old;
		foreach ( $bools as $key ) {
			$new[ $key ] = isset( $_POST[ $key ] );
		}
		$all_roles = array_keys( wp_roles()->roles );
		foreach ( array( 'enabled_roles', 'frontend_roles', 'group_creator_roles' ) as $key ) {
			$posted = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array();
			$new[ $key ] = array_values( array_intersect( array_map( 'sanitize_key', $posted ), $all_roles ) );
		}
		$new['edit_window_minutes']         = min( 10080, max( 0, absint( $_POST['edit_window_minutes'] ?? 15 ) ) );
		$new['delete_for_everyone_minutes'] = min( 10080, max( 0, absint( $_POST['delete_for_everyone_minutes'] ?? 60 ) ) );
		$new['rate_limit_per_minute']       = min( 300, max( 1, absint( $_POST['rate_limit_per_minute'] ?? 30 ) ) );
		$new['upload_rate_limit_per_minute'] = min( 60, max( 1, absint( $_POST['upload_rate_limit_per_minute'] ?? 10 ) ) );
		$new['max_attachment_mb']           = min( 100, max( 1, absint( $_POST['max_attachment_mb'] ?? 10 ) ) );
		$new['retention_days']              = min( 3650, max( 0, absint( $_POST['retention_days'] ?? 0 ) ) );
		$new['poll_interval_ms']            = min( 60000, max( 2000, absint( $_POST['poll_interval_ms'] ?? 3500 ) ) );
		$extensions = sanitize_text_field( wp_unslash( $_POST['allowed_extensions'] ?? '' ) );
		$new['allowed_extensions'] = implode( ',', array_values( array_unique( array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', strtolower( $extensions ) ) ) ) ) ) ) );
		$new['frontend_widget_title']    = sanitize_text_field( wp_unslash( $_POST['frontend_widget_title'] ?? '' ) );
		$new['frontend_widget_greeting'] = sanitize_text_field( wp_unslash( $_POST['frontend_widget_greeting'] ?? '' ) );
		$new['frontend_widget_accent']   = sanitize_hex_color( wp_unslash( $_POST['frontend_widget_accent'] ?? '' ) ) ?: '#229ED9';
		$new['frontend_widget_position'] = in_array( wp_unslash( $_POST['frontend_widget_position'] ?? 'right' ), array( 'left', 'right' ), true ) ? wp_unslash( $_POST['frontend_widget_position'] ) : 'right';
		$new['frontend_include_pages']   = self::sanitize_id_list( wp_unslash( $_POST['frontend_include_pages'] ?? '' ) );
		$new['frontend_exclude_pages']   = self::sanitize_id_list( wp_unslash( $_POST['frontend_exclude_pages'] ?? '' ) );

		$posted_matrix = isset( $_POST['role_matrix'] ) && is_array( $_POST['role_matrix'] ) ? wp_unslash( $_POST['role_matrix'] ) : array();
		$matrix = array();
		foreach ( $all_roles as $sender ) {
			foreach ( $all_roles as $recipient ) {
				$cell = isset( $posted_matrix[ $sender ][ $recipient ] ) && is_array( $posted_matrix[ $sender ][ $recipient ] ) ? $posted_matrix[ $sender ][ $recipient ] : array();
				$matrix[ $sender ][ $recipient ] = array(
					'initiate' => ! empty( $cell['initiate'] ),
					'reply'    => ! empty( $cell['reply'] ),
					'attach'   => ! empty( $cell['attach'] ),
				);
			}
		}
		$new['role_matrix'] = $matrix;
		RCM_DB::update_settings( $new );
		RCM_DB::audit( get_current_user_id(), 'settings_updated', 'settings', 0, array( 'changed_keys' => array_keys( array_diff_assoc( self::flatten_scalars( $new ), self::flatten_scalars( $old ) ) ) ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'rcm-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function sanitize_id_list( string $value ): string {
		return implode( ',', array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $value ) ) ) ) ) );
	}

	private static function flatten_scalars( array $data, string $prefix = '' ): array {
		$out = array();
		foreach ( $data as $key => $value ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( is_array( $value ) ) { $out += self::flatten_scalars( $value, $path ); }
			else { $out[ $path ] = is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value; }
		}
		return $out;
	}

	public static function moderation_page(): void {
		if ( ! current_user_can( 'rcm_moderate_chat' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to moderate chat.', 'rolechat-messenger' ) );
		}
		global $wpdb;
		$reports = $wpdb->get_results(
			"SELECT r.*, m.conversation_id, m.sender_id, m.content AS message_content
			 FROM " . RCM_DB::table( 'reports' ) . " r
			 LEFT JOIN " . RCM_DB::table( 'messages' ) . " m ON m.id = r.message_id
			 ORDER BY (r.status='open') DESC, r.created_at DESC LIMIT 100"
		);
		$can_view_audit = current_user_can( 'rcm_view_audit_log' ) || current_user_can( 'manage_options' );
		$logs           = $can_view_audit ? $wpdb->get_results( "SELECT * FROM " . RCM_DB::table( 'audit_log' ) . " ORDER BY id DESC LIMIT 100" ) : array();
		$settings       = RCM_Permissions::settings();
		$can_inspect    = ! empty( $settings['admin_can_read_all'] ) && current_user_can( 'manage_options' );
		$inspect_id     = $can_inspect ? absint( $_GET['conversation'] ?? 0 ) : 0;
		if ( $inspect_id ) {
			$inspect_nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
			if ( ! wp_verify_nonce( $inspect_nonce, 'rcm_inspect_conversation_' . $inspect_id ) ) {
				$inspect_id = 0;
			}
		}
		?>
		<div class="wrap rcm-settings-wrap">
			<div class="rcm-page-heading"><div><h1><?php esc_html_e( 'Chat Moderation & Audit', 'rolechat-messenger' ); ?></h1><p><?php esc_html_e( 'Review abuse reports, suspend chat access, and inspect security-relevant administrative actions.', 'rolechat-messenger' ); ?></p></div></div>
			<div class="rcm-settings-grid">
				<section class="rcm-settings-card">
					<div class="rcm-card-title"><span class="dashicons dashicons-lock"></span><div><h2><?php esc_html_e( 'User chat restriction', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Suspend a specific user independently of their WordPress role.', 'rolechat-messenger' ); ?></p></div></div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcm-moderation-form">
						<input type="hidden" name="action" value="rcm_moderation_action"><input type="hidden" name="moderation_action" value="suspend_user"><?php wp_nonce_field( 'rcm_moderation_action' ); ?>
						<label class="rcm-field"><span><?php esc_html_e( 'WordPress user ID', 'rolechat-messenger' ); ?></span><input type="number" name="user_id" min="1" required></label>
						<label class="rcm-field"><span><?php esc_html_e( 'Duration', 'rolechat-messenger' ); ?></span><select name="duration"><option value="60"><?php esc_html_e( '1 hour', 'rolechat-messenger' ); ?></option><option value="1440"><?php esc_html_e( '1 day', 'rolechat-messenger' ); ?></option><option value="10080"><?php esc_html_e( '1 week', 'rolechat-messenger' ); ?></option><option value="43200"><?php esc_html_e( '30 days', 'rolechat-messenger' ); ?></option><option value="forever"><?php esc_html_e( 'Forever', 'rolechat-messenger' ); ?></option></select></label>
						<label class="rcm-field"><span><?php esc_html_e( 'Reason', 'rolechat-messenger' ); ?></span><input type="text" name="reason" maxlength="500"></label>
						<button class="button button-primary"><?php esc_html_e( 'Suspend chat access', 'rolechat-messenger' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcm-inline-moderation-form"><input type="hidden" name="action" value="rcm_moderation_action"><input type="hidden" name="moderation_action" value="unsuspend_user"><?php wp_nonce_field( 'rcm_moderation_action' ); ?><input type="number" name="user_id" min="1" placeholder="<?php esc_attr_e( 'User ID', 'rolechat-messenger' ); ?>" required><button class="button"><?php esc_html_e( 'Restore access', 'rolechat-messenger' ); ?></button></form>
				</section>

				<section class="rcm-settings-card rcm-settings-card-wide">
					<div class="rcm-card-title"><span class="dashicons dashicons-flag"></span><div><h2><?php esc_html_e( 'Message reports', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Reported messages are visible to moderators regardless of general conversation-inspection policy.', 'rolechat-messenger' ); ?></p></div></div>
					<div class="rcm-table-scroll"><table class="widefat striped rcm-audit-table"><thead><tr><th>ID</th><th><?php esc_html_e( 'Reporter', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Sender', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Message / reason', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Status', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Actions', 'rolechat-messenger' ); ?></th></tr></thead><tbody>
					<?php if ( empty( $reports ) ) : ?><tr><td colspan="6"><?php esc_html_e( 'No reports.', 'rolechat-messenger' ); ?></td></tr><?php else : foreach ( $reports as $report ) : $reporter = get_userdata( (int) $report->reporter_id ); $sender = get_userdata( (int) $report->sender_id ); ?><tr><td>#<?php echo (int) $report->id; ?></td><td><?php echo esc_html( $reporter ? $reporter->display_name : '#' . $report->reporter_id ); ?></td><td><?php echo esc_html( $sender ? $sender->display_name : '#' . $report->sender_id ); ?></td><td><strong><?php echo esc_html( wp_html_excerpt( (string) $report->message_content, 100, '…' ) ); ?></strong><br><small><?php echo esc_html( $report->reason ); ?></small></td><td><span class="rcm-status-pill rcm-status-<?php echo esc_attr( $report->status ); ?>"><?php echo esc_html( ucfirst( $report->status ) ); ?></span></td><td><?php if ( 'open' === $report->status ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rcm-table-actions"><input type="hidden" name="action" value="rcm_moderation_action"><input type="hidden" name="report_id" value="<?php echo (int) $report->id; ?>"><?php wp_nonce_field( 'rcm_moderation_action' ); ?><button class="button button-small" name="moderation_action" value="resolve_report"><?php esc_html_e( 'Resolve', 'rolechat-messenger' ); ?></button><button class="button button-small button-link-delete" name="moderation_action" value="delete_reported_message"><?php esc_html_e( 'Delete message', 'rolechat-messenger' ); ?></button></form><?php endif; ?><?php if ( $can_inspect && $report->conversation_id ) : ?> <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'rcm-moderation', 'conversation' => (int) $report->conversation_id ), admin_url( 'admin.php' ) ), 'rcm_inspect_conversation_' . (int) $report->conversation_id ) ); ?>"><?php esc_html_e( 'Inspect', 'rolechat-messenger' ); ?></a><?php endif; ?></td></tr><?php endforeach; endif; ?>
					</tbody></table></div>
				</section>

				<?php if ( $inspect_id && $can_inspect ) : self::render_conversation_inspection( $inspect_id ); endif; ?>

				<?php if ( $can_view_audit ) : ?>
				<section class="rcm-settings-card rcm-settings-card-wide">
					<div class="rcm-card-title"><span class="dashicons dashicons-list-view"></span><div><h2><?php esc_html_e( 'Audit log', 'rolechat-messenger' ); ?></h2><p><?php esc_html_e( 'Administrative and security-sensitive chat actions.', 'rolechat-messenger' ); ?></p></div></div>
					<div class="rcm-table-scroll"><table class="widefat striped rcm-audit-table"><thead><tr><th><?php esc_html_e( 'Time', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Actor', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Action', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Object', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'IP', 'rolechat-messenger' ); ?></th><th><?php esc_html_e( 'Details', 'rolechat-messenger' ); ?></th></tr></thead><tbody><?php foreach ( $logs as $log ) : $actor = get_userdata( (int) $log->actor_id ); ?><tr><td><?php echo esc_html( get_date_from_gmt( $log->created_at, 'Y-m-d H:i:s' ) ); ?></td><td><?php echo esc_html( $actor ? $actor->display_name : '#' . $log->actor_id ); ?></td><td><code><?php echo esc_html( $log->action ); ?></code></td><td><?php echo esc_html( $log->object_type . ( $log->object_id ? ' #' . $log->object_id : '' ) ); ?></td><td><code><?php echo esc_html( $log->ip_address ); ?></code></td><td><small><?php echo esc_html( wp_html_excerpt( (string) $log->details, 160, '…' ) ); ?></small></td></tr><?php endforeach; ?></tbody></table></div>
				</section>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function render_conversation_inspection( int $conversation_id ): void {
		$settings = RCM_Permissions::settings();
		if ( ! current_user_can( 'manage_options' ) || empty( $settings['admin_can_read_all'] ) ) {
			return;
		}
		global $wpdb;
		$conversation = RCM_DB::conversation_row( $conversation_id );
		if ( ! $conversation ) { return; }
		$messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . RCM_DB::table( 'messages' ) . " WHERE conversation_id = %d ORDER BY id DESC LIMIT 200", $conversation_id ) );
		$members = array_map( array( 'RCM_DB', 'user_display' ), RCM_DB::conversation_members( $conversation_id ) );
		?>
		<section class="rcm-settings-card rcm-settings-card-wide rcm-inspection-card"><div class="rcm-card-title"><span class="dashicons dashicons-visibility"></span><div><h2><?php echo esc_html( sprintf( __( 'Conversation inspection #%d', 'rolechat-messenger' ), $conversation_id ) ); ?></h2><p><?php esc_html_e( 'This privacy-sensitive access is enabled by policy and should be used only for legitimate moderation or compliance needs.', 'rolechat-messenger' ); ?></p></div></div><div class="rcm-inspection-members"><?php foreach ( $members as $member ) : if ( ! $member ) continue; ?><span><?php echo get_avatar( (int) $member['id'], 24 ); ?> <?php echo esc_html( $member['name'] ); ?></span><?php endforeach; ?></div><div class="rcm-inspection-messages"><?php foreach ( array_reverse( $messages ) as $message ) : $sender = get_userdata( (int) $message->sender_id ); ?><div class="rcm-inspection-message"><div><strong><?php echo esc_html( $sender ? $sender->display_name : '#' . $message->sender_id ); ?></strong><time><?php echo esc_html( get_date_from_gmt( $message->created_at, 'Y-m-d H:i:s' ) ); ?></time></div><p><?php echo esc_html( $message->deleted_at ? __( '[deleted]', 'rolechat-messenger' ) : $message->content ); ?></p></div><?php endforeach; ?></div></section>
		<?php
		RCM_DB::audit( get_current_user_id(), 'conversation_inspected', 'conversation', $conversation_id );
	}

	public static function moderation_action(): void {
		if ( ! current_user_can( 'rcm_moderate_chat' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'rolechat-messenger' ), 403 );
		}
		check_admin_referer( 'rcm_moderation_action' );
		$action = sanitize_key( wp_unslash( $_POST['moderation_action'] ?? '' ) );
		$actor  = get_current_user_id();
		global $wpdb;
		switch ( $action ) {
			case 'suspend_user':
				$user_id = absint( $_POST['user_id'] ?? 0 );
				$duration = sanitize_text_field( wp_unslash( $_POST['duration'] ?? '1440' ) );
				$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
				$allowed_durations = array( '60', '1440', '10080', '43200', 'forever' );
				if ( $user_id && in_array( $duration, $allowed_durations, true ) && get_userdata( $user_id ) && ! user_can( $user_id, 'manage_options' ) ) {
					$until = 'forever' === $duration ? 'forever' : gmdate( 'Y-m-d H:i:s', time() + max( 1, absint( $duration ) ) * MINUTE_IN_SECONDS );
					update_user_meta( $user_id, '_rcm_suspended_until', $until );
					update_user_meta( $user_id, '_rcm_suspension_reason', $reason );
					RCM_DB::audit( $actor, 'user_suspended', 'user', $user_id, array( 'until' => $until, 'reason' => $reason ) );
				}
				break;
			case 'unsuspend_user':
				$user_id = absint( $_POST['user_id'] ?? 0 );
				if ( $user_id && get_userdata( $user_id ) ) {
					delete_user_meta( $user_id, '_rcm_suspended_until' );
					delete_user_meta( $user_id, '_rcm_suspension_reason' );
					RCM_DB::audit( $actor, 'user_unsuspended', 'user', $user_id );
				}
				break;
			case 'resolve_report':
			case 'delete_reported_message':
				$report_id = absint( $_POST['report_id'] ?? 0 );
				$report = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . RCM_DB::table( 'reports' ) . " WHERE id = %d", $report_id ) );
				if ( $report && 'open' === $report->status ) {
					if ( 'delete_reported_message' === $action ) {
						$deleted = $wpdb->update( RCM_DB::table( 'messages' ), array( 'content' => '', 'deleted_at' => RCM_DB::now(), 'deleted_by' => $actor ), array( 'id' => (int) $report->message_id ), array( '%s', '%s', '%d' ), array( '%d' ) );
						if ( false !== $deleted && RCM_REST::purge_message_attachments( (int) $report->message_id ) ) {
							RCM_DB::audit( $actor, 'reported_message_deleted', 'message', (int) $report->message_id, array( 'report_id' => $report_id ) );
						} else {
							break;
						}
					}
					$resolved = $wpdb->update( RCM_DB::table( 'reports' ), array( 'status' => 'resolved', 'reviewed_by' => $actor, 'reviewed_at' => RCM_DB::now() ), array( 'id' => $report_id ), array( '%s', '%d', '%s' ), array( '%d' ) );
					if ( false !== $resolved ) {
						RCM_DB::audit( $actor, 'report_resolved', 'report', $report_id );
					}
				}
				break;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rcm-moderation' ) );
		exit;
	}
}
