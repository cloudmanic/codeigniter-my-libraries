<?php defined('BASEPATH') OR exit('No direct script access allowed');

//
// Restful Controller…..
// Orginal Author: Phil Sturgeon https://github.com/philsturgeon/codeigniter-restserver
// Jazzed up By: Spicer Matthews of Cloudmanic Labs http://cloudmanic.com
//
// Description: Extend your controller with this REST_Controller and have a full restful API.
//
// Suggestion: Add this to your config.php to autoload this controller. 
//							http://philsturgeon.co.uk/news/2010/02/CodeIgniter-Base-Classes-Keeping-it-DRY
//
//			function __autoload($class)
//			{
//				if(strpos($class, 'CI_') !== 0)
//				{
//					@include_once( APPPATH . 'core/'. $class . EXT );
//				}
//			}
//
//
// Changes: 
// 				 1) Add options for authenticating with a session key as well as an API. Key.
//
 
class REST_Controller extends CI_Controller 
{
	protected $rest_format = NULL; // Set this in a controller to use a default format
	protected $methods = array(); // contains a list of method properties such as limit, log and level
	protected $request = NULL; // Stores accept, language, body, headers, etc
	protected $response = NULL; // What is gonna happen in output?
	protected $rest = NULL; // Stores DB, keys, key level, etc
	protected $_get_args = array();
	protected $_post_args = array();
	protected $_put_args = array();
	protected $_delete_args = array();
	protected $_args = array();
	protected $_allow = TRUE;
	protected $_model = ''; // Set the model for this API request
	protected $_select_fields = array(); // Set the feilds that should be selected on a get call.
	protected $_custom_auth = ''; // Return value to any custom auth. 
	protected $_return = array('status' => 1, 'errors' => array(), 'data' => array()); // Default response object
	
	// List all supported methods, the first will be the default format
	protected $_supported_formats = array(
		'xml' => 'application/xml',
		'rawxml' => 'application/xml',
		'json' => 'application/json',
		'jsonp' => 'application/javascript',
		'serialize' => 'application/vnd.php.serialized',
		'php' => 'text/plain',
		'html' => 'text/html',
		'csv' => 'application/csv'
	);

	//
	// Constructor ….
	//
	public function __construct()
	{
		parent::__construct();

		// Lets grab the config and get ready to party
		$this->load->config('rest');

		// How is this request being made? POST, DELETE, GET, PUT?
		$this->request->method = $this->_detect_method();

		// Set up our GET variables
		$this->_get_args = array_merge($this->_get_args, $this->uri->ruri_to_assoc());

		$this->load->library('security');

		// This library is bundled with REST_Controller 2.5+, but will eventually be part of CodeIgniter itself
		$this->load->library('format');

		// Setup our model variables for our default CRUD operations.
		$this->_model_init();

		// Try to find a format for the request (means we have a request body)
		$this->request->format = $this->_detect_input_format();

		// Some Methods cant have a body
		$this->request->body = NULL;

		// Based on which method we are calling switch.
		switch($this->request->method)
		{
			case 'get':
				// Grab proper GET variables
				parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $get);

				// If there are any, populate $this->_get_args
				empty($get) OR $this->_get_args = $get;
			break;

			case 'post':
				$this->_post_args = $_POST;

				$this->request->format and $this->request->body = file_get_contents('php://input');
			break;

			case 'put':
				// It might be a HTTP body
				if($this->request->format)
				{
					$this->request->body = file_get_contents('php://input');
				}

				// If no file type is provided, this is probably just arguments
				else
				{
					parse_str(file_get_contents('php://input'), $this->_put_args);
				}		
			break;

			case 'delete':
				// Set up out DELETE variables (which shouldn't really exist, but sssh!)
				parse_str(file_get_contents('php://input'), $this->_delete_args);
			break;
		}

		// Now we know all about our request, let's try and parse the body if it exists
		if($this->request->format and $this->request->body)
		{
			$this->request->body = $this->format->factory($this->request->body, $this->request->format)->to_array();
		}

		// Merge both for one mega-args variable
		$this->_args = array_merge($this->_get_args, $this->_put_args, $this->_post_args, $this->_delete_args);

		// Which format should the data be returned in?
		$this->response->format = $this->_detect_output_format();

		// Which format should the data be returned in?
		$this->response->lang = $this->_detect_lang();

		// Check if there is a specific auth type for the current class/method
		$this->auth_override = $this->_auth_override_check();

		// When there is no specific override for the current class/method, use the default auth value set in the config
		if($this->auth_override !== TRUE)
		{
			// Setup custom authentication options.
			$custom = config_item('rest_enable_custom_auth');
			$otherauth = TRUE;
			if(isset($custom['otherauth']))
			{
				$otherauth = $custom['otherauth'];
			}
			
			// We check to see if we have any custom authentication first.
			if(isset($custom['library']) && (! empty($custom['library'])))
			{
				$this->load->library($custom['library']);
				
				if($this->_custom_auth = $this->{$custom['library']}->{$custom['method']}())
				{
					$this->_allow = TRUE;
				}
			}
			
			// Failed with custom auth? Try basic or digest
			if((! $this->_allow) && ($otherauth))
			{
				if($this->config->item('rest_auth') == 'basic')
				{
				  $this->_prepare_basic_auth();
				}
				else if($this->config->item('rest_auth') == 'digest')
				{
				  $this->_prepare_digest_auth();
				}
			}
		}

		// Load DB if its enabled
		if(config_item('rest_database_group') AND (config_item('rest_enable_keys') OR config_item('rest_enable_logging')))
		{
			$this->rest->db = $this->load->database(config_item('rest_database_group'), TRUE);
		}

		// Checking for keys? GET TO WORK!
		if(config_item('rest_enable_keys') && (! $this->_allow) && ($otherauth))
		{
			$this->_allow = $this->_detect_api_key();
		}

		// only allow ajax requests
		if(! $this->input->is_ajax_request() AND config_item('rest_ajax_only') )
		{
			$this->response( array('status' => false, 'error' => 'Only AJAX requests are accepted.'), 505 );
		}
	}
	 
	//
	// Remap: Requests are not made to methods directly The request will be for an "object".
	//				this simply maps the object and method to the correct Controller method.
	//
	public function _remap($object_called, $arguments)
	{
		$pattern = '/^(.*)\.(' . implode('|', array_keys($this->_supported_formats)) . ')$/';
		if(preg_match($pattern, $object_called, $matches))
		{
			$object_called = $matches[1];
		}

		$controller_method = $object_called . '_' . $this->request->method;

		// Do we want to log this method (if allowed by config)?
		$log_method = ! (isset($this->methods[$controller_method]['log']) AND $this->methods[$controller_method]['log'] == FALSE);

		// Use keys for this method?
		$use_key = ! (isset($this->methods[$controller_method]['key']) AND $this->methods[$controller_method]['key'] == FALSE);

		// Get that useless shitty key out of here
		if(config_item('rest_enable_keys') AND $use_key AND $this->_allow === FALSE)
		{
			$this->response(array('status' => false, 'error' => 'Invalid API Key.'), 403);
		}

		// Sure it exists, but can they do anything with it?
		if(! method_exists($this, $controller_method))
		{
			$this->response(array('status' => false, 'error' => 'Unknown method.'), 404);
		}

		// Doing key related stuff? Can only do it if they have a key right?
		if(config_item('rest_enable_keys') AND ! empty($this->rest->key))
		{
			// Check the limit
			if (config_item('rest_enable_limits') AND ! $this->_check_limit($controller_method))
			{
				$this->response(array('status' => false, 'error' => 'This API key has reached the hourly limit for this method.'), 401);
			}

			// If no level is set use 0, they probably aren't using permissions
			$level = isset($this->methods[$controller_method]['level']) ? $this->methods[$controller_method]['level'] : 0;

			// If no level is set, or it is lower than/equal to the key's level
			$authorized = $level <= $this->rest->level;

			// IM TELLIN!
			if (config_item('rest_enable_logging') AND $log_method)
			{
				$this->_log_request($authorized);
			}

			// They don't have good enough perms
			$authorized OR $this->response(array('status' => false, 'error' => 'This API key does not have enough permissions.'), 401);
		}

		// No key stuff, but record that stuff is happening
		else if(config_item('rest_enable_logging') AND $log_method)
		{
			$this->_log_request($authorized = TRUE);
		}

		// And...... GO!
		call_user_func_array(array($this, $controller_method), $arguments);
	}
	
	//
	// If we have a custom authentication ($config['rest_enable_custom_auth']) whatever
	// the method for custom authentication returns (often a user profile) this function
	// will return that data. 
	//
	public function get_custom_auth_return()
	{
		return $this->_custom_auth;
	}
	
	//
	// Response: Takes pure data and optionally a status code, then creates the response.
	//
	public function response($data = array(), $http_code = null)
	{
		// If data is empty and not code provide, error and bail
		if (empty($data) && $http_code === null)
    	{
    		$http_code = 404;
    	}

		// Otherwise (if no data but 200 provided) or some data, carry on camping!
		else
		{
			is_numeric($http_code) OR $http_code = 200;

			// If the format method exists, call and return the output in that format
			if (method_exists($this, '_format_'.$this->response->format))
			{
				// Set the correct format header
				header('Content-Type: '.$this->_supported_formats[$this->response->format]);

				$output = $this->{'_format_'.$this->response->format}($data);
			}

			// If the format method exists, call and return the output in that format
			elseif (method_exists($this->format, 'to_'.$this->response->format))
			{
				// Set the correct format header
				header('Content-Type: '.$this->_supported_formats[$this->response->format]);

				$output = $this->format->factory($data)->{'to_'.$this->response->format}();
			}

			// Format not supported, output directly
			else
			{
				$output = $data;
			}
		}

		header('HTTP/1.1: ' . $http_code);
		header('Status: ' . $http_code);
		header('Content-Length: ' . strlen($output));

		exit($output);
	}
	 
	//
	// Detect input format: Detect which format the HTTP Body is provided in
	//
	protected function _detect_input_format()
	{
		if ($this->input->server('CONTENT_TYPE'))
		{
			// Check all formats against the HTTP_ACCEPT header
			foreach ($this->_supported_formats as $format => $mime)
			{
				if (strpos($match = $this->input->server('CONTENT_TYPE'), ';'))
				{
					$match = current(explode(';', $match));
				}

				if ($match == $mime)
				{
					return $format;
				}
			}
		}

		return NULL;
	}

	/*
	 * Detect format
	 *
	 * Detect which format should be used to output the data
	 */
	protected function _detect_output_format()
	{
		$pattern = '/\.(' . implode('|', array_keys($this->_supported_formats)) . ')$/';

		// Check if a file extension is used
		if (preg_match($pattern, $this->uri->uri_string(), $matches))
		{
			return $matches[1];
		}
		
		// Check if a file extension is used
		elseif ($this->_get_args AND ! is_array(end($this->_get_args)) AND preg_match($pattern, end($this->_get_args), $matches))
		{
			// The key of the last argument
			$last_key = end(array_keys($this->_get_args));

			// Remove the extension from arguments too
			$this->_get_args[$last_key] = preg_replace($pattern, '', $this->_get_args[$last_key]);
			$this->_args[$last_key] = preg_replace($pattern, '', $this->_args[$last_key]);

			return $matches[1];
		}

		// A format has been passed as an argument in the URL and it is supported
		if (isset($this->_get_args['format']) AND array_key_exists($this->_get_args['format'], $this->_supported_formats))
		{
			return $this->_get_args['format'];
		}

		// Otherwise, check the HTTP_ACCEPT (if it exists and we are allowed)
		if ($this->config->item('rest_ignore_http_accept') === FALSE AND $this->input->server('HTTP_ACCEPT'))
		{
			// Check all formats against the HTTP_ACCEPT header
			foreach (array_keys($this->_supported_formats) as $format)
			{
				// Has this format been requested?
				if (strpos($this->input->server('HTTP_ACCEPT'), $format) !== FALSE)
				{
					// If not HTML or XML assume its right and send it on its way
					if ($format != 'html' AND $format != 'xml')
					{

						return $format;
					}

					// HTML or XML have shown up as a match
					else
					{
						// If it is truely HTML, it wont want any XML
						if ($format == 'html' AND strpos($this->input->server('HTTP_ACCEPT'), 'xml') === FALSE)
						{
							return $format;
						}

						// If it is truely XML, it wont want any HTML
						elseif ($format == 'xml' AND strpos($this->input->server('HTTP_ACCEPT'), 'html') === FALSE)
						{
							return $format;
						}
					}
				}
			}
		} // End HTTP_ACCEPT checking
		
		// Well, none of that has worked! Let's see if the controller has a default
		if ( ! empty($this->rest_format))
		{
			return $this->rest_format;
		}

		// Just use the default format
		return config_item('rest_default_format');
	}

	/*
	 * Detect method
	 *
	 * Detect which method (POST, PUT, GET, DELETE) is being used
	 */

	protected function _detect_method()
	{
		$method = strtolower($this->input->server('REQUEST_METHOD'));

		if ($this->config->item('enable_emulate_request') && $this->input->post('_method'))
		{
			$method =  $this->input->post('_method');
		}

		if (in_array($method, array('get', 'delete', 'post', 'put')))
		{
			return $method;
		}

		return 'get';
	}

	/*
	 * Detect API Key
	 *
	 * See if the user has provided an API key
	 */

	protected function _detect_api_key()
	{
		// Work out the name of the SERVER entry based on config
		$key_name = 'HTTP_' . strtoupper(str_replace('-', '_', config_item('rest_key_name')));

		$this->rest->key = NULL;
		$this->rest->level = NULL;
		$this->rest->ignore_limits = FALSE;

		// Find the key from server or arguments
		if ($key = isset($this->_args['API-Key']) ? $this->_args['API-Key'] : $this->input->server($key_name))
		{
			if ( ! $row = $this->rest->db->where('key', $key)->get(config_item('rest_keys_table'))->row())
			{
				return FALSE;
			}

			$this->rest->key = $row->key;
			
			isset($row->level) AND $this->rest->level = $row->level;
			isset($row->ignore_limits) AND $this->rest->ignore_limits = $row->ignore_limits;

			return TRUE;
		}

		// No key has been sent
		return FALSE;
	}

	/*
	 * Detect language(s)
	 *
	 * What language do they want it in?
	 */

	protected function _detect_lang()
	{
		if ( ! $lang = $this->input->server('HTTP_ACCEPT_LANGUAGE'))
		{
			return NULL;
		}

		// They might have sent a few, make it an array
		if (strpos($lang, ',') !== FALSE)
		{
			$langs = explode(',', $lang);

			$return_langs = array();
			$i = 1;
			foreach ($langs as $lang)
			{
				// Remove weight and strip space
				list($lang) = explode(';', $lang);
				$return_langs[] = trim($lang);
			}

			return $return_langs;
		}

		// Nope, just return the string
		return $lang;
	}

	/*
	 * Log request
	 *
	 * Record the entry for awesomeness purposes
	 */

	protected function _log_request($authorized = FALSE)
	{
		return $this->rest->db->insert(config_item('rest_logs_table'), array(
			'uri' => $this->uri->uri_string(),
			'method' => $this->request->method,
			'params' => serialize($this->_args),
			'api_key' => isset($this->rest->key) ? $this->rest->key : '',
			'ip_address' => $this->input->ip_address(),
			'time' => function_exists('now') ? now() : time(),
			'authorized' => $authorized
		));
	}

	/*
	 * Log request
	 *
	 * Record the entry for awesomeness purposes
	 */

	protected function _check_limit($controller_method)
	{
		// They are special, or it might not even have a limit
		if (!empty($this->rest->ignore_limits) OR !isset($this->methods[$controller_method]['limit']))
		{
			// On your way sonny-jim.
			return TRUE;
		}

		// How many times can you get to this method an hour?
		$limit = $this->methods[$controller_method]['limit'];

		// Get data on a keys usage
		$result = $this->rest->db
						->where('uri', $this->uri->uri_string())
						->where('api_key', $this->rest->key)
						->get(config_item('rest_limits_table'))
						->row();

		// No calls yet, or been an hour since they called
		if (!$result OR $result->hour_started < time() - (60 * 60))
		{
			// Right, set one up from scratch
			$this->rest->db->insert(config_item('rest_limits_table'), array(
				'uri' => $this->uri->uri_string(),
				'api_key' => isset($this->rest->key) ? $this->rest->key : '',
				'count' => 1,
				'hour_started' => time()
			));
		}

		// They have called within the hour, so lets update
		else
		{
			// Your luck is out, you've called too many times!
			if ($result->count > $limit)
			{
				return FALSE;
			}

			$this->rest->db
					->where('uri', $this->uri->uri_string())
					->where('api_key', $this->rest->key)
					->set('count', 'count + 1', FALSE)
					->update(config_item('rest_limits_table'));
		}

		return TRUE;
	}
	/*
	 * Auth override check
	 *
	 * Check if there is a specific auth type set for the current class/method being called
	 */

	protected function _auth_override_check()
	{

		// Assign the class/method auth type override array from the config
		$this->overrides_array = $this->config->item('auth_override_class_method');

		// Check to see if the override array is even populated, otherwise return false
		if ( empty($this->overrides_array) )
		{
			return false;
		}

		// Check to see if there's an override value set for the current class/method being called
		if ( empty($this->overrides_array[$this->router->class][$this->router->method]) )
		{
			return false;
		}

		// None auth override found, prepare nothing but send back a true override flag
		if ($this->overrides_array[$this->router->class][$this->router->method] == 'none')
		{
			return true;
		}

		// Basic auth override found, prepare basic
		if ($this->overrides_array[$this->router->class][$this->router->method] == 'basic')
		{
			$this->_prepare_basic_auth();
			return true;
		}

		// Digest auth override found, prepare digest
		if ($this->overrides_array[$this->router->class][$this->router->method] == 'digest')
		{
			$this->_prepare_digest_auth();
			return true;
		}

		// Return false when there is an override value set but it doesn't match 'basic', 'digest', or 'none'.  (the value was misspelled)
		return false;
	}

	// --------------- Add functions to support generic API calls (class that just dump the database) -------------- //
	
	//
	// Return all data from a particular model. 
	//
	function all_get()
	{		
		$this->query_init();
		$this->_return['data'] = $this->{$this->_model}->get();
		$this->response($this->_return, 200);
	}
	
	// 
	// Setup all the config variables that are needed for our default 
	// CRUD operations triggered off models. 
	//
	private function _model_init()
	{
		$config = config_item('rest_model_guess');
		
		if(isset($config['segment']) && isset($config['postfix']))
		{
			$this->_model = $this->uri->segment($config['segment']) . $config['postfix']; 
		}
	}
	
	//
	// Here we set the model that we are using for this API call.
	//
	function set_model($model)
	{
		$this->_model = $model;
	}
	
	// 
	// Set which fields we should be allowed to select.
	// Some times we might not want to give full API access
	// to all our fields. 
	//
	function set_select_fields($data)
	{
		if(is_array($data))
		{
			foreach($data AS $key => $row)
			{
				$this->_select_fields[] = $row;
			}
		} else 
		{
			$this->_select_fields = $data;
		}
	}
	
	//
	// Clear select feilds.
	//
	function clear_select_fields()
	{
		$this->_select_fields = array();
	}
	
	//
	// There are certain arguments we can pass in at the url that
	// we use to call model based functions. We have to have the model
	// set for this function to do anythging.
	//
	function query_init()
	{
		if(isset($this->_model) && (! empty($this->_model)))
		{
			// Load model
			$this->load->model($this->_model);
		
			// Grab data from model
			if($this->get('order'))
			{
				$order = explode(':', $this->get('order'));
				foreach($order AS $key => $row)
				{
					$this->{$this->_model}->set_order($row . ' ' . $this->get('sort'));
				}
			} 
			
			// Set limit & offset
			if($this->get('limit'))
			{
				if($this->get('offset'))
				{
					$this->{$this->_model}->set_limit($this->get('limit'), $this->get('offset'));
				} else 
				{
					$this->{$this->_model}->set_limit($this->get('limit'));				
				}
			}
		
			// Set select fields
			if($this->_select_fields)
			{
				// Set the fields we want to return.
				foreach($this->_select_fields AS $key => $row)
				{
					$this->{$this->_model}->set_select($row);	
				}
			}
		}
	}

	// ------------------ End Generic API Calls -------------------- //


	// INPUT FUNCTION --------------------------------------------------------------

	public function get($key = NULL, $xss_clean = TRUE)
	{
		if ($key === NULL)
		{
			return $this->_get_args;
		}

		return array_key_exists($key, $this->_get_args) ? $this->_xss_clean($this->_get_args[$key], $xss_clean) : FALSE;
	}

	public function post($key = NULL, $xss_clean = TRUE)
	{
		if ($key === NULL)
		{
			return $this->_post_args;
		}

		return $this->input->post($key, $xss_clean);
	}

	public function put($key = NULL, $xss_clean = TRUE)
	{
		if ($key === NULL)
		{
			return $this->_put_args;
		}

		return array_key_exists($key, $this->_put_args) ? $this->_xss_clean($this->_put_args[$key], $xss_clean) : FALSE;
	}

	public function delete($key = NULL, $xss_clean = TRUE)
	{
		if ($key === NULL)
		{
			return $this->_delete_args;
		}

		return array_key_exists($key, $this->_delete_args) ? $this->_xss_clean($this->_delete_args[$key], $xss_clean) : FALSE;
	}

	protected function _xss_clean($val, $bool)
	{
		if (CI_VERSION < 2)
		{
			return $bool ? $this->input->xss_clean($val) : $val;
		}
		else
		{
			return $bool ? $this->security->xss_clean($val) : $val;
		}
	}

	public function validation_errors()
	{
		$string = strip_tags($this->form_validation->error_string());

		return explode("\n", trim($string, "\n"));
	}

	// SECURITY FUNCTIONS ---------------------------------------------------------

	protected function _check_login($username = '', $password = NULL)
	{
		if (empty($username))
		{
			return FALSE;
		}

		$valid_logins = & $this->config->item('rest_valid_logins');

		if (!array_key_exists($username, $valid_logins))
		{
			return FALSE;
		}

		// If actually NULL (not empty string) then do not check it
		if ($password !== NULL AND $valid_logins[$username] != $password)
		{
			return FALSE;
		}

		return TRUE;
	}

	protected function _prepare_basic_auth()
	{
		$username = NULL;
		$password = NULL;

		// mod_php
		if ($this->input->server('PHP_AUTH_USER'))
		{
			$username = $this->input->server('PHP_AUTH_USER');
			$password = $this->input->server('PHP_AUTH_PW');
		}

		// most other servers
		elseif ($this->input->server('HTTP_AUTHENTICATION'))
		{
			if (strpos(strtolower($this->input->server('HTTP_AUTHENTICATION')), 'basic') === 0)
			{
				list($username, $password) = explode(':', base64_decode(substr($this->input->server('HTTP_AUTHORIZATION'), 6)));
			}
		}

		if (!$this->_check_login($username, $password))
		{
			$this->_force_login();
		}
	}

	protected function _prepare_digest_auth()
	{
		$uniqid = uniqid(""); // Empty argument for backward compatibility
		// We need to test which server authentication variable to use
		// because the PHP ISAPI module in IIS acts different from CGI
		if ($this->input->server('PHP_AUTH_DIGEST'))
		{
			$digest_string = $this->input->server('PHP_AUTH_DIGEST');
		}
		elseif ($this->input->server('HTTP_AUTHORIZATION'))
		{
			$digest_string = $this->input->server('HTTP_AUTHORIZATION');
		}
		else
		{
			$digest_string = "";
		}

		/* The $_SESSION['error_prompted'] variabile is used to ask
		  the password again if none given or if the user enters
		  a wrong auth. informations. */
		if (empty($digest_string))
		{
			$this->_force_login($uniqid);
		}

		// We need to retrieve authentication informations from the $auth_data variable
		preg_match_all('@(username|nonce|uri|nc|cnonce|qop|response)=[\'"]?([^\'",]+)@', $digest_string, $matches);
		$digest = array_combine($matches[1], $matches[2]);

		if (!array_key_exists('username', $digest) OR !$this->_check_login($digest['username']))
		{
			$this->_force_login($uniqid);
		}

		$valid_logins = & $this->config->item('rest_valid_logins');
		$valid_pass = $valid_logins[$digest['username']];

		// This is the valid response expected
		$A1 = md5($digest['username'] . ':' . $this->config->item('rest_realm') . ':' . $valid_pass);
		$A2 = md5(strtoupper($this->request->method) . ':' . $digest['uri']);
		$valid_response = md5($A1 . ':' . $digest['nonce'] . ':' . $digest['nc'] . ':' . $digest['cnonce'] . ':' . $digest['qop'] . ':' . $A2);

		if ($digest['response'] != $valid_response)
		{
			header('HTTP/1.0 401 Unauthorized');
			header('HTTP/1.1 401 Unauthorized');
			exit;
		}
	}

	protected function _force_login($nonce = '')
	{
		if ($this->config->item('rest_auth') == 'basic')
		{
			header('WWW-Authenticate: Basic realm="' . $this->config->item('rest_realm') . '"');
		}
		elseif ($this->config->item('rest_auth') == 'digest')
		{
			header('WWW-Authenticate: Digest realm="' . $this->config->item('rest_realm') . '" qop="auth" nonce="' . $nonce . '" opaque="' . md5($this->config->item('rest_realm')) . '"');
		}

		$this->response(array('status' => false, 'error' => 'Not authorized'), 401);
	}

	// Force it into an array
	protected function _force_loopable($data)
	{
		// Force it to be something useful
		if ( ! is_array($data) AND ! is_object($data))
		{
			$data = (array) $data;
		}

		return $data;
	}

	// FORMATING FUNCTIONS ---------------------------------------------------------

	// Many of these have been moved to the Format class for better separation, but these methods will be checked too
	
	// Encode as JSONP
	protected function _format_jsonp($data = array())
	{
		return $this->get('callback') . '(' . json_encode($data) . ')';
	}
}

/* End File */