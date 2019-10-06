<?php

namespace WonderCache\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'clean_post_cache', '\WonderCache\Manager\clean_post_cache', 10, 2 );
add_action( 'admin_bar_menu', '\WonderCache\Manager\admin_bar_menu', 999 );
add_action( 'admin_post_wonder_cache_flush', '\WonderCache\Manager\wonder_cache_flush' );
add_action( 'admin_notices', '\WonderCache\Manager\display_notice' );

if ( is_multisite() ) {
	add_action( 'network_admin_notices', '\WonderCache\Manager\display_notice' );
} else {
	add_action( 'admin_notices', '\WonderCache\Manager\display_notice' );
}

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

/**
 * Adds a flush button to admin bar
 *
 * @param $wp_admin_bar
 *
 * @since 0.2.0
 */
function admin_bar_menu( $wp_admin_bar ) {
	if ( current_user_can( 'create_users' ) ) {
		$wp_admin_bar->add_menu( array(
			'id'    => 'wonder-cache',
			'title' => __( 'Flush Wonder Cache', 'wonder-cache' ),
			'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=wonder_cache_flush' ), 'wonder_cache_flush' )
		) );
	}
}

/**
 * clean-up cache directory and redirect to ref. page
 *
 * @since 0.2.0
 */
function wonder_cache_flush() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'wonder_cache_flush' ) ) {
		wp_nonce_ays( '' );
	}

	$directory = WP_CONTENT_DIR . '/cache/';
	if ( defined( 'WONDER_CACHE_CACHING_DIR' ) ) {
		$directory = WONDER_CACHE_CACHING_DIR;
	}

	\WonderCache\Utils\remove_directory( $directory );

	$redirect_url = esc_url( add_query_arg( 'wonder-cache-flush', 'ok', wp_get_referer() ) );

	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Show message to user after the action
 *
 * @since 0.2.0
 */
function display_notice() {
	if ( isset( $_GET['wonder-cache-flush'] ) && 'ok' === $_GET['wonder-cache-flush'] ):
		?>
		<div id="setting-error-settings_updated" class="updated notice is-dismissible">
			<p><strong><?php _e( 'Cache purged successfully!', 'wonder-cache' ); ?></strong></p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php _e( 'Dismiss this notice', 'wonder-cache' ); ?></span>
			</button>
		</div>
	<?php
	endif;
}
