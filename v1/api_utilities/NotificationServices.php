<?php 
/**
* Notification Services Class
*
* @version 1.0
*/

require_once('APIHelper.php');

class NotificationServices extends APIHelper {
    /**
    * @var array $windowsConfigs Windows notification configs
    */
    private $windowsConfigs = array('channel'=>'');

    /**
    * @var array $googleConfigs Google notification configs
    */
    private $googleConfigs = array('url'=>'https://fcm.googleapis.com/fcm/send', 'api-key'=>'');

   /**
    * @var array $iosConfigs iOS notification configs
    */
    private $iosConfigs = array('url'=>'ssl://gateway.sandbox.push.apple.com:2195', 'passphrase'=>'', 'ssl'=> '');

    /**
    * @var array $emailConfigs Email notification configs
    */
    private $emailConfigs = array('port'=>465, 'username'=>'', 'password'=>'', 'ssl'=>false, 'server'=>'');

    /**
    * Main Constructor
    *
    * @param array $configs Notification configs
    * @param bool $expose Expose API functions
    * @return void
    */
    public function __construct($configs=array(), $expose=false) {
	    if(!empty($configs)) {
            	// email notifications
            	if(array_key_exists('email', $configs)) {
                	$this->emailConfigs = array_merge($this->emailConfigs, $configs['email']);
            	}

            	// iOS notifications
            	if(array_key_exists('ios', $configs)) {
                	$this->iosConfigs = array_merge($this->iosConfigs, $configs['ios']);
            	}

            	// google notifications
            	if(array_key_exists('google', $configs)) {
                	$this->googleConfigs = array_merge($this->googleConfigs, $configs['google']);
            	}

            	// windows notifications
            	if(array_key_exists('windows', $configs)) {
                	$this->windowsConfigs = array_merge($this->windowsConfigs, $configs['windows']);
            	}
        }

	// expose API
	if($expose) {
		$this->exposeAPI();
	}
   }
	
	/**
	* Expose functions to API
	*
	* @return void
	*/
	private function exposeAPI() {
		Router::post('/notification/send', function($req, $res) {
			try {
				$this->sendNotification($req->body['payload'], $req->body['users'], $req->body['platforms']);
			} catch (Exception $e) {
				$res->send($e);
			}
		});

		Router::get('/push/tokens/user/:id', function($req, $res) {
			try {
				$res->send($this->getPushTokenForUser($req->params['id'], $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'get_push_token_user');

		Router::delete('/push/token/:token', function($req, $res) {
			try {
				$res->send($this->deletePushToken($req->params['token'], $req->body['user_id']));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'delete_push_token');

		Router::put('/push/token', function($req, $res) {
			try {
				if($this->checkIfDataExists(array('token'=>$req->body['token'], 'user_id'=>$req->body['user_id'], 'platform'=>$req->body['platform']))) {
					$res->send($this->savePushToken($req->body['user_id'], $req->body['token'], $req->body['platform']));
				}
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'save_push_token');

		Router::get('/notifications/:userID', function($req, $res) {
		    try {
		        $filters = array();

		        if(array_key_exists('filters', $req->body)) {
		            $filters = json_decode($req->body['filters'], true);
		        }

		        $res->send($this->getUserNotifications($req->params['userID'], $this->getQueryOffset(), $this->getQueryLimit()));
		    } catch (Exception $e) {
		        $res->send($e);
		    }
		}, 'local_notifications');

		Router::get('/notifications', function($req, $res) {
		    try {
		        $filters = array();

		        if(array_key_exists('filters', $req->body)) {
		            $filters = json_decode($req->body['filters'], true);
		        }

		        $res->send($this->getNotifications($filters, $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
		    } catch (Exception $e) {
		        $res->send($e);
		    }
		}, 'local_notifications');

		Router::get('/notification/:id', function($req, $res) {
		    try {
		        $res->send($this->getNotification($req->params['id']));
		    } catch (Exception $e) {
		        $res->send($e);
		    }
		}, 'local_notifications');

		Router::delete('/notification/:id', function($req, $res) {
		    try {
		        $res->send($this->deleteNotification($req->params['id']));
		    } catch (Exception $e) {
		        $res->send($e);
		    }
		}, 'local_notifications');

		Router::put('/notification', function($req, $res) {
		    try {
		        $res->send($this->sendLocalNotification($req->body['user_id'], $req->body['object_id'], $req->body['type'], $req->body['event'], $req->body['payload']));
		    } catch (Exception $e) {
		        $res->send($e);
		    }
		}, 'local_notifications');

		Router::post('/notification/:id/read', function($req, $res) {
		    try {
		        $res->send($this->updateLocalNotification($req->params['id'], $req->body['read']));
		    } catch (Exception $e) {
		        $res->send($e);
		    }
		}, 'local_notifications');
	}

	/**
	* Sends a notification to user or all users on a specific platform
	*
	* @param array $notification Notification Data
	* @param int $userID User ID to send to, leave empty to send to all Users
	* @param string $platform Platform to send on, leave blank to send to all
	* @return string
	* @throws Exception
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
	* @param array $data Notifcation payload data
	* @param string $regID Device registration ID
	* @param bool $notification Flag for notification, default is false 
	* return object
	*/
	public function android($data, $regID, $notification=false) {
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
	       	'Authorization: key=' .$this->googleConfigs['api-key'],
	        'Content-Type: application/json'
	    );

	    $fields = array(
	        'registration_ids' => array($regID),
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
	
	    $result = self::useCurl($this->googleConfigs['url'], $headers, json_encode($fields));

	    if($result['success'] == 0 && $result['failure'] == 1 && PUSH_UUID) {
	    	self::deletePushToken($regID);
	    }

	    return $result;
   	}
	
	/**
	* Sends Push's toast notification for Windows Phone 8 users
	*
	* @param array $data Notification Data
	* @param string $uri URL for sending the notification
	* @return string Send Result
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

		return $result;
	}
	
	/**
	* Sends Push notification for iOS users
	*
	* @param array $data Notification Data
	* @param string $devicetoken Device Token
	* @return string Send Result
	* @throws Exception
	*/
	public function iOS($data, $devicetoken) {
		$deviceToken = $devicetoken;

		if($this->iosConfigs['ssl']) {
			$ctx = stream_context_create();
			// ck.pem is your certificate file
			stream_context_set_option($ctx, 'ssl', 'verify_peer', false); # for sandboxing only
			stream_context_set_option($ctx, 'ssl', 'local_cert', $this->iosConfigs['ssl']);
			stream_context_set_option($ctx, 'ssl', 'passphrase', $this->iosConfigs['passpharse']);

			// Open a connection to the APNS server 2195
			$fp = stream_socket_client(
				$this->iosConfigs['url'], $err,
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
	* @param string $url URL to request
	* @param array $headers Headers to send
	* @param array $fields Body fields to send
	* @return object
	*/
	private function useCurl($url, $headers, $fields = null) {
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
    * @return array or string if response is just text
    * @throws Exception
    */
    public function sendNotificationToPlatform($data, $token, $platform) {
        if(is_array($platform)) {
            foreach($platform as $value) {
                return $this->sendNotificationToPlatform($data, $token, $value);
            }
        } else {
            if($platform == 'apple') {
                return $this->iOS($data, $token);
            } else if($platform == 'google') {
                return $this->android($data, $token, $data['notification']);
            } else if($platform == 'microsoft') {
                return $this->WP($data, $token);
            }
            // TODO: log error
            throw new Exception('Platform '.$platform.' Not Found',404);
    	}
    }

    /**
    * Saves a local notification in the database.
    *
    * @param int $userID Target user ID
    * @param string $payload A notification payload, typically JSON
    * @param string $type Notification type
    * @return string
    * @throws Exception
    */
    public function sendLocalNotification($userID, $objectID=0, $type='Row', $event='Event', $payload=null) {
        try {
            self::$db->query("INSERT INTO ".NOTIFICATIONS." SET payload=:p, user_id=:uID, object_id=:oID, type=:type", array(':p'=>$payload, ':uID'=>$userID, ':oID'=>$objectID, ':type'=>$type));

            return 'Notification Sent';
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
    * Updates a notifications status, (read)
    *
    * @return string
    * @throws Exception
    */
    public function updateLocalNotification($notificationID, $readStatus) {
        try {
            self::$db->find('*', array('id'=>$notificationID), NOTIFICATIONS);
            self::$db->query("UPDATE ".NOTIFICATIONS.' SET `read`=:s WHERE id=:nID', array(':nID'=>$notificationID, ':s'=>$readStatus));
            return 'Notification Updated';
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
    * Delete a local notification
    *
    * @param int $notificationID ID of notification
    * @return string
    * @throws Exception
    */
    public function deleteNotification($notificationID) {
        try {
            self::$db->find('id', array('id'=>$notificationID), NOTIFICATIONS);
            self::$db->query("UPDATE ".NOTIFICATIONS." SET deleted=1 WHERE id=:id", array(':id'=>$notificationID));
            return 'Notification Deleted.';
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
    * Gets all notifications
    *
    * @param array $filters Query filters (deleted, read)
    * @param string $order Pagination order
    * @param int $offset Pagination Offset
    * @param int $limit Pagination limit
    * @return array
    * @throws Exception
    */
    public function getNotifications($filters=array(), $direction='ASC', $offset=0, $limit=40) {
        try {
            $queryString = "SELECT * FROM ".NOTIFICATIONS;
            $params = array(':offset'=>$offset, ':limit'=>$limit);

            if(!empty($filters)) {
                $queryString .= ' WHERE ';
                foreach($filters as $key => $value) {
                    $queryString .= $key.'=:'.$key;
                    $params[':'.$key] = $value;

                    // check if end of array
                    if($value != end($filters)) {
                        $queryString .= ' AND ';
                    }
                }
            }

            $results = self::$db->query($queryString." ORDER BY id $direction LIMIT :offset,:limit", $params);
            $notifications = array();

            while($notification = $results->fetch()) {
                $notifications[] = $this->getNotificationData($notification);
            }

            return $notifications;
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
    * Gets all notifications for a user
    *
    * @param int $userID ID of user
    * @param array $filters Query filters (deleted, read)
    * @param int $offset Pagination Offset
    * @param int $limit Pagination limit
    * @return array
    * @throws Exception
    */
    public function getUserNotifications($userID, $filters=array(), $offset=0, $limit=40) {
        try {
            return $this->getNotifications(array_merge($filters, array('user_id'=>$userID)), 'ASC', $offset, $limit);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
    * Gets a notification object by id
    *
    * @param int $notificationID ID of notification
    * @return object
    * @throws Exception
    */
    public function getNotification($notificationID) {
        try {
            $notification = self::$db->find('*', array('id'=>$notificationID), NOTIFICATIONS);
            return $this->getNotificationData($notification);
        } catch(Exception $e) {
            throw $e;
        }
    }

    /**
    * Gets and formats data for a notification.
    *
    * @param object $notification
    * @return object
    * @throws Exception
    */
    public function getNotificationData($notification=null) {
        if(array_key_exists('date', $notification)) {
            $notification['string_data'] = $this->formatDate($notification['date']);
        }

        // TODO:

        return $notification;
    }

    /**
    * Saves a push notification token for a user
    *
    * @param string or int $userID User ID 
    * @param string $token Push Token
    * @return bool
    * @throws Exception
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
    * @param string | int $userID User ID 
    * @param string $token Push Token
    * @return bool
    * @throws Exception
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
    * @param string or int $userID User ID
    * @param int offset offset
	* @param int $limit fetch limit
    * @return bool
    * @throws Exception
    */
    public function getPushTokenForUser($userID, $offset=0, $limit=40) {
	    try {
	        $params = array(':uID'=>$userID, ':offset'=>$offset, ':limit'=>$limit);
		    $result = self::$db->query("SELECT * FROM ".PUSH_UUID.' WHERE user_id=:uID LIMIT :offset,:limit', $params);
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
	* @param string $to Email Receiver
	* @param string $from Email Sender
	* @param string $subject Email Subject
	* @param string $body Email body
	* @return bool
	*/
	public function sendEmail($to,$from,$fromName,$subject,$body,$html=false) {
		try {
            require_once('swift_mailer/swift_required.php');

            if(empty($fromName)) {
                $fromName = $from;
            }

            if(!empty($to) && !empty($from) && !empty($subject) && !empty($body)) {
                $transport = Swift_SmtpTransport::newInstance($this->emailConfigs['server'], $this->emailConfigs['port'], $this->emailConfigs['ssl'])
                ->setUsername($this->emailConfigs['username'])
                ->setPassword($this->emailConfigs['password']);

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
            throw new Exception('Check email fields, to, from, subject and body cannot be Null ', 404);
        } catch (Exception $e) {
            throw $e;
        }
	}  
}
?>
