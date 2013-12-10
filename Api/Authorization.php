<?php

class Api_Authorization {
	
	public $is_authorized = false;
	
	public $auth_method;
	
	
	public function doAuth( &$controller = null ){
		
		global $api;
		
		if ( $this->verifyHeaders() )
			$this->authorize(true, 'header');
				
		elseif ( $this->verifyApikey($controller, $api->callback[1]) )
			$this->authorize(true, 'apikey');
		
		elseif ( $api->is_ajax && ajax_verify_nonce() )
			$this->authorize(true, 'nonce');
		
		else 
			$api->error('Unauthorized request', 'unauthorized');
	}
	
	
	protected function verifyApikey( &$controller = null, $method ){
		global $api;
		
		if ( $this->is_authorized ) 
			return true;
		
		if ( !is_a($controller, 'Api_Controller') ) 
			return false;
		
		if ( !$controller->method_requires_auth($method) )
			return true;
		
		if ( !isset($api->query->apikey) )
			return false;
		
		$apikey = $api->query->apikey;
		$auth_model = get_model('Api_Auth');
		
		$response = $auth_model->touch($apikey);	
		
		if ( !is_array($response) )
			$api->error('Unauthorized - method requires valid API key.', 'unauthorized');
		
		$api->response->response = array_merge($api->response->response, $response);
		
		return true;
	}
		
	protected function verifyHeaders(){
		
		if ( isset($_SERVER['HTTP_X_API_KEY']) && isset($_SERVER['HTTP_X_API_AUTH_TOKEN']) ){
			
			if ( $_SERVER['HTTP_X_API_AUTH_TOKEN'] === api_get_auth_token($_SERVER['HTTP_X_API_KEY']) )
				return true;
		}
		return false;
	}
	
		
	protected function authorize( $is_authorized, $auth_method ){
		$this->is_authorized = $is_authorized;
		$this->auth_method = $auth_method;	
	}
	
}


function get_request_header( $name ){
	
	$name = strtoupper( str_replace('-', '_', $name) );
	
	if ( 0 !== strpos($name, 'CONTENT') )
		$name = 'HTTP_' . $name;
	
	return isset($_SERVER[$name]) ? $_SERVER[$name] : null;
}

