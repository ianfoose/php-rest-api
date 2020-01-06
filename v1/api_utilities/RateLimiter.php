<?php
/**
* RateLimiter Class
*
* @version 1.0
*/

$ipServicesFile = dirname(dirname(__FILE__)).'/modules/IPServices/IPServices.php';

if(!file_exists($ipServicesFile)) {
  error_log('IPServices Module is Missing!!');
  die();
}

require_once($ipServicesFile);

class RateLimiter {
  public function rateLimitByIP($limit=3, $secs=30) {
    session_start();

    $ipServices = new IPServices();
    $ip = $ipServices->getIP();
    $sessionID = 'RATE_LIMIT_'.$ip;

    if (isset($_SESSION[$sessionID])) {
        $last = strtotime($_SESSION[$sessionID]);
        $curr = strtotime(date('Y-m-d h:i:s'));
        $sec =  abs($last - $curr);
        if ($sec <= $secs) {
          // rate limit client
          die ('limit exceeded'); 

          throw new Exception('API Request Limit Exceeded!', 429);       
        }
      }
      $_SESSION[$sessionID] = date('Y-m-d h:i:s');

      return true;
    }
  }
?>
