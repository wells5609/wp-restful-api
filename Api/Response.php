<?php

class Api_Response {
	
	public $status;
	
	public $message;
	
	public $content_type;
	
	public $results;
	
	// Header: status code
	public $status_code;
	
	// Header: cache
	public $cache = false;
	
	// Header: no sniff
	public $no_sniff = true;
	
	// Header: others
	public $headers = array();
	
	public $response = array(
		'status' => null,
	);
	
	protected $send_auth_header = false;
		
	protected $content_types = array('html', 'xml', 'json', 'jsonp');
	
	
	public function is_content_type( $type ){
		return in_array($type, $this->content_types) ? true : false;
	}
	
	public function set_content_type( $type, $force = false ){
		if ( $this->is_content_type($type) && ( !isset($this->content_type) || $force ) ){
			$this->content_type = $type;
			return true;
		}
		return false;
	}
	
	public function setResults($results){
		$this->results = $results;	
	}
	
	public function setMessage($message){
		$this->message = $message;	
	}
	
	public function setCache($cache){
		if ( is_numeric($cache) )
			$this->cache['expires_offset'] = $cache;
		else 
			$this->cache = $cache;
	}
	
	public function setNosniff($nosniff){
		$this->no_sniff = (bool) $nosniff;	
	}
	
	public function setHeader($name, $value){
		$this->headers[$name] = $value;	
	}
	
	public function sendAuthHeader( $bool ){
		$this->send_auth_header = $bool;	
	}
	
	// Sets HTTP status header code from pre-defined response types or actual code
	public function setHeaderStatusCode( $response_type ){
		
		if ( is_numeric($response_type) )
			return $this->status_code = $response_type;	
		
		switch (strtolower($response_type)) {
			case 'ok':
			default:
				return $this->status_code = 200;
			case 'found':
				return $this->status_code = 302;
			case 'not-found':
			case 'error':
				return $this->status_code = 400;
			case 'unauthorized':
			case 'auth-error':
				return $this->status_code = 401;
			case 'forbidden':
				return $this->status_code = 403;			
			case 'invalid-method':
				return $this->status_code = 405;
		}	
	}
	
	public function build(){
	
		global $api;
		
		$status = empty($this->results) ? false : true;
		
		// status
		$this->response['status'] = $status ? 'ok' : 'error';
		
		// message
		if ( !empty($this->message) )
			$this->response['message'] = $this->message;
		elseif ( !$status )
			$this->response['message'] = 'Error';
		
		// count
		if ( $status && is_array($this->results) )
			$this->response['count'] = count($this->results);
		
		// time/queries/memory
		if ( defined('WP_DEBUG') && WP_DEBUG ){
			$this->response['time'] = timer_stop(0, 3) . ' s';
			$this->response['queries'] = get_num_queries();
			$this->response['memory'] = round(memory_get_peak_usage()/1024/1024, 3) . ' MB';
		}
		
		// results
		if ( $status )
			$this->response['results'] = $this->results;
		
		$this->send_headers();
				
		return array( 'response' => $this->response );
	}
	
	
	public function send_headers(){
	
		global $api;
		
		// header status code
		if ( empty($this->status_code) ){
			if ( !empty($this->results) )
				$this->setHeaderStatusCode(200);
			else
				$this->setHeaderStatusCode(400);
		}
		
		status_header( $this->status_code );
		
		if ( isset($this->content_type) ){
			$content_type = api_content_type_mime($this->content_type);
		}
		else {
			$content_type = $this->get_default_content_type();
		}
		
		$this->setHeader('Content-Type', "{$content_type}; charset=utf-8");
		
		if ( true === $this->no_sniff ){
			$this->setHeader('X-Content-Type-Options', 'nosniff');
		}
		
		if ( $this->send_auth_header && isset($api->auth) ){
			
			if ( $api->auth->is_authorized )
				$auth_header = $api->auth->auth_method;
			else 
				$auth_header = 'unauthorized';
			
			$this->setHeader( 'X-Api-Authorized', $auth_header );
		}
		
		$this->setCacheHeaders();
		
		foreach($this->headers as $header => $value){
			@header("{$header}: {$value}");	
		}
		
	}
	
	protected function get_default_content_type(){
		global $api;
		if ( $api->is_ajax )
			return api_content_type_mime( API_AJAX_DEFAULT_CONTENT_TYPE );
		return api_content_type_mime( API_DEFAULT_CONTENT_TYPE );
	}
	
	protected function setCacheHeaders(){
		
		if ( !empty($this->cache) && 0 != $this->cache['expires_offset']) {
			
			$cache = array_merge(array('expires_offset' => 86400, 'cache_control' => 'Public'), $this->cache);
			
			$this->setHeader( 'Expires', gmdate( "D, d M Y H:i:s", time() + $cache['expires_offset'] ) . ' GMT' );
			$this->setHeader( 'Cache-Control', $cache['cache_control'] . ', max-age=' . $cache['expires_offset'] );
		}
		else {
			
			$this->setHeader( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
			$this->setHeader( 'Cache-Control', 'no-cache, must-revalidate, max-age=0' );
			$this->setHeader( 'Pragma', 'no-cache' );
			
			if ( isset($this->headers['Last-Modified']) )
				unset( $this->headers['Last-Modified'] );
		}
	}
		
}