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
	* Main constructor
	*
	* @return void
	*/
	public function __construct() {
		// set CORS
		$this->setCorsPolicy(); 
		
		parent::__construct();

		$this->format = $this->getFormat($this->getAPIEndpoint());
		$this->res = new Response($this->format);
	}

	/**
	* Gets the API endpoint from URL 
	*
	* @return void
	*/
	private function getAPIEndpoint($format=false) {
		$endpoint = '/';

		if(!empty(@$this->configs['base_url'])) {
			$baseURL = $this->configs['base_url'];
		} else {
			$baseURL = str_replace('/'.basename($_SERVER['SCRIPT_FILENAME']), '', $_SERVER['SCRIPT_NAME']);
		}

		if(!empty(@$_SERVER['PATH_INFO'])) {
			$endpoint = $_SERVER['PATH_INFO'];
		}

		// for htaccess rewrite
		if(!empty(@$_SERVER['REQUEST_URI'])) {
			$endpoint = strtok(str_replace($baseURL, '', $_SERVER['REQUEST_URI']),'?');
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

			if(!empty(@$pendpoint[1])) {
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
			if($formatSuffix[1] == 'json' || $formatSuffix[1] == 'xml' || $formatSuffix[1] == 'csv') {
				return $formatSuffix[1];
			}
		}
		return 'json';
	}  

	/**
	* Function used to set CORS Policy, overridable
	*
	* @return void
	*/
	public function setCorsPolicy() {
		header("Access-Control-Allow-Origin: *");
	}

	/**
	* Function used for Authentication, overridable
	*
	* @param string $name route name
	* @param function $handler Auth handler for named route
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
	* Runs the API
	*
	* @return void
	*/
	public function run() {
		$method = $_SERVER['REQUEST_METHOD'];
		$endpoint = $this->getAPIEndpoint(true);
		
		$routes = Router::getInstance()->routes;

		$this->req = new Request();

		$params = array();
		$body = array();

		foreach ($routes as $key => $value) {
			$t = $value;

			foreach ($t as $tKey => $tValue) {
				if($t[0] == $method || $t[0] == 'ALL') { // method
					if($endpoint != '/') {
						$purl = explode('/',trim($t[1],'/')); // indexed route
						$route = explode('/',trim($endpoint,'/')); // requested routes

						if(count($purl) == count($route)) { // number of paths matches route
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
									if(substr($endpoint, 0,1) == '/' && substr($t[1],0,1) != '/') { // start
										$params = array();
										break;
									} elseif(substr($endpoint,0,1) != '/' && substr($t[1],0,1) != '/') {
										$params = array();
										break;
									}

									if(substr($endpoint, -1) == '/' && substr($t[1], -1) != '/') { // end
										$params = array();
										break;
									} else if(substr($endpoint, -1) != '/' && substr($t[1], -1) == '/') {
										$params = array();
										break;
									}

									$endpoint = $t[1];
								}

								$k++;
							}
						}
					} 

					// if route matches url
					if($t[1] === $endpoint) {	
						$body = array();
						parse_str(file_get_contents('php://input'),$body);

						if(!empty(trim(@$_SERVER['HTTP_IF_NONE_MATCH']))) {
							$body['etag'] = $_SERVER['HTTP_IF_NONE_MATCH'];
						}

						$params = array_merge($params, $_GET);
						$body = array_merge($body, $_POST); 
					
						$headers = array_merge($_SERVER, getallheaders());

						$this->req = new Request($params, @$body, $headers, $this->format);

						// checks for custom auth function
						if(isset($t[3]) && !empty($t[3]) && $t[3]) {
							$valid = $this->authHandler($t[3], $this->req, $this->res);

							if($valid === true) {
								$t[2]($this->req, $this->res);
							}
							$this->res->output($valid);
						} else {
							$t[2]($this->req, $this->res);
						}

						break;
					} 
					break; // breaks route comparison, if unsatisfied
				} 
			}
		}

		$this->res->output($endpoint.' does not exist', 400);
	}
}
?>