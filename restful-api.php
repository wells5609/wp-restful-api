<?php
/*
Plugin name: RESTful API
Description: A complete RESTful API: Formats (json, xml, html, etc.), AJAX (replace admin-ajax.php), Controllers (with method-specific variables), API Keys (limit requests per day), Authorization (via nonce, apikey, or http header).
Author: wells
Version: 0.3.3
*/

class Api_Config {
	
	public $default_content_type,
		
		$compression,
		
		$ajax,
		
		$auth;
	
	static protected $_instance;

	static function instance(){
		if ( !isset(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	private function __construct(){
			
	}
	
	public function add( $key, $vars = array() ){
		$_this = self::instance();
		$_this->$key = new stdClass;
		if ( !empty($vars) ){
			foreach($vars as $k => $v){
				$_this->$key->$k = $v;	
			}	
		}
		return true;
	}
	
	public function set( $key, $val ){
		
		$_this = self::instance();
		
		$parsed = $_this->parseKey($key);
		
		if ( is_array($parsed) ){
			$key = $parsed['key'];
			$var = $parsed['var'];
			if ( !isset($_this->$key) ){
				return $_this->add( $key, array($var => $val) );	
			}
			return $_this->$key->$var = $val;
		}
		
		return $_this->$key = $val;	
	}
	
	public function get( $key ){
		$_this = self::instance();
		$var = $_this->getParsed($key);
		return null !== $var ? $var : $_this->getDefault($key);	
	}
	
	private function getParsed($key){
		
		$parsed = $this->parseKey($key);
		
		if ( is_array($parsed) ){
			$key = $parsed['key'];
			$var = $parsed['var'];
		}
		
		if (!isset($this->$key)) return null;
		
		if ( isset($var) )
			return isset($this->$key->$var) ? $this->$key->$var : null;
		
		return $this->$key;
	}
	
	private function parseKey($key){
		if (false !== strpos($key, '.')){
			$parts = explode('.', $key);
			return array(
				'key' => $parts[0],
				'var' => $parts[1],
			);
		}
		return $key;
	}
	
	private function getDefault($var){
		switch($var){
			case 'default_content_type':
				return 'json';
			case 'compression.enable':
				return false;
			case 'ajax.default_content_type':
				return 'html';
			case 'ajax.action_prefix':
				return 'wp_ajax_';
			case 'ajax.action_prefix_nopriv':
				return 'wp_ajax_nopriv_';
			case 'auth.apikey_header':
				return 'x-api-key';
			case 'auth.token_header':
				return 'x-api-auth-token';
			default:
				return null;
		}	
	}
	
}


function api_config( $var, $val = null ){
	$config = Api_Config::instance();
	if ( null === $val )
		return $config->get( $var );
	return $config->set( $var, $val );
}


add_action('init', 'restful_api_init', 5);

	function restful_api_init() {
		
		define('APIBASE', 'api');	
		
		api_config('ajax.enable', true);
		api_config('auth.enable', true);
		
		if ( extension_loaded('zlib') ){
			api_config('compression.enable', true);	
		}
		
		if ( !defined('API_HASH_ALGO') ){
			define('API_HASH_ALGO', 'sha224');	
		}
		
		/** 
		* Define using a new key from:
		* @link http://api.wordpress.org/secret-key/1.1/
		*/
		if ( !defined('API_DIGEST_KEY') ){
			define('API_DIGEST_KEY', hash('sha384', $_SERVER['DOCUMENT_ROOT']) );
		}
		
		require 'Api/Main.php';
		
		if ( function_exists('autoload_paths') ){
			
			autoload_paths( 'Api', array(__DIR__) );
		} 
		else {
			include 'Api/Request.php';
			include 'Api/Router.php';
			include 'Api/Response.php';
			include 'Api/Authorization.php';
			include 'Api/Controller.php';
		}
		
		if ( api_config('auth.enable') && function_exists('register_datatype') ){
			
			if ( !defined('API_KEY_LENGTH') ){
				define('API_KEY_LENGTH', 40);	
			}
			
			register_datatype('Api_Auth');
		}
		
		if ( api_config('ajax.enable') ){
	
			if ( !defined('API_AJAX_BASE') )
				define('API_AJAX_BASE', 'ajax');		
		}
		
		require 'functions.api.php'; // include after constants set
			
		$GLOBALS['api'] =& Api_Main::instance();
	}