<?php
/**
* Token Services 
*
* @version 1.0
*/

require_once(dirname(dirname(dirname(__FILE__))).'/api_utilities/APIHelper.php');

class TokenServices extends APIHelper {
	/**
	* @var string $prefix Token hash prefix
	*/
	private $prefix;

	/**
	* @var string $secret Secret string for token hash
	*/
	private $secret;

	/**
	* Main Constructor
	*
	* @param bool $expose Expose API functions
	* @return void
	*/
	public function __construct($expose=true) {
		parent::__construct();

		if(defined('TOKENS')) {
			$tokensConfig = $this->configs['tokens'];

			if(!empty($tokensConfig['prefix'])) {
				$this->prefix = $tokensConfig['prefix'];
			}

			if(!empty($tokensConfig['secret'])) {
				$this->secret = $tokensConfig['secret'];
			}
		} else {
			throw new Exception('Tokens Table not set, check configuration key `tokens`', 404);
		}

		// expose API Methods
		if($expose) {
			$this->exposeAPI();
		}
	}

	/**
	* Expose methods to the API
	*
	* @return void
	*/
	public function exposeAPI() {
		// API Tokens

		Router::post('/token/refresh', function($req, $res) {
			if(!empty($req->body['token'])) {
				$res->send($this->refreshToken($req->body['token'], $req->body['token_exp'], $req->body['regenerate'], $req->body['refresh_exp']));
			}
			throw new Exception('Missing token', 401);
		});

		Router::get('/tokens', function($req, $res) {
			$res->send($this->getTokens($_GET['deleted'], $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
		}, 'get_tokens');

		Router::get('/token/:id', function($req, $res) {
			$res->send($this->getToken($req->params['id']));
		}, 'get_token');

		Router::get('/token/unique/:id', function($req, $res) {
			$res->send($this->getTokenUnique($req->params['id']));
		}, 'get_token_unique');

		Router::get('/token/validate', function($req, $res) {
			$res->send($this->validate($req->body['token']));
		}, 'validate_token');

        // API Keys

//		 Router::get('/keys', function($req, $res) {
//			 $filters = array();
//
//			 if(array_key_exists('filters', $req->body)) {
//				 $filters = json_decode($req->body['filters'], true);
//			 }
//
//			 $res->send($this->getKeys($filters, $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
//		 });
//
//		 Router::get('/key/:id', function($req, $res) {
//		 	$res->send($this->getKey($req->params['id']));
//		 });
//
//		 Router::put('/key', function($req, $res) {
//		 	$res->send($this->createKey($req->body['name']));
//		 });
//
//		 Router::delete('/key/:id', function($req, $res) {
//		 	$res->send($this->revokeKey($req->params['id']));
//		 });
	}

	/**
	* Decodes a token
	*
	* @param string $token Base64 Token
	* @return array
	*/
	public function decodeToken($token) {
		$token = base64_decode($token);
		$token = json_decode($token, true);
		return $token;
	}

	/**
	* validates a token, non database
	*
	* @param string $token Base64 Token
	* @return Bool
	*/
	public function validate($token) {
		if($this->checkTokenConfigs()) {
			$token = $this->decodeToken($token);
			$sig = sha1($this->prefix.':'.$this->secret.':'.$token['auth']['t']);

			if($sig === $token['auth']['s']) {
				$today = date('Y:m:d H:i:s');

				if($today < $token['auth']['t']){
				    return $token;
				} 
			}
			return false;
		} else {
			throw new Exception('Check token secret and or prefix in configs', 404);
		}
	}

	/**
	* Validates a refresh token
	*
	* @param string $rToken Refresh Token
	* @return bool
	* @throws Exception
	*/
	public function validateRefreshToken($rToken) {
		if($this->checkTokenConfigs()) {
			$token = $this->decodeToken($rToken);
			$sig = sha1($this->prefix.':'.$this->secret.':'.$token['auth']['t']);

			if($sig === $token['auth']['s']) {
				$today = date('Y:m:d H:i:s');

				if($today < $token['auth']['t']){
				    try {
				    	if(self::$db->find('id', array('token'=>$rToken, 'revoked'=>0), TOKENS, 'Token', false)) {
				    		return true;
				    	}
				    	throw new Exception('Token not found', 401);
				    } catch (Exception $e) {
				    	throw $e;
				    } 
				} 
			}
			return false;
		} else {
			throw new Exception('Check token secret and or prefix in configs', 401);
		}
	}

	/**
	* Refreshes a token
	*
	* @param string $refreshToken Refresh Token
        * @param string $expDate Refreshed token expiration date, default +5 minutes.
        * @param bool $regenerate Regenerate a new refresh token is the refresh token is still valid.
        * @param string $refreshTokenExp Regenerated refresh token expiration date, default +1 hour.
	* @return array
	* @throws Exception
	*/
	public function refreshToken($refreshToken, $expDate='+5 Minutes', $regenerate=false, $refreshTokenExp='+1 Hour') {
		if($this->validateRefreshToken($refreshToken)) {
			if($decodedToken = $this->decodeToken($refreshToken)) {
				if(!empty($decodedToken['data']['id'])) {
					$userID = $decodedToken['data']['id'];

					try {
						if(self::$db->find('id', array('token'=>$refreshToken, 'u_id'=>$userID, 'revoked'=>0), TOKENS, 'Token', false)) {
							if(self::$db->beginTransaction()) {
								try {
								    $tokenData = array('token'=>$this->createToken($userID, $expDate, $decodedToken['data']));

								    if($regenerate) {
                                        $newRefreshToken = $this->createRefreshToken($userID, $refreshTokenExp, $decodedToken['data']);
                                        $tokenData['refresh_token'] = $newRefreshToken;
                                    }

									if(self::$db->commit()) {
										return $tokenData;
									}
									
								} catch(Exception $ex) {
									throw $ex;
								}	
							}
						}
					} catch(Exception $e) {
						throw $e;
					}
				}
			}
		}
		throw new Exception('Invalid Refresh Token', 401);
	}

	/**
	* Creates a token
	*
	* @param string $id Unique ID 
	* @param array $data body data array
	* @return string
    * @throws Exception
	*/
	public function createToken($id, $expDate='+5 Minutes', $data=null) {
		return $this->create($id, $expDate, $data);
	}

	/**
	* Creates a refresh token
	*
	* @param string $id Unique ID 
	* @param array $data body data array
	* @return string
	* @throws Exception
	*/
	public function createRefreshToken($id, $expDate='+5 Minutes', $data=null) {
		$token = $this->create($id, $expDate, $data, true);

		try {
			$this->save($id, $token);
			return $token;
		} catch(Exception $e) {
			throw $e;
		}
	}

	/**
	* creates a token
	*
	* @param string $id Unique ID 
	* @param array $data body data array
	* @param bool $refresh Generate a refresh token
	* @return string
    * @throws Exception
	*/
	private function create($id, $expDate='+5 Minutes', $data=array(), $refresh=false) {
		if(empty($expDate)) {
			$expDate = '+5 Minutes';
		}
		
		if($this->checkTokenConfigs()) {
			$exp = date('Y:m:d H:i:s', strtotime($expDate));

			if($refresh) {
				$exp = date('Y:m:d H:i:s', strtotime($expDate));
			}

			$sig = sha1($this->prefix.':'.$this->secret.':'.$exp);
			$dataArray = array('id'=>$id);

			if(is_array($data)) { 
				$dataArray = array_merge($dataArray, $data);
			}
				
			$token = array('data'=>$dataArray, 'auth'=>array('s'=>$sig, 't'=>$exp)); 
			$token = json_encode($token);
			$token = base64_encode($token);

			return $token;
		} else {
			throw new Exception('Check token secret and or prefix in configs', 404);
		}
	}

	/**
	* Saves a token
	*
	* @param int $uniqueID Unique Token ID
	* @param object $token Token
	* @return string
	* @throws Exception
	*/
	public function save($uID, $token) {
		try {
			$dToken = $this->decodeToken($token);
			
			if(!empty($dToken['auth']['t'])) {
				try {
					if(self::$db->query("INSERT INTO ".TOKENS." SET token=:token,u_id=:uID,exp_date=:eDate", array(':token'=>$token, ':uID'=>$uID, ':eDate'=>$dToken['auth']['t']))) {
						return 'Saved';
					} 	
				} catch (Exception $e) {
					throw $e;
				}
			} else {
				throw new Exception('Auth contains no expiration time', 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Revokes a token pair
	*
	* @param string $token Token
	* @param int $id Unique ID
	* @return bool
	* @throws Exception
	*/
	public function revoke($token) {
		try {
			if(self::$db->find('id', array('token'=>$token), TOKENS, 'Token', false)) {
				if(self::$db->query("UPDATE ".TOKENS." SET revoked='1' WHERE token=:t", array(':t'=>$token))) {
					return 'Token Revoked';
				}
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets a token for Unique ID
	*
	* @param int $id Unique ID
	* @return Object
	* @throws Exception 
	*/
	public function getTokenUnique($uID) {
		try {
			if($result = self::$db->find('*', array('u_id'=>$uID), TOKENS)) {
				return $this->getTokenData($result);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets a token for id
	*
	* @param int $id Token ID
	* @return Object
	* @throws Exception
	*/
	public function getToken($id) {
		try {
			if($result = self::$db->find('*', array('id'=>$id), TOKENS)) {
				return $this->getTokenData($result);
			}
		} catch (Exception $e) {
			throw $e;
		}
 	}

 	/**
	* Gets all tokens
	*
    * @param string $deleted Deleted
	* @param int $offset Pagination offset
	* @param int $limit Pagination Limit
	* @return array
	* @throws Exception
	*/
	public function getTokens($deleted='', $direction='ASC', $offset=0, $limit=40) {
		try {
			$params = array(':deleted'=>$deleted, ':offset'=>$offset, ':limit'=>$limit);

			if($results =self::$db->query("SELECT * FROM ".TOKENS." WHERE deleted=:deleted ORDER BY id $direction LIMIT :offset,:limit", $params)) {
				$tokens = array();

				while($token = $results->fetch()) {
					$tokens[] = self::getTokenData($token);
				}

				return $tokens;
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets token data
	*
	* @param object $token Token
	* @return Object
	*/
	private function getTokenData($token) {
        if(array_key_exists('date', $token)) {
			$token['date'] = formatDate($token['date']);
        }

        if(array_key_exists('exp_date', $token)) {
	        $token['string_exp_date'] = formatDate($token['exp_date']);
        }

		return $token;
	}

	/**
	* Checks for mandatory token configs
	*
	* @return bool
	*/
	private function checkTokenConfigs() {
		if(!empty($this->prefix) && !empty($this->secret)) {
			return true;
		}
		return false;
	}

	// ==================== API Keys ====================

	/**
	* Validates an API Key
	*
	* @param string $key Key Value
	* @return bool
	* @throws Exception
	*/
	public function validateKey($key) {
	    try {

	    } catch(Exception $e) {
	        throw $e;
	    }
	}

    /**
    * Creates an API Key
	*
    * @param string $name API Key name
    * @return string
    * @throws Exception
    */
	public function createKey($name, $userID=null) {
	    try {
	        self::$db->query("INSERT INTO ".API_KEYS." SET name=:name,key=:key,user_id=:uID", array(':name'=>$name, ':key'=>$key, ':uID'=>$userID, ':expDate'=>$expDate));
	        return 'API Key Created';
	    } catch(Exception $e) {
	        throw $e;
	    }
	}

    /**
    * Revokes an API Key
    *
    * @param int $id API Key ID
    * @return string
    * @throws Exception
    */
	public function revokeKey($id) {
	    try {
	        self::$db->find('id', array('key'=>$key), API_KEYS);
	        self::$db->query('DELETE FROM '.API_KEYS.' WHERE id=:id', array(':id'=>$id));
	        return 'API Key Deleted';
	    } catch(Exception $e) {
	        throw $e;
	    }
	}

    /**
    * Gets all API Keys, filterable
    *
    * @param
	* @param
	* @param
    * @param
    * @return array
    * @throws Exception
    */
	public function getKeys($filters=array(), $order='ASC', $offset=0, $limit=40) {
	    try {
	        $queryString = "SELECT * FROM ".API_KEYS;
	        $params = array(':offset'=>0, ':limit'=>$limit);

	        if(!empty($filters)) {
	            foreach($filters as $key => $value) {
	                $queryString .= $key.'=:'.$key;
	                $params[':'.$key] = $value;

	                 if($value != end($filters)) {
                        $queryString .= ' AND ';
                    }
	            }
	        }

	        $results = self::$db->query($queryString." ORDER BY id $order LIMIT :offset,:limit", $params);
	        $apiKeys = array();

	        while($key = $results->fetch()) {
	            $apiKeys[] = $this->getKeyData($key);
	        }

	        return $apiKeys;
	    } catch(Exception $e) {
	        throw $e;
	    }
	}

    /**
    * Gets an API Key for a specified ID
    * 
    * @param int $id API Key ID
    * @return object
    * @throws Exception
    */
	public function getKey($id) {
	    try {
	        return $this->getKeyData(self::$db->find('*', array('id'=>$id)));
	    } catch(Exception $e) {
	        throw $e;
	    }
	}

    /**
    * Gets API Key data
    *
    * @return object
    * @throws Exception
    */
	public function getKeyData($key) {
	    try {
	        if(array_key_exists('date', $key)) {
                $key['string_date'] = $this->formatDate($key['date']);
            }

            if(array_key_exists('exp_date', $key)) {
			    $key['string_exp_date'] = formatDate($key['exp_date']);
            }

	        return $key;
	    } catch(Exception $e) {
	        throw $e;
	    }
	}
}
?>
