<?php

namespace WonderCache\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'clean_post_cache', '\WonderCache\Manager\clean_post_cache', 10, 2 );

function clean_post_cache( $post_id, $post ) {
	if ( ! $post || $post->post_type == 'revision' || ! in_array( get_post_status( $post_id ), array( 'publish', 'trash' ) ) ) {
		return;
	}
	$home = trailingslashit( get_option( 'home' ) );
	clear_url( $home );
	clear_url( $home . 'feed/' );
	clear_url( get_permalink( $post_id ) );
}

function clear_url( $url ) {

	if ( empty( $url ) ) {
		return false;
	}
	if ( 0 === strpos( $url, 'https://' ) ) {
		$url = str_replace( 'https://', 'http://', $url );
	}
	if ( 0 !== strpos( $url, 'http://' ) ) {
		$url = 'http://' . $url;
	}
	$url_key = md5( $url );

	return wondercache_delete( $url_key );
}