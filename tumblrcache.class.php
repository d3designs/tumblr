<?php
/**
 * File: api-tumblrCache
 * 	Handle the Tumblr API and Cache the results to files.
 *
 * Version:
 * 	2009.12.20
 *
 * Copyright:
 * 	2009 Jay Williams
 *
 * License:
 * 	Simplified BSD License - http://opensource.org/licenses/bsd-license.php
 */

class TumblrCache extends Tumblr
{
	
	/**
	 * Property: cache_mode
	 * 	Whether caching is enabled on the request or not
	 */
	var $cache_mode;
	
	/**
	 * Property: cache_ttl
	 * 	Length of time, in seconds, the cache will be considered valid
	 */
	var $cache_ttl;
	
	/**
	 * Property: cache_path
	 * 	Directory to store the cache files
	 */
	var $cache_path;

	/**
	 * Property: header_mode
	 * 	Whether header response will be include in the output
	 */
	var $header_mode;


	/*%******************************************************************************************%*/
	// CONSTRUCTOR

	/**
	 * Method: __construct()
	 * 	The constructor.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	hostname - _string_ (Optional) Your Tumblr Blog hostname. If blank, it will look for the <TUMBLR_HOSTNAME> constant.
	 * 	email - _string_ (Optional) Your Tumblr login e-mail address. If blank, it will look for the <TUMBLR_EMAIL> constant.
	 * 	password - _string_ (Optional) Your Tumblr login password. If blank, it will look for the <TUMBLR_PASSWORD> constant.
	 * 	subclass - _array_ (Optional) Don't use this. This is an internal parameter.
	 *
	 * Returns:
	 * 	boolean FALSE if no valid values are set, otherwise true.
	 */
	public function __construct($hostname = null, $email = null, $password = null, $subclass = null)
	{
		// Set default values
		$this->cache_mode  = false;
		$this->cache_ttl   = 3600;
		$this->cache_path  = './cache/';
		$this->header_mode = false;
		
		return parent::__construct($hostname, $email, $password, $subclass);
	}


	/*%******************************************************************************************%*/
	// SETTERS

	/**
	 * Method: cache_mode()
	 * 	Enables request file caching.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	enabled - _boolean_ (Optional) Whether cache is enabled or not.
	 * 	ttl - _integer_ (Optional) Length of time, in seconds, the cache will be considered valid
	 * 	path - _string_ (Optional) Directory to store the cache files
	 *
	 * Returns:
	 * 	void
	 */
	public function cache_mode($enabled = true, $ttl = null, $path = null)
	{
		
		// Set default values
		$this->cache_mode = $enabled;
		
		if ($ttl != null)
			$this->cache_ttl = $ttl;
		
		if ($path != null)
			$this->cache_path = $path;
		
		// Run cache directory checks
		if ($enabled && (!is_dir($this->cache_path) || !is_writable($this->cache_path)))
			throw new Tumblr_Exception('Cache directory doesn\'t exist or isn\'t writeable');
	}

	/**
	 * Method: header_mode()
	 * 	Enables header mode within the API. Enabling header mode will include the request header in addition to the body.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	enabled - _boolean_ (Optional) Whether header mode is enabled or not.
	 *
	 * Returns:
	 * 	void
	 */
	public function header_mode($enabled = true)
	{
		// Set default values
		$this->header_mode = $enabled;
	}


	/*%******************************************************************************************%*/
	// MAGIC METHODS

	/**
	 * Handle requests to properties
	 */
	public function __get($var)
	{
		// Determine the name of this class
		$class_name = get_class($this);
		
		$subclass = (array) $this->subclass;
		$subclass[] = strtolower($var);

		// Re-instantiate this class, passing in the subclass value
		$ref = new $class_name($this->hostname, $this->email, $this->password, $subclass);
		$ref->test_mode($this->test_mode); // Make sure this gets passed through.
		$ref->default_output($this->default_output); // Make sure this gets passed through.
		$ref->cache_mode($this->cache_mode, $this->cache_ttl, $this->cache_path); // Make sure this gets passed through.
		$ref->header_mode($this->header_mode); // Make sure this gets passed through.

		return $ref;
	}

	/**
	 * Handle requests to methods
	 */
	// public function __call($name, $args)
	// {
	// 	$this->output = 'json';
	// 	
	// 	return parent::__call($name, $args);
	// }


	/*%******************************************************************************************%*/
	// REQUEST/RESPONSE

	/**
	 * Method: request()
	 * 	Requests the data, parses it, and returns it. Requires RequestCore and SimpleXML.
	 *
	 * Parameters:
	 * 	url - _string_ (Required) The web service URL to request.
	 * 	body - _string_ (Optional) Any form values to include with a POST request.
	 * 	method - _string_ (Optional) The method used to submit the request, defaults to GET.
	 *
	 * Returns:
	 * 	ResponseCore object
	 */
	public function request($url, $body = null, $method = null)
	{
		if ($this->test_mode)
			return array('url' => $url, 'body' => $body, 'method' => $method);
		
		// Generate cache filename
		$cache = $this->cache_path . get_class() . '_' . md5($url.$body) . '.cache';
		
		// If cache exists, and is still valid, load it
		if($this->cache_mode && file_exists($cache) && (time() - filemtime($cache)) < $this->cache_ttl)
		{
			$response = json_decode(file_get_contents($cache));
			
			// Add notice that this is a cached file
			// if (is_object($response))
			// 	$response->_cached = true;
			// elseif (is_array($response))
			// 	$response['_cached'] = true;
			
			return $response;
		}
		
		if (!class_exists('RequestCore'))
			throw new Exception('This class requires RequestCore. http://requestcore.googlecode.com');
		
		$http = new RequestCore($url);
		$http->set_useragent(TUMBLR_USERAGENT);
		$http->send_request();
		
		$response = $this->parse_response($http->get_response_body());

		if ($this->header_mode)
		{
			if (is_object($response))
				$response->_header = $http->get_response_header();
			elseif (is_array($response))
				$response['_header'] = $http->get_response_header();
		}
		
		// Cache only successfuly requests, check if http code begins with 2 (eg. 200,201,etc.)
		if ($this->cache_mode && substr($http->get_response_code(),0,1) == '2')
		{
			file_put_contents($cache . '_tmp', json_encode($response));
			rename($cache . '_tmp', $cache);
		}
		
		return $response;
	}

}
