<?php

abstract class Api_Controller {
	
	public $routes = array();
	
	public $protected_methods = array();
	
	public $_last_auth_message;
	
	
	abstract static function instance();
	
	
	// Whether the method requires authorization
	public function method_requires_auth( $method ){
	
		return ( true === $method || in_array($method, $this->protected_methods) ) ? true : false;
	}
	
	public function get_protected_methods(){
		
		
	}
	
}