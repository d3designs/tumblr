<?php
/**
 * File: api-tumblr
 * 	Handle the Tumblr API.
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
	 * Property: hostname
	 * 	The Tumblr blog hostname. This is inherited by all service-specific classes.
	 */
	var $hostname;

	/**
	 * Property: email
	 * 	The Tumblr login email address. This is inherited by all service-specific classes.
	 * 	Note: Only required for authenticated requests.
	 */
	private $email;

	/**
	 * Property: secret_key
	 * 	The Tumblr password. This is inherited by all service-specific classes.
	 * 	Note: Only required for authenticated requests.
	 */
	private $password;
	
	/**
	 * Property: subclass
	 * 	The API subclass to point the request to.
	 */
	var $subclass;

	/**
	 * Property: default_output
	 * 	The default output format (e.g. XML, JSON)
	 */
	var $default_output;

	/**
	 * Property: output
	 * 	The current output format (e.g. XML, JSON)
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
		$this->hostname = $hostname;
		$this->email    = $email;
		$this->password = $password;
		$this->subclass = (array) $subclass;
		$this->output         = null;
		$this->default_output = 'xml';

		if ($email && $password && $hostname)
			return true;

		// If neither are passed in, look for the constants instead.
		if (defined('TUMBLR_EMAIL') && defined('TUMBLR_PASSWORD') && defined('TUMBLR_HOSTNAME'))
		{
			$this->email    = (empty($this->email))? TUMBLR_EMAIL : $this->email;
			$this->password = (empty($this->password))? TUMBLR_PASSWORD : $this->password;
			$this->hostname = (empty($this->hostname))? TUMBLR_HOSTNAME : $this->hostname;
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
	 * Method: default_output()
	 * 	Sets the default output format for the API.
	 *
	 * Access:
	 * 	public
	 *
	 * Parameters:
	 * 	output - _string_ (Required) the default output mode. (e.g. xml, json)
	 *
	 * Returns:
	 * 	void
	 */
	public function default_output($output)
	{
		// Set default values
		$this->default_output = strtolower($output);
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

		return $ref;
	}

	/**
	 * Handle requests to methods
	 */
	public function __call($name, $args)
	{
	
		// Change the names of the methods to match what the API expects
		$name = strtolower($name);
		
		$args = (array) $args[0];

		$path = (count($this->subclass) > 0)? implode('/',$this->subclass) . '/' : '';
		
		$hostname = (isset($args['_hostname']))? $args['_hostname'] : $this->hostname;
		
		$this->output = (isset($args['_output']))? $args['_output'] : $this->default_output;
		
		$output = ($this->output == 'xml')? '' : '/' . $this->output;
		
		$login = (isset($args['_login']))? $args['_login'] : false;
		
		$method = ($login)? 'POST' : 'GET';
		
		$query = '';
		
		if($login)
		{
			// Include login credentials
			$login_credentials = array('email' => $this->email, 'password' => $this->password);
			$args = array_merge($login_credentials, $args);
		}
		
		// Remove private class arguments
		unset($args['_login'], $args['_hostname'], $args['_output']);
		
		// Construct the rest of the query parameters with what was passed to the method
		$args = http_build_query($args, '', '&');
		
		if ($this->output == 'json')
			$query = '?debug=1';
		
		if(!empty($args) && $method == 'GET')
		{
			$query = (empty($query))? '?' . $args : $query . '&' . $args;
			$args = '';
		}
		
		// Construct the URL to request
		$url = "http://{$hostname}/api/" . $path  . $name . $output . $query;

		
		// Return the value
		return $this->request($url,$args,$method);
	}


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

		if (!class_exists('RequestCore'))
			throw new Exception('This class requires RequestCore. http://github.com/skyzyx/requestcore.');

		$http = new RequestCore($url);
		
		$http->set_useragent(TUMBLR_USERAGENT);
		
		if (!empty($method))
		 	$http->set_method($method);

		if (!empty($body))
			$http->set_body($body);
		
		$http->send_request();
		
		$response = new stdClass();
		$response->header = $http->get_response_header();
		$response->status = $http->get_response_code();

		if ($response->status == 200)
			$response->body = $this->parse_response($http->get_response_body());
			
		else if ($response->status == 404)
			$response->body = 'Not Found';
			
		else 
			$response->body = $http->get_response_body();
		
		return $response;
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
		switch ($this->output) {
			case 'json':
				return json_decode($data);
				break;
			
			case 'xml':
			default:
				return new SimpleXMLElement($data, LIBXML_NOCDATA);
				break;
		}
	}
}
