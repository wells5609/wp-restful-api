<?php
class Api_Ajax_Callbacks {
	
	public $actions = array();
	
	static $_actioned = false;
	
	function __construct(){
		if ( !self::$_actioned )
			$this->add_actions();
	}
	
	function add_actions(){
		if (self::$_actioned) return;
		foreach($this->actions as $action => $private){
			add_action('wp_ajax_' . $action , array($this, $action));
			if ( $private ) 
				add_action('wp_ajax_nopriv_' . $action, array($this, 'unauthorized'));
			else 
				add_action('wp_ajax_nopriv_' . $action , array($this, $action));
		}	
	}
	
	function unauthorized(){
		die( alert('error', "Unauthorized action.") );	
	}

}