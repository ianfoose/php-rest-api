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
	* Runs before anything else
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function set($n,$f) {
		$f();
	}

	/**
	* Adds a route for all methods
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function all($n, $f) {
		self::addRoute('ALL',$n,$f);
	}

	// public static function get($n,$t,$f) {
	// 	self::addRoute('GET',$n,$t,$f);
	// }

	/**
	* Adds a get route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function get($n,$f) {
		self::addRoute('GET',$n,$f);
	}
	
	/**
	* Adds a post route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function post($n,$f) {
		self::addRoute('POST',$n,$f);
	}

	/**
	* Adds a patch route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function patch($n,$f) {
		self::addRoute('PATCH',$n,$f);
	}

	/**
	* Adds a put route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function put($n,$f) {
		self::addRoute('PUT',$n,$f);
	}

	/**
	* Adds a delete route
	*
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	public static function delete($n,$f) {
		self::addRoute('DELETE',$n,$f);
	}

	/**
	* Adds a route
	*
	* @param string $m Method
	* @param string $n Route name
	* @param function $f Route function
	* @return void
	*/
	private static function addRoute($m,$n,$f) {
		self::getInstance()->routes[] = array($m,$n,$f);
	}
}
?>