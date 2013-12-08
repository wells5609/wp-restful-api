<?php
/*
Plugin name: RESTful API
Description: A complete RESTful API: Formats (json, xml, html, etc.), AJAX (replace admin-ajax.php), Controllers (with method-specific variables), API Keys (limit requests per day), Authorization (via nonce, apikey, or http header).
Author: wells
Version: 0.2
*/

add_action('init', '_restful_api_init', 1);

function _restful_api_init() {
	
	define('APIBASE', 'api');	
	
	// Authenticate via apikey, HTTP header, or nonce
	if ( !defined('API_AUTH_REQUESTS') )
		define('API_AUTH_REQUESTS', true);
		
	// Use API for AJAX rather than wp-admin/admin-ajax.php
	if ( !defined('API_USE_FOR_AJAX') )		
		define('API_USE_FOR_AJAX', true);
	
	// this should be changed
	if ( !defined('API_DIGEST_KEY') )
		define('API_DIGEST_KEY', 'UwHg`#sK.zSBLs.Dr9KgYaSw@_H@;A`f;:z+^NprX.9/-KqOy!7*ke`/tZR&s-_m');
	
	if ( !defined('API_HASH_ALGO') )
		define('API_HASH_ALGO', 'sha1');
		
	require 'Api/Main.php';
	
	if ( function_exists('autoload_paths') ){
		
		autoload_paths( 'Api', array(__DIR__) );
	} 
	else {
		include 'Api/Query.php';
		include 'Api/Router.php';
		include 'Api/Response.php';
		include 'Api/Controller.php';
		include 'Api/Authorization.php';
	}
	
	if ( API_AUTH_REQUESTS && function_exists('register_datatype') ){
		
		register_datatype('Api_Auth'); // requires Models, Objects, etc.
	}
	
	if ( API_USE_FOR_AJAX ){
		
		if ( !defined('API_AJAX_BASE') )
			define('API_AJAX_BASE', 'ajax');
		
		if ( !defined('API_AJAX_ACTION_PREFIX') )
			define('API_AJAX_ACTION_PREFIX', 'wp_ajax_');
		
		if ( !defined('API_AJAX_ACTION_PREFIX_NOPRIV') )
			define('API_AJAX_ACTION_PREFIX_NOPRIV', 'wp_ajax_nopriv_');
	}
	
	require 'functions.api.php'; // include after constants set
		
	$GLOBALS['api'] =& Api_Main::instance();
	
}