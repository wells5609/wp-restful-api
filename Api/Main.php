<?php

class Api_Main {
	
	public $request;
	
	public $response;
	
	public $router;
	
	public $is_api_request = false;
	
	public $controller;
	
	public $method;
	
	public $query_vars;
	
	protected $controllers = array();
	
	protected $content_types = array('html', 'xml', 'json', 'jsonp');
	
	static protected $_controller;
	
	static protected $_instance;
	
	static function instance(){
		if ( !isset(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	protected function __construct(){
		
		$this->request	= new Api_Request();
		$this->response = new Api_Response();
		$this->router	= new Api_Router();
		
		if ( api_config('auth.enable') ){
			$this->auth = new Api_Authorization();
			api_config('auth.send_header', true);
		}
		
		if ( api_config('ajax.enable') ){
			$this->is_ajax = false;	
		}
		
		add_filter( 'do_parse_request', array($this, 'route'), 0, 3 );
	}
	
	
	/** 
	* @filter do_parse_request
	*
	* Matches routes, calls $api->respond() if match.
	* 
	* If no match but is API request, return error.
	* Otherwise, must return true to continue loading WordPress
	*/
	function route( $load_wp, $wp, $extra_query_vars ) {
		
		do_action('api/init');
		
		$this->controllersInit();
		
		if ( $this->router->matchRoute() ){
			
			$this->request->init( $this->query_vars );
			
			$this->respond();
		
		}
		else if ( $this->is_api_request ){
			
			vardump( $this );
		
			$this->set_content_type('json');
			$this->error( 'Unknown route' );	
		}
		
		return $load_wp;
	}
	

	/** ==================================
				Public methods 
	=================================== */
	
	// Send error
	public function error( $message = 'Error', $response_type = 'not-found' ){
		die( $this->getResponse(null, $message, $response_type) );
	}
	
	public function set_controller( $controller ){
		$this->controller = $controller;	
	}
	
	public function set_method( $method ){
		$this->method = $method;
	}
	
	public function set_callback( $callback ){
		$this->callback = $callback;	
	}
	
	public function set_query_vars( $vars ){
		$this->query_vars = $vars;	
	}
	
	public function get_callback_class(){
		// $this->controller is the string found after APIBASE in the matched route, while
		// $this->callback[0] is the actual class string as defined by the route callback.
		$class = isset($this->controller) ? $this->controller : $this->callback[0];
		if ( '_ApiController' !== substr($class, -14) ){
			$class = ucfirst($class) . '_ApiController';
		}
		return $class;
	}
	
	public function get_callback_method(){
		return isset($this->callback[1]) ? $this->callback[1] : $this->method;
	}
	
	public function get_controller_instance(){
		if ( !isset(self::$_controller) ){
			self::$_controller = call_user_func( array($this->get_callback_class(), 'instance') );
		}
		return self::$_controller;		
	}
	
	public function register_controller( $name ){
		$this->controllers[$name] = $name;	
	}
	
	public function is_ajax(){
		return isset($this->is_ajax) ? $this->is_ajax : false;	
	}
	
	public function add_query_var( $name, $regex ){
		return $this->router->addQueryVar($name, $regex);	
	}
	
	public function add_route_group( $controller, array $routes, $priority = 5, $position = 'top' ){
		return $this->router->addRouteGroup($controller, $routes, $priority, $position);
	}
	
	public function add_route_to_group( $controller, $route, $priority = 5, $position = 'top' ){
		return $this->route->addRouteToGroup($controller, $route, $priority, $position);	
	}
	
	/** ======== Request ======== */
		
	public function get_request_method(){
		return $this->request->request_method;	
	}
	
	public function get_request_uri(){
		return $this->request->request_uri;	
	}
	
	public function get_request_headers(){
		return $this->request->getHeaders();	
	}
	
	public function get_query_string(){
		return $this->request->query_string;	
	}
	
	public function get_query_var( $var ){
		return $this->request->get( $var );	
	}
	
	public function get_param( $var ){
		return $this->request->get( $var );	
	}
	
	public function get_jsonp_callback(){
		return isset($this->request->callback) ? $this->request->callback : null;	
	}
	
	public function get_ajax_action(){
		return isset($this->request->action) ? $this->request->action : null;	
	}
	
	// Returns array of matched query var keys and values
	public function get_query_vars(){
		$qv = array();
		foreach($this->get_matches('keys') as $key){
			$qv[$key] =& $this->request->$key;	
		}
		return $qv;
	}
	
	public function get_matches( $kv = null ){
		if ('keys' === $kv) return $this->router->matches['keys'];
		elseif ('values' === $kv) return $this->router->matches['values'];
		return $this->router->matches;	
	}
		
	/** ======== Response ======== */
	
	public function set_status($status){
		return $this->response->setStatus($status);
	}
	
	public function set_content_type( $type ){
		return $this->is_content_type($type) ? $this->response->setContentType($type) : false;
	}
	
	public function is_content_type( $type ){
		return in_array($type, $this->content_types) ? true : false;	
	}
	
	public function is_content_type_set(){
		return isset($this->response->content_type) ? true : false;	
	}
	
	public function content_type_is( $type ){
		return ($this->is_content_type_set() && $type === $this->get_content_type()) ? true : false;	
	}
	
	public function get_content_type(){
		return $this->response->content_type;	
	}
	
	public function get_content_types(){
		return $this->content_types;	
	}
	
	public function set_cache( $length ){
		return $this->response->setCache($length);	
	}
	
	public function send_headers(){
		return $this->response->sendHeaders();	
	}
	
	public function is_json(){
		if ( !$this->is_content_type_set() || $this->content_type_is('json') || $this->content_type_is('jsonp') )
			return true;
		return false;
	}
	
	// Formats response as JSON
	public function to_json( $response, $callback = null ){
		$json = json_encode( array('response' => $response), JSON_FORCE_OBJECT );
		if ( $cb = $this->get_jsonp_callback() )
			$callback = $cb;
		if ( !empty($callback) )
			$json = $callback . '(' . $json . ')';
		return $json;
	}
	
	public function is_xml(){
		return $this->content_type_is('xml');
	}
	
	// Formats response as XML
	public function to_xml( $response ){
		if (!is_array($response)) $response = (array) $response;	
		return xml_document( $response, 'response' );
	}
	
	/** ======== Auth ======== */
	
	public function is_authorized(){
		return (isset($this->auth) && $this->auth->is_authorized) ? true : false;	
	}
	
	public function get_auth_method(){
		return $this->is_authorized() ? $this->auth->auth_method : null;	
	}
	
	// Initiates request authorization
	public function authorize_request( &$controller = null ){
		if (isset($this->auth)) $this->auth->doAuth($controller);
	}
	
	
	/** ============ Callback routing ============ */
	
	// Callback for API requests (called in Router)
	function respond(){
		
		ini_set('html_errors', 0);
			
		$this->resolveCallback();
		
		if ( !is_callable( $this->callback ) )
			$this->error('Unknown method');
		
		$controller = $this->get_controller_instance();
		
		$this->authorize_request( $controller );
		
		$method = $this->get_callback_method();
		
		// Controller methods can use '$this'	
		$results = $controller->$method( $this->get_query_vars() );
		
		if ( !api_config('compression.enable') || !ob_start('ob_gzhandler') ){
			ob_start();
		}
		
		echo $this->getResponse($results);
		
		ob_end_flush();
		
		exit;
	}
		
	
	/** ============ Protected Methods ============ */
	
	// Sets up the request response
	protected function getResponse( $results = null, $message = null, $response_type = null){
		
		$this->response->setResults($results);
		$this->response->setMessage($message);
		$this->response->setStatus($response_type);
		
		$response = $this->response->build();
		
		if ( $this->is_json() )
			$response = $this->to_json($response);
		elseif ( $this->is_xml() )
			$response = $this->to_xml($response);
		
		return $response;
	}
	
	/**
	* Maps controller name to class if not done so in callback definition.
	*
	* For example: 'GET:some_route' => array('bikes') 
	* resolves to: Bikes_ApiController::some_route()
	*/
	protected function resolveCallback( ){
		
		// callbacks should always be an array. strings are not accepted
		if ( !is_array($this->callback) ){
			$callback = $this->callback;
		}
		else {
			$callback = array( $this->get_callback_class(), $this->get_callback_method() );
		}
		
		return $this->callback = apply_filters( "api/resolveCallback", $callback );
	}
	
	// Adds registered Controller routes to Router	
	protected function controllersInit(){
		if ( !empty($this->controllers) ){
			foreach($this->controllers as $controller){
				if ( '_ApiController' !== substr($controller, -14) ){
					$controller = ucfirst($controller) . '_ApiController';
				}
				call_user_func( array($controller, 'register_routes') );
			}
		}	
	}
	
}
