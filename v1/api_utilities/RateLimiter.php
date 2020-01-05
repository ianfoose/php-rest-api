<?php
/**
* RateLimiter Class
*
* @version 1.0
*/

class RateLimiter {
  public function rateLimitByIP($limit=100, $secs=60) {
    session_start();
    if (isset($_SESSION['LAST_CALL'])) {
        $last = strtotime($_SESSION['LAST_CALL']);
        $curr = strtotime(date('Y-m-d h:i:s'));
        $sec =  abs($last - $curr);
        if ($sec <= $secs) {
          // rate limit
          die ('limit exceeded');        
        }
      }
      $_SESSION['LAST_CALL'] = date('Y-m-d h:i:s');

      return true;
    }
  }
?>
