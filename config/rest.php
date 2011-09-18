<?php defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| REST Format
|--------------------------------------------------------------------------
|
| What format should the data be returned in by default?
|
|	Default: xml
|
*/
$config['rest_default_format'] = 'json';

/*
|--------------------------------------------------------------------------
| Enable emulate request
|--------------------------------------------------------------------------
|
| Should we enable emulation of the request (e.g. used in Mootools request)?
|
|	Default: false
|
*/
$config['enable_emulate_request'] = FALSE;

/*
|--------------------------------------------------------------------------
| REST Realm
|--------------------------------------------------------------------------
|
| Name for the password protected REST API displayed on login dialogs
|
|	E.g: My Secret REST API
|
*/
$config['rest_realm'] = 'Cloudmanic // API';

/*
|--------------------------------------------------------------------------
| REST Login
|--------------------------------------------------------------------------
|
| Is login required and if so, which type of login?
|
|	'' = no login required, 'basic' = unsecure login, 'digest' = more secure login
|
*/
$config['rest_auth'] = '';

/*
|--------------------------------------------------------------------------
| Override auth types for specific class/method
|--------------------------------------------------------------------------
|
| Set specific authentication types for methods within a class (controller)
|
| Set as many config entries as needed.  Any methods not set will use the default 'rest_auth' config value.
|
| example:  
| 
|			$config['auth_override_class_method']['deals']['view'] = 'none';
|			$config['auth_override_class_method']['deals']['insert'] = 'digest';
|			$config['auth_override_class_method']['accounts']['user'] = 'basic'; 
|
| Here 'deals' and 'accounts' are controller names, 'view', 'insert' and 'user' are methods within. 
|	(NOTE: leave off the '_get' or '_post' from the end of the method name)
| Acceptable values are; 'none', 'digest' and 'basic'.  
|
*/
//$config['auth_override_class_method']['users']['get'] = 'basic';

/*
|--------------------------------------------------------------------------
| REST Login usernames
|--------------------------------------------------------------------------
|
| Array of usernames and passwords for login
|
|	array('admin' => '1234')
|
*/
$config['rest_valid_logins'] = array();

/*
|--------------------------------------------------------------------------
| REST Database Group
|--------------------------------------------------------------------------
|
| Connect to a database group for keys, logging, etc. It will only connect
| if you have any of these features enabled.
|
|	'default'
|
*/
if($_SERVER['SERVER_ADDR'] == '127.0.0.1')
{
	$config['rest_database_group'] = 'local';
} else
{
	$config['rest_database_group'] = 'live';
}

/*
|--------------------------------------------------------------------------
| REST API Keys Table Name
|--------------------------------------------------------------------------
|
| The table name in your database that stores API Keys.
|
|	'keys'
|
*/
$config['rest_keys_table'] = 'ApiKeys';

/*
|--------------------------------------------------------------------------
| REST Enable Keys
|--------------------------------------------------------------------------
|
| When set to true REST_Controller will look for a key and match it to the DB.
| If no key is provided, the request will return an error.
|
|	FALSE

	CREATE TABLE `keys` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `key` varchar(40) NOT NULL,
	  `level` int(2) NOT NULL,
	  `ignore_limits` tinyint(1) NOT NULL DEFAULT '0',
	  `date_created` int(11) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
|
*/
$config['rest_enable_keys'] = TRUE;

/*
|--------------------------------------------------------------------------
| REST Key Length
|--------------------------------------------------------------------------
|
| How long should created keys be? Double check this in your db schema.
|
|	Default: 32
|	Max: 40
|
*/
$config['rest_key_length'] = 40;

/*
|--------------------------------------------------------------------------
| REST API Key Variable
|--------------------------------------------------------------------------
|
| Which variable will provide us the API Key
|
| Default: X-API-KEY 
|
*/
$config['rest_key_name'] = 'apikey';

/*
|--------------------------------------------------------------------------
| REST API Logs Table Name
|--------------------------------------------------------------------------
|
| The table name in your database that stores logs.
|
|	'logs'
|
*/
$config['rest_logs_table'] = 'ApiLogs';

/*
|--------------------------------------------------------------------------
| REST Enable Logging
|--------------------------------------------------------------------------
|
| When set to true REST_Controller will log actions based on key, date,
| time and IP address. This is a general rule that can be overridden in the
| $this->method array in each controller.
|
|	FALSE
|
	CREATE TABLE `logs` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `uri` varchar(255) NOT NULL,
	  `method` varchar(6) NOT NULL,
	  `params` text NOT NULL,
	  `api_key` varchar(40) NOT NULL,
	  `ip_address` varchar(15) NOT NULL,
	  `time` int(11) NOT NULL,
	  `authorized` tinyint(1) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
|
*/
$config['rest_enable_logging'] = TRUE;

/*
|--------------------------------------------------------------------------
| REST API Limits Table Name
|--------------------------------------------------------------------------
|
| The table name in your database that stores limits.
|
|	'logs'
|
*/
$config['rest_limits_table'] = 'limits';

/*
|--------------------------------------------------------------------------
| REST Enable Limits
|--------------------------------------------------------------------------
|
| When set to true REST_Controller will count the number of uses of each method
| by an API key each hour. This is a general rule that can be overridden in the
| $this->method array in each controller.
|
|	FALSE
|
	CREATE TABLE `limits` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `uri` varchar(255) NOT NULL,
	  `count` int(10) NOT NULL,
	  `hour_started` int(11) NOT NULL,
	  `api_key` varchar(40) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;
|
*/
$config['rest_enable_limits'] = FALSE;

/*
|--------------------------------------------------------------------------
| REST Ignore HTTP Accept
|--------------------------------------------------------------------------
|
| Set to TRUE to ignore the HTTP Accept and speed up each request a little.
| Only do this if you are using the $this->rest_format or /format/xml in URLs
|
|	FALSE
|
*/
$config['rest_ignore_http_accept'] = FALSE;

/*
|--------------------------------------------------------------------------
| REST AJAX Only
|--------------------------------------------------------------------------
|
| Set to TRUE to only allow AJAX requests. If TRUE and the request is not 
| coming from AJAX, a 505 response with the error message "Only AJAX 
| requests are accepted." will be returned. This is good for production 
| environments. Set to FALSE to also accept HTTP requests. 
|
|	FALSE
|
*/
$config['rest_ajax_only'] = FALSE;


/*
|--------------------------------------------------------------------------
| REST Enable Custom Authentication
|--------------------------------------------------------------------------
|
| Sometimes we want to handle our own custom authentication. The 'library' 
| array index is the name of CI library we should load to check authentication
| the 'method' array index is the method in that library. If this library 
| returns true we assume the user is authenticated. The 'otherauth' index in this
| array if this library call returns false should we go on to check the 
| $config['rest_auth'] and $config['rest_enable_keys'] for authentication 
| or just kick the user out. If true we check other authentication methods. 
| Often this is used to support sessions within your web app, but also provide
| support for on web-based api calls, like a mobile device or something.
|
*/
$config['rest_enable_custom_auth'] = array('library' => 'cloudauth', 
																						'method' => 'sessioninit', 
																						'otherauth' => TRUE);
																						
																						
/*
|--------------------------------------------------------------------------
| REST Auto Guess Model
|--------------------------------------------------------------------------
|
| Programmers are lazy. Lets make our controllers even simpler by auto guessing
| what model the url request is for. This way to activate this API call 
| all you have to do build an empty controller and BOOM you have full CRUD on a model. 
| If this array is empty it will not try to guess your model you will have to call
| the $this->set_model() function. If you set this array you do not have to call set_model()
| First argment is which url segment use as a guess for the model. The section is a postfix
| you might want to append to a model file name. 
|
| $config['rest_model_guess'] = array('segment' => 1, 'postfix' => '_model');
|
*/
$config['rest_model_guess'] = array('segment' => 2, 'postfix' => '_model');
 

/* End of file config.php */
/* Location: ./system/application/config/rest.php */