<?php
/**
* APIHandler Class
*
* @copyright Foose Industries
* @version 1.0
*/
require_once('APIHelper.php');
require_once('Router.php');

abstract class APIHandler extends APIHelper {
	/**
	* @var Request $req Request Object
	*/ 
	public $req;

	/**
	* @var Response $res Response Object
	*/
	public $res;

	/**
	* @var string $format Response Format, json, xml, etc...
	*/
	private $format;

	/**
	* @var array $authHandlers Handlers for authentication by type
	*/
	private $authHandlers = array();

	/**
	* @var array $rateLimitHandlers Handlers for rate limiting
	*/
	private $rateLimitHandlers = array();

	/**
	* Main constructor
	*
	* @return void
	*/
	public function __construct() {
		parent::__construct();

		$this->setCorsPolicy($this->configs['cors']);

		$this->format = $this->getFormat($this->getAPIEndpoint());

		$responses = null;
		if(array_key_exists('status_responses', $this->configs)) {
			$responses = $this->configs['status_responses'];
		}

		$this->res = new Response($this->format, $responses);
	}

	/**
	* Gets the API endpoint from URL 
	*
	* @return void
	*/
	private function getAPIEndpoint($format=false) {
        if(array_key_exists('base_url', $this->configs)) {
            $baseURL = $this->configs['base_url'];
        } else {
            $baseURL = str_replace('/'.basename($_SERVER['SCRIPT_FILENAME']), '', $_SERVER['SCRIPT_NAME']);
        }

        if(array_key_exists('PATH_INFO', $_SERVER)) {
            $endpoint = $_SERVER['PATH_INFO'];
        }

		// for htaccess rewrite
		if(array_key_exists('REQUEST_URI', $_SERVER)) {
		    $endpoint = strtok(str_replace($baseURL, '', $_SERVER['REQUEST_URI']),'?');
		}

        // set endpoint to '/' if still empty
		if(empty($endpoint)) {
		   $endpoint = '/';
		}

		if($format) {
			$endpoint = $this->formatAPIEndpoint($endpoint);
		}

		return $endpoint;
	}

	/**
	* Formats the API endpoint for route matching
	*
	* @return void
	*/
	private function formatAPIEndpoint($endpoint='/') {
		// remove format extension from endpoint (ex. .xml, .json)
		if(strpos($endpoint, '.')) { // set endpoint
			$endpoint = explode('/', $endpoint);

			$pendpoint = end($endpoint);
			$pendpoint = explode('.',$pendpoint);

			if(!empty($pendpoint[1])) {
				$endpoint[count($endpoint)-1] = $pendpoint[0];
				$endpoint = implode('/', $endpoint);
			}
		} 
		return $endpoint;
	}

	/**
	* Returns the format specified
	*
	* param string $function The input function
	* @return string
	*/
	public function getFormat($function) {
		$formatSuffix = explode('.',$function);
		
		if(count($formatSuffix)>1) {
			if($formatSuffix[1] == 'json' || $formatSuffix[1] == 'xml' || $formatSuffix[1] == 'text' || $formatSuffix[1] == 'csv') {
				return $formatSuffix[1];
			}
		}

		if(!empty($this->configs['format'])) {
			return $this->configs['format'];
		}

		return 'text';
	}  

	/**
	* Function used to set CORS Policy, overridable
	*
	* @param array | string $headers An array or string of header(s) 
	* @return void
	*/
	public function setCorsPolicy($policy=null) {
	    if(empty($policy)) {
		    if($headers = array_key_exists('cors', $this->configs)) {
                if(is_array($headers)) {
                    foreach ($headers as $value) {
                        header($value);
                    }
                } else {
                    header($headers);
                }
            }
        } else {
            header($policy);
        }
	}

	/**
	* Function used for Authentication, overridable
	*
	* @param string $name route name
	* @param function $handler Authentication handler for named route
	*
	* @return void
	*/
	public function addAuthHandler($names, $handler) {
		if(is_array($names)) {
			foreach ($names as $name) {
				$this->addAuthHandler($name, $handler);
			}
		} else {
			$this->authHandlers[$names] = $handler;
		}
	}

	/**
	* Handle authorization by method
	*
	* @param string $method Handler name or true for default
	* @return method Handler function
	*/
	private function authHandler($method=true, $req, $res) {
		if($method === true) {
			if(isset($this->authHandlers['default'])) {
				return $this->authHandlers['default']($req, $res);
			}
			return true;
		}

		if(isset($this->authHandlers[$method])) {
			return $this->authHandlers[$method]($req, $res);
		}
		return false;
	}

	/**
	* Function used for rate limiting, overridable
	*
	* @param string $name route name
	* @param function $handler rate limiting handler for named route
	*
	* @return void
	*/
	public function addRateLimitHandler($names, $handler) {

		if(is_array($names)) {
			foreach ($names as $name) {
				$this->addRateLimitHandler($name, $handler);
			}
		} else {
			$this->rateLimitHandlers[$names] = $handler;
		}
	}

	/**
	* Handle rate limiting by method
	*
	* @param string $method Handler name or true for default
	* @return method Handler function
	*/
	private function rateLimitHandler($method=true, $req, $res) {
		if($method === true) {
			if(isset($this->rateLimitHandlers['default'])) {
				return $this->rateLimitHandlers['default']($req, $res);
			}
			return true;
		}

		if(isset($this->rateLimitHandlers[$method])) {
			return $this->rateLimitHandlers[$method]($req, $res);
		}
		return false;
	}

	/**
	* Calls a route endpoint's function
	*
	* @param object $route a route object
	* @return void
	*/
	private function callEndpointFunc($route) {
		try {
			$route['function']($this->req, $this->res);
		} catch(Exception $e) {
			$this->res->send($e);
		}
	}

	/**
	* Runs the API
	*
	* @return void
	*/
	public function run() {
		if(array_key_exists('REQUEST_METHOD', $_SERVER)) {
		    $method = $_SERVER['REQUEST_METHOD'];
		} else {
		    $this->res->send('Every Request must have a method!', 400);
		}
		
		$endpoint = $this->getAPIEndpoint(true);

		$routes = Router::getInstance()->routes;

		$this->req = new Request();

		$params = array();
		$body = array();

		foreach ($routes as $key => $value) {
			if($value['method'] == $method || $value['method'] == 'ALL') { // method
				if($endpoint != '/') {
					$purl = explode('/',trim($value['route'],'/')); // indexed route
					$route = explode('/',trim($endpoint,'/')); // requested routes

					if(count($purl) == count($route)) { // number of paths that match route
						$k = 0;

						// get any params in path
						foreach ($purl as $pKey => $pValue) {
							$p = substr($pValue, 0, 1); // param placeholder

							if($p === ':') { // param
								if(!empty($route[ltrim($pKey,':')])) {
									$params[ltrim($pValue,':')] = $route[urldecode(ltrim($pKey,':'))];
								} 
							} else { // no param
								if($pValue != $route[$pKey]) { // no match, abort
									$params = array();
									break;
								} 
							}

							// put params in array
							if($k == count($purl) - 1) {
								if(substr($endpoint, 0,1) == '/' && substr($value['route'],0,1) != '/') { // start
									$params = array();
									break;
								} elseif(substr($endpoint,0,1) != '/' && substr($value['route'],0,1) != '/') {
									$params = array();
									break;
								}

								if(substr($endpoint, -1) == '/' && substr($value['route'], -1) != '/') { // end
									$params = array();
									break;
								} else if(substr($endpoint, -1) != '/' && substr($value['route'], -1) == '/') {
									$params = array();
									break;
								}

								$endpoint = $value['route'];
							}

							$k++;
						}
					}
				} 

				// if route matches url
				if($value['route'] === $endpoint) {	
					$body = array();

					parse_str(file_get_contents('php://input'),$body);

					/*if(!empty(trim($_SERVER['HTTP_IF_NONE_MATCH']))) {
						$body['etag'] = $_SERVER['HTTP_IF_NONE_MATCH'];
					}*/

					$params = array_merge($params, $_GET);
					$body = array_merge($body, $_POST); 
					
					// for nginx
					if(!function_exists('getallheaders')) {
						function getallheaders() {
							$headers = array();
							
							foreach($_SERVER as $name => $value) {
								$headers[$name] = $value;
							}
							
							return $headers;
						}
					}
					
					$headers = array_merge($_SERVER, getallheaders());

					$this->req = new Request($params, $body, $headers, $this->format);

					// check for rate limiting
					if(isset($value['rate_limit']) && !empty($value['rate_limit'])) {
						
						$valid = $this->rateLimitHandler($value['rate_limit'], $this->req, $this->res);

						if($valid === true) { // run api endpoint function
							$this->callEndpointFunc($value);
						}
						$this->res->send('Rate Limited', 429);
					} else { // no authentication required, run function
						$this->callEndpointFunc($value);
					}

					// checks for custom auth function
					if(isset($value['verify']) && !empty($value['verify'])) {
						$valid = $this->authHandler($value['verify'], $this->req, $this->res);

						if($valid === true) { // run api endpoint function
							$this->callEndpointFunc($value);
						}
						$this->res->send('Invalid authentication', 401);
					} else { // no authentication required, run function
						$this->callEndpointFunc($value);
					}

					break;
				} 
			}

			if($key == (count($routes) - 1)) {
		        $this->res->send('Endpoint: '.$endpoint.' does not exist', 400);
			}
		}
	}
}
?>
