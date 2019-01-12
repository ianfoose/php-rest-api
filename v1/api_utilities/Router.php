<?php
/**
* Router Class
*
* @version 1.0
*/
class Router {
	public $routes = array();
	private static $instance;

	/**
	* Construct Function 
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
	* @return void
	*/
	public static function all($n,$f,$v=false) {
		self::addRoute('ALL',$n,$f,$v);
	}

	/**
	* Adds a get route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function get($n,$f,$v=false) {
		self::addRoute('GET',$n,$f,$v);
	}
	
	/**
	* Adds a post route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function post($n,$f,$v=false) {
		self::addRoute('POST',$n,$f,$v);
	}

	/**
	* Adds a patch route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function patch($n,$f,$v=false) {
		self::addRoute('PATCH',$n,$f,$v);
	}

	/**
	* Adds a put route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function put($n,$f,$v=false) {
		self::addRoute('PUT',$n,$f,$v);
	}

	/**
	* Adds a delete route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function delete($n,$f,$v=false) {
		self::addRoute('DELETE',$n,$f,$v);
	}

	/**
	* Adds a route
	*
	* @param string $m Method
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	private static function addRoute($m,$n,$f,$v=false) {
		self::getInstance()->routes[] = array($m,$n,$f,$v);
	}
}
?>
