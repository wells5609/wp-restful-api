<?php

class Api_Router {
	
	public $route_groups = array();
	
	public $matches = array();
	
	protected $request_methods = array(
		'GET', 'POST', 'DELETE', 'PUT',
	);
	
	protected $api_query_vars = array(
		'dir'		=> '([^_/][^/]+)',
		'path'		=> '(.+?)',
		'q'			=> '([\w\.*]+)',
		'id'		=> '(\d+)',
		's'			=> '(.?.+?)',
	);
		
	function __construct(){
	
		$this->routesInit();
	}
	
	/**
	* Adds query var and regex
	*
	* @param string $name The query var name
	* @param string $regex The var's regex, or another registered var name
	*/
	public function addQueryVar( $name, $regex ){
		$this->api_query_vars[ $name ] = $regex;
		return $this;	
	}
		
	/**
	* Adds a group of routes.
	*
	* Group can already exist in same or other grouping (priority).
	*
	* @param string $controller The lowercase controller name
	* @param array $routes Array of 'route => callback'
	* @param int $priority The group priority level
	* @param string $position The routes' position within the group, if exists already
	*/
	public function addRouteGroup( $controller, array $routes, $priority = 5, $position = 'top' ){
		
		$group = array( $controller => $routes );
		
		// priority does not exist, just set to group
		if ( !isset($this->route_groups[$priority]) || empty($this->route_groups[$priority]) ){
			$this->route_groups[$priority] = $group;	
		}
		// priority exists and controller already in priority
		elseif ( isset($this->route_groups[$priority][$controller]) ){
			if ( 'top' === $position ){
				$this->route_groups[$priority][$controller] = array_merge( $group[$controller], $this->route_groups[$priority][$controller] );
			}
			else {
				$this->route_groups[$priority][$controller] = array_merge( $this->route_groups[$priority][$controller], $group[$controller] );	
			}
		}
		else {
			// priority set but group not in it
			if ( 'top' === $position ){
				$this->route_groups[$priority] = array_merge( $group, $this->route_groups[$priority] );
			}
			else {
				$this->route_groups[$priority] = array_merge( $this->route_groups[$priority], $group );	
			}
		}
				
		return true;	
	}
	
	/**
	* Adds a route to a group
	*
	* @param string $controller The lowercase controller name (e.g. "bike" for "Bike_ApiController")
	* @param string $route The route path
	* @param int $priority The group priority level
	* @param string $position The route's position within the group
	*/
	public function addRouteToGroup( $controller, $route, $priority = 5, $position = 'top' ){
		return $this->addRouteGroup($controller, array($route), $priority, $position);	
	}
	
	/**
	* Adds an ajax route.
	*/
	public function addAjaxRoute( $action, $args = array('q' => 'q'), $priority = 2 ){
		
		$route = '';
		
		if ( is_string($args) ){
			$route .= $args;
		}
		else {
			foreach($args as $k => $v){
				if ( isset($this->api_query_vars[$v]) ){
					$route .= ':' . $v;			
				}
				if ($k !== $v && !is_numeric($k)){
					$route .= '(' . $k . ')';
				}
				$route .= '/';
			}
		}
		
		$route = rtrim($route, '/');
		
		$this->addRouteGroup( 'ajax', array($action . '/' . $route => $action), $priority);
	}
	
	/**
	* Returns whether controller is in a priority group
	*
	* @param int $priority The priority grouping in which to look
	* @param string $controller The controller name
	*/
	public function isControllerInGroup($priority, $controller){
		return !empty($this->route_groups[$priority][$controller]) ? true : false;	
	}
	
	/**
	* Matches request URI to a route.
	* Sets up Query if match and returns true
	*/
	public function matchRoute(){
		global $api;
		
		$uri = $api->get_request_uri();
		
		// Don't bother parsing if not an api request
		if (0 !== strpos($uri, APIBASE)) return false;
		
		$api->is_api_request = true;
		
		$components = explode('/', $uri);
		
		$controller = $components[1];
		
		$api->set_controller( $controller );
		$api->set_method( $components[2] );
		
		$uri = $api->get_request_method() . ':' . $uri; // prepend HTTP method 
		
		ksort($this->route_groups);
		
		foreach($this->route_groups as $group){
			
			if (!isset($group[$controller])) continue; // Controller not in group
			
			foreach($group[$controller] as $route => $callback){
		
				// Replace w/ regex and set matched query var keys
				$regex_route = $this->regexRoute($route);
				
				if ( preg_match('#^/?' . $regex_route . '/?$#', $uri, $this->matches['values']) ) {
					// remove full match
					unset($this->matches['values'][0]);
					
					$api->set_callback($callback);
					
					$api->_matched_route[$route] = $regex_route;
					
					$vars = array();
					
					if ( !empty($this->matches['keys']) && !empty($this->matches['values']) ){
						$api->set_query_vars( array_combine($this->matches['keys'], $this->matches['values']) );
					}
					
					return true;
				}
			}
		}
		
		return false;
	}
	
	/**
	* Sets initial route groups
	*/
	protected function routesInit(){
		
		$this->route_groups = array(
			
			2 => array(
				'post' => array(
					'get/:id'			=> array('Post_ApiController', 'get'),		// Post_ApiController::get( $id )
					'POST::id/:q'		=> array('Post_ApiController', 'update'),	// Post_ApiController::get( $id, $q )
					'GET::id'			=> array('Post_ApiController', 'get'),		// etc...
					'DELETE::id'		=> array('Post_ApiController', 'delete'),
				),
			),
			
			5 => array(
				'ajax' => array(
					':dir(action)/:q/:id'			=> array('Api_Main', 'ajax'), // Will pass $q and $id to action callback
					':dir(action)/:q/:s(extra)'		=> array('Api_Main', 'ajax'), // Will pass $q and $extra to action callback
					':dir(action)/:q'				=> array('Api_Main', 'ajax'),
					':dir(action)'					=> array('Api_Main', 'ajax'),
				),
			),
			
		);
				
		return true;	
	}
	
	/**
	* Takes route and converts query vars (e.g. ":id") to regex (e.g. "(\d+)")
	* Sets $matches['keys'] for use as query var keys if route match
	* @param string $route The route path string
	*/
	protected function regexRoute($route){
		global $api;
		
		// set $request_method to actual HTTP method - will be prepended to route if not set 
		$request_method = $api->request->request_method;
				
		foreach($this->request_methods as $method){
			
			if ( 0 === strpos($route, $method.':') ){
				// set $request_method to method defined in route
				$request_method = $method; 
				$route = str_replace($method.':', '', $route);
			}
		}
		
		$var_keys = array();
		
		// match query vars in route and replace with regex 
		foreach($this->api_query_vars as $var => $regex){
			
			// Match query vars with renamings - e.g. ".../:id(post_id)/..."
			if ( preg_match_all('/:(' . $var . '){1}\((\w+)\)?/', $route, $matches) && !empty($matches[2]) ){
				
				$translations = array_combine( $matches[2], $matches[1] );
				
				foreach($translations as $friendly_key => $regex_key){
					
					$route = str_replace(':'.$regex_key.'('.$friendly_key.')', $this->api_query_vars[$regex_key], $route);
					$var_keys[] = $friendly_key;
				}
			}
			
			// have non-(re)named var e.g. ".../:q/..."
			if ( strpos($route, ':' . $var) !== false ){
				$route = str_replace( ':' . $var, $regex, $route );
				$var_keys[] = $var;
			}
		}
		
		$this->matches['keys'] = $var_keys;
		
		// Append APIBASE and controller to $route to match complete uri and avoid false matches
		return $request_method . ':' . APIBASE . '/' . $api->controller . '/' . $route;
	}
		
}