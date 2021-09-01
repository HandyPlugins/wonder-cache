<?php
/**
 * WonderCache Cron
 *
 * @package WonderCache
 */

namespace WonderCache\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'init', __NAMESPACE__ . '\\schedule_events' );
add_action( 'wonder_cache_purge_cache', __NAMESPACE__ . '\\purge_cache' );

/**
 * Purge expired files
 */
function purge_cache() {
	global $wondercache;

	if ( ! defined( 'WONDER_CACHE_CACHING_DIR' ) ) {
		return;
	}

	$expired_files = \WonderCache\Utils\get_exprired_files( WONDER_CACHE_CACHING_DIR, $wondercache->max_age );

	foreach ( $expired_files as $file_path ) {
		@unlink( $file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

/**
 * Schedule cleanup cron
 */
function schedule_events() {
	$timestamp = wp_next_scheduled( 'wonder_cache_purge_cache' );

	if ( ! $timestamp ) {
		wp_schedule_event( time(), 'hourly', 'wonder_cache_purge_cache' );
	}
}


