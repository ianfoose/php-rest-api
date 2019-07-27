<?php 
require_once('APIHelper.php');

/**
* Notification Services Class
*
* version 1.0
*/
class NotificationServices extends APIHelper {
	/**
	* var string $API_ACCESS_KEY (Android)API access key from Google API's Console.
	*/
	private static $API_ACCESS_KEY = '';

	/**
	* var string $gcmURL (Android) GCM URL, defaults to firebase
	*/
	private static $gcmURL = 'https://fcm.googleapis.com/fcm/send';
	
	/**
	* var string $ssl iOS Push SSL cert
	*/
	private static $ssl;

	/**
	* var string $passphrase (iOS) Private key's passphrase.
	*/
	private static $passphrase = '';

	/**
	* var string $apnsURL (iOS) APNS URL, defaults to sandbox
	*/
	private static $apnsURL = 'ssl://gateway.sandbox.push.apple.com:2195';
	
	/**
	* var string $channelName (Windows Phone 8) The name of our push channel.
	*/
    	private static $channelName = '';
	
	/**
	* var string $emailPort Email Server port
	*/
    	private static $emailPort = '465';

    	/**
	* var string $emailServer Email Server
	*/
    	private static $emailServer = '';

    	/**
	* var string $emailSSL Email Server Use SSL
	*/
    	private static $emailSSL = '';

    	/**
	* var string $emailUsername Email Server username
	*/
    	private static $emailUsername = '';

    	/**
	* var string $emailPassword Email Server Password
	*/
    	private static $emailPassword = ''; 

    	/**
    	* Main Constructor
    	*
    	* param array $configs Notification configs
    	* param bool $expose Expose API functions
    	* return void
    	*/
	public function __construct($configs=null, $expose=false) {
		if(!empty($email)) {
			self::$emailSSL = $email['ssl'];
			self::$emailUsername = $email['username'];
			self::$emailPassword = $email['password'];
			self::$emailServer = $email['server'];
			self::$emailPort = $email['port'];
		} else {
			self::$emailSSL = $this->configs['notifications']['email']['ssl'];
			self::$emailUsername = $this->configs['notifications']['email']['username'];
			self::$emailPassword = $this->configs['notifications']['email']['password'];
			self::$emailServer = $this->configs['notifications']['email']['server'];
			self::$emailPort = $this->configs['notifications']['email']['port'];
		}

		if(!empty($ios)) {
			self::$ssl = $ios['ssl-key'];
			self::$passphrase = $ios['passphrase'];
			self::$apnsURL = $ios['url'];
		} else {
			self::$ssl = $this->configs['notifications']['ios']['ssl-key'];
			self::$passphrase = $this->configs['notifications']['ios']['passpahrase'];
			self::$apnsURL = $this->configs['notifications']['ios']['url'];
		}

		if(!empty($android)) {
			self::$API_ACCESS_KEY = $android['api-key'];
			self::$gcmURL = $android['url'];
		} else {
			self::$API_ACCESS_KEY = $this->configs['notifications']['android']['api-key'];
			self::$gcmURL = $this->configs['notifications']['android']['url'];
		}

		if(!empty($windows)) {
			self::$channelName = $windows['channel'];
		} else {
			self::$channelName = $this->configs['notifications']['windows']['channel'];
		}

		// expose API
		if($expose) {
			$this->exposeAPI();
		}
	}
	
	/**
	* Expose functions to API
	*
	* return void
	*/
	private function exposeAPI() {
		Router::post('/notification/send', function($req, $res) {
			try {
				$this->sendNotification($req->body['payload'],$req->body['users'], $req->body['platforms']);
			} catch (Exception $e) {
				$res->output($e);
			}
		});

		Router::get('/push/token/user/:id', function($req, $res) {
			try {
				$res->output($this->getPushTokenForUser($req->params['id']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'get_push_token_user');

		Router::delete('/push/token/:token', function($req, $res) {
			try {
				$res->output($this->deletePushToken($req->params['token'], $req->body['user_id']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'delete_push_token');

		Router::put('/push/token', function($req, $res) {
			try {
				if($this->checkIfDataExists(array('token'=>$req->body['token'], 'user_id'=>$req->body['user_id'], 'platform'=>$req->body['platform']))) {
					$res->output($this->savePushToken($req->body['user_id'], $req->body['token'], $req->body['platform']));
				}
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'save_push_token');
	}

	/**
	* Sends a notifcation to user or all users on a specific platform
	*
	* param array $notificaion Notification Data
	* param int $userID User ID to send to, leave empty to send to all Users
	* param string $platform Platform to send on, leave blank to send to all
	*
	* return string
	* throws Exception
	*/
	public function sendNotification($payload, $userID=null, $platform=null) {
		if(!empty($notification)) {
			$query  = "SELECT token,platform FROM ".PUSH_UUID;
			$params = array();

			if(!empty($userID)) {
				$query .= " WHERE user_id=:uID";
				$params[':uID'] = $userID;
			}

			if(!empty($platform)) {
				if(empty($userID)) {
					$query .= " WHERE ";
				} else {
					$query .= " AND ";
				}

				$query .= " platform=:p";
				$params[':p'] = $platform;
			}

			$results = self::$db->query($query, $params);
			if($results->rowCount() > 0) {
				while($row = $results->fetch()) {
					// send notification
					$this->sendNotificationToPlatform($payload, $row['token'], $row['platform']);
				}
			}
		} 
		throw new Exception('Must provide a notification object', 404);
	}

    /**
	* Sends Push notification for Android users
	*
	*
	* return 
	*/
	public function android($data, $reg_id, $notification=false) {
	    $vibrate = 1;
	    if(!empty($data['vibrate']))
	    	$vibrate = $data['vibrate'];

	    $message = array(
	        'title' => $data['mtitle'],
	        'message' => $data['mdesc'],
	        'subtitle' => $data['subtitle'],
	        'tickerText' => $data['tickerText'],
	        'msgcnt' => 1,
	        'vibrate' => $vibrate
	    );
	        
	    $headers = array(
	       	'Authorization: key=' .self::$API_ACCESS_KEY,
	        'Content-Type: application/json'
	    );

	    $fields = array(
	        'registration_ids' => array($reg_id),
	        'data' => $message
	    );

	    if($notification) {
	       	$fields['notification'] = $message;
	    }

	    if(!empty($data['data'])) {
	    	foreach ($data['data'] as $value) {
	    		$message[] = $value;
	    	}
	    }
	
	    $result = self::useCurl(self::$gcmURL, $headers, json_encode($fields)); 

	    if($result['success'] == 0 && $result['failure'] == 1 && PUSH_UUID) {
	    	self::deletePushToken($reg_id);
	    }

	   	return $result;
   	}
	
	/**
	* Sends Push's toast notification for Windows Phone 8 users
	*
	* param array $data Notification Data
	* param string $uri URL for sending the notification
	* return string Send Result
	*/
	public function WP($data, $uri) {
		$delay = 2;
		$msg =  "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
		        "<wp:Notification xmlns:wp=\"WPNotification\">" .
		            "<wp:Toast>" .
		                "<wp:Text1>".htmlspecialchars($data['mtitle'])."</wp:Text1>" .
		                "<wp:Text2>".htmlspecialchars($data['mdesc'])."</wp:Text2>" .
		            "</wp:Toast>" .
		        "</wp:Notification>";
		
		$sendedheaders =  array(
		    'Content-Type: text/xml',
		    'Accept: application/*',
		    'X-WindowsPhone-Target: toast',
		    "X-NotificationClass: $delay"
		);
		
		$response = $this->useCurl($uri, $sendedheaders, $msg);
		
		$result = array();
		foreach(explode("\n", $response) as $line) {
		    $tab = explode(":", $line, 2);
		    if (count($tab) == 2)
		        $result[$tab[0]] = trim($tab[1]);
		}

		// todo windows 

		return $result;
	}
	
	/**
	* Sends Push notification for iOS users
	*
	* param array $data Notifiction Data
	* param string $devicetoken Device Token
	* return string Send Result
	* throws Exception
	*/
	public function iOS($data, $devicetoken) {
		$deviceToken = $devicetoken;

		if(!empty($this->ssl)) {
			$ctx = stream_context_create();
			// ck.pem is your certificate file
			stream_context_set_option($ctx, 'ssl', 'verify_peer', false); # for sandboxing only
			stream_context_set_option($ctx, 'ssl', 'local_cert', $this->ssl);
			stream_context_set_option($ctx, 'ssl', 'passphrase', $this->passphrase);

			// Open a connection to the APNS server 2195
			$fp = stream_socket_client(
				$this->apnsURL, $err,
				$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

			if (!$fp)
				exit("Failed to connect: $err $errstr" . PHP_EOL);

			$sound = 'default';

			if(!empty($data['sound']))
				$sound = $data['sound'];

			// Create the payload body
			$body['aps'] = array(
				'alert' => array(
				    'title' => $data['mtitle'],
	                'body' => $data['mdesc'],
				 ),
				'content-available' => $data['mcontent'],
				'link_url' => $data['mlinkurl'],
				'category' => $data['mcategory'],
				'sound' => $sound
			);

			// custom data
			if(!empty($data['data'])) {
				foreach ($data['date'] as $value) {
					$body[] = $value;
				}
			}

			// Encode the payload as JSON
			$payload = json_encode($body);

			// Build the binary notification
			$msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;

			// Send it to the server
			$result = fwrite($fp, $msg, strlen($msg));

			// Close the connection to the server
			fclose($fp);

			if(!$result) {
				throw new Exception('Message not delivered',500);
			} else {
				return $result;
			}
		} else {
			throw new Exception('APNS SSL Cert cannot be NULL');
		}
	}
	
	/**
	* CURL Request
	*
	* param string $url URL to request
	* param array $headers Headers to send
	* param array $fields Body fields to send
	* return object
	*/
	private static function useCurl($url, $headers, $fields = null) {
	    // Open connection
	    $ch = curl_init();
	    if ($url) {
	        // Set the url, number of POST vars, POST data
	        curl_setopt($ch, CURLOPT_URL, $url);
	        curl_setopt($ch, CURLOPT_POST, true);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	     
	        // Disabling SSL Certificate support temporarly
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	        if ($fields) {
	            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
	        }
	     
	        // Execute post
	        $result = curl_exec($ch);
	        if ($result === FALSE) {
	            die('Curl failed: ' . curl_error($ch));
	        }
	     
	        // Close connection
	        curl_close($ch);
	
	        return $result;
        }
    }

    /**
    * Sends a notification to a specific device on a specific platform
    *
    * param array $data Notification Payload
    * param string $token Device Token
    * param string $platform Notification Platform
    *
    * return array or string if response is just text
    * throws Exception
    */
    public function sendNotificationToPlatform($data, $token, $platform) {
    	if($platform == 'apple') {
    		return $this->iOS($data, $token);
    	} else if($platform == 'google') {
    		return $this->android($data, $token, $data['notification']);
    	} else if($platform == 'microsoft') {
    		return $this->WP($data, $token);
    	}
    	throw new Exception('Invalid Platform',500);
    }

    /**
    * Saves a push notification token for a user
    *
    * param string or int $userID User ID 
    * param string $token Push Token
    *
    * return bool
    * throws Exception
    */
    public function savePushToken($userID, $token, $platform) {
    	try {
	    	if(!self::$db->find('id',array('uuid'=>$token,'user_id'=>$userID,'platform'=>$platform),'uuid,user_id,platform',PUSH_UUID,'Token')) {
	   			self::$db->query("INSERT INTO ".PUSH_UUID." SET platform=:p,uuid=:uuid,user_id=:userID",array(':p'=>$platform,':uuid'=>$token,':userID'=>$userID));
	   			return 'Push Token Added';
	   		} 
    	} catch(Exception $e) {
	   		throw $e;
	   	}
    }

    /**
    * Deletes a push notification token for a user
    *
    * param string | int $userID User ID 
    * param string $token Push Token
    *
    * return bool
    * throws Exception
    */
    public function deletePushToken($token, $userID) {
    	try {
	    	if(self::$db->find('id',array('user_id'=>$userID,'uuid'=>$token), PUSH_UUID, 'Token')) {
	    		self::$db->query("UPDATE ".PUSH_UUID." SET deleted='1' WHERE token=:t",array(':t'=>$token));

	    		return 'Push Token Deleted';
	   		}
	   	} catch (Exception $e) {
	    	throw $e;
	    }
    }

    /**
    * Gets push notification tokens for a user
    *
    * param string or int $userID User ID 
    * param string $token Push Token
    *
    * return bool
    * throws Exception
    */
    public function getPushTokenForUser($userID) {
	    try {
		    $result = self::$db->query("SELECT * FROM ".PUSH_UUID." WHERE user_id=:uID",array(':uID'=>$userID));	
		    $tokens = array();
		
		    while($token = $result->fetch()) {
		    	$token['string_date'] = formatDate($token['date']);
		    	$tokens[] = $token;
		    }
		   	return $tokens;
		} catch (Exception $e) {
		    throw $e;
		}
    }

    /**
	* Sends an email
	*
	* param string $to Email Reciever
	* param string $from Email Sender
	* param string $subject Email Subject
	* param string $body Email body
	*
	* return bool
	*/
	public function sendEmail($to,$from,$fromName,$subject,$body,$html=false) {
		require_once('swift_mailer/swift_required.php');

		if(!empty($to) && !empty($from) && !empty($fromName) && !empty($subject) && !empty($body) && !empty(self::$emailUsername) && !empty(self::$emailPassword) && !empty(self::$emailServer)) {
			$transport = Swift_SmtpTransport::newInstance(self::$emailServer, self::$emailPort, self::$emailSSL)
			->setUsername(self::$emailUsername) 
			->setPassword(self::$emailPassword); 

			$mailer = Swift_Mailer::newInstance($transport);

			$message = Swift_Message::newInstance($subject)
			->setFrom(array($from => $fromName))
			->setTo(array($to))
			->setBody($body);

			if($html) 
				$message->setContentType("text/html");

			$result = $mailer->send($message);
													
			if($result == 1) {
				return true;
			} 
		}
		return false;
	}  
}
?>
