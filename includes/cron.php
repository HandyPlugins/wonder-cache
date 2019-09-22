<?php

namespace WonderCache\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'init', '\WonderCache\Cron\schedule_events' );
add_action( 'wonder_cache_purge_cache', '\WonderCache\Cron\purge_cache' );


function purge_cache() {
	global $wondercache;

	if ( ! defined( 'WONDER_CACHE_CACHING_DIR' ) ) {
		return;
	}

	$expired_files = \WonderCache\Utils\get_exprired_files( WONDER_CACHE_CACHING_DIR, $wondercache->max_age );

	foreach ( $expired_files as $file_path ) {
		@unlink( $file_path );
	}
}


function schedule_events() {
	$timestamp = wp_next_scheduled( 'wonder_cache_purge_cache' );

	if ( ! $timestamp ) {
		wp_schedule_event( time(), 'wondercache', 'wonder_cache_purge_cache' );
	}
}


