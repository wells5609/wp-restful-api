<?php

class Api_Router {
	
	public $matches = array();
	
	public $route_groups = array();
	
	protected $request_methods = array(
		'GET', 'POST', 'DELETE', #'PUT',
	);
	
	protected $api_query_vars = array(
		'dir'		=> '([^_/][^/]+)',
		'path'		=> '(.+?)',
		'q'			=> '([\w][\w\.*]+)',
		'id'		=> '(\d+)',
		's'			=> '(.?.+?)',
	);
	
	protected $_is_api_request = false;
	
	function __construct(){
		
		$this->routesInit();
		
		add_filter( 'do_parse_request', array($this, 'route'), 0, 3 );
	}
	
	/** @filter do_parse_request
	*
	* Must return true to continue loading WordPress
	*/
	function route( $load_wp, $wp, $extra_query_vars ) {
		
		global $api;
			
		do_action('api/routes/init');
		
		if ( $this->matchRoute() ){
			
			$api->respond();	
		}
		else if ( $this->_is_api_request ){
			
			$api->set_content_type('json');
			$api->error( 'Unknown route' );	
		}
		
		return $load_wp;
	}
	
	public function add_query_var( $name, $regex ){
		$this->api_query_vars[ $name ] = $regex;
		return $this;	
	}
	
	public function add_route_to_group( $controller, $route, $priority = 5, $position = 'top' ){
		return $this->add_route_group($controller, array($route), $priority, $position);	
	}
	
	public function add_route_group( $controller, array $routes, $priority = 5, $position = 'top' ){
		
		$group = array( $controller => $routes );
		
		if ( !isset($this->route_groups[$priority]) || empty($this->route_groups[$priority]) ){
			$this->route_groups[$priority] = $group;	
		}
		elseif ( isset($this->route_groups[$priority][$controller]) ){
			
			if ( 'top' === $position ){
				$this->route_groups[$priority][$controller] = array_merge( $group[$controller], $this->route_groups[$priority][$controller] );
			}
			else {
				$this->route_groups[$priority][$controller] = array_merge( $this->route_groups[$priority][$controller], $group[$controller] );	
			}
		}
		else {
			if ( 'top' === $position ){
				$this->route_groups[$priority] = array_merge( $group, $this->route_groups[$priority] );
			}
			else {
				$this->route_groups[$priority] = array_merge( $this->route_groups[$priority], $group );	
			}
		}
				
		return true;	
	}
	
	function isControllerInGroup($priority, $controller){
		return !empty($this->route_groups[$priority][$controller]) ? true : false;	
	}
	
	function matchRoute(){
		global $api;
		
		$uri = $api->query->request_uri;
		
		// Don't bother parsing if not an api request
		if ( 0 !== strpos($uri, APIBASE) ) return false;
		
		$this->_is_api_request = true;
		
		$controller = $api->query->controller; // set in Api_Query
		$uri = $api->query->request_method . ':' . $uri; // prepend HTTP method 
		
		ksort($this->route_groups); // sort route groups by priority
		
		foreach($this->route_groups as $group){
			
			if ( !isset($group[$controller]) )	continue; // Controller not in group
			
			foreach($group[$controller] as $route => $callback){
		
				// Replace w/ regex and set matched query var keys
				$regex_route = $this->regexRoute($route);
				
				if ( preg_match('#/?' . $regex_route . '/?#', $uri, $this->matches['values']) ) {
					unset($this->matches['values'][0]); // remove full match
					
					$api->callback = $callback;
					$api->_matched_route[$route] = $regex_route;
					
					$vars = array_combine($this->matches['keys'], $this->matches['values']);
					
					$api->query->init( $vars );
					
					#do_action( 'api/route/match', $uri );	
					
					return true;
				}
			}
		}
		
		return false;
	}
		
	protected function routesInit(){
		
		$this->route_groups = array(
			
			0 => array(),
			1 => array(),
			
			2 => array(
				'ajax' => array(
					':dir(action)/:q/:id'			=> array('Api_Main', 'ajax'),
					':dir(action)/:q/:s(extra)'		=> array('Api_Main', 'ajax'),
					':dir(action)/:q'				=> array('Api_Main', 'ajax'),
					':dir(action)'					=> array('Api_Main', 'ajax'),
				),
				'post' => array(
					'get/:id'			=> array('Post_ApiController', 'get'),
					'POST::id/:q'		=> array('Post_ApiController', 'update'),
					'GET::id'			=> array('Post_ApiController', 'get'),
					'DELETE::id'		=> array('Post_ApiController', 'delete'),
				),
			),
			
			3 => array(
				'company' => array(
					'POST:update/:id/:q/:s(extra)'	=> array('Company_ApiController', 'update_company'),
					'GET:get/:q'				=> array('Company_ApiController', 'get_company'),
					'get_company/:q'			=> array('Company_ApiController', 'get_company'),
					'get_company/:id'			=> array('Company_ApiController', 'get_company'),
				),
			),
			
		);
				
		return true;	
	}
	
	protected function regexRoute($route){
		global $api;
		$var_keys = array();
		$request_method = $api->query->request_method;
		
		foreach($this->request_methods as $method){
			
			if ( 0 === strpos($route, $method.':') ){
				$request_method = $method;
				$route = str_replace($method.':', '', $route);
			}
		}
		
		foreach($this->api_query_vars as $var => $regex){
			
			// Match query vars with renamings - e.g. :id(post_id)
			if ( preg_match_all('/(:{1}(' . $var . ')\((\w+)\)?)/', $route, $matches) && !empty($matches[3]) ){
				
				$translations = array_combine( $matches[3], $matches[2] );
				
				foreach($translations as $friendly_key => $regex_key){
					
					$route = str_replace(':'.$regex_key.'('.$friendly_key.')', $this->api_query_vars[$regex_key], $route);
					$var_keys[] = $friendly_key;
				}
			}
			
			// have non-(re)named var
			if ( strpos($route, ':' . $var) !== false ){
				$route = str_replace( ':' . $var, $regex, $route );
				$var_keys[] = $var;
			}
		}
		
		$this->matches['keys'] = $var_keys;
		
		// Append APIBASE and controller to $route so as to avoid false matches
		return $request_method . ':' . APIBASE . '/' . $api->query->controller . '/' . $route;
	}
		
}