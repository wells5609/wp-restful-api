<?php

abstract class _Api_Controller {
	
	public $name;
	
	public $protected_methods = array();
	
	public $routes = array();
	
	public $route_priority;
	
	abstract static function instance();
	
}

class Api_Controller extends _Api_Controller {
	
	public $name; # Controller name (lowercase)
	
	public $protected_methods = array();
	
	public $routes = array();
	
	public $route_priority = 5;
	
	static protected $_instance;
		
	static function instance(){
		if ( !isset(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	static function register_routes(){
		global $api;
		$_this = self::instance();
		
		if ( !empty($_this->routes) ){
			return $api->router->add_route_group( $_this->name, $_this->routes, $_this->route_priority );
		}
		
		return false;
	}
	
	// Whether the method requires authorization
	public function method_requires_auth( $method ){
	
		return ( true === $method || in_array($method, $this->protected_methods) ) ? true : false;
	}
	
	public function get_protected_methods(){
		return $this->protected_methods;
	}
	
	
}