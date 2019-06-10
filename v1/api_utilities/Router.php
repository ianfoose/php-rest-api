<?php
/**
* Router Class
*
* @version 2.0
*/
class Router {
	/**
	* @var array $routes Routes array
	*/
	public $routes = array();
	
	/**
	* @var Router $instance Singleton instance of router (this class)
	*/
	private static $instance;

	/**
	* Main Constructor 
	*
	* @return void
	*/
	public function __construct() { }

	/**
	* Clone Function 
	*
	* @return void
	*/
	public function __clone() { }

	/**
	* Wakeup Function 
	*
	* @return void
	*/
	public function __wakeup() { }

	/**
	* Gets a class instance
	*
	* @return Class Instance
	*/
	public static function getInstance() {
		if(null === static::$instance) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	/**
	* Adds a route for all methods
	*
	* @param string $n Route name
	* @param function $f Route function
	* @param bool $v API verification
	* @param bool $o Override pre existing route
	* @return void
	*/
	public static function all($n,$f,$v=false,$o=false) {
		self::addRoute('ALL',$n,$f,$v,$o);
	}

	/**
	* Adds a get route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @param bool $v API verification
	* @param bool $o Override pre existing route
	* @return void
	*/
	public static function get($n,$f,$v=false,$o=false) {
		self::addRoute('GET',$n,$f,$v,$o);
	}
	
	/**
	* Adds a post route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @param bool $v API verification
	* @param bool $o Override pre existing route
	* @return void
	*/
	public static function post($n,$f,$v=false,$o=false) {
		self::addRoute('POST',$n,$f,$v,$o);
	}

	/**
	* Adds a patch route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @param bool $v API verification
	* @param bool $o Override pre existing route
	* @return void
	*/
	public static function patch($n,$f,$v=false,$o=false) {
		self::addRoute('PATCH',$n,$f,$v,$o);
	}

	/**
	* Adds a put route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @param bool $v API verification
	* @param bool $o Override pre existing route
	* @return void
	*/
	public static function put($n,$f,$v=false,$o=false) {
		self::addRoute('PUT',$n,$f,$v,$o);
	}

	/**
	* Adds a delete route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @param bool $v API verification
	* @param bool $o Override pre existing route
	* @return void
	*/
	public static function delete($n,$f,$v=false,$o=false) {
		self::addRoute('DELETE',$n,$f,$v,$o);
	}

	/**
	* Adds a route
	*
	* @param string $m Method
	* @param string $n Route name
	* @param function $f Route function
	* @param bool $v API verification
	* @param bool $o Override pre existing route
	* @return void
	*/
	private static function addRoute($m,$n,$f,$v=false,$o=false) {	
		$newRoute = array('method'=>$m,'route'=>$n,'function'=>$f,'verify'=>$v,'override'=>$o);
		
		//if($n == '/group/:id')
			//echo "string";

		// route overriding
		if(sizeof(self::getInstance()->routes) == 0) {
			self::getInstance()->routes[] = $newRoute;
		} else {
			if($routes = self::getInstance()->routes) {
				foreach($routes as $key => $route) {
					if($route['route'] == $n && $m == $route['method']) {
						if($o == true && $route['override'] == false) {
							self::getInstance()->routes[$key] = $newRoute;
							break;
						}
						break;
					} else {
						if(end($routes) == $route) {
							self::getInstance()->routes[] = $newRoute;
							break;
						}
					}	 
				}
			}
		}		
	}
}

?>
