<?php 
/**
* PushNotifications Class
*
* @version 1.0
*/

require_once('./Constants.php');
require_once('DatabaseHelper.php');

class NotificationServices {
	// (Android)API access key from Google API's Console.
	private static $API_ACCESS_KEY = '';
	// (Android) GCM URL, defaults to firebase
	private static $gcmURL = 'https://fcm.googleapis.com/fcm/send';
	// (iOS) ssl cert
	private static $ssl;
	// (iOS) Private key's passphrase.
	private static $passphrase = '';
	// (iOS) APNS URL, defaults to sandbox
	private static $apnsURL = 'ssl://gateway.sandbox.push.apple.com:2195';
	// (Windows Phone 8) The name of our push channel.
    	private static $channelName = '';
	// Email Server port
    	private static $emailPort = '465';
   	// Email Server
    	private static $emailServer = '';
    	// Email Server Use SSL
    	private static $emailSSL = '';
    	// Email Server username
    	private static $emailUsername = '';
    	// Email Server Password
    	private static $emailPassword = ''; 

    	protected static $dataHelper;

	public function __construct($email=null, $android=null, $ios=null, $windows=null) {
		if(!empty(@$email)) {
			self::$emailSSL = @$email['ssl'];
			self::$emailUsername = @$email['username'];
			self::$emailPassword = @$email['password'];
			self::$emailServer = @$email['server'];
			self::$emailPort = @$email['port'];
		}

		if(!empty($ios)) {
			self::$ssl = @$ios['ssl'];
			self::$passphrase = @$ios['passphrase'];
			self::$apnsURL = @$ios['url'];
		}

		if(!empty($android)) {
			self::$API_ACCESS_KEY = @$android['key'];
			self::$gcmURL = @$android['url'];
		}

		if(!empty($windows)) {
			self::$channelName = @$windows['channel'];
		}

		self::$dataHelper = new DatabaseHelper(URL,USER,PASSWORD,DB);
	}
	
    	// Sends Push notification for Android users
	public function android($data, $reg_id, $notification=false) {
	    $vibrate = 1;
	    if(!empty(@$data['vibrate']))
	    	$vibrate = $data['vibrate'];

	    $message = array(
	        'title' => $data['mtitle'],
	        'message' => $data['mdesc'],
	        'subtitle' => @$data['subtitle'],
	        'tickerText' => @$data['tickerText'],
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

	    if(!empty(@$data['data'])) {
	    	foreach ($data['data'] as $value) {
	    		$message[] = $value;
	    	}
	    }
	
	    $result = self::useCurl(self::$gcmURL, $headers, json_encode($fields)); 

	    if(@$result['success'] == 0 && @$result['failure'] == 1 && PUSH_UUID) {
	    	self::deletePushToken($reg_id);
	    }

	   	return $result;
   	}
	
	// Sends Push's toast notification for Windows Phone 8 users
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
	
    	// Sends Push notification for iOS users
	public function iOS($data, $devicetoken) {
		$deviceToken = $devicetoken;

		$ctx = stream_context_create();
		// ck.pem is your certificate file
		stream_context_set_option($ctx, 'ssl', 'verify_peer', false); # for sandboxing only
		stream_context_set_option($ctx, 'ssl', 'local_cert', '/Library/WebServer/Documents/cias/api/v2/api_utilities/ck.pem');
		stream_context_set_option($ctx, 'ssl', 'passphrase', self::$passphrase);

		// Open a connection to the APNS server 2195
		$fp = stream_socket_client(
			self::$apnsURL, $err,
			$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

		if (!$fp)
			exit("Failed to connect: $err $errstr" . PHP_EOL);

		$sound = 'default';

		if(!empty(@$data['sound']))
			$sound = $data['sound'];

		// Create the payload body
		$body['aps'] = array(
			'alert' => array(
			    'title' => $data['mtitle'],
                'body' => $data['mdesc'],
			 ),
			'content-available' => @$data['mcontent'],
			'link_url' => @$data['mlinkurl'],
			'category' => @$data['mcategory'],
			'sound' => $sound
		);

		// custom data
		if(!empty(@$data['data'])) {
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
	}
	
	// Curl 
	//private static function useCurl($url, $headers, $fields = null) {
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
    * @param array $data Notification Payload
    * @param string $token Device Token
    * @param string $platform Notification Platform
    * @return array | string
    */
    public function sendNotification($data, $token, $platform) {
    	if($platform == 'apple') {
    		return self::iOS($data, $token);
    	} else if($platform == 'google') {
    		return self::android($data, $token, @$data['notification']);
    	} else if($platform == 'microsoft') {
    		return self::WP($data, $token);
    	}
    	return 'Invalid Platform';
    }

    /**
    * Saves a push notification token for a user
    *
    * @param string | int $userID User ID 
    * @param string $token Push Token
    * @return boolean
    */
    public function savePushToken($userID, $token, $platform) {
    	if(!empty(PUSH_UUID)) {
    		if(!self::$dataHelper->find('id','uuid,user_id,platform',$token.','.$userID.','.$platform,PUSH_UUID,'Token')) {
    			if(self::$dataHelper->query("INSERT INTO ".PUSH_UUID." SET platform=:p,uuid=:uuid,user_id=:userID",array(':p'=>$platform,':uuid'=>$token,':userID'=>$userID))) {
    				return 'Push Token Added';
    			}
    		} else {
    			throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
    		}
    	}
    	throw new Exception('Push Tokens Table not set',500);
    }

    /**
    * Deletes a push notification token for a user
    *
    * @param string | int $userID User ID 
    * @param string $token Push Token
    * @return boolean
    */
    public function deletePushToken($token) {
    	if(!empty(PUSH_UUID)) {
    		if(self::$dataHelper->find('id','user_id,uuid',$userID.','.$$token,PUSH_UUID,'Token')) {
    			self::$dataHelper->query("UPDATE ".PUSH_UUID." SET deleted='1' WHERE token=:t",array(':t'=>$token));
    		}
    		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
    	}
    	throw new Exception('Push Tokens Table not set',500);
    }

    /**
    * Gets push notification tokens for a user
    *
    * @param string | int $userID User ID 
    * @param string $token Push Token
    * @return boolean
    */
    public function getPushTokenForUser($userID) {
    	if(!empty(PUSH_UUID)) {
	    	if($result = self::$dataHelper->query("SELECT * FROM ".PUSH_UUID." WHERE user_id=:uID",array(':uID'=>$userID))) {
	    		$tokens = array();

	    		while($token = $result->fetch()) {
	    			$token['string_date'] = formatDate($token['date']);
	    			$tokens[] = $token;
	    		}
	    		return $tokens;
	    	}
	    	throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	    }
	    throw new Exception('Push Tokens Table not set',500);
    }

    /**
	* Sends an email
	*
	* @param string $to Email Reciever
	* @param string $from Email Sender
	* @param string $subject Email Subject
	* @param string $body Email body
	* @return bool
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
