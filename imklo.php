<?php

/*******************************************************************************

 *
 *  AlterVision CPA White
 *  Created by AlterVision - www.altercpa.pro
 *  Copyright (c) 2018-2020 Anton Reznichenko
 *

 *
 *  File: 			imklo.php
 *  Description:	IM KLO connector
 *  Author:			Anton 'AlterVision' Reznichenko - altervision13@gmail.com
 *

*******************************************************************************/

// Settings
define( 'IMKLO', 'klo.site.ru' ); // Domain of your IM KLO installation
#define( 'IMBLACK', 'site.ru' ); // Domain of your black site with AlterCPA site storage
#define( 'BLACKHTTPS', true ); // Use HTTPS connection to black site, not recommended

// Cloak function
function cloak() {

	// Create the request
	$post['ip'] = ip();
	$post['domain'] = $_SERVER['HTTP_HOST'];
	$post['referer'] = @$_SERVER['HTTP_REFERER'];
	$post['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
	$post['url'] = $_SERVER['REQUEST_URI'];
	$post['headers'] = json_encode(apache_request_headers());

	// Get IP info
	$curl = curl_init( 'http://'.IMKLO.'/api/check_ip');
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $curl, CURLOPT_TIMEOUT, 60 ); // Yes, it can be slow :(
	curl_setopt( $curl, CURLOPT_POST, true );
	curl_setopt( $curl, CURLOPT_POSTFIELDS, $post );
	$result = curl_exec( $curl );
	curl_close( $curl );

	// Check the result
	if ( ! $result ) return false;
	$info = json_decode( $result, true );
	if ( ! $info ) return false;

	// Work with the links the smart way
	$black = ( isset($info['link']) && filter_var( $info['link'], FILTER_VALIDATE_URL ) ) ? $info['link'] : false;
	$white = ( isset($info['white_link']) && filter_var( $info['white_link'], FILTER_VALIDATE_URL ) ) ? $info['white_link'] : false;
	if ( $black ) return (bool) smartway(smartcookies( $black ));
	if ( $white ) return (bool) smartway(smartcookies( $white ));

	// Generic integration
	if ( isset( $info['result'] ) && $info['result'] == 0 ) return false;
	if ( isset( $info['white_link'] ) && $info['white_link'] ) return false;
	if ( defined( 'IMBLACK' ) && !defined( 'BLACKDOMAIN' ) ) define( 'BLACKDOMAIN', IMBLACK );
	return true;

}

// Load the CMS
require_once 'index.php';
// end. =)