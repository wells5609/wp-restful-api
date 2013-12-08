<?php

// Controllers & Routes

function api_controller_init( $group ){
	global $api;
	
	// append _ApiController if not present
	if ( '_ApiController' !== substr($group, -strlen('_ApiController')) )
		$controller = ucfirst($group) . '_ApiController';
	
	$object = call_user_func( array($controller, 'instance') );
	
	if ( !empty($object->routes) )
		$api->router->add_route_group( $group, $object->routes, $object->route_priority );
	return;
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


// Authorization

function api_hash( $data ){
	return hash_hmac(API_HASH_ALGO, $data, API_DIGEST_KEY);
}

if ( API_AUTH_REQUESTS ){
		
	function api_get_auth_object( $apikey ){
	
		$model =& get_model('Api_Auth');
		
		return $model->get_auth_object($apikey);
	}
	
	function api_get_auth_token( $apikey, &$auth_object = null ){
		
		if ( null === $auth_object )
			$auth_object = api_get_auth_object($apikey);
		
		return api_generate_auth_token($apikey, $auth_object);
	}
	
	function api_generate_auth_token( $apikey, &$auth_object ){
		
		if ( !isset($auth_object->secret_key) || !isset($auth_object->day_start_time) )
			return false;
		
		$prefix = api_hash($auth_object->secret_key);
		$suffix = api_hash($auth_object->day_start_time);
		$b64 = base64_encode($prefix . $apikey . $suffix);
		
		return str_replace(array('+', '/', '\r', '\n', '='), array('-', '_'), $b64);
	}
	
	function api_get_user_apikey( $user_id = null ){
	
		if (empty($user_id)) 
			$user_id = get_current_user_ID();
	
		return api_get_apikey_by('user_id', $user_id);
	}
	
	function api_get_apikey_by( $field, $value ){
	
		$model =& get_model('Api_Auth');	
	
		return $model->get_apikey_by($field, $value);
	}
	
}
