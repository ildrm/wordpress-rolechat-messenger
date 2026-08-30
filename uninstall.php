<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;


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

$settings = get_option( 'rcm_settings', array() );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}

global $wpdb;

// Remove encrypted chat attachment storage before dropping metadata tables.
$upload = wp_upload_dir( null, false );
if ( empty( $upload['error'] ) ) {
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

delete_option( 'rcm_settings' );
delete_option( 'rcm_db_version' );
delete_option( 'rcm_attachment_secret' );
