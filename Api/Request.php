<?php
class Api_Request {
	
	public $request_method;
	
	public $request_uri;
	
	public $query_string;
	
	public $headers = array();
	
	public $params = array();
	
	public $callback; // jsonp callback
	
	public function __construct(){
		
		$request_uri = $_SERVER['REQUEST_URI'];
		$query_string =  $_SERVER['QUERY_STRING'];
		
		if ( !empty($query_string) )
			$request_uri = str_replace('?' . $query_string, '', $request_uri);
		
		$this->request_method = strtoupper($_SERVER['REQUEST_METHOD']);
		$this->request_uri = $this->filterUriComponent($request_uri);
		$this->query_string = $this->filterUriComponent($query_string);
		
		$this->headers = get_request_headers();
		
		switch($this->request_method){
			case 'GET':
				$this->params = $_GET;
				break;
			case 'POST':
				$this->params = $_POST;
				break;
			case 'PUT':
				parse_str( file_get_contents('php://input'), $this->params );
				break;
		}
		
		// allow params to override request method
		if ( isset($this->params['_method']) ){
			$this->request_method = strtoupper($this->params['_method']);
		}
		
	}
	
	/**
	* Set up matched query
	*/
	public function init( $matches ){
		
		$this->import( $matches );
		
		$this->request_uri = $this->matchSetContentType( $this->request_uri );
				
		if ( !empty($this->query_string) ){
			parse_str( $this->query_string, $query );
			$this->import($query);
		}
		
		if ( isset($this->extra) ){
			$this->parseStringToParams($this->extra);
		}
	}
	
	/**
	* Import array of data as object properties
	*/
	public function import( array $vars, $as_args = false ){
		
		foreach($vars as $var => $val){
			
			$var = str_replace('amp;', '', $var); // TODO: encoding issue, probably parse_str
			
			$val = wp_filter_kses( $this->matchSetContentType($val) );
				
			$this->set( $var, $val );
		}
	}
	
	/**
	* Set a property or parameter
	*/
	public function set($var, $val){
		if ( empty($var) || is_numeric($var) )
			$this->setParam(null, $val);
		else 
			$this->$var = $val;
		return $this;
	}
	
	/**
	* Set a parameter
	*/
	public function setParam( $var, $val ){
		if ( empty($var) || is_numeric($var) )
			$this->params[] = $val;
		else 
			$this->params[$var] = $val;
	}
	
	/**
	* Set an array of data as parameters
	*/
	public function setParams( array $args ){
		foreach($args as $k => $v){
			$this->setParam($k, $v);
		}
		return $this;	
	}
		
	/**
	* Returns property or parameter value if exists
	*/
	public function get($var){
		if (isset($this->$var))
			return $this->$var;
		elseif (isset($this->params[$var]))
			return $this->params[$var];
		return null;	
	}
	
	/**
	* Returns a parameter value
	*/
	public function getParam( $name ){
		return isset($this->params[$name]) ? $this->params[$name] : null;
	}
	
	/**
	* Returns array of matched query var keys and values
	*/
	public function getQueryVars(){
		global $api;
		$qv = array();
		foreach($api->get_matches('keys') as $key){
			$qv[$key] =& $this->$key;	
		}
		return $qv;
	}
	
	/**
	* Returns array of parsed headers
	*/
	public function getHeaders(){
		return $this->headers;	
	}
	
	/**
	* Dynamic get_*() mapping
	*/
	function __call($func, $args){
		
		if (0 === strpos($func, 'get_')){
			
			return $this->get( substr($func, 4) );
		}
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
		
		if ( $this->matchPathEnding($string, implode('|', $api->get_content_types()), $match, $sep) ){
			$api->set_content_type($match);
			$string = str_replace($sep . $match, '', $string);
		}
		return $string;
	}
	
	protected function matchPathEnding( $path, $endings = 'html|json', &$match = '', &$separator = ''){
		// match "something.xml" or "something/xml"
		if ( preg_match("/[\.|\/]($endings)/", $path, $matches) && isset($matches[1]) ){
			$separator = str_replace($matches[1], '', $matches[0]);
			return $match = $matches[1];
		}
		return false;
	}
	
	/**
	* Parses string into arguments - accepts paths
	*/
	protected function parseStringToParams( $path ){
		
		if ( false !== strpos($path, '/') )
			$args = explode('/', $path);
		else
			$args = array($path);
			
		array_walk($args, array($this, 'matchSetContentType'));
		
		$this->setParams( $args );
		
		unset( $path );	
	}
	
	/**
	* strips naughty text from uri components
	*/
	protected function filterUriComponent( $str ){
		return trim( wp_filter_nohtml_kses($str), '/' );	
	}
	
}