<?php
/**
 * Utils
 *
 * @package WonderCache
 */

namespace WonderCache\Utils;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

//phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
//phpcs:disable WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
//phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

/**
 * Get the list of expired files
 *
 * @param string $path     The directory
 * @param int    $lifespan TTL of the cache file
 *
 * @return array The list of expired files
 */
function get_exprired_files( $path, $lifespan = 0 ) {

	$current_time = time();

	$expired_files = array();

	// return immediately if the path is not exist!
	if ( ! file_exists( $path ) ) {
		return $expired_files;
	}

	$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );

	foreach ( $files as $file ) {

		if ( $file->isDir() ) {
			continue;
		}

		$path = $file->getPathname();

		if ( @filemtime( $path ) + $lifespan <= $current_time ) {
			$expired_files[] = $path;
		}
	}

	return $expired_files;
}

/**
 * Generate advanced-cache dropin.
 *
 * @return bool
 */
function generate_advanced_cache_file() {
	$dropin_path = plugin_dir_path( WONDER_CACHE_PLUGIN_FILE ) . 'dropins/advanced-cache.php';
	$file_path   = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';

	$content = '<?php ' . PHP_EOL; // phpcs:ignore Generic.Formatting.MultipleStatementAlignment.NotSameWarning
	$content .= "defined( 'ABSPATH' ) || exit;" . PHP_EOL;
	$content .= 'if ( @file_exists( \'' . $dropin_path . '\' ) ) {' . PHP_EOL;
	$content .= "\t" . 'include_once( \'' . $dropin_path . '\');' . PHP_EOL;
	$content .= '}' . PHP_EOL;

	return (bool) file_put_contents( $file_path, $content );
}

/**
 * Remove advanced-cache.php dropin
 *
 * @return bool
 */
function remove_advanced_cache_file() {
	$file_path = untrailingslashit( WP_CONTENT_DIR ) . '/advanced-cache.php';

	return @unlink( $file_path );
}


/**
 * Toggle WP_CACHE on or off in wp-config.php
 * C/P form simple cache
 *
 * @param boolean $status Status of cache.
 *
 * @return boolean
 * @since  1.0
 */
function toggle_caching( $status ) {
	if ( defined( 'WP_CACHE' ) && WP_CACHE === $status ) {
		return;
	}
	$file        = '/wp-config.php';
	$config_path = false;
	for ( $i = 1; $i <= 3; $i ++ ) {
		if ( $i > 1 ) {
			$file = '/..' . $file;
		}
		if ( file_exists( untrailingslashit( ABSPATH ) . $file ) ) {
			$config_path = untrailingslashit( ABSPATH ) . $file;
			break;
		}
	}
	// Couldn't find wp-config.php.
	if ( ! $config_path ) {
		return false;
	}
	$config_file_string = file_get_contents( $config_path );
	// Config file is empty. Maybe couldn't read it?
	if ( empty( $config_file_string ) ) {
		return false;
	}
	$config_file = preg_split( "#(\n|\r)#", $config_file_string );
	$line_key    = false;
	foreach ( $config_file as $key => $line ) {
		if ( ! preg_match( '/^\s*define\(\s*(\'|")([A-Z_]+)(\'|")(.*)/', $line, $match ) ) {
			continue;
		}
		if ( 'WP_CACHE' === $match[2] ) {
			$line_key = $key;
		}
	}
	if ( false !== $line_key ) {
		unset( $config_file[ $line_key ] );
	}
	$status_string = ( $status ) ? 'true' : 'false';
	array_shift( $config_file );
	array_unshift( $config_file, '<?php', "define( 'WP_CACHE', $status_string ); // added by wondercache" );
	foreach ( $config_file as $key => $line ) {
		if ( '' === $line ) {
			unset( $config_file[ $key ] );
		}
	}
	if ( ! file_put_contents( $config_path, implode( "\n\r", $config_file ) ) ) {
		return false;
	}

	return true;
}

/**
 * Removes given directory
 *
 * @param string $path    Target directory for removal.
 * @param array  $exclude Excluded files
 *
 * @since 0.2.0
 */
function remove_directory( $path, $exclude = array() ) {
	$dir = @opendir( $path );

	if ( $dir ) {
		while ( ( $entry = @readdir( $dir ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			foreach ( $exclude as $mask ) {
				if ( fnmatch( $mask, basename( $entry ) ) ) {
					continue 2;
				}
			}

			$full_path = $path . DIRECTORY_SEPARATOR . $entry;

			if ( @is_dir( $full_path ) ) {
				\WonderCache\Utils\remove_directory( $full_path, $exclude );
			} else {
				@unlink( $full_path );
			}
		}

		@closedir( $dir );
		@rmdir( $path );
	}
}
