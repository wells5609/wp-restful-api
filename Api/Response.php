<?php

class Api_Response {
	
	public $message;
	
	public $content_type;
	
	public $results;
	
	// Header: others
	public $headers = array();
	
	// Header: cache
	public $cache = false;
	
	public $response = array();
	
	public $options = array(
		'status' => null,
		'iframes' => 'nosniff',
		'cache' => false,
	);
	
		
	public function setContentType( $type, $force = false ){
		global $api;
		if ( $api->is_content_type($type) && ( !isset($this->content_type) || $force ) ){
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
	
	public function setHeader($name, $value){
		$this->headers[$name] = $value;	
	}
	
	public function setOption($name, $value){
		$this->options[$name] = $value;	
	}
	
	public function setIframes($value){
		$this->setOption('iframes', false === $value ? 'deny' : $value);	
	}
	
	// Sets HTTP status header code from pre-defined response types or actual code
	public function setStatus( $response_type ){
		
		if ( is_numeric($response_type) )
			return $this->setOption('status', $response_type);	
		
		switch (strtolower($response_type)) {
			case 'ok':
			default:
				return $this->setOption('status', 200);
			case 'found':
				return $this->setOption('status', 302);
			case 'not-found':
			case 'error':
				return $this->setOption('status', 400);
			case 'unauthorized':
			case 'auth-error':
				return $this->setOption('status', 401);
			case 'forbidden':
				return $this->setOption('status', 403);			
			case 'invalid-method':
				return $this->setOption('status', 405);
		}	
	}
	
	
	protected function parseHeaders(){
		global $api;
		
		$headers = $api->request->get_headers();
		
		if ( isset($headers['accept_encoding']) && (false !== strpos($headers['accept_encoding'], 'gzip')) ){
			$this->options['gzip'] = true;
		}
		
		if ( isset($headers['accept']) && !isset($this->content_type) ){
			
			$types = explode(',', $headers['accept']);
			
			foreach($types as $type){
				$content_type = substr($type, 0, strpos($type, '/'));
				if ( $api->is_content_type($content_type) )
					return $this->setContentType($content_type);
			}	
		}
		
	}
	
	public function build( $send_headers = true ){
	
		global $api;
		
		$this->parseHeaders();
		
		$status = empty($this->results) ? false : true;
		
		// message
		if ( !empty($this->message) )
			$this->response['message'] = $this->message;
		elseif ( !$status )
			$this->response['message'] = 'Error';
		
		// time/queries/memory
		if ( defined('WP_DEBUG') && WP_DEBUG ){
			$this->response['debug'] = array( 
				'time' => timer_stop(0, 3) . ' s',
				'queries' => get_num_queries(),
				'memory' => round(memory_get_peak_usage()/1024/1024, 3) . ' MB',
			);
		}
		
		// results
		if ( $status )
			$this->response['items'] = $this->results;
		
		if ( $send_headers )
			$this->sendHeaders();
				
		return $this->response;
	}
	
	
	public function sendHeaders(){
	
		global $api;
		
		// header status code
		if ( empty($this->options['status']) ){
			if ( !empty($this->results) )
				$this->setStatus(200);
			else
				$this->setStatus(400);
		}
		
		status_header( $this->options['status'] );
		
		if ( isset($this->content_type) )
			$content_type = api_content_type_mime($this->content_type);
		else
			$content_type = $this->getDefaultContentType();
		
		$this->setHeader('Content-Type', "{$content_type}; charset=utf-8");
		
		if ( true === $this->options['iframes'] ){
			$this->setHeader('X-Content-Type-Options', 'nosniff');
		}
		
		if ( isset($api->auth) && api_config('auth.send_header') ){
			
			if ( $api->is_authorized() )
				$auth_header = $api->get_auth_method();
			else 
				$auth_header = 'unauthorized';
			
			$this->setHeader('X-Api-Authorized', $auth_header);
		}
		
		$this->setCacheHeaders();
		
		foreach($this->headers as $header => $value){
			@header("{$header}: {$value}");	
		}
		
	}
	
	protected function getDefaultContentType(){
		global $api;
		if ( $api->is_ajax() )
			return api_content_type_mime( api_config('ajax.default_content_type') );
		return api_content_type_mime( api_config('default_content_type') );
	}
	
	protected function setCacheHeaders(){
		
		if ( !empty($this->cache) && 0 != $this->cache['expires_offset']) {
			
			$cache = array_merge(array('expires_offset' => 86400, 'cache_control' => 'Public'), $this->cache);
			
			$this->setHeader('Expires', gmdate("D, d M Y H:i:s", time() + $cache['expires_offset']) . ' GMT');
			$this->setHeader('Cache-Control', $cache['cache_control'] . ', max-age=' . $cache['expires_offset']);
		}
		else {
			
			$this->setHeader('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
			$this->setHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0');
			$this->setHeader('Pragma', 'no-cache');
			
			if ( isset($this->headers['Last-Modified']) )
				unset( $this->headers['Last-Modified'] );
		}
	}
		
}