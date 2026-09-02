<?php

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ . DIRECTORY_SEPARATOR );

$rcm_test_failures = array();
$rcm_test_count    = 0;
$rcm_test_settings = array(
	'enabled'                       => true,
	'enabled_roles'                 => array( 'sender', 'recipient' ),
	'allow_attachments'             => true,
	'allow_groups'                  => true,
	'group_creator_roles'           => array( 'sender' ),
	'rate_limit_per_minute'         => 2,
	'upload_rate_limit_per_minute'  => 1,
	'role_matrix'                   => array(),
);
$rcm_test_users = array(
	1 => (object) array( 'ID' => 1, 'roles' => array( 'sender' ) ),
	2 => (object) array( 'ID' => 2, 'roles' => array( 'recipient' ) ),
);
$rcm_test_meta       = array();
$rcm_test_transients = array();
$rcm_test_upload_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rolechat-tests-' . bin2hex( random_bytes( 6 ) );
$rcm_test_upload_error = '';

function rcm_test( bool $condition, string $description ): void {
	global $rcm_test_count, $rcm_test_failures;
	$rcm_test_count++;
	if ( ! $condition ) {
		$rcm_test_failures[] = $description;
	}
}

function __( string $text, string $domain = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return $text;
}

function sanitize_key( string $key ): string {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '' );
}

function get_userdata( int $user_id ): object|false {
	global $rcm_test_users;
	return $rcm_test_users[ $user_id ] ?? false;
}

function get_current_user_id(): int {
	return 1;
}

function get_user_meta( int $user_id, string $key, bool $single = false ): mixed { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	global $rcm_test_meta;
	return $rcm_test_meta[ $user_id ][ $key ] ?? '';
}

function delete_user_meta( int $user_id, string $key ): void {
	global $rcm_test_meta;
	unset( $rcm_test_meta[ $user_id ][ $key ] );
}

function get_transient( string $key ): mixed {
	global $rcm_test_transients;
	return $rcm_test_transients[ $key ] ?? false;
}

function set_transient( string $key, mixed $value, int $expiration ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	global $rcm_test_transients;
	$rcm_test_transients[ $key ] = $value;
	return true;
}

function wp_upload_dir( mixed $time = null, bool $create_dir = true ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	global $rcm_test_upload_dir, $rcm_test_upload_error;
	return array( 'basedir' => $rcm_test_upload_dir, 'error' => $rcm_test_upload_error );
}

function trailingslashit( string $value ): string {
	return rtrim( $value, '/\\' ) . DIRECTORY_SEPARATOR;
}

function wp_mkdir_p( string $path ): bool {
	return is_dir( $path ) || mkdir( $path, 0777, true );
}

function is_wp_error( mixed $value ): bool {
	return $value instanceof WP_Error;
}

final class WP_Error {
	public function __construct( public string $code = '', public string $message = '', public mixed $data = null ) {}
}

final class RCM_Activator {
	public static function attachment_secret(): string {
		return hash( 'sha256', 'rolechat-test-secret', true );
	}
}

final class RCM_DB {
	public static array $members = array( 10 => array( 1, 2 ) );
	public static array $conversations = array( 10 => array( 'status' => 'active', 'type' => 'direct' ) );

	public static function get_settings(): array {
		global $rcm_test_settings;
		return $rcm_test_settings;
	}

	public static function is_member( int $conversation_id, int $user_id ): bool {
		return in_array( $user_id, self::$members[ $conversation_id ] ?? array(), true );
	}

	public static function conversation_row( int $conversation_id ): ?object {
		return isset( self::$conversations[ $conversation_id ] ) ? (object) self::$conversations[ $conversation_id ] : null;
	}

	public static function conversation_members( int $conversation_id ): array {
		return self::$members[ $conversation_id ] ?? array();
	}

	public static function member_row( int $conversation_id, int $user_id ): ?object {
		return self::is_member( $conversation_id, $user_id ) ? (object) array( 'member_role' => 'member' ) : null;
	}

	public static function table( string $name ): string {
		return $name;
	}
}

final class RCM_Test_WPDB {
	public bool $blocked = false;

	public function prepare( string $query, mixed ...$args ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $query;
	}

	public function get_var( string $query ): int { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $this->blocked ? 1 : 0;
	}
}

$wpdb = new RCM_Test_WPDB();

require_once dirname( __DIR__ ) . '/includes/class-rcm-permissions.php';
require_once dirname( __DIR__ ) . '/includes/class-rcm-attachments.php';

function rcm_set_matrix( bool $reply, bool $attach ): void {
	global $rcm_test_settings;
	$rcm_test_settings['role_matrix'] = array(
		'sender' => array(
			'recipient' => array( 'initiate' => true, 'reply' => $reply, 'attach' => $attach ),
		),
		'recipient' => array(
			'sender' => array( 'initiate' => true, 'reply' => true, 'attach' => true ),
		),
	);
}

rcm_set_matrix( true, false );
rcm_test( RCM_Permissions::can_send_to_conversation( 1, 10, false, true ), 'Text sends must use the reply permission.' );
rcm_test( ! RCM_Permissions::can_send_to_conversation( 1, 10, true, false ), 'Attachment-only sends must fail without attach permission.' );
rcm_test( ! RCM_Permissions::can_send_to_conversation( 1, 10, true, true ), 'An attachment must not carry text when reply permission is disabled.' );

rcm_set_matrix( false, true );
rcm_test( ! RCM_Permissions::can_send_to_conversation( 1, 10, false, true ), 'Text must fail when only attach permission is granted.' );
rcm_test( RCM_Permissions::can_send_to_conversation( 1, 10, true, false ), 'Attachment-only sends must work independently of reply permission.' );
rcm_test( ! RCM_Permissions::can_send_to_conversation( 1, 10, true, true ), 'Mixed content must require both reply and attach permissions.' );

rcm_test( RCM_Permissions::rate_limit_ok( 1, 'message' ), 'First message must pass the rate limit.' );
rcm_test( RCM_Permissions::rate_limit_ok( 1, 'message' ), 'Second message must pass the configured rate limit.' );
rcm_test( ! RCM_Permissions::rate_limit_ok( 1, 'message' ), 'Messages over the configured limit must fail.' );
rcm_test( RCM_Permissions::rate_limit_ok( 1, 'upload', 1 ), 'A separate upload bucket must not inherit message usage.' );
rcm_test( ! RCM_Permissions::rate_limit_ok( 1, 'upload', 1 ), 'The upload bucket must enforce its own limit.' );

if ( RCM_Attachments::encryption_available() ) {
	foreach ( array( '', 'small payload', random_bytes( 1048576 ), random_bytes( 2097152 + 17 ) ) as $index => $plaintext ) {
		$input = tempnam( sys_get_temp_dir(), 'rcm-input-' );
		file_put_contents( $input, $plaintext );
		$key = RCM_Attachments::new_storage_key();
		$result = RCM_Attachments::encrypt_uploaded_file( $input, $key );
		rcm_test( true === $result, "Attachment encryption case {$index} must succeed." );
		$stream = RCM_Attachments::decrypted_stream( $key );
		rcm_test( is_resource( $stream ), "Attachment decryption case {$index} must return a stream." );
		if ( is_resource( $stream ) ) {
			rcm_test( stream_get_contents( $stream ) === $plaintext, "Attachment round trip case {$index} must preserve every byte." );
			fclose( $stream );
		}

		if ( 1 === $index ) {
			file_put_contents( RCM_Attachments::path( $key ), 'trailing-tamper', FILE_APPEND );
			ob_start();
			$tampered = RCM_Attachments::stream_decrypted( $key );
			$exposed  = ob_get_clean();
			rcm_test( false === $tampered, 'Ciphertext with trailing data must be rejected.' );
			rcm_test( '' === $exposed, 'No plaintext may be emitted before the complete ciphertext is authenticated.' );
		}

		RCM_Attachments::delete_storage( $key );
		unlink( $input );
	}
} else {
	echo "SKIP: PHP Sodium is unavailable; attachment cryptography tests were not run.\n";
}

$rcm_test_upload_error = 'uploads unavailable';
rcm_test( '' === RCM_Attachments::storage_dir(), 'An upload-directory error must not fall back to a relative or root path.' );
$rcm_test_upload_error = '';

$root       = dirname( __DIR__ );
$rest       = file_get_contents( $root . '/includes/class-rcm-rest.php' );
$activator  = file_get_contents( $root . '/includes/class-rcm-activator.php' );
$cron       = file_get_contents( $root . '/includes/class-rcm-cron.php' );
$client     = file_get_contents( $root . '/assets/js/chat-core.js' );
$frontend   = file_get_contents( $root . '/assets/js/frontend.js' );
$entry      = file_get_contents( $root . '/rolechat-messenger.php' );
$readme     = file_get_contents( $root . '/readme.txt' );
$uninstall  = file_get_contents( $root . '/uninstall.php' );

rcm_test( substr_count( $rest, "'permission_callback'" ) === 29, 'Every registered REST method must retain a permission callback.' );
rcm_test( str_contains( $client, "case 'load-more'" ), 'The historical-message button must have a click handler.' );
rcm_test( str_contains( $client, '[data-message-jump]' ), 'Reply previews must have a delegated jump handler.' );
rcm_test( str_contains( $frontend, 'isVisible:' ), 'The frontend client must provide widget visibility to read-receipt logic.' );
rcm_test( str_contains( $activator, 'conversation_id bigint(20) unsigned NOT NULL DEFAULT 0' ), 'Uploads must be bound to the authorized conversation in the schema.' );
rcm_test( str_contains( $activator, '$network_wide && is_multisite()' ), 'Activation and deactivation must handle network-wide multisite use.' );
rcm_test( str_contains( $rest, 'AND conversation_id = %d AND message_id = 0' ), 'Pending attachments must be claimed only in their authorized conversation.' );
rcm_test( str_contains( $cron, "LOCK_OPTION = 'rcm_cleanup_lock'" ), 'Cleanup workers must use a shared overlap lock.' );
rcm_test( str_contains( $uninstall, "delete_option( 'rcm_cleanup_lock' )" ), 'Uninstall must remove the cleanup lock.' );
rcm_test( str_contains( $uninstall, "get_sites( array( 'fields' => 'ids', 'number' => 0 ) )" ), 'Uninstall must process every site in a multisite network.' );

$expected_tables = array( 'conversations', 'members', 'messages', 'attachments', 'reactions', 'contacts', 'contact_categories', 'blocks', 'reports', 'audit_log' );
foreach ( $expected_tables as $table ) {
	rcm_test( str_contains( $activator, "RCM_DB::table( '{$table}' )" ), "Installer schema must define the {$table} table." );
}
preg_match_all( "/RCM_DB::table\( '([^']+)' \)/", implode( "\n", array_map( 'file_get_contents', glob( $root . '/includes/*.php' ) ) ), $referenced_tables );
$undefined_tables = array_diff( array_unique( $referenced_tables[1] ?? array() ), $expected_tables );
rcm_test( ! $undefined_tables, 'Every custom table referenced by the implementation must exist in the installer schema.' );

$expected_api_fragments = array( 'bootstrap', 'conversations', 'messages', 'upload', 'users', 'contacts', 'contact-categories', 'blocks', 'presence' );
foreach ( $expected_api_fragments as $fragment ) {
	rcm_test( str_contains( $rest, "'/{$fragment}" ) || str_contains( $rest, "'/conversations/(?P<id>\\d+)/{$fragment}" ), "The REST API must expose the {$fragment} client family." );
}
rcm_test( str_contains( $client, 'esc(message.content).replace' ), 'Message text must be escaped before HTML insertion.' );
rcm_test( str_contains( $client, 'href="${esc(a.url)}"' ), 'Attachment links must be escaped before HTML insertion.' );

foreach ( glob( $root . '/includes/*.php' ) as $include_file ) {
	rcm_test( str_contains( $entry, "includes/" . basename( $include_file ) ), basename( $include_file ) . ' must be loaded by the plugin entry point.' );
}

$project_sources = implode( "\n", array_map( 'file_get_contents', array_merge( glob( $root . '/includes/*.php' ), glob( $root . '/assets/js/*.js' ) ) ) );
rcm_test( 1 !== preg_match( '/\b(?:TODO|FIXME|XXX)\b/', $project_sources ), 'Release source must not contain unfinished-code markers.' );

preg_match( "/define\( 'RCM_VERSION', '([^']+)'/", $entry, $version_match );
preg_match( '/Stable tag: ([^\r\n]+)/', $readme, $stable_match );
rcm_test( ( $version_match[1] ?? '' ) === ( $stable_match[1] ?? '' ), 'Plugin version and stable tag must match.' );

foreach ( array( 'assets/css/chat.css', 'assets/css/admin.css', 'assets/css/frontend.css' ) as $css_file ) {
	$css = file_get_contents( $root . '/' . $css_file );
	rcm_test( substr_count( $css, '{' ) === substr_count( $css, '}' ), "CSS braces must be balanced in {$css_file}." );
}

$secure_dir = $rcm_test_upload_dir . DIRECTORY_SEPARATOR . 'rolechat-secure';
if ( is_dir( $secure_dir ) ) {
	foreach ( array( 'index.php', '.htaccess', 'web.config' ) as $name ) {
		$path = $secure_dir . DIRECTORY_SEPARATOR . $name;
		if ( is_file( $path ) ) {
			unlink( $path );
		}
	}
	rmdir( $secure_dir );
}
if ( is_dir( $rcm_test_upload_dir ) ) {
	rmdir( $rcm_test_upload_dir );
}

if ( $rcm_test_failures ) {
	fwrite( STDERR, "FAILED {$rcm_test_count} checks:\n- " . implode( "\n- ", $rcm_test_failures ) . "\n" );
	exit( 1 );
}

echo "Passed {$rcm_test_count} regression checks.\n";
