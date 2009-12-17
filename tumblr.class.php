<?php
/**
 * File: api-tumblr
 * 	Handle the Tumblr API.
 *
 * Version:
 * 	2009.12.14
 *
 * Copyright:
 * 	2009 Jay Williams
 *
 * License:
 * 	Simplified BSD License - http://opensource.org/licenses/bsd-license.php
 */


/*%******************************************************************************************%*/
// CORE DEPENDENCIES

// Include the config file
if (file_exists(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.inc.php'))
{
	include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.inc.php';
}

/*%******************************************************************************************%*/
// EXCEPTIONS

class Tumblr_Exception extends Exception {}


/*%******************************************************************************************%*/
// CONSTANTS

/**
 * Constant: TUMBLR_NAME
 * 	Name of the software.
 */
define('TUMBLR_NAME', 'api-tumblr');

/**
 * Constant: TUMBLR_VERSION
 * 	Version of the software.
 */
define('TUMBLR_VERSION', '1.0');

/**
 * Constant: TUMBLR_BUILD
 * 	Build ID of the software.
 */
define('TUMBLR_BUILD', gmdate('YmdHis', strtotime(substr('$Date$', 7, 25)) ? strtotime(substr('$Date$', 7, 25)) : filemtime(__FILE__)));

/**
 * Constant: TUMBLR_URL
 * 	URL to learn more about the software.
 */
define('TUMBLR_URL', 'http://github.com/jaywilliams/tumblr/');

/**
 * Constant: TUMBLR_USERAGENT
 * 	User agent string used to identify the software
 */
define('TUMBLR_USERAGENT', TUMBLR_NAME . '/' . TUMBLR_VERSION . ' (Tumblr Toolkit; ' . TUMBLR_URL . ') Build/' . TUMBLR_BUILD);


/*%******************************************************************************************%*/
// CLASS

/**
 * Class: Tumblr
 */
class Tumblr
{
	/**
	 * Property: email
	 * 	The Tumblr email. This is inherited by all service-specific classes.
	 */
	private $email;

	/**
	 * Property: secret_key
	 * 	The Tumblr password. This is inherited by all service-specific classes.
	 */
	private $password;
	/**
	 * Property: subclass
	 * 	The API subclass (e.g. album, artist, user) to point the request to.
	 */
	var $subclass;

	/**
	 * Property: output
	 * 	The output format (e.g. XML, JSON, PHP)
	 */
	var $output;

	/**
	 * Property: test_mode
	 * 	Whether we're in test mode or not.
	 */
	var $test_mode;

	/**
	 * Property: auth_mode
	 * 	Whether we should include the email/password and send the request as a POST.
	 */
	var $auth_mode;

	/**
	 * Property: set_hostname
	 * 	Stores the hostname to use. This is inherited by all service-specific classes.
	 */
	var $hostname = null;

	/**
	 * Property: auth_hostname
	 * 	Stores the auth hostname to use. This is inherited by all service-specific classes.
	 */
	var $auth_hostname = null;


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
	 * 	key - _string_ (Optional) Your Tumblr API Key. If blank, it will look for the <AWS_KEY> constant.
	 * 	secret_key - _string_ (Optional) Your Tumblr API Secret Key. If blank, it will look for the <AWS_SECRET_KEY> constant.
	 * 	subclass - _string_ (Optional) Don't use this. This is an internal parameter.
	 *
	 * Returns:
	 * 	boolean FALSE if no valid values are set, otherwise true.
	 */
	public function __construct($email = null, $password = null, $hostname = null, $subclass = null)
	{
		// Set default values
		$this->email = null;
		$this->password = null;
		$this->hostname = null;
		$this->subclass = (array) $subclass;
		$this->auth_mode = null;
		$this->auth_hostname = 'www.tumblr.com';
		$this->auth_hostname = & $this->hostname;
		$this->output = 'xml';
		
		// If both a email and secret email are passed in, use those.
		if ($email && $password && $hostname)
		{
			$this->email = $email;
			$this->password = $password;
			$this->password = $hostname;
			return true;
		}
		// If neither are passed in, look for the constants instead.
		else if (defined('TUMBLR_EMAIL') && defined('TUMBLR_PASSWORD') && defined('TUMBLR_HOSTNAME'))
		{
			$this->email = TUMBLR_EMAIL;
			$this->password = TUMBLR_PASSWORD;
			$this->hostname = TUMBLR_HOSTNAME;
			return true;
		}

		// Otherwise set the values to blank and return false.
		else
		{
			throw new Tumblr_Exception('No valid credentials were used to authenticate with Tumblr.');
		}
		
		return true;
	}


	/*%******************************************************************************************%*/
	// SETTERS

	/**
	 * Method: set_hostname()
	 * 	Assigns a new hostname to use for an API-compatible web service.
	 *
	 * Parameters:
	 * 	hostname - _string_ (Required) The hostname to make requests to.
	 *
	 * Returns:
	 * 	void
	 */
	public function set_hostname($hostname)
	{
		$this->hostname = $hostname;
	}

	/**
	 * Method: test_mode()
	 * 	Enables test mode within the API. Enabling test mode will return the request URL instead of requesting it.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	enabled - _boolean_ (Optional) Whether test mode is enabled or not.
	 *
	 * Returns:
	 * 	void
	 */
	public function test_mode($enabled = true)
	{
		// Set default values
		$this->test_mode = $enabled;
	}

	/**
	 * Method: test_mode()
	 * 	Enables test mode within the API. Enabling test mode will return the request URL instead of requesting it.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	enabled - _boolean_ (Optional) Whether test mode is enabled or not.
	 *
	 * Returns:
	 * 	void
	 */
	public function auth_mode($enabled = true)
	{
		// Set default values
		$this->auth_mode = $enabled;
	}
	
	public function _login()
	{
		// Set default values
		$this->auth_mode = true;
		return $this;
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
		$ref = new $class_name($this->email, $this->password, $this->hostname, $subclass);
		$ref->test_mode($this->test_mode); // Make sure this gets passed through.
		$ref->auth_mode($this->auth_mode); // Make sure this gets passed through.

		return $ref;
	}

	/**
	 * Handle requests to methods
	 */
	public function __call($name, $args)
	{
	
		// Change the names of the methods to match what the API expects
		$name = strtolower($name);

		$path = (count($this->subclass) > 0)? implode('/',$this->subclass) . '/' : '';
		
		$output = ($this->output == 'xml')? '' : '/' . $this->output;
		
		$method = $name;
		
		$hostname = $this->hostname;
		
		if($this->auth_mode)
		{
			// Include default arguments
			$default_args = array('email' => $this->email, 'password' => $this->password);
			
			$args[0] = @array_merge($default_args, (array)$args[0]);
			
			$hostname = $this->auth_hostname;
		}
		
		// Construct the rest of the query parameters with what was passed to the method
		$args = ((count($args) > 0))? http_build_query($args[0], '', '&') : '';
		
		// Construct the URL to request
		$url = "http://{$hostname}/api/" . $path  . $method . $output;
		
		if(!$this->auth_mode)
		{
			$url = (!empty($args))? $url . '?' . $args : $url;
			$args = '';
		}
		
		// Return the value
		return $this->request($url,$args);
	}


	/*%******************************************************************************************%*/
	// REQUEST/RESPONSE

	/**
	 * Method: request()
	 * 	Requests the data, parses it, and returns it. Requires RequestCore and SimpleXML.
	 *
	 * Parameters:
	 * 	url - _string_ (Required) The web service URL to request.
	 *
	 * Returns:
	 * 	ResponseCore object
	 */
	public function request($url,$args=null)
	{
		if (!$this->test_mode)
		{
			if (class_exists('RequestCore'))
			{
				$http = new RequestCore($url);
				$http->set_useragent(TUMBLR_USERAGENT);
				
				if($this->auth_mode)
				{
					$http->set_method(HTTP_POST);
					$http->set_body($args);
				}
				
				$http->send_request();
				var_dump($http);
				$response = new stdClass();
				$response->header = $http->get_response_header();
				$response->status = $http->get_response_code();

				if ($response->status == 200)
				{
					$response->body = $this->parse_response($http->get_response_body());
				}
				else if ($response->status == 404)
				{
					$response->body = 'Not Found';
				}
				else 
				{
					$response->body = $http->get_response_body();
				}
				
				$this->auth_mode = false;
				
				return $response;
			}

			throw new Exception('This class requires RequestCore. http://github.com/skyzyx/requestcore.');
		}
		
		$this->auth_mode = false;

		return $url;
	}

	/**
	 * Method: parse_response()
	 * 	Default method for parsing the response data. You can extend the class and override this method for other response types.
	 *
	 * Parameters:
	 * 	data - _string_ (Required) The data to parse.
	 *
	 * Returns:
	 * 	mixed data
	 */
	public function parse_response($data)
	{
		return new SimpleXMLElement($data, LIBXML_NOCDATA);
		// return $data;
	}
}
