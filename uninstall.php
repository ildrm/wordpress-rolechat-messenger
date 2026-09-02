<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove site-scoped RoleChat state and report its data-retention decision.
 *
 * @return array{installed:bool,deleted:bool}
 */
function rcm_uninstall_current_site(): array {
	wp_clear_scheduled_hook( 'rcm_daily_cleanup' );
	wp_clear_scheduled_hook( 'rcm_cleanup_continuation' );
	delete_option( 'rcm_cleanup_lock' );

	// Capabilities should never remain orphaned after the plugin is removed.
	if ( function_exists( 'wp_roles' ) ) {
		$roles = wp_roles();
		if ( $roles ) {
			foreach ( array_keys( $roles->roles ) as $role_name ) {
				$role = get_role( $role_name );
				if ( $role ) {
					$role->remove_cap( 'rcm_manage_chat' );
					$role->remove_cap( 'rcm_moderate_chat' );
					$role->remove_cap( 'rcm_view_audit_log' );
				}
			}
		}
	}

	$settings  = get_option( 'rcm_settings', null );
	$installed = is_array( $settings );
	$delete    = $installed && ! empty( $settings['delete_data_on_uninstall'] );
	if ( ! $delete ) {
		return array( 'installed' => $installed, 'deleted' => false );
	}

	global $wpdb;

	// Remove encrypted chat attachment storage before dropping metadata tables.
	$upload = wp_upload_dir( null, false );
	if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) ) {
		$secure_dir = trailingslashit( $upload['basedir'] ) . 'rolechat-secure';
		if ( is_dir( $secure_dir ) ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $secure_dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $iterator as $item ) {
				if ( $item->isDir() ) {
					@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				} else {
					@unlink( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
			@rmdir( $secure_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	$tables = array( 'conversations', 'members', 'messages', 'attachments', 'reactions', 'contacts', 'contact_categories', 'blocks', 'reports', 'audit_log' );
	foreach ( $tables as $table ) {
		$name = $wpdb->prefix . 'rcm_' . $table;
		$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	$transient_like = $wpdb->esc_like( '_transient_rcm_' ) . '%';
	$timeout_like   = $wpdb->esc_like( '_transient_timeout_rcm_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $transient_like, $timeout_like ) );

	delete_option( 'rcm_settings' );
	delete_option( 'rcm_db_version' );
	delete_option( 'rcm_attachment_secret' );

	return array( 'installed' => true, 'deleted' => true );
}

$site_results = array();
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( (int) $site_id );
		try {
			$site_results[] = rcm_uninstall_current_site();
		} finally {
			restore_current_blog();
		}
	}
} else {
	$site_results[] = rcm_uninstall_current_site();
}

// User metadata is network-global. Delete it only when every installed RoleChat site opted into full deletion.
$installed_results = array_values( array_filter( $site_results, static fn( array $result ): bool => $result['installed'] ) );
$delete_user_meta  = ! empty( $installed_results ) && count( $installed_results ) === count( array_filter( $installed_results, static fn( array $result ): bool => $result['deleted'] ) );
if ( $delete_user_meta ) {
	global $wpdb;
	$user_meta_keys    = array( '_rcm_suspended_until', '_rcm_suspension_reason', '_rcm_last_seen', '_rcm_presence', '_rcm_presence_touch' );
	$meta_placeholders = implode( ',', array_fill( 0, count( $user_meta_keys ), '%s' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ({$meta_placeholders})", $user_meta_keys ) );
}
