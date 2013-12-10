<?php

// Controllers & Routes

function api_controller_init( $group ){
	global $api;
	
	// append _ApiController if not present
	if ( '_ApiController' !== substr($group, -14) )
		$controller = ucfirst($group) . '_ApiController';
	
	return call_user_func( array($controller, 'register_routes') );
}

function api_add_route_group($controller, array $routes, $priority = 5, $position = 'top'){
	global $api;
	$api->router->add_route_group($controller, $routes, $priority, $position);
	return;	
}


// Output formats

function api_is_json(){
	global $api;
	return $api->is_json();	
}

function api_is_xml(){
	global $api;
	return $api->is_xml();	
}

function api_content_type_mime($content_type){
	switch($content_type){
		case 'html':
			return 'text/html';
		case 'json':
			return 'application/json';
		case 'jsonp':
			return 'text/javascript';
		case 'xml':
			return 'text/xml';
		case 'xhtml':
			return 'text/xhtml';	
	}	
}


function api_hash( $data, $key = API_DIGEST_KEY ){
	return hash_hmac( API_HASH_ALGO, $data, $key );
}


// Authorization

if ( API_AUTH_REQUESTS ){
		
	function api_get_auth_object( $apikey ){
	
		$model =& get_model('Api_Auth');
		
		return $model->get_auth_object($apikey);
	}
	
	function api_get_auth_token( $apikey, &$auth_object = null ){
		
		if ( null === $auth_object )
			$auth_object = api_get_auth_object($apikey);
		
		if ( !isset($auth_object->secret_key) || !isset($auth_object->email) )
			return false;
		
		$prefix = api_hash($auth_object->email, $auth_object->secret_key);
				
		return base64_encode($prefix . $apikey);
	}
	
	function api_get_user_apikey( $user_id = null ){
	
		if (empty($user_id)) 
			$user_id = get_current_user_ID();
		
		$model =& get_model('Api_Auth');	
		
		return $model->get_apikey_by('user_id', $user_id);
	}
	
	function api_get_apikey_by( $field, $value ){
	
		$model =& get_model('Api_Auth');	
	
		return $model->get_apikey_by($field, $value);
	}
	
}
