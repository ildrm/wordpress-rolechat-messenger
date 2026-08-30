<?php

defined( 'ABSPATH' ) || exit;

final class RCM_Attachments {
	private const CHUNK_SIZE = 1048576; // 1 MiB.

	public static function encryption_available(): bool {
		return function_exists( 'sodium_crypto_secretstream_xchacha20poly1305_init_push' )
			&& defined( 'SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES' );
	}

	public static function storage_dir(): string {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . 'rolechat-secure';
	}

	public static function ensure_storage_dir(): bool {
		$dir = self::storage_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$files = array(
			'index.php'   => "<?php\n// Silence is golden.\n",
			'.htaccess'   => "Deny from all\n",
			'web.config'  => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $contents ) {
			$path = trailingslashit( $dir ) . $name;
			if ( ! file_exists( $path ) ) {
				@file_put_contents( $path, $contents, LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		return true;
	}

	public static function new_storage_key(): string {
		return bin2hex( random_bytes( 24 ) );
	}

	public static function path( string $storage_key ): string {
		return trailingslashit( self::storage_dir() ) . preg_replace( '/[^a-f0-9]/', '', strtolower( $storage_key ) ) . '.rcm';
	}

	private static function key( string $storage_key ): string {
		$secret = RCM_Activator::attachment_secret();
		if ( '' === $secret ) {
			throw new RuntimeException( 'attachment_secret_unavailable' );
		}
		return hash_hmac( 'sha256', $storage_key, $secret, true );
	}

	public static function encrypt_uploaded_file( string $tmp_path, string $storage_key ): bool|WP_Error {
		if ( ! self::encryption_available() ) {
			return new WP_Error( 'rcm_sodium_required', __( 'Secure attachment encryption requires the PHP Sodium extension.', 'rolechat-messenger' ) );
		}
		if ( ! self::ensure_storage_dir() ) {
			return new WP_Error( 'rcm_storage_unavailable', __( 'RoleChat secure attachment storage is not writable.', 'rolechat-messenger' ) );
		}
		$in = @fopen( $tmp_path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $in ) {
			return new WP_Error( 'rcm_upload_read_failed', __( 'The uploaded file could not be read.', 'rolechat-messenger' ) );
		}
		$path = self::path( $storage_key );
		$out  = @fopen( $path, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $out ) {
			fclose( $in );
			return new WP_Error( 'rcm_storage_write_failed', __( 'The encrypted attachment could not be created.', 'rolechat-messenger' ) );
		}

		try {
			$key = self::key( $storage_key );
			list( $state, $header ) = sodium_crypto_secretstream_xchacha20poly1305_init_push( $key );
			fwrite( $out, $header );
			while ( true ) {
				$chunk = fread( $in, self::CHUNK_SIZE );
				if ( false === $chunk ) {
					throw new RuntimeException( 'read_failed' );
				}
				$final = feof( $in );
				if ( '' === $chunk && ! $final ) {
					continue;
				}
				$tag    = $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
				$cipher = sodium_crypto_secretstream_xchacha20poly1305_push( $state, $chunk, '', $tag );
				fwrite( $out, pack( 'N', strlen( $cipher ) ) );
				fwrite( $out, $cipher );
				if ( $final ) {
					break;
				}
			}
		} catch ( Throwable $e ) {
			fclose( $in );
			fclose( $out );
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'rcm_encrypt_failed', __( 'The attachment could not be encrypted.', 'rolechat-messenger' ) );
		}
		fclose( $in );
		fclose( $out );
		@chmod( $path, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return true;
	}

	public static function stream_decrypted( string $storage_key ): bool {
		if ( ! self::encryption_available() ) {
			return false;
		}
		$path = self::path( $storage_key );
		$in   = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $in ) {
			return false;
		}
		$header_bytes = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
		$header       = fread( $in, $header_bytes );
		if ( false === $header || strlen( $header ) !== $header_bytes ) {
			fclose( $in );
			return false;
		}
		try {
			$state = sodium_crypto_secretstream_xchacha20poly1305_init_pull( $header, self::key( $storage_key ) );
			$final_seen = false;
			while ( ! feof( $in ) ) {
				$len_raw = fread( $in, 4 );
				if ( '' === $len_raw && feof( $in ) ) {
					break;
				}
				if ( false === $len_raw || 4 !== strlen( $len_raw ) ) {
					throw new RuntimeException( 'invalid_frame' );
				}
				$unpacked = unpack( 'Nlength', $len_raw );
				$length   = (int) ( $unpacked['length'] ?? 0 );
				if ( $length <= 0 || $length > ( self::CHUNK_SIZE + 4096 ) ) {
					throw new RuntimeException( 'invalid_length' );
				}
				$cipher = '';
				while ( strlen( $cipher ) < $length ) {
					$part = fread( $in, $length - strlen( $cipher ) );
					if ( false === $part || '' === $part ) {
						throw new RuntimeException( 'truncated_frame' );
					}
					$cipher .= $part;
				}
				$result = sodium_crypto_secretstream_xchacha20poly1305_pull( $state, $cipher );
				if ( false === $result ) {
					throw new RuntimeException( 'authentication_failed' );
				}
				list( $plain, $tag ) = $result;
				echo $plain; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary authenticated file stream.
				if ( SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL === $tag ) {
					$final_seen = true;
					break;
				}
			}
			fclose( $in );
			return $final_seen;
		} catch ( Throwable $e ) {
			fclose( $in );
			return false;
		}
	}

	public static function delete_storage( string $storage_key ): void {
		$path = self::path( $storage_key );
		if ( is_file( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	public static function download_url( int $attachment_row_id ): string {
		return add_query_arg(
			array(
				'action' => 'rcm_download_attachment',
				'id'     => $attachment_row_id,
				'nonce'  => wp_create_nonce( 'rcm_download_attachment_' . $attachment_row_id ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}
}
