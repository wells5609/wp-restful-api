<?php
class Api_Query {
	
	public $request_method;
	
	public $request_uri;
	
	public $query_string;
	
	public $callback; // jsonp callback
	
	public $args = array();
	
	protected $read_headers = array();
	
	public function __construct(){
		
		$request_uri = $_SERVER['REQUEST_URI'];
		$query_string =  $_SERVER['QUERY_STRING'];
		
		if ( !empty($query_string) )
			$request_uri = str_replace('?' . $query_string, '', $request_uri);
		
		$this->set( 'request_method', strtoupper($_SERVER['REQUEST_METHOD']) );
		$this->set( 'request_uri', $this->filterUriComponent($request_uri) );
		$this->set( 'query_string', $this->filterUriComponent($query_string) );
		
		$components = explode('/', $this->request_uri);
		
		if ( APIBASE === $components[0] ){
			
			unset($components[0]);
			
			$this->set( 'controller', $components[1] );
			unset($components[1]); 
			
			$this->set( 'method', $components[2] );
			unset($components[2]);
			
			$this->readHeaders();
		}
	}
	
	protected function readHeaders(){
		
		
			
	}
	
	public function import( array $vars, $as_args = false ){
		global $api;
		
		foreach($vars as $var => $val){
			
			$var = str_replace('amp;', '', $var); // TODO: encoding issue, probably parse_str
			
			$val = wp_filter_kses( $this->matchSetContentType($val) );
				
			$this->set( $var, $val );
		}
	}
	
	public function init( $matches ){
		global $api;
		
		$this->import( $matches );
		
		$this->request_uri = $this->matchSetContentType( $this->request_uri );
				
		if ( !empty($this->query_string) ){
			parse_str( $this->query_string, $query );
			$this->import($query);
		}
		
		if ( isset($this->extra) ){
			$this->parseStringToArgs($this->extra);
		}
	}
	
	public function get_query_vars(){
		global $api;
		$qv = array();
		foreach($api->router->matches['keys'] as $key){
			$qv[$key] = $this->$key;	
		}
		return $qv;
	}
	
	public function get_vars( $output = ARRAY_A ){
		$vars = get_object_vars( $this );
		if ( OBJECT === $output )
			$vars = (object) $vars;
		else if ( ARRAY_N === $output )
			$vars = array_values( $vars );	
		return $vars;
	}
	
	public function set($var, $val){
		if ( empty($var) || is_numeric($var) )
			$this->setArg($val);
		else 
			$this->$var = $val;
		return $this;
	}
	
	public function setArgs( array $args ){
		foreach($args as $arg){
			$this->setArg( $this->matchSetContentType($arg) );
		}
		return $this;	
	}
	
	public function setArg( $val ){
		$this->args[] = $val;
	}
	
	public function get($var){
		return isset($this->$var) ? $this->$var : null;	
	}
	
	
	// Protected
	
	
	/**
	* Matches content type file extensions appended to strings
	* 
	* e.g: ../api/method/param.xml
	*	=> $content_type = 'xml' 
	*	=> $string = 'param'
	*/
	protected function matchSetContentType( $string ){
		global $api;
		
		if ( preg_match("/[\.](html|xml|jsonp|json)/", $string, $matches) && isset($matches[1]) ){
			
			$api->response->set_content_type($matches[1]);
			
			$string = str_replace('.'.$matches[1], '', $string);
		}
		else if ( $api->response->is_content_type($string) ){
			
			$api->response->set_content_type($string);
		}
		
		return $string;
	}
	
	/**
	* Parses string into arguments - accepts paths
	*/
	protected function parseStringToArgs( $path ){
		
		if ( false !== strpos($path, '/') )
			$args = explode('/', $path);
		else
			$args = array($path);
		
		$this->setArgs( $args );
		
		unset( $path );	
	}
	
	/**
	* strips naughty text from uri components
	*/
	protected function filterUriComponent( $str ){
		return trim( wp_filter_nohtml_kses($str), '/' );	
	}
	
}