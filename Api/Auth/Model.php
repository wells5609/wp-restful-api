<?php

class Api_Auth_Model extends Model {
	
	public $table_basename = 'api_auth';
	
	public $columns = array(
		'aid' 					=> "bigint(20) NOT NULL auto_increment",
		'apikey'				=> "varchar(39) NOT NULL",
		'day_limit'				=> "int(6) NOT NULL",
		'day_requests'			=> "int(6) default 0",
		'day_start_time'		=> "int(12) default 0",
		'requests'		 		=> "int(10) default 0",
		'email'					=> "varchar(64) NOT NULL",
		'domain'				=> "varchar(120) NOT NULL",
		'ip_address'			=> "varchar(16) NOT NULL",
		'time_registered'		=> "int(12) NOT NULL",
		'secret_key'			=> "varchar(32) NOT NULL",
		'user_id' 				=> "bigint(20) default 0",
	);
	
	public $primary_key = 'aid';
	
	public $unique_keys = array(
		'apikey'		=> 'apikey',
	);
	
	public $keys = array(
		'email'			=> 'email',
		'ip_address'	=> 'ip_address',
		'user_id'		=> 'user_id',
	);
	
	public $_object_class = 'Api_Auth_Object';
	
		
	function get_auth_object( $value, $get_by_column = 'apikey' ){
		
		if ( !$this->is_column($get_by_column) )
			return 'Cannot get auth object - invalid column ' . $get_by_column;
		if ( !$this->is_key($get_by_column) )
			return 'Cannot query database using non-indexed field ' . $get_by_column;
		
		if ( '%s' === $this->get_column_format($get_by_column) ){
			$where = $get_by_column . " LIKE '" . like_escape($value) . "'";
		}
		else {
			$where = $get_by_column . ' = ' . $value;
		}
		
		$result = $this->get_results( 
			"SELECT day_limit, day_requests, day_start_time, requests, email, secret_key 
			FROM {$this->table} 
			WHERE $where 
			LIMIT 1"
		);
				
		if ( empty($result) )
			return 'Invalid API key';
		
		$result = array_shift($result);
		
		return $result;	
	}
	
	function get_apikey_by( $column, $value ){
		
		$result = null;
		
		if ( !$this->is_column($column) )
			return 'Cannot get auth object - invalid column ' . $column;
		if ( !$this->is_key($column) )
			return 'Cannot query database using non-indexed field ' . $column;
		
		if ( '%s' === $this->get_column_format($column) ){
			$where = $column . " LIKE '" . like_escape($value) . "'";
		}
		else {
			$where = $column . ' = ' . $value;
		}
		
		$result = $this->get_results( 
			"SELECT apikey 
			FROM {$this->table} 
			WHERE $where 
			LIMIT 1"
		);
				
		if ( empty($result) )
			return 'No API key found';
		
		$result = array_shift($result);
		
		if ( $result )
			$result = $result->apikey;
		
		return $result;
	}
	
	function create_new_apikey( $email, $secret_key, $domain ){
		
		if ( !is_email($email) )
			return 'Invalid email address.';
		
		$secret_key = wp_filter_post_kses($secret_key);
		$domain = str_replace(array('http:','https:','//','www.'), '', wp_filter_kses($domain));
		
		if ( 8 >= strlen($secret_key) )
			return 'Secret key must be more than 8 characters.';
		
		$ip = $_SERVER['REMOTE_ADDR'];
		$user_id = is_user_logged_in() ? get_current_user_ID() : 0;	
		
		$apikey = api_create_apikey( $ip, $user_id, $email );
		
		switch ($user_id) {
			case 1:
				$limit = 5000;
				break;
			case ($user_id > 1):
				$limit = 1000;
				break;
			case 0:
			default:
				$limit = 500;
				break;
		}
		
		$this->insert( array( 
			'apikey'			=> $apikey,
			'day_limit'			=> $limit,
			'email'				=> $email,
			'domain'			=> $domain,
			'ip_address'		=> $ip,
			'time_registered'	=> time(),
			'secret_key' 		=> sha1( $secret_key ),
			'user_id'			=> $user_id,
		) );
		
		$sitename = get_bloginfo('name');
		wp_mail( $email, 'Your API key for ' . $sitename, "Hi,\n\n Your new API key for $sitename is <b>$apikey</b>.\n\n Be sure to save this email for future reference. \n" );
		
		return $apikey;
	}
	
	function touch( $apikey ){
		
		$result = $this->get_auth_object($apikey, 'apikey');
		
		if ( !is_object($result) )
			return 'Invalid API key';
		
		$update = array();
				
		if ( ($result->day_start_time + DAY_IN_SECONDS) >= time() ){
			
			if ( $result->day_requests >= $result->day_limit )
				return 'Daily request limit reached';	
			else
				$update['day_requests'] = $result->day_requests + 1;
		}
		else {
			$update['day_start_time'] = time();
			$update['day_requests'] = 1;
		}
		
		$update['requests'] = $result->requests + 1;
		
		$requests_remaining = $result->day_limit - $update['day_requests'];
		
		$timestamp_start = isset($update['day_start_time']) ? $update['day_start_time'] : $result->day_start_time;
		
		$requests_reset = ($timestamp_start + DAY_IN_SECONDS - time())/(60*60);
		
		$reset_hours = floor($requests_reset);
		$reset_mins = floor(($requests_reset - $reset_hours)*60);
		
		$this->update( $update, array('apikey' => $apikey) );
		
		if ( isset($GLOBALS['api']) && isset($GLOBALS['api']->response->response) ){
			$GLOBALS['api']->response->response['requests_remaining'] = $requests_remaining;
			$GLOBALS['api']->response->response['requests_reset'] = $reset_hours . ' hours, ' . $reset_mins . ' minutes';
		}
		
		return true;
	}
	
	function retrieve_forgotton_apikey( $email, $secret_key, $domain ){
		
		$result = $this->query_by('email', $email);
		
		if ( $secret_key === $result->secret_key && $domain === $result->domain ){
			return $result->apikey;
		}
		
		return 'Invalid secret key.';
	}
	
}