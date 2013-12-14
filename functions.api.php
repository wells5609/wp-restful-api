<?php

// Controllers & Routes

function api_register_controller( $controller ){
	global $api;
	return $api->register_controller($controller);
}

function api_add_route_group($controller, array $routes, $priority = 5, $position = 'top'){
	global $api;
	$api->add_route_group($controller, $routes, $priority, $position);
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


function get_request_headers(){
		
	$misfits = array('CONTENT_TYPE', 'CONTENT_MD5', 'CONTENT_LENGTH');
	$headers = array();
	foreach($_SERVER as $key => $value){
		if (0 === strpos($key, 'HTTP_'))
			$headers[ strtolower(str_replace('HTTP_', '', $key)) ] = $value;
		elseif (in_array($key, $misfits))
			$headers[ strtolower($key) ] = $value;
	}
	return $headers;
}

function get_request_header($name){
	$name = strtoupper( str_replace('-', '_', $name) );
	$misfits = array(
        'CONTENT_TYPE',
        'CONTENT_LENGTH',
		'CONTENT_MD5',
        'PHP_AUTH_USER',
        'PHP_AUTH_PW',
        'PHP_AUTH_DIGEST',
        'AUTH_TYPE'
    );
	if ( !in_array($name, $misfits) )
		$name = 'HTTP_' . $name;
	return isset($_SERVER[$name]) ? $_SERVER[$name] : null;
}


function api_hash( $data, $key = API_DIGEST_KEY ){
	return hash_hmac( API_HASH_ALGO, $data, $key );
}


/** ======== Authorization ======== */

function api_create_apikey($ip, $user_id, $email){
	
	if ( defined('API_KEY_POOL') ){
		$pool = API_KEY_POOL;
	}
	else {
		$pool ='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
	}
	
	if ( defined('API_KEY_PREFIX') ){
		$pre = API_KEY_PREFIX;
	}
	else {
		$pre = md5($_SERVER['DOCUMENT_ROOT']);
	}
	
	$key = api_generate_random_string( API_KEY_LENGTH, $pool );
	
	$pre = substr($pre, 0, 5);
	$key = substr($key, 0, API_KEY_LENGTH - 5);
	
	return $pre . $key;
}

function api_get_auth_token( $apikey, &$auth_object = null ){
	
	if ( null === $auth_object )
		$auth_object = api_get_auth_object($apikey);
	
	if ( !isset($auth_object->secret_key) || !isset($auth_object->email) )
		return false;
	
	$prefix = api_hash($auth_object->email, $auth_object->secret_key);
			
	return str_replace(array('-','_','+','='), '', base64_encode($prefix . $apikey));
}

function api_get_auth_object( $apikey ){

	$model =& get_model('Api_Auth');
	
	return $model->get_auth_object($apikey);
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


// Helper
function api_generate_random_string($length, $pool = null){
	
	if ( null === $pool ){
		if ( defined('API_KEY_POOL') )
			$pool = API_KEY_POOL;
		else // twice as many lowercase as uppercase and numbers
			$pool ='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	}
	$string = '';
	for ($i=0; $i < $length+1; $i++){
		$string .= substr($pool, mt_rand(0, strlen($pool)-1), 1);
	}
	return $string;
}