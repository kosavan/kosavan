<?php

/*******************************************************************************

 *
 *  AlterVision CPA White
 *  Created by AlterVision - www.altercpa.pro
 *  Copyright (c) 2018-2021 Anton Reznichenko
 *

 *
 *  File:	index.php
 *  About:	AlterCPA White Site CMS
 *  Author:	Anton 'AlterVision' Reznichenko - altervision13@gmail.com
 *  URL:	https://gitlab.com/altervision/altercpa-white
 *

*******************************************************************************/

// Settings
#define( 'CLOAK', 'http://cpa.st/fltr/1-abc-13' );	// AlterCPA Filtering API URL
define( 'WHITEDOMAIN', 'https://kosavan.notion.site/c4814d7ecdca4d0dbc8f6f0800cfa8d0' );	// Domain of your white site for proxy-based work
define( 'WHITEMAP', 'https://notion.so/sitemap.xml' );	// Path to sitemap file of your white domain, required for good proxy
define( 'WHITEHTTPS', true );	// Use HTTPS connection to white site
define( 'WHITEREDIR', true );	// Set true for redirect mode of white site
#define( 'BLACKDOMAIN', 'site.com' );	// Domain of your black site, required for cloaking
#define( 'BLACKHTTPS', true );	// Use HTTPS connection to black site
#define( 'BLACKREDIR', true );	// Set true for redirect mode of black site
#define( 'SMARTLINK', 'https://your.smart/link?url' );	// URL of smartlink
#define( 'SMARTBASE', true ); // Dont redirect smartlink, rebase instead
#define( 'SMARTCOOKIE', 'authssid' );	// Name of smartlink cookie
#define( 'SMARTPATHCC', 'authkey' );	// Name of smartlink path cookie
#define( 'SMARTPATHIN', 'authuid' );	// Name of smartlink first path cookie
#define( 'RECOOKIE', 'PHPSESSID' ); // Name for redirection cookie
#define( 'IP4ONLY', true );	// Use only IPv4 addresses, ignore IPv6
#define( 'CACHE', true );	// Cache static files for proxy requests
#define( 'CACHEPATH', '/var/www/cache' );	// Path to static cache directory

// Your cloaling function
/* function cloak() {
	$result = ( rand() % 2 ) ? true : false; // Make some magic here
	return $result;	// Return true for black and false for white site
} */

//
// Common functions
//

// Remote IP address
function ip() {
	static $ip;
	if (isset( $ip )) return $ip;
	$ip = remoteip( $_SERVER );
	return $ip;
}

// Get remote IP address
function remoteip( $server ) {

	// Special IP headers
	if (goodip( $server['HTTP_CF_CONNECTING_IP'] )) return $server['HTTP_CF_CONNECTING_IP'];
	if (goodip( $server['HTTP_CLIENT_IP'] )) return $server['HTTP_CLIENT_IP'];
	if (goodip( $server['HTTP_X_REAL_IP'] )) return $server['HTTP_X_REAL_IP'];

	// Check X-Forwarded-For
	if ( $server['HTTP_X_FORWARDED_FOR'] ) {
		if (strpos( $server['HTTP_X_FORWARDED_FOR'], ',' ) !== false ) {
			$xffd = explode( ',', $server['HTTP_X_FORWARDED_FOR'] );
			foreach ( $xffd as $xff ) if ( $xff = trim( $xff ) ) if (goodip( $xff )) return $xff;
		} elseif (goodip( $server['HTTP_X_FORWARDED_FOR'] )) return $server['HTTP_X_FORWARDED_FOR'];
	}

	// Use remote address
	return $server['REMOTE_ADDR'];

}

// Check IP to be public
function goodip( $ip ) {
	if (defined( 'IP4ONLY' )) {
		return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ? 1 : 0;
	} else return filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ? 1 : 0;
}

// Directory scanning
function pathfinder( $base, $file, &$paths ) {
	$files = scandir( $base . $file );
	foreach ( $files as $f ) {
		if ( $f == '.' || $f == '..' ) continue;
		$fn = $base . $file . $f;
		if (!is_dir( $fn )) {
			if ( substr( $f, -5, 5 ) == '.html' ) $paths[] = $file.$f;
			if ( substr( $f, -4, 4 ) == '.htm' ) $paths[] = $file.$f;
		} else pathfinder( $base, $file . $f . '/', $paths );
	}
}

// XML sitemap scanner
function xmlsitemap( $url, &$paths ) {

	// Load the sitemap file
	$smd = curl( $url );
	if ( ! $smd ) return false;
	$xml = simplexml_load_string( $smd );
	if ( ! $xml ) return false;

	// Walk through sitemaps and URLs
	if (isset( $xml->sitemap )) foreach ( $xml->sitemap as $m ) xmlsitemap( $m->loc, $paths );
	if (isset( $xml->url )) foreach ( $xml->url as $u ) {
		$ud = parse_url( $u->loc );
		$url = ltrim( $ud['path'], '/' );
		if ( $ud['query'] ) $url .= '?' . $ud['query'];
		$paths[] = $url;
	}

}

// Generic CURL request
function curl( $url, $post = false ) {
	$curl = curl_init( $url );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, 1 );
	curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, 0 );
	curl_setopt( $curl, CURLOPT_SSL_VERIFYHOST, 0 );
	curl_setopt( $curl, CURLOPT_ENCODING, '' );
	curl_setopt( $curl, CURLOPT_FAILONERROR, false );
	curl_setopt( $curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:63.0) Gecko/20100101 Firefox/63.0' );
	if ( $post ) {
		curl_setopt( $curl, CURLOPT_POST, 1 );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
	}
	$result = curl_exec( $curl );
	curl_close( $curl );
	return $result;
}

// 404 error
function e404() {
	header( 'HTTP/1.1 404 Not Found' );
	header( 'Status: 404 Not Found' );
	echo '<html><head><title>404 Not Found</title></head><body><center><h1>404 Not Found</h1></center></body>';
	die();
}

// Is HTTPS server
function ishttps() {
	return $_SERVER['HTTPS'] ? ( ( $_SERVER['HTTPS'] == 'off' ) ? false : true ) : false;
}

// Get current URL
function thisurl() {
	return ( ishttps() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// Smartlink parser
function smartlink( $smart = false ) {

	// Prepare smartlink URL with parameters
	if (defined( 'SMARTLINK' )) $smart = SMARTLINK;
	if ( ! $smart ) return false;
	if ( $_GET ) {
		$smart .= ( strpos( $smart, '?' ) ? '&' : '?' ) . http_build_query( $_GET );
	} elseif ( $_SERVER['REQUEST_URI'] ) {
		$tailinfo = explode( '?', $_SERVER['REQUEST_URI'], 2 );
		if ( $tailinfo[1] ) $smart .= ( strpos( $smart, '?' ) ? '&' : '?' ) . $tailinfo[1];
	}

	// Rebase via incomimg URL
	if ( strtolower(substr( $smart, 0, 3 )) == 'rb:' ) {
		$smart = substr( $smart, 3 );
		if (!defined( 'SMARTBASE' )) define( 'SMARTBASE', true );
	}

	// Try to load the full page
	$curl = curl_init( $smart );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, true );
	curl_setopt( $curl, CURLOPT_ENCODING, '' );
	curl_setopt( $curl, CURLOPT_HEADER, true );

	// Add main headers to request
	curl_setopt( $curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
	if ( $_SERVER['HTTP_REFERER'] ) curl_setopt( $curl, CURLOPT_REFERER, $_SERVER['HTTP_REFERER'] );

	// Add other headers to request
	$ip = ip();
	$sendhead = [ "X-Real-IP: $ip", "Client-IP: $ip", "X-Forwarded-For: $ip" ];
	if ( $_SERVER['HTTP_ACCEPT'] ) $sendhead[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
	if ( $_SERVER['HTTP_ACCEPT_CHARSET'] ) $sendhead[] = 'Accept-Charset: ' . $_SERVER['HTTP_ACCEPT_CHARSET'];
	if ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) $sendhead[] = 'Accept-Language: ' . $_SERVER['HTTP_ACCEPT_LANGUAGE'];
	if ( $_SERVER['HTTP_AUTHORIZATION'] ) $sendhead[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
	if ( $_SERVER['HTTP_EXPECT'] ) $sendhead[] = 'Expect: ' . $_SERVER['HTTP_EXPECT'];
	if ( $_SERVER['HTTP_PROXY_AUTHORIZATION'] ) $sendhead[] = 'Proxy-Authorization: ' . $_SERVER['HTTP_PROXY_AUTHORIZATION'];
	if ( $_SERVER['HTTP_COOKIE'] ) $sendhead[] = 'Cookie: ' . $_SERVER['HTTP_COOKIE'];
	curl_setopt( $curl, CURLOPT_HTTPHEADER, $sendhead );

	// Exec the curl and get data
	$result = curl_exec( $curl );
	$info = curl_getinfo( $curl );
	curl_close( $curl );

	// Process all the other cookies
	$cookies = [];
	$domain = parse_url( $info['url'], PHP_URL_HOST );
	$cdm = $ndm = parse_url( $smart, PHP_URL_HOST );
	$dtp = explode( "\r\n\r\n", $result );
	foreach ( $dtp as $dt ) {

		// Check the result block for header
		$dt = trim( $dt );
		if ( ! $dt ) continue;
		if ( substr( $dt, 0, 5 ) != 'HTTP/' ) continue;
		$neocoo = [];

		// Prepare lines to process
		$dls = explode( "\n", $dt );
		foreach ( $dls as $l ) if ( $l = trim( $l ) ) {

			// Get the name and value of the header
			$li = explode( ':', $l, 2 );
			$hn = strtolower(trim( $li[0] ));
			$hv = trim( $li[1] );

			// Check for the cookie
			if ( ! $hv ) continue;
			if ( $hn == 'set-cookie' ) $neocoo[] = $hv;

			// Check for the domain
			if ( $hn == 'location' ) {
				$nu = parse_url( $hv );
				if ( $nu['host'] ) $ndm = $nu['host'];
			}

		}

		// Check domains and cookies
		if ( $cdm != $ndm ) {
			$cdm = $ndm;
			$cookies = $neocoo;
		} else $cookies = array_merge( $cookies, $neocoo );

	}

	// Working with cookies
	if ( $cookies ) {

		// Replaces for the cookies
		$coore = [ $domain ];
		$dti = explode( '.', $domain );
		$dtc = count( $dti );
		while ( $dtc > 2 ) {
			$dtc -= 1;
			array_shift( $dti );
			$coore[] = implode( '.', $dti );
		}

		// Setting cookies the simple way
		$cookies = array_unique( $cookies );
		$cookiesend = [];
		foreach ( $cookies as $h ) {
			foreach ( $coore as $c ) $h = str_ireplace( $c, $_SERVER['HTTP_HOST'], $h );
			if (!ishttps()) $h = str_ireplace( ' secure;', '', $h );
			$cookiesend[] = $h;
		}

		// Send the cookies
		$cookiesend = array_unique( $cookiesend );
		foreach ( $cookiesend as $h ) header( "Set-Cookie: $h", false );

	}

	// Check the URL
	$hh = parse_url( $info['url'] );
	$path = $hh['path'] . ( $hh['query'] ? '?' . $hh['query'] : '' );
	if ( $path == $_SERVER['REQUEST_URI'] || defined('SMARTBASE') ) {

		// Make the contents
		$content = $header = [];
		foreach ( $dtp as $dt ) {
			if ( substr( $dt, 0, 5 ) == 'HTTP/' ) {
				$header[] = $dt;
			} else $content[] = $dt;
		}

		$header = implode( "\r\n", $header );
		$headers = parseheader( $header, $hh['host'] );
		foreach ( $headers as $h ) header( $h, false );

		smartcookies( $info['url'] );
		$content = implode( "\r\n\r\n", $content );
		reurl( $content, $hh['host'], $path, defined('SMARTBASE') );
		echo $content;
		die();

	}

	// Done, set the cookie
	return smartcookies( $info['url'] );

}

// Check smartlink status
function smartcheck() {
	if (isset( $_COOKIE[SMARTCOOKIE] )) {

		// Check path for first redirect
		if ( isset( $_COOKIE[SMARTPATHCC] ) && $_SERVER['REQUEST_URI'] == '/' ) {
			$path = gzuncompress(base64_decode( $_COOKIE[SMARTPATHCC] ));
			if ( $path && $path != '/' ) {
				header( "Location: $path" );
				die();
			}
		}

		// Path is OK, working
		return $_COOKIE[SMARTCOOKIE];

	} else return false;
}

// Set cookie for smartlink
function smartcookies( $url ) {

	// Set the URL to work with
	$hh = parse_url( $url );
	$domain = $hh['host'];
	$path = $hh['path'] . ( $hh['query'] ? '?' . $hh['query'] : '' );
	$cookie = [ $domain, ( $hh['scheme'] == 'https' ) ? 1 : 0 ];
	$cookie = json_encode( $cookie, JSON_UNESCAPED_UNICODE );
	$cookie = base64_encode(gzcompress( $cookie, 9 ));
	$ccpath = base64_encode(gzcompress( $path, 9 ));
	$inpath = base64_encode(gzcompress( thisurl(), 9 ));

	// Redirect to new path or show the page via black domain
	setcookie( SMARTCOOKIE, $cookie, time() + 86400, '/' );
	setcookie( SMARTPATHCC, $ccpath, time() + 86400, '/' );
	setcookie( SMARTPATHIN, $inpath, time() + 86400, '/' );
	if (defined( 'SMARTBASE' )) return $cookie;
	if ( $path != $_SERVER['REQUEST_URI'] ) {
		header( "Location: $path" );
		die();
	} else return $cookie;

}

// Load cloaking settings the smart way
function smartway( $smartcookie ) {

	// Uncompress the smart cookie
	if ( ! $smartcookie ) e404();
	$smartinfo = gzuncompress(base64_decode( $smartcookie ));
	if ( ! $smartinfo ) e404();
	$smartinfo = json_decode( $smartinfo, true );
	if ( ! $smartinfo ) e404();

	// Make the black domain settings
	if ( $smartinfo[0] ) {
		define( 'BLACKDOMAIN', $smartinfo[0] );
		if ( $smartinfo[1] ) define( 'BLACKHTTPS', true );
		return [ true, true, true ]; // black, black is set, white is set
	} else e404();

}

// Cache file name
function cachename( $url, $meta = false ) {
	$path = defined('CACHEPATH') ? CACHEPATH : __DIR__ . '/' . md5( __FILE__ . $_SERVER['HTTP_HOST'] );
	$cfn = md5( $url );
	$dn1 = substr( $cfn, 0, 2 );
	$dn2 = substr( $cfn, 2, 2 );
	$ext = $meta ? 'json' : 'dat';
	return "$path/$dn1/$dn2/$cfn.$ext";

}

// Save data to cache
function cachesave( $url, &$meta, &$data ) {

	// Save the file itself
	$dcn = cachename( $url );
	$dir = dirname( $dcn );
	if (!is_dir( $dir )) mkdir( $dir, 0777, true );
	file_put_contents( $dcn, $data );

	// Save meta data
	$mcn = cachename( $url, true );
	$mdt = json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	file_put_contents( $mcn, $mdt );

}

// Guess if caching is needeed
function cacheneed( $headers ) {

	// Find cache headers
	$nocache = $cti = false;
	foreach ( $headers as $h ) {

		// Check header contents
		$hh = explode( ':', $h, 2 );
		$hn = trim( $hh[0] );
		$hv = trim( $hh[1] );

		// Content type header
		if ( $hn == 'content-type' ) {
			$ctx = explode( ';', $hv );
			$cti = trim(strtolower( $ctx[0] ));
		}

		// Work with cache control headers
		if ( $hn == 'expires' ) if ( $et = strtotime( $hv ) ) if ( $et < time() ) $nocache = true;
		if ( $hn == 'pragma' ) $nocache = true;
		if ( $hn == 'cache-control' ) {
			if ( strpos( $hv, 'no-cache' ) !== false ) $nocache = true;
			if ( strpos( $hv, 'no-store' ) !== false ) $nocache = true;
		}

	}

	// Work with content type
	$ct = $cti ? explode( '/', $cti ) : [];
	$cache = ( $ct[0] == 'text' ) ? false : true;
	if ( $cti == 'application/json' ) $cache = false;
	if ( $cti == 'text/css' ) $cache = true;
	if ( $cti == 'text/javascript' ) $cache = true;
	if ( $cti == 'application/javascript' ) $cache = true;
	if ( $nocache ) $cache = false;
	if (!empty( $_POST )) $cache = false;
	return $cache;

}

// Process different cloaling algorithms
// Result in boolean: [ black, black is set, white is set ]
function makeitclo() {

	// Define empty but useful variables
	$cookiebase = md5( __FILE__ . ip() );
	if (!defined( 'CLOAKCOOKIEWH' )) define( 'CLOAKCOOKIEWH', 'PHPSID'.substr( $cookiebase, 0, 8 ) );
	if (!defined( 'CLOAKCOOKIEBL' )) define( 'CLOAKCOOKIEBL', 'PHPSID'.substr( $cookiebase, 8, 8 ) );
	if (!defined( 'CLOAKMARKWH' )) define( 'CLOAKMARKWH', md5(substr( $cookiebase, 16, 8 )) );
	if (!defined( 'CLOAKMARKBL' )) define( 'CLOAKMARKBL', md5(substr( $cookiebase, 24, 8 )) );
	if (!defined( 'SMARTCOOKIE' )) define( 'SMARTCOOKIE', 'ssid'.substr( $cookiebase, 11, 6 ) );
	if (!defined( 'SMARTPATHCC' )) define( 'SMARTPATHCC', 'ssid'.substr( $cookiebase, 20, 6 ) );
	if (!defined( 'SMARTPATHIN' )) define( 'SMARTPATHIN', 'ssid'.substr( $cookiebase, 26, 6 ) );

	// Smartlink processing
	if ( $sk = smartcheck() ) {
		return smartway( $sk );
	} elseif (defined( 'SMARTLINK' )) return smartway(smartlink());

	// Check generic cloading
	if ( isset( $_COOKIE[CLOAKCOOKIEWH] ) && $_COOKIE[CLOAKCOOKIEWH] == CLOAKMARKWH ) {
		return [ false, false, true ];
	} elseif ( isset( $_COOKIE[CLOAKCOOKIEBL] ) && $_COOKIE[CLOAKCOOKIEBL] == CLOAKMARKBL ) {
		return [ true, true, false ];
	} elseif (function_exists( 'cloak' )) return [ cloak(), false, false ];

	// Ask AlterCPA Cloak
	if (defined( 'CLOAK' )) {
		$result = curl( CLOAK, $_SERVER );
		if ( $result ) {
			$clo = json_decode( $result, true );
			if ( $clo['status'] == 'ok' ) {
				if ( defined('BLACKDOMAIN') && ( $clo['type'] == 'black' || $clo['type'] == 'target' ) ) {
					return [ true, false, false ];
				} elseif ( defined('WHITEDOMAIN') && ( $clo['type'] == 'white' || $clo['type'] == 'dummy' ) ) {
					return [ false, false, false ];
				} elseif (filter_var( $clo['url'], FILTER_VALIDATE_URL )) {
					// $uin = parse_url( $clo['url'] );
					// if ( ( $uin['path'] == '/' || !$uin['path'] ) && !$uin['query'] ) {
					// 	if ( !defined('BLACKHTTPS') && $uin['scheme'] == 'https' ) define( 'BLACKHTTPS', true );
					// 	if (!defined('BLACKDOMAIN')) define( 'BLACKDOMAIN', $uin['host'] );
					// 	return [ true, false, false ];
					// } else return smartway(smartlink( $clo['url'] ));
					return smartway(smartlink( $clo['url'] ));
				} elseif (filter_var( $clo['url'], FILTER_VALIDATE_DOMAIN )) {
					if (!defined('BLACKDOMAIN')) define( 'BLACKDOMAIN', $clo['url'] );
					return [ true, false, false ];
				} else {
					$black = ( $clo['type'] == 'black' ) ? true : false;
					return [ $black, false, false ];
				}
			} else return [ false, false, false ];
		} else return [ false, false, false ];
	} else return [ false, false, false ];

}

// Check content type for URL rebuilding
function needreurl( $cth ) {
	$ctx = explode( ';', $cth );
	$cti = trim(strtolower( $ctx[0] ));
	$ct = explode( '/', $cti );
	$reurl = ( $ct[0] == 'text' ) ? true : false;
	if ( $cti == 'application/json' ) $reurl = true;
	if ( $cti == 'application/javascript' ) $reurl = true;
	return $reurl;
}

// Rebuild URL structure
function reurl( &$data, $domain, $url, $rebase = true ) {

	// Simply replace domain in all URLs
	$data = str_ireplace( 'src="http://'.$domain, 'src="', $data );
	$data = str_ireplace( 'src="https://'.$domain, 'src="', $data );
	$data = str_ireplace( 'href="http://'.$domain, 'href="', $data );
	$data = str_ireplace( 'href="https://'.$domain, 'href="', $data );
	$data = str_ireplace( 'src=\'http://'.$domain, 'src=\'', $data );
	$data = str_ireplace( 'src=\'https://'.$domain, 'src=\'', $data );
	$data = str_ireplace( 'href=\'http://'.$domain, 'href=\'', $data );
	$data = str_ireplace( 'href=\'https://'.$domain, 'href=\'', $data );
	$data = str_ireplace( $domain, $_SERVER['HTTP_HOST'], $data );
	$data = preg_replace( '/([a-z0-9\_\-\.]+)\.'.preg_quote($_SERVER['HTTP_HOST']).'/i',  '$1.'.$domain, $data );

	// Automatic HTTP(S) rewrites
	if ( $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != 'off' ) {
		$data = str_ireplace( 'http://' . $_SERVER['HTTP_HOST'], 'https://' . $_SERVER['HTTP_HOST'], $data );
	} else $data = str_ireplace( 'https://' . $_SERVER['HTTP_HOST'], 'http://' . $_SERVER['HTTP_HOST'], $data );

	// Try to add the base href if needed
	if ( $rebase ) {
		$base = '<base href="'.rtrim( dirname( $url . 'dummy' ), '/' ).'/" />';
		if ( stripos( $data, '<base' ) === false ) {
			if ( stripos( $data, '<head' ) !== false ) {
				$data = preg_replace( '#<head([^>]*)>#', '<head$1>'.$base, $data );
			} elseif ( stripos( $data, '</head' ) !== false ) {
				$data = str_ireplace( '</head', $base.'</head', $data );
			} elseif ( stripos( $data, '<body' ) !== false ) {
				$data = str_ireplace( '<body', $base.'</head><body', $data );
			}
		}
	}

}

// Process headers for domain
function parseheader( $header, $domain, $nocookie = false ) {

	// Prepare headers to process
	$headers = [];
	$hh = explode( "\n", $header );
	$hb = [ 'server', 'date', 'strict-transport-security', 'transfer-encoding', 'connection', 'content-length', 'content-encoding' ];

	// Show all the headers received
	foreach ( $hh as $h ) if ( $h = trim($h) ) {

		// Check header for black list
		if ( substr( $h, 0, 5 ) == 'HTTP/' ) continue;
		$hx = explode( ':', $h, 2 );
		$hn = strtolower(trim( $hx[0] ));
		$hv = trim( $hx[1] );
		if ( ! $hv ) continue;
		if (in_array( $hn, $hb )) continue;

		// Replace full URL with domain in locations
		if ( $hn == 'location' ) {
			$h = str_ireplace( 'http://'.$domain, '', $h );
			$h = str_ireplace( 'https://'.$domain, '', $h );
		}

		// Replace domain in all headers
		$h = str_ireplace( $domain, $_SERVER['HTTP_HOST'], $h );
		if ( $hn == 'set-cookie' ) {
			if ( $nocookie ) continue;
			$dti = explode( '.', $domain );
			$dtc = count( $dti );
			while ( $dtc > 2 ) {
				$dtc -= 1;
				array_shift( $dti );
				$dtz = implode( '.', $dti );
				$h = str_ireplace( $dtz, $_SERVER['HTTP_HOST'], $h );
			}
		}

		// Save the header
		$headers[] = $h;

	}

	return $headers;

}

//
// Processing
//

// Check the cloaking mode
if ( defined( 'BLACKDOMAIN' ) || defined( 'SMARTLINK' ) || function_exists( 'cloak' ) || defined( 'CLOAK' ) ) {
	$bc = makeitclo();
	if ( $bc[0] ) {
		$black = true;
		if ( ! $bc[1] ) setcookie( CLOAKCOOKIEBL, CLOAKMARKBL, time() + 86400, '/' );
	} elseif ( ! $bc[2] ) setcookie( CLOAKCOOKIEWH, CLOAKMARKWH, time() + 86400, '/' );
} else $black = false;

// Find current path and ext
$url = $_SERVER['REQUEST_URI'];
$pp = explode( '?', $url );
$path = ltrim( $pp[0], '/' );
$exti = strrpos( $path, '.' );
$ext = ( $exti === false ) ? false : strtolower(substr( $path, $exti+1 ));

// Choose the mode to work
// $mode: 0 - direct, 1 - proxy, 2 - redirect
if ( $black ) {

	// Black sites simply work via proxy or redirects
	$domain = BLACKDOMAIN;
	$mode = defined( 'BLACKREDIR' ) ? 2 : 1;
	$https = defined( 'BLACKHTTPS' ) ? BLACKHTTPS : false;

} else {

	// White sites work in all modes
	$domain = defined( 'WHITEDOMAIN' ) ? WHITEDOMAIN : $_SERVER['HTTP_HOST'];
	if (!defined( 'WHITEREDIR' )) {
		$mode = defined( 'WHITEDOMAIN' ) ? 1 : 0;
		$https = defined( 'WHITEHTTPS' ) ? WHITEHTTPS : false;
	} else {
		$mode = 2;
		$https = $_SERVER['HTTPS'] ? ( ( $_SERVER['HTTPS'] == 'off' ) ? false : true ) : false;
		if (defined( 'WHITEHTTPS' )) $https = WHITEHTTPS ? true : false;
	}

	// Check referer for path finder
	if ( $_SERVER['HTTP_REFERER'] ) {
		$rr = parse_url( $_SERVER['HTTP_REFERER'] );
		$findpath = ( $rr['host'] == $_SERVER['HTTP_HOST'] ) ? false : true;
	} else $findpath = true;

	// Extension black list
	$extbl = [ 'jpg', 'jpeg', 'png', 'bmp', 'svg', 'ico', 'js', 'css', 'map', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'otd', 'otf', 'ttf', 'woff', 'json', 'mp3', 'wav', 'wma', 'ogg', 'mp3', 'mpeg', 'avi', 'flv', 'swf', 'gz', 'zip', 'rar', '7z' ];
	if (in_array( $ext, $extbl )) $findpath = false;

	// Find the path
	if ( $path && $findpath ) {

		// Choose mode to work with
		if (defined( 'WHITEDOMAIN' )) {

			// Paths file for direct
			$pf = md5(__FILE__).'.json';
			if (!file_exists( $pf )) {
				$paths = array();
				if (defined( 'WHITEMAP' )) xmlsitemap( WHITEMAP, $paths );
				file_put_contents( $pf, json_encode( $paths ) );
			} else $paths = json_decode( file_get_contents( $pf ), true );

			// Check if we are on existing page
			$exist = in_array( $path, $paths ) ? true : false;
			if ( ! $exist ) {

				// Find the direct page we need
				$pages = count( $paths );
				if ( $pages ) {
					$pid = (int) abs(crc32( $path ));
					$pid %= $pages;
					$url = '/' . $paths[$pid];
				}

			}

		} else {

			// Paths file for direct
			$pf = md5(__FILE__).'.json';
			if (!file_exists( $pf )) {
				$paths = array();
				pathfinder( dirname(__FILE__), '/', $paths );
				file_put_contents( $pf, json_encode( $paths ) );
			} else $paths = json_decode( file_get_contents( $pf ), true );

			// Find the direct page we need
			$pages = count( $paths );
			if ( $pages ) {
				$pid = (int) abs(crc32( $path ));
				$pid %= $pages;
				$url = '/' . $paths[$pid];
			}

		}

	}

}

// Redirect processing
if ( $mode == 2 ) {
	if ( $domain ) $url = ( $https ? 'https://' : 'http://' ) . $domain . $url;
	header( "Location: $url" );
	if (defined('RECOOKIE')) setcookie( RECOOKIE, md5(microtime()), time() + 86400, '/' ); // Just for fun
	die();
}

// Check the cache and switch mode
if ( $mode && defined('CACHE') ) {

	// Check the URL and cached names
	$uri = ( $https ? 'https://' : 'http://' ) . $domain . $url;
	$mcn = cachename( $uri, true );
	$dcn = cachename( $uri );

	// Check cache files with 15 minutes lifetime
	if ( file_exists( $mcn ) && file_exists( $dcn ) ) {
		$fmt = max( @filemtime( $mcn ), @filemtime( $dcn ) );
		$fmx = time() + 900;
		if ( $fmt > $fmx ) {
			$mode = 3; // Change mode to cache
			$data = file_get_contents( $dcn );
			$header = json_decode( file_get_contents( $mcn ), true );
			foreach ( $header as $h ) header( $h, false );
		}
	}

}

// Loading data directly or as proxy
if ( $mode == 1 ) {

	// Loading external data
	$uri = ( $https ? 'https://' : 'http://' ) . $domain . $url;
	$curl = curl_init( $uri );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl, CURLOPT_FOLLOWLOCATION, false );
	curl_setopt( $curl, CURLOPT_HEADER, true );
	curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD'] );
	curl_setopt( $curl, CURLOPT_ENCODING, '' );

	// Find the basic headers
	$ip = ip();
	$sendhead = [ "X-Real-IP: $ip", "Client-IP: $ip", "X-Forwarded-For: $ip" ];
	if ( $_SERVER['HTTP_ACCEPT'] ) $sendhead[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
	if ( $_SERVER['HTTP_ACCEPT_CHARSET'] ) $sendhead[] = 'Accept-Charset: ' . $_SERVER['HTTP_ACCEPT_CHARSET'];
	if ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) $sendhead[] = 'Accept-Language: ' . $_SERVER['HTTP_ACCEPT_LANGUAGE'];
	if ( $_SERVER['HTTP_AUTHORIZATION'] ) $sendhead[] = 'Authorization: ' . $_SERVER['HTTP_AUTHORIZATION'];
	if ( $_SERVER['HTTP_EXPECT'] ) $sendhead[] = 'Expect: ' . $_SERVER['HTTP_EXPECT'];
	if ( $_SERVER['HTTP_PROXY_AUTHORIZATION'] ) $sendhead[] = 'Proxy-Authorization: ' . $_SERVER['HTTP_PROXY_AUTHORIZATION'];
	if ( $_SERVER['HTTP_COOKIE'] ) $sendhead[] = 'Cookie: ' . $_SERVER['HTTP_COOKIE'];
	if ( $_SERVER['HTTP_CONECTION'] ) $sendhead[] = 'Connection: ' . $_SERVER['HTTP_CONECTION'];

	// Special headers
	if ( $_SERVER['HTTP_CONTENT_TYPE'] ) {
		$sendhead[] = 'Content-Type: ' . $_SERVER['HTTP_CONTENT_TYPE'];
	} elseif ( $_SERVER['CONTENT_TYPE'] ) $sendhead[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
#	if ( $_SERVER['HTTP_CONTENT_LENGTH'] ) {
#		$sendhead[] = 'Content-Length: ' . $_SERVER['HTTP_CONTENT_LENGTH'];
#	} elseif ( $_SERVER['CONTENT_LENGTH'] ) $sendhead[] = 'Content-Length: ' . $_SERVER['CONTENT_LENGTH'];

	// Find the X-headers
	foreach ( $_SERVER as $k => $v ) if ( substr( $k, 0, 7 ) == 'HTTP_X_' ) {
		$k = strtolower(substr( $k, 7 ));
		$k = strtr( $k, '_', ' ' );
		$k = ucfirst( $k );
		$k = strtr( $k, ' ', '-' );
		$sendhead[] = "X-$k: $v";
	}

	// Add all the headers to request
	curl_setopt( $curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
	if ( $_SERVER['HTTP_REFERER'] ) curl_setopt( $curl, CURLOPT_REFERER, str_replace( $_SERVER['HTTP_HOST'], $domain, $_SERVER['HTTP_REFERER'] ) );
	curl_setopt( $curl, CURLOPT_HTTPHEADER, $sendhead );

	// Add post data to the request
	if ( $_POST ) {
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $_POST ) );
	} elseif ( $post = file_get_contents('php://input') ) curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );

	// Exec the curl and get data
	$result = curl_exec( $curl );
	$info = curl_getinfo( $curl );
	curl_close( $curl );
	$reurl = needreurl( $info['content_type'] );

	// Split reply to headers and data
	$z = explode( "\r\n\r\n", $result );
	foreach ( $z as $zi => $zv ) {
		unset ( $z[$zi] );
		$hl = explode( "\n", $zv );
		$h1 = explode( ' ', trim( $hl[0] ) );
		$hs = (int) $h1[1];
		if ( $hs > 99 && $hs < 200 ) continue;
		$header = $zv;
		break;
	}
	$data = implode( "\r\n\r\n", $z );
	unset( $result, $z, $hl, $h1 );

	// Work with headers and cache
	$headers = parseheader( $header, $domain );
	foreach ( $headers as $h ) header( $h, false );
	if ( defined('CACHE') && cacheneed($headers) ) cachesave( $uri, $headers, $data );

} elseif ( $mode == 0 ) {

	// Simply find the file and load it
	$page = dirname(__FILE__) . $url;
	if (!file_exists( $page )) e404();
	$data = file_get_contents( $page );
	$reurl = true;	// Always replace URLs for direct HTML

}

// Show the page
if ( $reurl ) reurl( $data, $domain, $url );
echo $data;
// end. =)