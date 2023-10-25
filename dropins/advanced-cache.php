<?php
/**
 * This dropin forked from Batcache – https://github.com/Automattic/batcache
 */


// Variants can be set by functions which use early-set globals like $_SERVER to run simple tests.
// Functions defined in WordPress, plugins, and themes are not available and MUST NOT be used.
// Example: vary_cache_on_function('return preg_match("/feedburner/i", $_SERVER["HTTP_USER_AGENT"]);');
//          This will cause wondercache to cache a variant for requests from Feedburner.
// Tips for writing $function:
//  X_X  DO NOT use any functions from your theme or plugins. Those files have not been included. Fatal error.
//  X_X  DO NOT use any WordPress functions except is_admin() and is_multisite(). Fatal error.
//  X_X  DO NOT include or require files from anywhere without consulting expensive professionals first. Fatal error.
//  X_X  DO NOT use $wpdb, $blog_id, $current_user, etc. These have not been initialized.
//  ^_^  DO understand how anonymous functions works. This is how your code is used: eval( '$fun = function() { ' . $function . '; };' );
//  ^_^  DO remember to return something. The return value determines the cache variant.
function vary_cache_on_function( $function ) {
	global $wondercache;

	if ( preg_match( '/include|require|echo|(?<!s)print|dump|export|open|sock|unlink|`|eval/i', $function ) ) {
		die( 'Illegal word in variant determiner.' );
	}

	if ( ! preg_match( '/\$_/', $function ) ) {
		die( 'Variant determiner should refer to at least one $_ variable.' );
	}

	$wondercache->add_variant( $function );
}


#[AllowDynamicProperties]
class wondercache {
	// This is the base configuration. You can edit these variables or move them into your wp-config.php file.
	public $max_age = 900; // Expire wondercache items aged this many seconds (zero to disable wondercache)

	public $seconds = 360; // ...in this many seconds (zero to ignore this and use wondercache immediately)

	public $unique = array(); // If you conditionally serve different content, put the variable values here.

	public $vary = array(); // Array of functions for anonymous function eval. The return value is added to $unique above.

	public $headers = array(); // Add headers here as name=>value or name=>array(values). These will be sent with every response from the cache.

	public $cache_redirects = false; // Set true to enable redirect caching.
	public $redirect_status = false; // This is set to the response code during a redirect.
	public $redirect_location = false; // This is set to the redirect location.

	public $use_stale = true; // Is it ok to return stale cached response when updating the cache?
	public $uncached_headers = array( 'transfer-encoding' ); // These headers will never be cached. Apply strtolower.

	public $debug = true; // Set false to hide the wondercache info <!-- comment -->

	public $cache_control = true; // Set false to disable Last-Modified and Cache-Control headers

	public $cancel = false; // Change this to cancel the output buffer. Use wondercache_cancel();

	public $noskip_cookies = array( 'wordpress_test_cookie' ); // Names of cookies - if they exist and the cache would normally be bypassed, don't bypass it
	public $cacheable_origin_hostnames = array(); // A whitelist of HTTP origin `<host>:<port>` (or just `<host>`) names that are allowed as cache variations.

	public $query = '';
	public $genlock = false;
	public $do = false;

	function __construct( $settings ) {
		if ( is_array( $settings ) ) {
			foreach ( $settings as $k => $v ) {
				$this->$k = $v;
			}
		}
	}

	function client_accepts_only_json() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) )
			return false;

		$is_json_only = false;

		foreach ( explode( ',', $_SERVER['HTTP_ACCEPT'] ) as $mime_type ) {
			if ( false !== $pos = strpos( $mime_type, ';' ) )
				$mime_type = substr( $mime_type, 0, $pos );

			$mime_type = trim( $mime_type );

			if ( '/json' === substr( $mime_type, -5 ) || '+json' === substr( $mime_type, -5 ) ) {
				$is_json_only = true;
				continue;
			}

			return false;
		}

		return $is_json_only;
	}

	function is_cacheable_origin( $origin ) {
		$parsed_origin = parse_url( $origin );

		if ( false === $parsed_origin ) {
			return false;
		}

		$origin_host   = ! empty( $parsed_origin['host'] ) ? strtolower( $parsed_origin['host'] ) : null;
		$origin_scheme = ! empty( $parsed_origin['scheme'] ) ? strtolower( $parsed_origin['scheme'] ) : null;
		$origin_port   = ! empty( $parsed_origin['port'] ) ? $parsed_origin['port'] : null;

		return $origin
		       && $origin_host
		       && ( 'http' === $origin_scheme || 'https' === $origin_scheme )
		       && ( null === $origin_port || 80 === $origin_port || 443 === $origin_port )
		       && in_array( $origin_host, $this->cacheable_origin_hostnames, true );
	}

	function status_header( $status_header, $status_code ) {
		$this->status_header = $status_header;
		$this->status_code   = $status_code;

		return $status_header;
	}

	function redirect_status( $status, $location ) {
		if ( $this->cache_redirects ) {
			$this->redirect_status   = $status;
			$this->redirect_location = $location;
		}

		return $status;
	}

	function do_headers( $headers1, $headers2 = array() ) {
		// Merge the arrays of headers into one
		$headers = array();
		$keys    = array_unique( array_merge( array_keys( $headers1 ), array_keys( $headers2 ) ) );
		foreach ( $keys as $k ) {
			$headers[ $k ] = array();
			if ( isset( $headers1[ $k ] ) && isset( $headers2[ $k ] ) ) {
				$headers[ $k ] = array_merge( (array) $headers2[ $k ], (array) $headers1[ $k ] );
			} elseif ( isset( $headers2[ $k ] ) ) {
				$headers[ $k ] = (array) $headers2[ $k ];
			} else {
				$headers[ $k ] = (array) $headers1[ $k ];
			}
			$headers[ $k ] = array_unique( $headers[ $k ] );
		}
		// These headers take precedence over any previously sent with the same names
		foreach ( $headers as $k => $values ) {
			$clobber = true;
			foreach ( $values as $v ) {
				header( "$k: $v", $clobber );
				$clobber = false;
			}
		}
	}


	// Defined here because timer_stop() calls number_format_i18n()
	function timer_stop( $display = 0, $precision = 4 ) {
		global $timestart, $timeend;
		$mtime     = microtime();
		$mtime     = explode( ' ', $mtime );
		$mtime     = $mtime[1] + $mtime[0];
		$timeend   = $mtime;
		$timetotal = $timeend - $timestart;
		$r         = number_format( $timetotal, $precision );
		if ( $display ) {
			echo $r;
		}

		return $r;
	}

	function ob( $output ) {


		if ( $this->cancel !== false ) {
			wondercache_delete( "{$this->url_key}_genlock" );

			return $output;
		}

		// Do not wondercache blank pages unless they are HTTP redirects
		$output = trim( $output );
		if ( $output === '' && ( ! $this->redirect_status || ! $this->redirect_location ) ) {
			wondercache_delete( $this->url_key . "_genlock" );

			return;
		}

		// Do not cache 5xx responses
		if ( isset( $this->status_code ) && intval( $this->status_code / 100 ) == 5 ) {
			wondercache_delete( $this->url_key . "_genlock" );

			return $output;
		}

		$this->do_variants( $this->vary );
		$this->generate_keys();

		// Construct and save the wondercache
		$this->cache = array(
			'output'            => $output,
			'time'              => isset( $_SERVER['REQUEST_TIME'] ) ? $_SERVER['REQUEST_TIME'] : time(),
			'timer'             => $this->timer_stop( false, 4 ),
			'headers'           => array(),
			'status_header'     => $this->status_header,
			'redirect_status'   => $this->redirect_status,
			'redirect_location' => $this->redirect_location,
			'version'           => $this->url_version
		);

		foreach ( headers_list() as $header ) {
			list( $k, $v ) = array_map( 'trim', explode( ':', $header, 2 ) );
			$this->cache['headers'][ $k ][] = $v;
		}

		if ( ! empty( $this->cache['headers'] ) && ! empty( $this->uncached_headers ) ) {
			foreach ( $this->uncached_headers as $header ) {
				unset( $this->cache['headers'][ $header ] );
			}
		}

		foreach ( $this->cache['headers'] as $header => $values ) {
			// Do not cache if cookies were set
			if ( strtolower( $header ) === 'set-cookie' ) {
				wondercache_delete( $this->url_key . "_genlock" );

				return $output;
			}

			foreach ( (array) $values as $value ) {
				if ( preg_match( '/^Cache-Control:.*max-?age=(\d+)/i', "$header: $value", $matches ) ) {
					$this->max_age = intval( $matches[1] );
				}
			}
		}

		$this->cache['max_age'] = $this->max_age;

		wondercache_set( $this->key, $this->cache );

		// Unlock regeneration
		wondercache_delete( "{$this->url_key}_genlock" );

		if ( $this->cache_control ) {
			// Don't clobber Last-Modified header if already set, e.g. by WP::send_headers()
			if ( ! isset( $this->cache['headers']['Last-Modified'] ) ) {
				header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $this->cache['time'] ) . ' GMT', true );
			}
			if ( ! isset( $this->cache['headers']['Cache-Control'] ) ) {
				header( "Cache-Control: max-age=$this->max_age, must-revalidate", false );
			}
		}

		$this->do_headers( $this->headers );

		// Add some debug info just before <head
		if ( $this->debug ) {
			$this->add_debug_just_cached();
		}


		return $this->cache['output'];
	}

	function add_variant( $function ) {
		$key                = md5( $function );
		$this->vary[ $key ] = $function;
	}

	function do_variants( $dimensions = false ) {
		// This function is called without arguments early in the page load, then with arguments during the OB handler.
		if ( $dimensions === false ) {
			$dimensions = wondercache_get( "{$this->url_key}_vary" );
		} else {
			wondercache_set( "{$this->url_key}_vary", $dimensions );
		}

		if ( is_array( $dimensions ) ) {
			ksort( $dimensions );
			foreach ( $dimensions as $key => $function ) {
				$fun = function () use ( $function ) {
					return $function;
				};

				$value              = $fun();
				$this->keys[ $key ] = $value;
			}
		}

		do_action( 'wondercache_variants', $dimensions );
	}

	function generate_keys() {
		// ksort($this->keys); // uncomment this when traffic is slow
		$this->key     = md5( serialize( $this->keys ) );
		$this->req_key = $this->key . '_reqs';
	}

	function add_debug_just_cached() {
		$generation = $this->cache['timer'];
		$bytes      = strlen( serialize( $this->cache ) );
		$html       = <<<HTML
<!--
	generated in $generation seconds
	$bytes bytes wonderfully cached for {$this->max_age} seconds
-->

HTML;
		$this->add_debug_html_to_output( $html );
	}

	function add_debug_from_cache() {
		$seconds_ago = time() - $this->cache['time'];
		$generation  = $this->cache['timer'];
		$serving     = $this->timer_stop( false, 4 );
		$expires     = $this->cache['max_age'] - time() + $this->cache['time'];
		$html        = <<<HTML
<!--
	generated $seconds_ago seconds ago
	generated in $generation seconds
	served from wonder-cache in $serving seconds
	expires in $expires seconds
-->

HTML;
		$this->add_debug_html_to_output( $html );
	}

	function add_debug_html_to_output( $debug_html ) {
		// Casing on the Content-Type header is inconsistent
		foreach ( array( 'Content-Type', 'Content-type' ) as $key ) {
			if ( isset( $this->cache['headers'][ $key ][0] ) && 0 !== strpos( $this->cache['headers'][ $key ][0], 'text/html' ) ) {
				return;
			}
		}

		$head_position = strpos( $this->cache['output'], '<head' );
		if ( false === $head_position ) {
			return;
		}
		$this->cache['output'] .= "\n$debug_html";
	}
}


global $wondercache;
// Pass in the global variable which may be an array of settings to override defaults.
$wondercache = new wondercache( $wondercache );

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	return;
}


if ( ! defined( 'WONDER_CACHE_CACHING_DIR' ) ) {
	define( 'WONDER_CACHE_CACHING_DIR', WP_CONTENT_DIR . '/cache/wonder-cache/' );
}

// Never wondercache interactive scripts or API endpoints.
if ( in_array(
	basename( $_SERVER['SCRIPT_FILENAME'] ),
	array(
		'wp-app.php',
		'xmlrpc.php',
		'wp-cron.php',
	) ) ) {
	return;
}

// Never wondercache WP javascript generators
if ( strstr( $_SERVER['SCRIPT_FILENAME'], 'wp-includes/js' ) ) {
	return;
}

// Only cache HEAD and GET requests.
if ( ( isset( $_SERVER['REQUEST_METHOD'] ) && ! in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ) ) ) ) {
	return;
}

// Never wondercache a request with X-WP-Nonce header.
if ( ! empty( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
	return;
}

// Never wondercache a response for a request with an Origin request header.
// *Unless* that Origin header is in the configured whitelist of allowed origins with restricted schemes and ports.
if ( isset( $_SERVER['HTTP_ORIGIN'] ) ) {
	if ( ! $wondercache->is_cacheable_origin( $_SERVER['HTTP_ORIGIN'] ) ) {
		return;
	}

	$wondercache->origin = $_SERVER['HTTP_ORIGIN'];
}


// Never cache when cookies indicate a cache-exempt visitor.
if ( is_array( $_COOKIE ) && ! empty( $_COOKIE ) ) {
	foreach ( array_keys( $_COOKIE ) as $wondercache->cookie ) {
		if ( ! in_array( $wondercache->cookie, $wondercache->noskip_cookies ) && ( substr( $wondercache->cookie, 0, 2 ) == 'wp' || substr( $wondercache->cookie, 0, 9 ) == 'wordpress' || substr( $wondercache->cookie, 0, 14 ) == 'comment_author' ) ) {

			return;
		}
	}
}


// Necessary to prevent clients using cached version after login cookies set. If this is a problem, comment it out and remove all Last-Modified headers.
header( 'Vary: Cookie', false );

// Things that define a unique page.
if ( isset( $_SERVER['QUERY_STRING'] ) ) {
	parse_str( $_SERVER['QUERY_STRING'], $wondercache->query );

	// Normalize query paramaters for better cache hits.
	ksort( $wondercache->query );
}


$wondercache->keys = array(
	'host'   => $_SERVER['HTTP_HOST'],
	'method' => $_SERVER['REQUEST_METHOD'],
	'path'   => ( $wondercache->pos = strpos( $_SERVER['REQUEST_URI'], '?' ) ) ? substr( $_SERVER['REQUEST_URI'], 0, $wondercache->pos ) : $_SERVER['REQUEST_URI'],
	'query'  => $wondercache->query,
	'extra'  => $wondercache->unique
);

if ( is_ssl() ) {
	$wondercache->keys['ssl'] = true;
}

# Some plugins return html or json based on the Accept value for the same URL.
if ( $wondercache->client_accepts_only_json() ) {
	$wondercache->keys['json'] = true;
}


// Recreate the permalink from the URL
$wondercache->permalink   = 'http://' . $wondercache->keys['host'] . $wondercache->keys['path'] . ( isset( $wondercache->keys['query']['p'] ) ? "?p=" . $wondercache->keys['query']['p'] : '' );
$wondercache->url_key     = md5( $wondercache->permalink );
$wondercache->url_version = (int) wondercache_get( "{$wondercache->url_key}_version" );
$wondercache->do_variants();
$wondercache->generate_keys();

// Get the wondercache
$wondercache->cache = wondercache_get( $wondercache->key );
$is_cached = is_array( $wondercache->cache ) && isset( $wondercache->cache['time'] );
$has_expired = $is_cached && time() > $wondercache->cache['time'] + $wondercache->cache['max_age'];

if ( isset( $wondercache->cache['version'] ) && $wondercache->cache['version'] != $wondercache->url_version ) {
	// Always refresh the cache if a newer version is available.
	$wondercache->do = true;
} else if ( $wondercache->seconds < 1 ) {
	// Are we only caching frequently-requested pages?
	$wondercache->do = true;
} else {
	// No wondercache item found, or ready to sample traffic again at the end of the wondercache life?
	if ( ! $is_cached || time() >= $wondercache->cache['time'] + $wondercache->max_age - $wondercache->seconds ) {
		if ( $has_expired ) {
			wondercache_delete( $wondercache->req_key );
		}
		$wondercache->do = true;
	}
}

// Obtain cache generation lock
if ( $wondercache->do ) {
	$wondercache->genlock = wondercache_set( "{$wondercache->url_key}_genlock", 1 );

}

if (
	$is_cached && // We have cache
	! $wondercache->genlock && // We have not obtained cache regeneration lock
	(
		! $has_expired || // Wondercache page that hasn't expired
		( $wondercache->do && $wondercache->use_stale ) // Regenerating it in another request and can use stale cache
	)
) {
	// Issue redirect if cached and enabled
	if ( $wondercache->cache['redirect_status'] && $wondercache->cache['redirect_location'] && $wondercache->cache_redirects ) {
		$status   = $wondercache->cache['redirect_status'];
		$location = $wondercache->cache['redirect_location'];
		// From vars.php
		$is_IIS = ( strpos( $_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS' ) !== false || strpos( $_SERVER['SERVER_SOFTWARE'], 'ExpressionDevServer' ) !== false );

		$wondercache->do_headers( $wondercache->headers );
		if ( $is_IIS ) {
			header( "Refresh: 0;url=$location" );
		} else {
			if ( php_sapi_name() != 'cgi-fcgi' ) {
				$texts    = array(
					300 => 'Multiple Choices',
					301 => 'Moved Permanently',
					302 => 'Found',
					303 => 'See Other',
					304 => 'Not Modified',
					305 => 'Use Proxy',
					306 => 'Reserved',
					307 => 'Temporary Redirect',
				);
				$protocol = $_SERVER["SERVER_PROTOCOL"];
				if ( 'HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol ) {
					$protocol = 'HTTP/1.0';
				}
				if ( isset( $texts[ $status ] ) ) {
					header( "$protocol $status " . $texts[ $status ] );
				} else {
					header( "$protocol 302 Found" );
				}
			}
			header( "Location: $location" );
		}
		exit;
	}

	// Respect ETags served with feeds.
	$three04 = false;
	if ( isset( $SERVER['HTTP_IF_NONE_MATCH'] ) && isset( $wondercache->cache['headers']['ETag'][0] ) && $_SERVER['HTTP_IF_NONE_MATCH'] == $wondercache->cache['headers']['ETag'][0] ) {
		$three04 = true;
	} // Respect If-Modified-Since.
	elseif ( $wondercache->cache_control && isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
		$client_time = strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
		if ( isset( $wondercache->cache['headers']['Last-Modified'][0] ) ) {
			$cache_time = strtotime( $wondercache->cache['headers']['Last-Modified'][0] );
		} else {
			$cache_time = $wondercache->cache['time'];
		}

		if ( $client_time >= $cache_time ) {
			$three04 = true;
		}
	}

	// Use the wondercache save time for Last-Modified so we can issue "304 Not Modified" but don't clobber a cached Last-Modified header.
	if ( $wondercache->cache_control && ! isset( $wondercache->cache['headers']['Last-Modified'][0] ) ) {
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $wondercache->cache['time'] ) . ' GMT', true );
		header( 'Cache-Control: max-age=' . ( $wondercache->cache['max_age'] - time() + $wondercache->cache['time'] ) . ', must-revalidate', true );
	}

	// Add some debug info just before </head>
	if ( $wondercache->debug ) {
		$wondercache->add_debug_from_cache();
	}


	$wondercache->do_headers( $wondercache->headers, $wondercache->cache['headers'] );

	if ( $three04 ) {
		header( "HTTP/1.1 304 Not Modified", true, 304 );
		die;
	}

	if ( ! empty( $wondercache->cache['status_header'] ) ) {
		header( $wondercache->cache['status_header'], true );
	}


	// Have you ever heard a death rattle before?
	die( $wondercache->cache['output'] );
}


// Didn't meet the minimum condition?
if ( ! $wondercache->do || ! $wondercache->genlock ) {
	return;
}

//WordPress 4.7 changes how filters are hooked. Since WordPress 4.6 add_filter can be used in advanced-cache.php. Previous behaviour is kept for backwards compatability with WP < 4.6
add_filter( 'status_header', array( &$wondercache, 'status_header' ), 10, 2 );
add_filter( 'wp_redirect_status', array( &$wondercache, 'redirect_status' ), 10, 2 );

ob_start( array( &$wondercache, 'ob' ) );


/**
 * retrieve cached content from the file
 * @param $file
 *
 * @return false|mixed|string|void
 */
function wondercache_get( $file ) {
	$path = wondercache_get_cache_path( $file );
	if ( ! file_exists( $path ) ) {
		return;
	}
	$content = file_get_contents( $path );
	if ( $content ) {
		$content = str_replace( '<?php exit; ?>', '', $content );
		$content = unserialize( trim( $content ) );
	}

	return $content;
}

/**
 * save cached content
 * @param $file
 * @param $content
 *
 * @return bool|int
 */
function wondercache_set( $file, $content ) {
	$content = serialize( $content );
	$content = '<?php exit; ?>' . PHP_EOL . $content;

	$path = wondercache_get_cache_path( $file );

	return file_put_contents( $path, $content );
}

/**
 * simply delete cached file
 * @param $file
 *
 * @return bool
 */
function wondercache_delete( $file ) {
	$path = wondercache_get_cache_path( $file );

	return @unlink( $path );
}

/**
 * calculate the path for a file
 *
 * @param $file
 *
 * @return array|string
 */
function wondercache_get_cache_path( $file ) {

	$url_key   = explode( '_', $file ); // split url_key from string _genlock
	$path      = str_split( $url_key[0], 12 );
	$file_name = array_pop( $path );
	$sub_path  = implode( '/', $path );

	if ( isset( $url_key[1] ) ) {
		$file_name .= '_' . $url_key[1];
	}

	$file_name .= '.php';

	$cache_dir = WONDER_CACHE_CACHING_DIR . $sub_path . '/';


	if ( ! file_exists( $cache_dir ) ) {
		mkdir( $cache_dir, 0775, true );

		if ( ! file_exists( WONDER_CACHE_CACHING_DIR . '.htaccess' ) ) {
			file_put_contents( WONDER_CACHE_CACHING_DIR . '.htaccess', 'Options -Indexes' );
		}
	}

	$path = $cache_dir . $file_name;

	return $path;
}

