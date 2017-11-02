<?php
/**
* APIHelper Class
*
* @copyright Foose Industries
* @version 1.0
*/

// Includes
require_once('utilities.php');
require_once('api_utilities/Router.php');
require_once('Response.php');

abstract class APIHelper {
	public $endpoint = '/';
	public $params = array();
	public $headers = array();
	public $method = '';
	public $format = '';
	public $scheme = '';

	public function __construct() {
		if(!empty(@$_SERVER['PATH_INFO']))
			$this->endpoint = $_SERVER['PATH_INFO'];

		// for htaccess rewrite
		if(!empty(@$_SERVER['REQUEST_URI'])) {
			$this->endpoint = strtok(str_replace($this->scheme, "", $_SERVER['REQUEST_URI']),'?');
		}

		// output format 
		$this->format = getFormat($this->endpoint); 

		// remove extension from endpoint
		if(strpos($this->endpoint, '.')) { // set endpoint
			$endpoint = $this->endpoint;
			$endpoint = explode('/', $endpoint);

			$pendpoint = $endpoint[1];
			$pendpoint = explode('.',$pendpoint);

			if(!empty(@$pendpoint[1])) {
				if($pendpoint[1] == 'json') {
					$this->format = $pendpoint[1];
					$endpoint[1] = $pendpoint[0];

					$this->endpoint = implode('/', $endpoint);
				}
			}
		} 

		$this->headers = $_SERVER;
		$this->method = $_SERVER['REQUEST_METHOD'];
	}

	/**
	* Function used for Authentication
	*
	* @param function $f Function
	* @return void
	*/
	public function auth($f) { $f(); }

	/**
	* Runs the API
	*
	* @return void
	*/
	public function run() {
		$routes = Router::getInstance()->routes;

		foreach ($routes as $key => $value) {
			$t = $value;

			foreach ($t as $tKey => $tValue) {
				if($t[0] == $this->method || $t[0] == 'ALL') { // method
					if($this->endpoint != '/') {
						$purl = explode('/',trim($t[1],'/')); // indexed route
						$route = explode('/',trim($this->endpoint,'/')); // requested routes

						if(count($purl) == count($route)) {
							$k = 0;

							foreach ($purl as $pKey => $pValue) {
								$p = substr($pValue, 0, 1); // param placeholder

								if($p === ':') { // param
									if(!empty($route[ltrim($pKey,':')])) {
										$this->params[ltrim($pValue,':')] = $route[ltrim($pKey,':')];
									} 
								} else { // no param
									if($pValue != $route[$pKey]) { // no match, abort
										$this->params = array();
										break;
									} 
								}

								if($k == count($purl) - 1) {
									if(substr($this->endpoint, 0,1) == '/' && substr($t[1],0,1) != '/') { // start
										$this->params = array();
										break;
									} elseif(substr($this->endpoint,0,1) != '/' && substr($t[1],0,1) != '/') {
										$this->params = array();
										break;
									}

									if(substr($this->endpoint, -1) == '/' && substr($t[1], -1) != '/') { // end
										$this->params = array();
										break;
									} else if(substr($this->endpoint, -1) != '/' && substr($t[1], -1) == '/') {
										$this->params = array();
										break;
									}

									$this->endpoint = $t[1];
								}

								$k++;
							}
						}
					}

					if($t[1] === $this->endpoint) {	
						$input = array();
						parse_str(file_get_contents('php://input'),$input);

						if(!empty(trim(@$_SERVER['HTTP_IF_NONE_MATCH']))) {
							$input['etag'] = $_SERVER['HTTP_IF_NONE_MATCH'];
						}

						$this->params = array_merge($this->params, $_GET);
						$input = array_merge($input, $_POST);

						$req = array('body'=>@$input, 'params'=>@$this->params,'headers'=>getallheaders());

						if(isset($t[3]) && !empty($t[3])) {
							if(checkIfDataExists($t[3])) {
								self::output($t[2]($req));
							}
						} else {
							self::output($t[2]($req));
						}

						break;
					} 
					break; // breaks route comparison
				} 
			}
		}

		self::output(new Response(400,'endpoint does not exist'));
	}

	/**
	* Outputs data in JSON
	*
	* @param string $format Data format
	* @param array $data Data array
	* @return JSON
	*/
	public function output($data, $headers=null) {
		$headerString = ('HTTP/1.1 200 OK'); 

		// check if data is a 'Response' object
		if(is_a($data, 'Response')) {
			$headerString = ('HTTP/1.1'.' '.$data->status.' '.$data->getMessage());
			$data = $data->data;
		}
				
		header($headerString);
		
		if(!empty($headers)) {
			header($headers);
		}

		if(!is_array($data)) { // for single values	
			$data = array('result'=>$data); 
		} 

		if($this->format == 'json') { // json	
			echo json_encode($data);
		} else { // plain text, default
			echo $data;
		}	

		exit;	
	}
}
?>