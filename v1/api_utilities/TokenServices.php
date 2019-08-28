<?php
/**
* Token Services 
*
* version 1.0
*/

require_once('APIHelper.php');

class TokenServices extends APIHelper {
	/**
	* var string $prefix Token hash prefix
	*/
	private $prefix;

	/**
	* var string $secret Secret string for token hash
	*/
	private $secret;

	/**
	* Main Constructor
	*
	* param bool $expose Expose API functions
	* return void
	*/
	public function __construct($expose=true) {
		parent::__construct();

		if(!empty($this->tables['tokens'])) {
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
	* return void
	*/
	public function exposeAPI() {
		Router::post('/token/refresh', function($req, $res) {
			try {
				if(!empty($req->body['token'])) {
					$res->send($this->refreshToken($req->body['token']));
				}
				throw new Exception('Missing token', 404);
			} catch (Exception $e) {
				$res->send($e);
			}
		});

		Router::get('/tokens', function($req, $res) {
			try {
				$res->send($this->getTokens($_GET['since_id'], $_GET['max_id'], $_GET['limit'], $_GET['deleted']));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'get_tokens');

		Router::get('/token/:id', function($req, $res) {
			try {
				$res->send($this->getToken($req->params['id']));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'get_token');

		Router::get('/token/unique/:id', function($req, $res) {
			try {
				$res->send($this->getTokenUnique($req->params['id']));
			} catch (Exception $e) {
				$res->send($e);
			}
		}, 'get_token_unique');
	}

	/**
	* Decodes a token
	*
	* param string $token Base64 Token
	* return array
	*/
	public function decodeToken($token) {
		$token = base64_decode($token);
		$token = json_decode($token,true);

		return $token;
	}

	/**
	* validates a token, non database
	*
	* param string $token Base64 Token
	* return Bool
	*/
	public function validate($token) {
		if($this->checkTokenConfigs()) {
			$token = $this->decodeToken($token);

			$sig = sha1($this->prefix.':'.$this->secret.':'.$token['auth']['t']);

			if($sig === $token['auth']['s']) {
				$today = date("Y:m:d H:i:s");

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
	* param string $rToken Refresh Token
	* return bool
	* throws Exception
	*/
	public function validateRefreshToken($rToken) {
		if($this->checkTokenConfigs()) {
			$token = $this->decodeToken($rToken);

			$sig = sha1($this->prefix.':'.$this->secret.':'.$token['auth']['t']);

			if($sig === $token['auth']['s']) {
				$today = date("Y:m:d H:i:s");

				if($today < $token['auth']['t']){
				    try {
				    	if(self::$db->find('id',array('token'=>$rToken,'revoked'=>0), TOKENS, 'Token')) {
				    		return true;
				    	}
				    } catch (Exception $e) {
				    	throw $e;
				    } 
				} 
			}
			return false;
		} else {
			throw new Exception('Check token secret and or prefix in configs', 404);
		}
	}

	/**
	* Refreshes a token
	*
	* param string $rToken Refresh Token
	* return array
	* throws Exception
	*/
	public function refreshToken($rToken) {
		if($this->validateRefreshToken($rToken)) {
			if($dToken = $this->decodeToken($rToken)) {
				if(!empty($dToken['data']['id'])) {
					$userID = $dToken['data']['id'];

					try {
						if(self::$db->find('id', array('token'=>$rToken,'u_id'=>$userID,'revoked'=>0), TOKENS,'Token')) {
							if(self::$db->beginTransaction()) {
								try {
									$newRToken = $this->createRefreshToken($userID,$dToken['data']);
									$newDToken = $this->decodeToken($newRToken);

									if(self::$db->commit()) {
										return array('token'=>$this->createToken($userID,$dToken['data']),'refresh_token'=>$newRToken);
									}
									
								} catch(Excpetion $ex) {
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
		throw new Exception('Invalid Refresh Token',401);
	}

	/**
	* Creates a token
	*
	* param string $id Unique ID 
	* param array $data body data array
	* return string
	*/
	public function createToken($id, $data=null) {
		return $this->create($id,$data);
	}

	/**
	* Creates a refresh token
	* param string $id Unique ID 
	* param array $data body data array
	* return string
	* throws Exception
	*/
	public function createRefreshToken($id, $data=null) {
		$token = $this->create($id,$data,true);

		try {
			$this->save($id,$token);
			return $token;
		} catch(Excpetion $e) {
			throw $e;
		}
	}

	/**
	* creates a token
	*
	* param string $id Unique ID 
	* param array $data body data array
	* param bool $refresh Generate a refresh token
	* return string
	*/
	private function create($id, $data=null, $refresh=false) {
		if($this->checkTokenConfigs()) {
			$exp = date('Y:m:d H:i:s', strtotime('+5 Minutes'));

			if($refresh) {
				$exp = date('Y:m:d H:i:s', strtotime('+1 Hour')); 
			}

			$sig = sha1($this->prefix.':'.$this->secret.':'.$exp);
			$dataArray = array('id'=>$id);

			if(!empty($data)) 
				$dataArray = array_merge($dataArray, $data);

			$token = array('data'=>$dataArray,'auth'=>array('s'=>$sig,'t'=>$exp)); 

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
	* param int $uniqueID Unique Token ID
	* param object $token Token
	* return string
	* throws Exception
	*/
	public function save($uID, $token) {
		try {
			$dToken = $this->decodeToken($token);
			
			if(!empty($dToken['auth']['t'])) {
				try {
					/*if(self::$dataHelper->find('id', array('token'=>$token), $this->tables['tokens'])) {
						self::$dataHelper->query("UPDATE ".$this->tables['tokens']." SET revoked='1' WHERE u_id=:uID AND token=:t",array(':uID'=>$uID,':t'=>$token));
					}*/

					if(self::$db->query("INSERT INTO ".TOKENS." SET token=:token,u_id=:uID,exp_date=:eDate",array(':token'=>$token,':uID'=>$uID,':eDate'=>$dToken['auth']['t']))) {
						return 'Saved';
					} 	
				} catch (Excpetion $e) {
					throw $e;
				}
			} else {
				throw new Excpetion('Auth containes no expiration time', 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Revokes a token pair
	*
	* param string $token Token
	* param int $id Unique ID
	* return bool
	* throws Exception
	*/
	public function revoke($token) {
		try {
			if(self::$db->beginTransaction()) {
				if(self::$db->find('id', array('token'=>$token), TOKENS)) {
					if(self::$db->query("UPDATE ".TOKENS." SET revoked='1' WHERE token=':t'",array(':t'=>$token))) {
						if(self::$db->commit()) {
							return 'Token Revoked';
						}
					}
				}
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets a token for Unique ID
	*
	* param int $id Unique ID
	* return Object
	* throws Exception 
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
	* param int $id Token ID
	* return Object
	* throws Exception
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
	* param int $sinceID Since ID
	* param int $maxID Max ID
	* param int $limit Limit
	* param string $deleted Deleted
	* return array
	* throws Exception
	*/
	public function getTokens($sinceID=0, $maxID=0, $limit, $deleted) {
		try {
			$o = self::$db->getOffset(TOKENS, 'id', $sinceID, $maxID, $deleted, $limit);

			if($results =self::$db->query("SELECT * FROM ".TOKENS." WHERE ".$o[0],$o[1])) {
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
	* param object $token Token
	* return Object
	*/
	private function getTokenData($token) {
		if(!empty($token['date'])) 
			$token['string_date'] = formatDate($token['date']);

		if(!empty($token['exp_date']))
			$token['string_exp_date'] = formatDate($token['exp_date']);

		return $token;
	}

	/**
	* Checks for mandatory token configs
	*
	* return bool
	*/
	private function checkTokenConfigs() {
		if(!empty($this->prefix) && !empty($this->secret)) {
			return true;
		}
		return false;
	}
}
?>