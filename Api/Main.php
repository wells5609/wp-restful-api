<?php

class Api_Main {
	
	public $query;
	
	public $response;
	
	public $router;
	
	static protected $_instance;
	
	static function instance(){
		if ( !isset(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	protected function __construct(){
		
		$this->query	= new Api_Query();
		$this->response = new Api_Response();
		$this->router	= new Api_Router();
		
		if ( API_AUTH_REQUESTS ){
			$this->auth = new Api_Authorization();
		}
		
		if ( API_USE_FOR_AJAX ){
			$this->is_ajax = false;	
		}	
	}
	
	/** ============ Public methods ============ */
	
	// Send error
	public function error( $message = 'Error', $response_type = 'not-found' ){
		
		die( $this->getResponse(false, $message, $response_type) );
	}
	
	// Sets response content type
	public function set_content_type( $type ){
		return $this->response->set_content_type($type);	
	}
	
	// Returns whether request content type is JSON
	public function is_json(){
		if ( !isset($this->response->content_type) || ('json' === $this->response->content_type || 'jsonp' === $this->response->content_type) )
			return true;
		return false;
	}
	
	// Formats response as JSON
	public function to_json( $response, $callback = null ){
		
		$json = json_encode( $response, JSON_FORCE_OBJECT );
		
		if ( isset($this->query->callback) )
			$callback = $this->query->callback;
		
		if ( !empty($callback) )
			$json = $callback . '(' . $json . ')';
		
		return $json;
	}
	
	// Returns whether request content type is XML
	public function is_xml(){
		if ( isset($this->response->content_type) && ('xml' === $this->response->content_type) )
			return true;
		return false;
	}
	
	// Formats response as XML
	public function to_xml( $response ){
		
		if ( !is_array($response) ) $response = (array) $response;
		
		return xml_start('1.0') . xml( $response );
	}
	
	/** ============ Callbacks ============ */
	
	// Callback for normal API requests (called in Router)
	function respond(){
		
		ini_set('html_errors', 0);
			
		if ( is_array($this->callback) && !isset($this->callback[1]) )
			$this->resolveCallback( $this->callback[0] );
		
		if ( !is_callable( $this->callback ) )
			$this->error('Unknown method');
		
		$controller = call_user_func( array($this->callback[0], 'instance') );
		
		$this->authorizeRequest($controller);
		
		// Controller methods can use $this	
		$results = $controller->{$this->callback[1]}( $this->query->get_query_vars() );
				
		die( $this->getResponse($results) );
	}
	
	// Callback for AJAX requests (called in Router)
	function ajax(){
		
		$_this = self::instance();
		$_this->is_ajax = true;
		
		define('DOING_AJAX', true);
		ini_set('html_errors', 0);
		
		$this->authorizeRequest();
				
		do_action('load_ajax_handlers');
		
		$_this->response->headers(200, false, true);
		
		if ( is_user_logged_in() )
			do_action( API_AJAX_ACTION_PREFIX . $_this->query->action, $_this->query->get_vars() );
		else
			do_action( API_AJAX_ACTION_PREFIX_NOPRIV . $_this->query->action, $_this->query->get_vars() );			
		
		wp_die( 'Your AJAX call does not exist.', API_AJAX_BASE );
		
		exit;	
	}
	
	/** ============ Protected Methods ============ */
	
	// Builds the request response
	protected function getResponse( $results = null, $message = null, $response_type = null, $cache = false ){
		
		$status_code = $this->getHeaderStatusCode($response_type);
		
		$response = $this->response->build($results, $message, $status_code, $cache);
		
		if ( $this->is_json() )
			$response = $this->to_json($response);
		elseif ( $this->is_xml() )
			$response = $this->to_xml($response);
		
		return $response;
	}
		
	// Initiates request authorization
	protected function authorizeRequest( &$controller = null ){
		
		if ( isset($this->auth) ) 
			$this->auth->doAuth($controller);
	}
	
	// Maps controller name if method is omitted from route callback definition
	protected function resolveCallback( $arg ){
		
		$controller = isset($this->query->controller) ? $this->query->controller : $arg;
		
		$this->callback = array( ucfirst($controller) . '_ApiController', $this->query->method );
	}
	
	// Returns HTTP status header code from pre-defined response types
	protected function getHeaderStatusCode( $response_type ){
		switch (strtolower($response_type)) {
			case 'ok':
			default:
				return 200;
			case 'found':
				return 302;
			case 'not-found':
			case 'error':
				return 400;
			case 'unauthorized':
			case 'auth-error':
				return 401;
			case 'forbidden':
				return 403;			
			case 'invalid-method':
				return 405;
		}	
	}
	
}
