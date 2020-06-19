<?php
/**
 * Router Class
 *
 * @version 4.0
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
    * @param string $name Route name
    * @param function $func Route function
    * @param bool $verify API verification flag
    * @param bool $override Override pre existing route flag
    * @param bool $rate Rate limiting flag
    * @return void
    */
    public static function all($name, $func, $verify=false, $override=false, $rate=false) {
        self::addRoute('ALL', $name, $func, $verify, $override, $rate);
    }

    /**
    * Adds a get route
    *
    * @param string $name Route name
    * @param function $func Route function
    * @param bool $verify API verification flag
    * @param bool $override Override pre existing route flag
    * @param bool $rate Rate limiting flag
    * @return void
    */
    public static function get($name, $func, $verify=false, $override=false, $rate=false) {
        self::addRoute('GET', $name, $func, $verify, $override, $rate);
    }

    /**
    * Adds a post route
    *
    * @param string $name Route name
    * @param function $func Route function
    * @param bool $verify API verification flag
    * @param bool $override Override pre existing route flag
    * @param bool $rate Rate limiting flag
    * @return void
    */
    public static function post($name, $func, $verify=false, $override=false, $rate=false) {
        self::addRoute('POST', $name, $func, $verify, $override, $rate);
    }

    /**
    * Adds a patch route
    *
    * @param string $name Route name
    * @param function $func Route function
    * @param bool $verify API verification flag
    * @param bool $override Override pre existing route flag
    * @param bool $rate Rate limiting flag
    * @return void
    */
    public static function patch($name, $func, $verify=false, $override=false, $rate=false) {
        self::addRoute('PATCH', $name, $func, $verify, $override, $rate);
    }

    /**
    * Adds a put route
    *
    * @param string $name Route name
    * @param function $func Route function
    * @param bool $verify API verification flag
    * @param bool $override Override pre existing route flag
    * @param bool $rate Rate limiting flag
    * @return void
    */
    public static function put($name, $func, $verify=false, $override=false, $rate=false) {
        self::addRoute('PUT', $name, $func, $verify, $override, $rate);
    }

    /**
    * Adds a delete route
    *
    * @param string $name Route name
    * @param function $func Route function
    * @param bool $verify API verification flag
    * @param bool $override Override pre existing route flag
    * @param bool $rate Rate limiting flag
    * @return void
    */
    public static function delete($name, $func, $verify=false, $override=false, $rate=false) {
        self::addRoute('DELETE', $name, $func, $verify, $override, $rate);
    }

    /**
    * Adds a route
    *
    * @param string $method Method
    * @param string $name Route name
    * @param function $func Route function
    * @param bool $verify API verification flag
    * @param bool $override Override pre existing route flag
    * @param bool $rate Rate limiting flag
    * @return void
    */
    private static function addRoute($method, $name, $func, $verify=false, $override=false, $rate=false) {
        $newRoute = array('method'=>$method, 'route'=>$name, 'function'=>$func, 'verify'=>$verify, 'override'=>$override, 'rate_limit'=>$rate);

        // route overriding
        if(sizeof(self::getInstance()->routes) == 0) {
            self::getInstance()->routes[] = $newRoute;
        } else {
            if($routes = self::getInstance()->routes) {
                foreach($routes as $key => $route) {
                    if($route['route'] == $name && $method == $route['method']) {
                        if($override == true && $route['override'] == false) {
                            self::getInstance()->routes[$key] = $newRoute;
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