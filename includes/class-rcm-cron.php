<?php

defined( 'ABSPATH' ) || exit;

final class RCM_Cron {
	public static function init(): void {
		add_action( 'rcm_daily_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	public static function cleanup(): void {
		$settings = RCM_Permissions::settings();
		self::cleanup_orphan_uploads();

		$days = max( 0, (int) $settings['retention_days'] );
		if ( 0 === $days ) {
			return;
		}

		global $wpdb;
		$messages      = RCM_DB::table( 'messages' );
		$attachments   = RCM_DB::table( 'attachments' );
		$reactions     = RCM_DB::table( 'reactions' );
		$conversations = RCM_DB::table( 'conversations' );
		$cutoff        = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$message_rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id,conversation_id FROM {$messages} WHERE created_at < %s ORDER BY id ASC LIMIT 5000", $cutoff ) );

		if ( empty( $message_rows ) ) {
			return;
		}

		$message_ids      = array_map( static fn( $row ) => (int) $row->id, $message_rows );
		$conversation_ids = array_values( array_unique( array_map( static fn( $row ) => (int) $row->conversation_id, $message_rows ) ) );
		$ids_sql          = implode( ',', array_map( 'absint', $message_ids ) );
		$storage_keys     = array_values( array_unique( array_filter( array_map( 'strval', $wpdb->get_col( "SELECT storage_key FROM {$attachments} WHERE message_id IN ({$ids_sql})" ) ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$wpdb->query( "DELETE FROM {$attachments} WHERE message_id IN ({$ids_sql})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::delete_unreferenced_storage( $storage_keys );
		$wpdb->query( "DELETE FROM {$reactions} WHERE message_id IN ({$ids_sql})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$messages} WHERE id IN ({$ids_sql})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $conversation_ids as $conversation_id ) {
			$last = $wpdb->get_row( $wpdb->prepare( "SELECT id,created_at FROM {$messages} WHERE conversation_id = %d ORDER BY id DESC LIMIT 1", $conversation_id ) );
			$wpdb->update(
				$conversations,
				array(
					'last_message_id' => $last ? (int) $last->id : 0,
					'last_message_at' => $last ? $last->created_at : null,
					'updated_at'       => RCM_DB::now(),
				),
				array( 'id' => $conversation_id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		RCM_DB::audit( 0, 'retention_cleanup', 'messages', 0, array( 'deleted' => count( $message_ids ), 'cutoff' => $cutoff ) );
	}

	private static function cleanup_orphan_uploads(): void {
		global $wpdb;
		$attachments = RCM_DB::table( 'attachments' );
		$cutoff      = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$rows        = $wpdb->get_results( $wpdb->prepare( "SELECT id,storage_key FROM {$attachments} WHERE message_id = 0 AND created_at < %s ORDER BY id ASC LIMIT 500", $cutoff ) );
		if ( empty( $rows ) ) {
			return;
		}

		$ids          = array_map( static fn( $row ) => (int) $row->id, $rows );
		$storage_keys = array_values( array_unique( array_filter( array_map( static fn( $row ) => (string) $row->storage_key, $rows ) ) ) );
		$ids_sql      = implode( ',', array_map( 'absint', $ids ) );
		$wpdb->query( "DELETE FROM {$attachments} WHERE id IN ({$ids_sql})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::delete_unreferenced_storage( $storage_keys );
		RCM_DB::audit( 0, 'orphan_attachment_cleanup', 'attachment', 0, array( 'deleted' => count( $ids ) ) );
	}

	private static function delete_unreferenced_storage( array $storage_keys ): void {
		global $wpdb;
		$attachments = RCM_DB::table( 'attachments' );
		foreach ( $storage_keys as $storage_key ) {
			if ( '' === $storage_key ) {
				continue;
			}
			$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$attachments} WHERE storage_key = %s", $storage_key ) );
			if ( 0 === $remaining ) {
				RCM_Attachments::delete_storage( $storage_key );
			}
		}
	}
}
