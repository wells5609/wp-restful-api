<?php

abstract class _Api_Controller {
	
	public $name;
	
	public $routes = array();
	
	public $protected_methods = array();
	
	public $route_priority;
	
	abstract static function instance();
	
}

class Api_Controller extends _Api_Controller {
	
	public $name; # Controller name (lowercase)
	
	public $routes = array();
	
	public $protected_methods = array();
	
	public $route_priority = 5;
	
	static protected $_instance;
		
	static function instance(){
		if ( !isset(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	static function register_routes(){
		global $api;
		$_this = static::instance();
		
		if ( !empty($_this->routes) ){
			
			return $api->add_route_group( $_this->name, $_this->routes, $_this->route_priority );
		}
		
		return false;
	}
	
	// Whether the method requires authorization
	public function method_requires_auth( $method ){
	
		return ( true === $method || in_array($method, $this->get_protected_methods()) ) ? true : false;
	}
	
	public function get_protected_methods(){
		return $this->protected_methods;
	}
	
}

class Ajax_ApiController extends Api_Controller {
	
	public $name = 'ajax';
	
	public $routes = array();	
	
	public $protected_methods = array();
	
	public $route_priority = 4;
	
	static protected $_instance;
		
	static function instance(){
		if ( !isset(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	function ajax( $q ){
		
		global $api;
		
		$api->is_ajax = true;
		
		define('DOING_AJAX', true);
		
		$api->authorize_request( $this );
				
		do_action('load_ajax_handlers');
		
		$api->set_status(200);
		$api->send_headers();
		
		// Request may end in action via die() or (incorrectly) return data.
		if ( is_user_logged_in() ){
			$r = do_action( api_config('ajax.action_prefix') . $api->get_ajax_action(), $api->get_query_vars() );
		}
		else {
			$r = do_action( api_config('ajax.action_prefix_nopriv') . $api->get_ajax_action(), $api->get_query_vars() );			
		}
		
		// we didn't die() and no data was returned
		if ( empty($r) ){
			$api->error('Unknown AJAX call');
		}
		
		die($r);
	}
	
}