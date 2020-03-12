<?php
/**
* APIHandler Class
*
* @copyright Foose Industries
* @version 2.0
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
    * @throws Exception
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
	* @param bool $format flag to format API endpoint, default `false`
	* @return array | string
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
	* @param string $endpoint API endpoint to format
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
	* @param string $function The input function
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
	* @param array | string $policy An array or string of header(s)
	* @return void
	*/
	public function setCorsPolicy($policy=null) {

	    if(empty($policy)) {
		    if($headers = array_key_exists('cors', $this->configs)) {
                if(is_array($headers)) {
                    foreach ($headers as $value) {
                        header('Access-Control-Allow-Origin: '.$value);
                    }
                } else {
                    header('Access-Control-Allow-Origin: '.$headers);
                }
            }
        } else {

            header('Access-Control-Allow-Origin: '.$policy);

            header('Access-Control-Allow-Methods: GET, POST');
            header("Access-Control-Allow-Headers: X-Requested-With");
        }
	}

	/**
	* Function used for Authentication, overridable
	*
	* @param string $names route name
	* @param function $handler Authentication handler for named route
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
	* @param bool $method Handler name or true for default
	* @param object $req Request Object
	* @param object $res Response Object
	* @return bool
	*/
	private function authHandler($method=true, $req, $res) {
		if($method === true) {
			$method = 'default';
		}

		if(isset($this->authHandlers[$method])) {
			return $this->authHandlers[$method]($req, $res);
		}
		return false;
	}

	/**
	* Function used for rate limiting, overridable
	*
	* @param string $names route name
	* @param function $handler rate limiting handler for named route
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
	* @param object $req Resuest Object
	* @param object $res Response Object
	* @return bool
	*/
	private function rateLimitHandler($method=true, $req, $res) {
		if($method === true) {
			$method = 'default';
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

		if($method == 'OPTIONS') {
            if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"])) {
                header("Access-Control-Allow-Methods: ".$this->configs['methods']);
            }

            if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }

            exit(0);
        }

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

					if(!empty($_SERVER['HTTP_IF_NONE_MATCH'])) {
						$body['etag'] = $_SERVER['HTTP_IF_NONE_MATCH'];
					}

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

					if(empty($value['rate_limit'])) {
						$value['rate_limit'] = false;

						if($this->configs['rate_limiting']['auto_enforce'] == true) {
							$value['rate_limit'] = true;
						}
					}

					// check for rate limiting
					$rateLimited = false;

					if($value['rate_limit']) { 
						$rateLimited = !$this->rateLimitHandler($value['rate_limit'], $this->req, $this->res);
					} 

					// checks for custom auth function
					$tokenIsValid = true;

					if(isset($value['verify']) && !empty($value['verify'])) {
						$tokenIsValid = $this->authHandler($value['verify'], $this->req, $this->res);
					} 

					if($rateLimited && $tokenIsValid) {
						// endpoint is rate limited and token is valid
						$this->res->send('Rate Limited', 429);	
					} else if(!$tokenIsValid) {
						// token is expired
						$this->res->send('Invalid authentication', 401);
					} else {
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
