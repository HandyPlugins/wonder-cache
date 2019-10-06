<?php

/**
 * Plugin Name:     Wonder Cache
 * Plugin URI:      https://github.com/mustafauysal/wonder-cache
 * Description:     A simple yet powerful caching plugin. It powers and abilities include: superhuman strength and durability.
 * Author:          Mustafa Uysal
 * Author URI:      https://uysalmustafa.com
 * Text Domain:     wonder-cache
 * Domain Path:     /languages
 * Version:         0.1.0
 * Network:         true
 *
 * @package         WonderCache
 */

/**
....................................................................................................
....................................................................................................
............';;;;;;;;;;;;;;;;;;;;;;,'............'.............';;;;;;;;;;;;;;;;;;;;;;;'............
............:OKKKKKKKKKKKKKKKKKKKKKKko,.........lOOl.........,oOKKKKKKKKKKKKKKKKKKKKKKO;............
.............;dO000000000000000000KXXXOc......'oKXX0o.......lOXXXXK00000000000000000Od;.............
...............';lddddxxddddxddddddx0XXKd'...'dKXXXXKo'...,dKXX0xdddddddddddddddddl;'...............
.................lKXXXXXXXXXXXXXXKOdlkKXKx,.'xKXXXXXXKd'.,kXXKkldOXXXXXXXXXXXXXXXKl.................
.................':oxkkkkkkkkkkkkkkkdcxKXXxcxXXX0OO0XXXxckKXKdldkkkkkkkkkkkkkkkxd:..................
.....................'ldddddxxdddddddc,oKXXKXXX0o;;o0XXXKXXKo,cdddxddddddddddc'.....................
......................:OXXXXXXXXXXXXX0dcxKXKXX0ldOOoo0XKXXKdcd0XXXXXXXXXXXXXO:......................
.......................,:lolooooodOKXXXklxXXXOldKXXKolOXKKxlOXKXXOdooloolll:'.......................
..................................'oKXKXkckXOldKXXXXKdlOXxlkXXK0o'..................................
....................................l0XXXxlolxKXXXXXXKdlolkKXX0l....................................
.....................................l0XKKxcxKXX0kk0XXKxcxKXX0c.....................................
......................................c0XXXKXXX0l..lKXXKKKXX0c......................................
.......................................c0XXXXX0c....lKXXXXX0c.......................................
........................................c0XKX0l......l0XXXO:........................................
.........................................:OX0l........lKXO:.........................................
..........................................:kl..........lk:..........................................
....................................................................................................
....................................................................................................
 */

namespace WonderCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WONDER_CACHE_REQUIRED_WP_VERSION', '4.7' );
define( 'WONDER_CACHE_PLUGIN_FILE', __FILE__ );

/**
 * Check requirement
 */
function requirements_notice() {
	if ( ! current_user_can( 'update_core' ) ) {
		return;
	}

	?>

	<div id="message" class="error notice">
		<p><strong><?php esc_html_e( 'Your site does not support Wonder Cache.', 'wonder-cache' ); ?></strong></p>
		<p><?php printf( esc_html__( 'Your site is currently running WordPress version %1$s, while Wonder Cache requires version %2$s or greater.', 'wonder-cache' ), esc_html( get_bloginfo( 'version' ) ), WONDER_CACHE_REQUIRED_WP_VERSION ); ?></p>
		<p><?php esc_html_e( 'Please update your WordPress or deactivate Wonder Cache.', 'wonder-cache' ); ?></p>
	</div>

	<?php
}

if ( version_compare( get_bloginfo( 'version' ), WONDER_CACHE_REQUIRED_WP_VERSION, '<' ) ) {
	add_action( 'admin_notices', '\WonderCache\requirements_notice' );
	add_action( 'network_admin_notices', '\WonderCache\requirements_notice' );

	return;
}

require_once 'includes/utils.php';
require_once 'includes/manager.php';
require_once 'includes/cron.php';


function activate() {
	\WonderCache\Utils\toggle_caching( true );
	\WonderCache\Utils\generate_advanced_cache_file();
}

function deactivate() {
	\WonderCache\Utils\toggle_caching( false );
	\WonderCache\Utils\remove_directory( WONDER_CACHE_CACHING_DIR );
	\WonderCache\Utils\remove_advanced_cache_file();
	wp_clear_scheduled_hook( 'wonder_cache_purge_cache' );
}


register_activation_hook( __FILE__, '\WonderCache\activate' );
register_deactivation_hook( __FILE__, '\WonderCache\deactivate' );


