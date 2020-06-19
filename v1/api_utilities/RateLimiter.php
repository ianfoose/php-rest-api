<?php
/**
* RateLimiter Class
*
* @version 1.0
*/

$ipServicesFile = dirname(dirname(__FILE__)).'/modules/IPServices/IPServices.php';

if(!file_exists($ipServicesFile)) {
  error_log('Rate Limiter: IPServices Module is Missing!!');
  die();
}

require_once($ipServicesFile);

class RateLimiter extends APIHelper {
  /**
  * @var int $limit 'Rate Limit' Max requests for an interval, default=100.
  */
  private $limit = 100;

  /**
  * @var int $interval Max amount of time that the max number of requests can be made.
  */
  private $interval = 60;

  /**
  * Main constructor
  */
  public function __construct() {
    parent::__construct();

    if(isset($this->configs['rate_limiting'])) {
      $rateLimiterConfigs = $this->configs['rate_limiting'];

      if(array_key_exists('interval', $rateLimiterConfigs)) {
        $this->interval = $rateLimiterConfigs['interval'];
      }

      if(array_key_exists('max_requests', $rateLimiterConfigs)) {
        $this->limit = $rateLimiterConfigs['max_requests'];
      }
    }
  }

  /**
  * Rate limit an IP be the number of requests made over an interval.
  *
  * @param int $limit Max requests for an interval, default=100.
  * @param int $interval Max amount of time that the max number of requests can be made, default=60 seconds.
  * @return bool
  */
  public function rateLimitByIP($limit=100, $interval=60) {
    session_start();

    if($limit != $this->limit) {
      $limit = $this->limit;
    }

    if($interval != $this->interval) {
      $interval = $this->interval;
    }

    $defaultSession = array('requests'=>1, 'date'=>date('Y-m-d h:i:s'));

    $ipServices = new IPServices();
    $ip = $ipServices->getIP();
    $sessionID = 'RATE_LIMIT_'.$ip;

    if (isset($_SESSION[$sessionID])) {
      $sessionData = $_SESSION[$sessionID];

      $last = strtotime($_SESSION[$sessionID]['date']);
      $curr = strtotime(date('Y-m-d h:i:s'));
      $sec =  abs($last - $curr);

      if ($sec <= $interval) { 
        if($sessionData['requests'] == $limit) {
          // rate limit client
          return false;
        } else {
          if($sessionData['requests'] > $limit) {
            $_SESSION[$sessionID] = $defaultSession;
            return false;
          }

          $sessionData['requests'] = $sessionData['requests'] + 1;
          $_SESSION[$sessionID] = $sessionData;
          return true;
        }     
      } 
    }
  
    $_SESSION[$sessionID] = $defaultSession;
    return true;
  }
}
?>