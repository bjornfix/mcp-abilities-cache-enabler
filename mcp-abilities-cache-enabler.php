<?php
/**
 * Plugin Name: MCP Abilities - Cache Enabler
 * Plugin URI: https://github.com/bjornfix/mcp-abilities-cache-enabler
 * Description: MCP abilities for Cache Enabler. Inspect configuration, diagnose cache state, and clear cached content safely.
 * Version: 1.0.0
 * Author: Devenia
 * Author URI: https://devenia.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.9
 * Requires PHP: 8.0
 *
 * @package MCP_Abilities_Cache_Enabler
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MCP_CACHE_ENABLER_TARGET_PLUGIN = 'cache-enabler/cache-enabler.php';

function mcp_cache_enabler_check_dependencies(): bool {
	if ( function_exists( 'wp_register_ability' ) ) {
		return true;
	}

	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p><strong>MCP Abilities - Cache Enabler</strong> requires the Abilities API plugin.</p></div>';
		}
	);

	return false;
}

function mcp_cache_enabler_permission_callback(): bool {
	return current_user_can( 'manage_options' );
}

function mcp_cache_enabler_empty_input_schema(): array {
	return array(
		'type'                 => array( 'object', 'array', 'null' ),
		'properties'           => array(
			'_ignored' => array(
				'type'        => 'boolean',
				'description' => 'Optional placeholder ignored by this ability.',
			),
		),
		'additionalProperties' => false,
		'maxItems'             => 0,
	);
}

function mcp_cache_enabler_output_schema(): array {
	return array(
		'type'                 => 'object',
		'properties'           => array(
			'success' => array( 'type' => 'boolean' ),
			'message' => array( 'type' => 'string' ),
		),
		'additionalProperties' => true,
	);
}

function mcp_cache_enabler_normalize_data( $value ) {
	if ( $value instanceof stdClass ) {
		$value = get_object_vars( $value );
	}

	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = mcp_cache_enabler_normalize_data( $item );
		}
	}

	return $value;
}

function mcp_cache_enabler_normalize_input( $input ): array {
	$input = mcp_cache_enabler_normalize_data( $input );

	return is_array( $input ) ? $input : array();
}

function mcp_cache_enabler_is_plugin_installed(): bool {
	return file_exists( WP_PLUGIN_DIR . '/' . MCP_CACHE_ENABLER_TARGET_PLUGIN );
}

function mcp_cache_enabler_is_plugin_active(): bool {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	return is_plugin_active( MCP_CACHE_ENABLER_TARGET_PLUGIN );
}

function mcp_cache_enabler_require_active(): ?array {
	if ( class_exists( 'Cache_Enabler' ) ) {
		return null;
	}

	return array(
		'success'   => false,
		'message'   => 'Cache Enabler is not active.',
		'installed' => mcp_cache_enabler_is_plugin_installed(),
		'active'    => mcp_cache_enabler_is_plugin_active(),
	);
}

function mcp_cache_enabler_plugin_version(): string {
	if ( defined( 'CACHE_ENABLER_VERSION' ) ) {
		return (string) CACHE_ENABLER_VERSION;
	}

	if ( ! mcp_cache_enabler_is_plugin_installed() ) {
		return '';
	}

	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$data = get_plugin_data( WP_PLUGIN_DIR . '/' . MCP_CACHE_ENABLER_TARGET_PLUGIN, false, false );

	return isset( $data['Version'] ) ? (string) $data['Version'] : '';
}

function mcp_cache_enabler_cache_dir(): string {
	if ( defined( 'CACHE_ENABLER_CACHE_DIR' ) ) {
		return (string) CACHE_ENABLER_CACHE_DIR;
	}

	return WP_CONTENT_DIR . '/cache/cache-enabler';
}

function mcp_cache_enabler_settings_dir(): string {
	if ( defined( 'CACHE_ENABLER_SETTINGS_DIR' ) ) {
		return (string) CACHE_ENABLER_SETTINGS_DIR;
	}

	return WP_CONTENT_DIR . '/settings/cache-enabler';
}

function mcp_cache_enabler_path_is_inside( string $path, string $root ): bool {
	$real_path = realpath( $path );
	$real_root = realpath( $root );

	if ( false === $real_path || false === $real_root ) {
		return false;
	}

	return 0 === strpos( wp_normalize_path( $real_path ), trailingslashit( wp_normalize_path( $real_root ) ) )
		|| wp_normalize_path( $real_path ) === wp_normalize_path( $real_root );
}

function mcp_cache_enabler_wp_filesystem(): ?WP_Filesystem_Base {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	if ( ! $wp_filesystem ) {
		WP_Filesystem();
	}

	return $wp_filesystem instanceof WP_Filesystem_Base ? $wp_filesystem : null;
}

function mcp_cache_enabler_format_bytes( int $bytes ): string {
	if ( $bytes < 1024 ) {
		return $bytes . ' B';
	}

	$units = array( 'KB', 'MB', 'GB', 'TB' );
	$value = (float) $bytes;

	foreach ( $units as $unit ) {
		$value /= 1024;
		if ( $value < 1024 ) {
			return round( $value, 2 ) . ' ' . $unit;
		}
	}

	return round( $value, 2 ) . ' PB';
}

function mcp_cache_enabler_scan_directory( string $dir, int $sample_limit = 50 ): array {
	$stats = array(
		'exists'       => is_dir( $dir ),
		'readable'     => is_readable( $dir ),
		'writable'     => wp_is_writable( $dir ),
		'files'        => 0,
		'directories'  => 0,
		'bytes'        => 0,
		'oldest_mtime' => 0,
		'newest_mtime' => 0,
		'sample'       => array(),
	);

	if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
		return $stats;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			++$stats['directories'];
			continue;
		}

		if ( ! $item->isFile() ) {
			continue;
		}

		$path  = $item->getPathname();
		$size  = (int) $item->getSize();
		$mtime = (int) $item->getMTime();

		++$stats['files'];
		$stats['bytes'] += $size;
		$stats['oldest_mtime'] = 0 === $stats['oldest_mtime'] ? $mtime : min( $stats['oldest_mtime'], $mtime );
		$stats['newest_mtime'] = max( $stats['newest_mtime'], $mtime );

		if ( count( $stats['sample'] ) < $sample_limit ) {
			$stats['sample'][] = array(
				'path'     => ltrim( str_replace( wp_normalize_path( $dir ), '', wp_normalize_path( $path ) ), '/' ),
				'bytes'    => $size,
				'modified' => gmdate( 'Y-m-d H:i:s', $mtime ),
			);
		}
	}

	$stats['size_human'] = mcp_cache_enabler_format_bytes( (int) $stats['bytes'] );
	$stats['oldest'] = $stats['oldest_mtime'] ? gmdate( 'Y-m-d H:i:s', (int) $stats['oldest_mtime'] ) : '';
	$stats['newest'] = $stats['newest_mtime'] ? gmdate( 'Y-m-d H:i:s', (int) $stats['newest_mtime'] ) : '';

	return $stats;
}

function mcp_cache_enabler_settings(): array {
	$settings = array();

	if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'get_settings' ) ) {
		$settings = Cache_Enabler::get_settings( false );
	}

	if ( ! is_array( $settings ) || empty( $settings ) ) {
		$settings = get_option( 'cache_enabler', array() );
	}

	return is_array( $settings ) ? $settings : array();
}

function mcp_cache_enabler_public_settings( array $settings ): array {
	$public = array();

	foreach ( $settings as $key => $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			$public[ sanitize_key( (string) $key ) ] = $value;
		} elseif ( is_array( $value ) ) {
			$public[ sanitize_key( (string) $key ) ] = $value;
		}
	}

	return $public;
}

function mcp_cache_enabler_status(): array {
	$cache_dir    = mcp_cache_enabler_cache_dir();
	$settings_dir = mcp_cache_enabler_settings_dir();
	$settings     = mcp_cache_enabler_settings();

	$advanced_cache = WP_CONTENT_DIR . '/advanced-cache.php';

	return array(
		'success'          => true,
		'installed'        => mcp_cache_enabler_is_plugin_installed(),
		'active'           => mcp_cache_enabler_is_plugin_active(),
		'class_available'  => class_exists( 'Cache_Enabler' ),
		'version'          => mcp_cache_enabler_plugin_version(),
		'home_url'         => home_url( '/' ),
		'site_url'         => site_url( '/' ),
		'wp_cache'         => defined( 'WP_CACHE' ) ? (bool) WP_CACHE : null,
		'advanced_cache'   => array(
			'path'     => $advanced_cache,
			'exists'   => file_exists( $advanced_cache ),
			'readable' => is_readable( $advanced_cache ),
			'writable' => file_exists( $advanced_cache ) ? wp_is_writable( $advanced_cache ) : wp_is_writable( WP_CONTENT_DIR ),
		),
		'cache_dir'        => $cache_dir,
		'settings_dir'     => $settings_dir,
		'cache'            => mcp_cache_enabler_scan_directory( $cache_dir, 20 ),
		'settings_files'   => mcp_cache_enabler_scan_directory( $settings_dir, 20 ),
		'cache_size_bytes' => class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'get_cache_size' ) ? (int) Cache_Enabler::get_cache_size() : (int) mcp_cache_enabler_scan_directory( $cache_dir, 0 )['bytes'],
		'settings'         => mcp_cache_enabler_public_settings( $settings ),
	);
}

function mcp_cache_enabler_get_settings(): array {
	return array(
		'success'  => true,
		'settings' => mcp_cache_enabler_public_settings( mcp_cache_enabler_settings() ),
	);
}

function mcp_cache_enabler_allowed_setting_schema(): array {
	return array(
		'cache_expires'                     => 'bool',
		'cache_expiry_time'                 => 'int',
		'clear_site_cache_on_saved_post'    => 'bool',
		'clear_site_cache_on_saved_comment' => 'bool',
		'clear_site_cache_on_saved_term'    => 'bool',
		'clear_site_cache_on_saved_user'    => 'bool',
		'clear_site_cache_on_changed_plugin'=> 'bool',
		'convert_image_urls_to_webp'        => 'bool',
		'create_webp_cache'                 => 'bool',
		'mobile_cache'                      => 'bool',
		'compress_cache'                    => 'bool',
		'minify_html'                       => 'bool',
		'pre_compression'                   => 'bool',
		'excluded_post_ids'                 => 'string',
		'excluded_page_paths'               => 'string',
		'excluded_query_strings'            => 'string',
		'excluded_cookies'                  => 'string',
	);
}

function mcp_cache_enabler_cast_setting( string $type, $value ) {
	switch ( $type ) {
		case 'bool':
			return rest_sanitize_boolean( $value ) ? 1 : 0;
		case 'int':
			return max( 0, (int) $value );
		case 'string':
		default:
			return sanitize_text_field( (string) $value );
	}
}

function mcp_cache_enabler_update_settings( $input ): array {
	$input   = mcp_cache_enabler_normalize_input( $input );
	$changes = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
	$dry_run = array_key_exists( 'dry_run', $input ) ? rest_sanitize_boolean( $input['dry_run'] ) : true;

	if ( empty( $changes ) ) {
		return array(
			'success' => false,
			'message' => 'No settings were provided.',
		);
	}

	$allowed  = mcp_cache_enabler_allowed_setting_schema();
	$current  = mcp_cache_enabler_settings();
	$proposed = $current;
	$applied  = array();
	$rejected = array();

	foreach ( $changes as $key => $value ) {
		$key = sanitize_key( (string) $key );
		if ( ! isset( $allowed[ $key ] ) ) {
			$rejected[ $key ] = 'Unsupported Cache Enabler setting.';
			continue;
		}

		$proposed[ $key ] = mcp_cache_enabler_cast_setting( $allowed[ $key ], $value );
		$applied[ $key ]  = array(
			'old' => $current[ $key ] ?? null,
			'new' => $proposed[ $key ],
		);
	}

	if ( empty( $applied ) ) {
		return array(
			'success'  => false,
			'message'  => 'No supported settings were provided.',
			'rejected' => $rejected,
		);
	}

	if ( ! $dry_run ) {
		update_option( 'cache_enabler', $proposed );
		if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'update_backend' ) ) {
			Cache_Enabler::update_backend();
		}
		if ( class_exists( 'Cache_Enabler' ) && method_exists( 'Cache_Enabler', 'clear_site_cache' ) ) {
			Cache_Enabler::clear_site_cache();
		}
	}

	return array(
		'success'  => true,
		'dry_run'  => $dry_run,
		'applied'  => $applied,
		'rejected' => $rejected,
		'settings' => mcp_cache_enabler_public_settings( $dry_run ? $proposed : mcp_cache_enabler_settings() ),
	);
}

function mcp_cache_enabler_purge_all(): array {
	if ( $error = mcp_cache_enabler_require_active() ) {
		return $error;
	}

	$before = mcp_cache_enabler_scan_directory( mcp_cache_enabler_cache_dir(), 0 );
	Cache_Enabler::clear_complete_cache();
	delete_transient( 'cache_enabler_cache_size' );
	$after = mcp_cache_enabler_scan_directory( mcp_cache_enabler_cache_dir(), 0 );

	return array(
		'success' => true,
		'message' => 'Complete Cache Enabler cache clear requested.',
		'before'  => $before,
		'after'   => $after,
	);
}

function mcp_cache_enabler_purge_site(): array {
	if ( $error = mcp_cache_enabler_require_active() ) {
		return $error;
	}

	$before = mcp_cache_enabler_scan_directory( mcp_cache_enabler_cache_dir(), 0 );
	Cache_Enabler::clear_site_cache();
	delete_transient( 'cache_enabler_cache_size' );
	$after = mcp_cache_enabler_scan_directory( mcp_cache_enabler_cache_dir(), 0 );

	return array(
		'success' => true,
		'message' => 'Current site Cache Enabler cache clear requested.',
		'before'  => $before,
		'after'   => $after,
	);
}

function mcp_cache_enabler_purge_expired(): array {
	if ( $error = mcp_cache_enabler_require_active() ) {
		return $error;
	}

	$before = mcp_cache_enabler_scan_directory( mcp_cache_enabler_cache_dir(), 0 );
	Cache_Enabler::clear_expired_cache();
	delete_transient( 'cache_enabler_cache_size' );
	$after = mcp_cache_enabler_scan_directory( mcp_cache_enabler_cache_dir(), 0 );

	return array(
		'success' => true,
		'message' => 'Expired Cache Enabler cache clear requested.',
		'before'  => $before,
		'after'   => $after,
	);
}

function mcp_cache_enabler_normalize_url( string $url ): string {
	$url = trim( $url );

	if ( '' === $url ) {
		return '';
	}

	if ( 0 === strpos( $url, '/' ) ) {
		$url = home_url( $url );
	}

	$url = esc_url_raw( $url, array( 'http', 'https' ) );
	if ( '' === $url ) {
		return '';
	}

	$host      = wp_parse_url( $url, PHP_URL_HOST );
	$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

	if ( ! $host || ! $home_host || strtolower( $host ) !== strtolower( $home_host ) ) {
		return '';
	}

	return $url;
}

function mcp_cache_enabler_purge_url( $input ): array {
	if ( $error = mcp_cache_enabler_require_active() ) {
		return $error;
	}

	$input = mcp_cache_enabler_normalize_input( $input );
	$url   = mcp_cache_enabler_normalize_url( (string) ( $input['url'] ?? '' ) );

	if ( '' === $url ) {
		return array(
			'success' => false,
			'message' => 'A same-site absolute URL or root-relative URL is required.',
		);
	}

	Cache_Enabler::clear_page_cache_by_url( $url );
	delete_transient( 'cache_enabler_cache_size' );

	return array(
		'success' => true,
		'message' => 'Cache Enabler URL cache clear requested.',
		'url'     => $url,
	);
}

function mcp_cache_enabler_purge_post( $input ): array {
	if ( $error = mcp_cache_enabler_require_active() ) {
		return $error;
	}

	$input   = mcp_cache_enabler_normalize_input( $input );
	$post_id = absint( $input['post_id'] ?? 0 );

	if ( ! $post_id || ! get_post( $post_id ) ) {
		return array(
			'success' => false,
			'message' => 'A valid post_id is required.',
		);
	}

	Cache_Enabler::clear_page_cache_by_post( $post_id );
	delete_transient( 'cache_enabler_cache_size' );

	return array(
		'success'   => true,
		'message'   => 'Cache Enabler post cache clear requested.',
		'post_id'   => $post_id,
		'permalink' => get_permalink( $post_id ),
	);
}

function mcp_cache_enabler_diagnose_page( $input ): array {
	$input = mcp_cache_enabler_normalize_input( $input );
	$url   = mcp_cache_enabler_normalize_url( (string) ( $input['url'] ?? home_url( '/' ) ) );

	if ( '' === $url ) {
		return array(
			'success' => false,
			'message' => 'A same-site absolute URL or root-relative URL is required.',
		);
	}

	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 12,
			'redirection' => 3,
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'Pragma'        => 'no-cache',
				'Accept'        => 'text/html',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'message' => $response->get_error_message(),
			'url'     => $url,
		);
	}

	$headers = wp_remote_retrieve_headers( $response );
	$body    = (string) wp_remote_retrieve_body( $response );

	return array(
		'success'          => true,
		'url'              => $url,
		'status_code'      => (int) wp_remote_retrieve_response_code( $response ),
		'x_cache_handler'  => isset( $headers['x-cache-handler'] ) ? (string) $headers['x-cache-handler'] : '',
		'cache_signature'  => false !== strpos( $body, 'Cache Enabler' ),
		'content_length'   => strlen( $body ),
		'headers'          => array(
			'cache_control' => isset( $headers['cache-control'] ) ? (string) $headers['cache-control'] : '',
			'last_modified' => isset( $headers['last-modified'] ) ? (string) $headers['last-modified'] : '',
			'etag'          => isset( $headers['etag'] ) ? (string) $headers['etag'] : '',
		),
	);
}

function mcp_cache_enabler_list_cached_urls( $input ): array {
	$input = mcp_cache_enabler_normalize_input( $input );
	$limit = max( 1, min( 500, (int) ( $input['limit'] ?? 100 ) ) );

	$cache_dir = mcp_cache_enabler_cache_dir();
	$items     = array();

	if ( ! is_dir( $cache_dir ) || ! is_readable( $cache_dir ) ) {
		return array(
			'success' => true,
			'items'   => array(),
			'total'   => 0,
			'message' => 'Cache directory is missing or unreadable.',
		);
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $cache_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $file ) {
		if ( count( $items ) >= $limit ) {
			break;
		}

		if ( ! $file->isFile() || false === strpos( $file->getFilename(), 'index' ) ) {
			continue;
		}

		$relative = ltrim( str_replace( wp_normalize_path( $cache_dir ), '', wp_normalize_path( $file->getPathname() ) ), '/' );
		$items[]  = array(
			'relative_path' => $relative,
			'bytes'         => (int) $file->getSize(),
			'modified'      => gmdate( 'Y-m-d H:i:s', (int) $file->getMTime() ),
		);
	}

	return array(
		'success' => true,
		'items'   => $items,
		'total'   => count( $items ),
		'limited' => count( $items ) >= $limit,
	);
}

function mcp_cache_enabler_set_enabled( $input ): array {
	$input   = mcp_cache_enabler_normalize_input( $input );
	$enabled = rest_sanitize_boolean( $input['enabled'] ?? false );

	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	if ( $enabled ) {
		if ( ! mcp_cache_enabler_is_plugin_installed() ) {
			return array(
				'success' => false,
				'message' => 'Cache Enabler is not installed.',
			);
		}

		$result = activate_plugin( MCP_CACHE_ENABLER_TARGET_PLUGIN );
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}
	} else {
		deactivate_plugins( MCP_CACHE_ENABLER_TARGET_PLUGIN );
	}

	return array(
		'success' => true,
		'enabled' => $enabled,
		'active'  => mcp_cache_enabler_is_plugin_active(),
	);
}

function mcp_cache_enabler_refresh_backend(): array {
	if ( $error = mcp_cache_enabler_require_active() ) {
		return $error;
	}

	$result = true;
	if ( method_exists( 'Cache_Enabler', 'update_backend' ) ) {
		$result = Cache_Enabler::update_backend();
	}

	return array(
		'success' => true,
		'message' => 'Cache Enabler backend files/settings refresh requested.',
		'result'  => $result,
		'status'  => mcp_cache_enabler_status(),
	);
}

function mcp_cache_enabler_delete_tree( string $dir ): array {
	$wp_filesystem = mcp_cache_enabler_wp_filesystem();
	$stats = array(
		'files_deleted'       => 0,
		'directories_deleted' => 0,
		'bytes_deleted'       => 0,
		'errors'              => array(),
	);

	if ( ! is_dir( $dir ) ) {
		return $stats;
	}

	if ( ! $wp_filesystem ) {
		$stats['errors'][] = 'WordPress filesystem API is unavailable.';
		return $stats;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		$path = $item->getPathname();
		if ( ! mcp_cache_enabler_path_is_inside( $path, $dir ) ) {
			$stats['errors'][] = 'Refused path outside cache directory: ' . $path;
			continue;
		}

		if ( $item->isDir() ) {
			if ( $wp_filesystem->rmdir( $path, false ) ) {
				++$stats['directories_deleted'];
			}
			continue;
		}

		if ( $item->isFile() ) {
			$size = (int) $item->getSize();
			if ( wp_delete_file( $path ) ) {
				++$stats['files_deleted'];
				$stats['bytes_deleted'] += $size;
			} else {
				$stats['errors'][] = 'Could not delete file: ' . $path;
			}
		}
	}

	$wp_filesystem->rmdir( $dir, false );

	return $stats;
}

function mcp_cache_enabler_delete_cache_directory( $input ): array {
	$input   = mcp_cache_enabler_normalize_input( $input );
	$confirm = isset( $input['confirm'] ) && true === rest_sanitize_boolean( $input['confirm'] );

	if ( ! $confirm ) {
		return array(
			'success' => false,
			'message' => 'Set confirm=true to delete the Cache Enabler cache directory contents.',
		);
	}

	$cache_dir = mcp_cache_enabler_cache_dir();
	$root      = WP_CONTENT_DIR . '/cache';

	if ( ! mcp_cache_enabler_path_is_inside( $cache_dir, $root ) ) {
		return array(
			'success' => false,
			'message' => 'Refused to delete because the Cache Enabler directory is outside wp-content/cache.',
			'path'    => $cache_dir,
		);
	}

	$before = mcp_cache_enabler_scan_directory( $cache_dir, 0 );
	$result = mcp_cache_enabler_delete_tree( $cache_dir );
	delete_transient( 'cache_enabler_cache_size' );
	$after = mcp_cache_enabler_scan_directory( $cache_dir, 0 );

	return array(
		'success' => empty( $result['errors'] ),
		'before'  => $before,
		'result'  => $result,
		'after'   => $after,
	);
}

function mcp_cache_enabler_register_ability( string $name, string $label, string $description, array $input_schema, callable $callback, bool $readonly, bool $destructive, bool $idempotent ): void {
	wp_register_ability(
		$name,
		array(
			'label'               => $label,
			'description'         => $description,
			'category'            => 'site',
			'input_schema'        => $input_schema,
			'output_schema'       => mcp_cache_enabler_output_schema(),
			'execute_callback'    => $callback,
			'permission_callback' => 'mcp_cache_enabler_permission_callback',
			'meta'                => array(
				'annotations' => array(
					'readonly'    => $readonly,
					'destructive' => $destructive,
					'idempotent'  => $idempotent,
				),
			),
		)
	);
}

function mcp_cache_enabler_register_abilities(): void {
	if ( ! mcp_cache_enabler_check_dependencies() ) {
		return;
	}

	mcp_cache_enabler_register_ability( 'cache-enabler/status', 'Cache Enabler Status', 'Inspect Cache Enabler plugin state, cache directory stats, backend files, WP_CACHE, and current settings.', mcp_cache_enabler_empty_input_schema(), 'mcp_cache_enabler_status', true, false, true );
	mcp_cache_enabler_register_ability( 'cache-enabler/get-settings', 'Get Cache Enabler Settings', 'Return Cache Enabler settings stored in WordPress.', mcp_cache_enabler_empty_input_schema(), 'mcp_cache_enabler_get_settings', true, false, true );
	mcp_cache_enabler_register_ability(
		'cache-enabler/update-settings',
		'Update Cache Enabler Settings',
		'Update known Cache Enabler settings. Defaults to dry_run=true and clears site cache when applied.',
		array(
			'type'                 => 'object',
			'required'             => array( 'settings' ),
			'properties'           => array(
				'dry_run'  => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Preview changes without writing them. Defaults to true.',
				),
				'settings' => array(
					'type'                 => 'object',
					'description'          => 'Known Cache Enabler settings to update.',
					'additionalProperties' => true,
				),
			),
			'additionalProperties' => false,
		),
		'mcp_cache_enabler_update_settings',
		false,
		false,
		false
	);
	mcp_cache_enabler_register_ability( 'cache-enabler/purge-all', 'Purge Complete Cache Enabler Cache', 'Clear Cache Enabler cache across all sites in the install when supported by Cache Enabler.', mcp_cache_enabler_empty_input_schema(), 'mcp_cache_enabler_purge_all', false, true, false );
	mcp_cache_enabler_register_ability( 'cache-enabler/purge-site', 'Purge Current Site Cache', 'Clear Cache Enabler cache for the current site.', mcp_cache_enabler_empty_input_schema(), 'mcp_cache_enabler_purge_site', false, true, false );
	mcp_cache_enabler_register_ability( 'cache-enabler/purge-expired', 'Purge Expired Cache', 'Clear only expired Cache Enabler files for the current site.', mcp_cache_enabler_empty_input_schema(), 'mcp_cache_enabler_purge_expired', false, true, false );
	mcp_cache_enabler_register_ability(
		'cache-enabler/purge-url',
		'Purge URL Cache',
		'Clear Cache Enabler cache for a same-site URL.',
		array(
			'type'                 => 'object',
			'required'             => array( 'url' ),
			'properties'           => array(
				'url' => array( 'type' => 'string' ),
			),
			'additionalProperties' => false,
		),
		'mcp_cache_enabler_purge_url',
		false,
		true,
		false
	);
	mcp_cache_enabler_register_ability(
		'cache-enabler/purge-post',
		'Purge Post Cache',
		'Clear Cache Enabler cache for a post/page by post ID.',
		array(
			'type'                 => 'object',
			'required'             => array( 'post_id' ),
			'properties'           => array(
				'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
			),
			'additionalProperties' => false,
		),
		'mcp_cache_enabler_purge_post',
		false,
		true,
		false
	);
	mcp_cache_enabler_register_ability(
		'cache-enabler/diagnose-page',
		'Diagnose Page Cache',
		'Fetch a same-site URL and report Cache Enabler-related response headers/signatures.',
		array(
			'type'                 => 'object',
			'properties'           => array(
				'url' => array( 'type' => 'string', 'default' => '/' ),
			),
			'additionalProperties' => false,
		),
		'mcp_cache_enabler_diagnose_page',
		true,
		false,
		false
	);
	mcp_cache_enabler_register_ability(
		'cache-enabler/list-cached-urls',
		'List Cached Files',
		'List cached file entries from the Cache Enabler cache directory for diagnostics.',
		array(
			'type'                 => 'object',
			'properties'           => array(
				'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 500, 'default' => 100 ),
			),
			'additionalProperties' => false,
		),
		'mcp_cache_enabler_list_cached_urls',
		true,
		false,
		false
	);
	mcp_cache_enabler_register_ability(
		'cache-enabler/set-enabled',
		'Enable Or Disable Cache Enabler',
		'Activate or deactivate Cache Enabler on the current site.',
		array(
			'type'                 => 'object',
			'required'             => array( 'enabled' ),
			'properties'           => array(
				'enabled' => array( 'type' => 'boolean' ),
			),
			'additionalProperties' => false,
		),
		'mcp_cache_enabler_set_enabled',
		false,
		true,
		false
	);
	mcp_cache_enabler_register_ability( 'cache-enabler/refresh-backend', 'Refresh Cache Enabler Backend', 'Rebuild Cache Enabler backend files/settings such as advanced-cache.php and settings files when Cache Enabler supports it.', mcp_cache_enabler_empty_input_schema(), 'mcp_cache_enabler_refresh_backend', false, false, false );
	mcp_cache_enabler_register_ability(
		'cache-enabler/delete-cache-directory',
		'Delete Cache Directory Contents',
		'Emergency fallback that recursively deletes only the Cache Enabler cache directory contents after explicit confirmation.',
		array(
			'type'                 => 'object',
			'required'             => array( 'confirm' ),
			'properties'           => array(
				'confirm' => array( 'type' => 'boolean' ),
			),
			'additionalProperties' => false,
		),
		'mcp_cache_enabler_delete_cache_directory',
		false,
		true,
		false
	);
}

add_action( 'wp_abilities_api_init', 'mcp_cache_enabler_register_abilities' );
