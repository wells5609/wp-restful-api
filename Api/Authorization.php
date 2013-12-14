<?php

class Api_Authorization {
	
	public $is_authorized = false;
	
	public $credentials = array();
	
	public $auth_method;
	
	
	public function doAuth( &$controller = null ){
		
		global $api;
		
		if ( $this->verifyHeaders() ){
			$this->is_authorized = true;
			$this->auth_method = 'header';
		}
		elseif ( $this->verifyApikey($controller, $api->callback[1]) ){
			$this->is_authorized = true;
			$this->auth_method = 'apikey';
		}
		elseif ( $api->is_ajax && ajax_verify_nonce() ){
			$this->is_authorized = true;
			$this->auth_method = 'nonce';
		}
		else {
			$api->error('API requires authorization', 'unauthorized');
		}
	}
	
	
	protected function verifyApikey( &$controller = null, $method ){
		global $api;
		
		if ( $this->is_authorized ) 
			return true;
		
		if ( !is_a($controller, 'Api_Controller') ) 
			return false;
		
		if ( !$controller->method_requires_auth($method) )
			return true;
		
		if ( !$api->get_param('apikey') )
			return false;
		
		$apikey = $api->get_param('apikey');
		
		// Apply filters("api/auth/apikey", false, $apikey, $controller, $method);
		$auth_model = get_model('Api_Auth');
		$response = $auth_model->touch($apikey); // return $response
		
		if ( !$response || empty($response) )
			$api->error('Method requires valid API key.', 'unauthorized');
		
		$this->credentials['apikey'] = $apikey;
		
		return true;
	}
	
	
	protected function verifyHeaders(){
		
		if ( $apikey = get_request_header('x-api-key') && $auth_token = get_request_header('x-api-auth-token') ){
			
			if ( $auth_token == api_get_auth_token($apikey) ){
				
				$this->credentials['apikey'] = $apikey;
				$this->credentials['token'] = $auth_token;
				
				return true;
			}
		}
		return false;
	}
	
	
}
