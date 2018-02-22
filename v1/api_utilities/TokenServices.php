<?php
include_once('DatabaseHelper.php');
include_once('utilities.php');
include_once('Response.php');
include_once('./Constants.php');

/**
* Token Services 
*
* @version 1.0
*/
class TokenServices {
	protected static $dataHelper;

	/**
	* Constructor
	*
	* @return void
	*/
	public function __construct() {
		self::$dataHelper = new DatabaseHelper(URL,USER,PASSWORD,DB);
	}

	/**
	* Decodes a token
	*
	* @param $token Object Token
	* @return array
	*/
	public static function decodeToken($token) {
		$token = base64_decode($token);
		$token = json_decode($token,true);

		return $token;
	}

	/**
	* validates a token, non database
	*
	* @param $token String Token
	* @return Bool | Token
	*/
	public static function validate($token) {
		$token = self::decodeToken($token);

		$sig = sha1(PREFIX.':'.TOKEN_SECRET.':'.$token['auth']['t']);

		if($sig === $token['auth']['s']) {
			$today = date("Y:m:d H:i:s");

			if($today < $token['auth']['t']){
			    return $token;
			} 
		}
		return false;
	}

	/**
	* Validates a refresh token
	*
	* @param $rToken string Refresh Token
	* @return bool
	*/
	public function validateRefreshToken($rToken) {
		$token = self::decodeToken($rToken);

		$sig = sha1(PREFIX.':'.TOKEN_SECRET.':'.$token['auth']['t']);

		if($sig === $token['auth']['s']) {
			$today = date("Y:m:d H:i:s");

			if($today < $token['auth']['t']){
			    if(self::$dataHelper->find('id','token,revoked',$rToken.',0',TOKENS,'Token')) {
			    	return true;
			    } 
			} 
		}
		return false;
	}

	/**
	* Refreshes a token
	*
	* @param $rToken string Refresh Token
	* @return array | Exception
	*/
	public function refreshToken($rToken) {
		if(self::validateRefreshToken($rToken)) {
			if($dToken = self::decodeToken($rToken)) {
				if(!empty(@$dToken['data']['id'])) {
					$userID = $dToken['data']['id'];

					if(self::$dataHelper->find('id','token,u_id,revoked',$rToken.','.$userID.',0',TOKENS,'Token')) {
						if(self::$dataHelper->beginTransaction()) {
							try {
								$newRToken = $this->createRefreshToken($userID,$dToken['data']);
								$newDToken = self::decodeToken($newRToken);

								if(self::$dataHelper->commit()) {
									return array('token'=>$this->createToken($userID,$dToken['data']),'refresh_token'=>$newRToken);
								}
								
							} catch(Excpetion $ex) {
								throw $ex;
							}	
						}
					}
				}
			}
			throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
		}
		throw new Exception('Invalid Refresh Token',401);
	}

	/**
	* Creates a token
	*
	* @param $id String Unique ID 
	* @param $data array body data array
	* @return string
	*/
	public static function createToken($id, $data=null) {
		return self::create($id,$data);
	}

	/**
	* Creates a refresh token
	* @param $id String Unique ID 
	* @param $data array body data array
	* @return string
	*/
	public function createRefreshToken($id, $data=null) {
		$token = $this->create($id,$data,true);

		try {
			$this->save($id,$token);
			return $token;
		} catch(Excpetion $e) {
			throw new Exception($e->getMessage(), $e->getCode());
		}
	}

	/**
	* creates a token
	*
	* @param $id String Unique ID 
	* @param $data array body data array
	* @param $refresh Bool Generate a refresh token
	* @return String
	*/
	private static function create($id, $data=null, $refresh=false) {
		$exp = date('Y:m:d H:i:s', strtotime('+5 Minutes'));

		if($refresh) {
			$exp = date('Y:m:d H:i:s', strtotime('+1 Hour')); 
		}

		$sig = sha1(PREFIX.':'.TOKEN_SECRET.':'.$exp);
		$dataArray = array('id'=>$id);

		if(!empty($data)) 
			$dataArray = array_merge($dataArray, $data);

		$token = array('data'=>$dataArray,'auth'=>array('s'=>$sig,'t'=>$exp)); 

		$token = json_encode($token);
		$token = base64_encode($token);

		return $token;
	}

	/**
	* Saves a token
	*
	* @param $uniqueID int Unique Token ID
	* @param $token Object Token
	* @return string | Response
	*/
	public function save($uID, $token) {
		$dToken = self::decodeToken($token);
		
		if(!empty(@$dToken['auth']['t'])) {
			if(self::$dataHelper->query("UPDATE ".TOKENS." SET revoked='1' WHERE u_id=:uID",array(':uID'=>$uID))) { //->find('id','u_id',$uID,TOKENS,'Token')) {
				if(self::$dataHelper->query("INSERT INTO ".TOKENS." SET token=:token,u_id=:uID,exp_date=:eDate",array(':token'=>$token,':uID'=>$uID,':eDate'=>$dToken['auth']['t']))) {
					return 'Saved';
				} else {
					throw new Excpetion(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
				}
			} else {
				throw new Excpetion(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
			}
		} else {
			throw new Excpetion('Auth containes no expiration time');
		}
	}

	/**
	* Revokes a token pair
	*
	* @param string $token Token
	* @param int $id Unique ID
	* @return boolean
	*/
	public function revoke($token) {
		if(self::$dataHelper->beginTransaction()) {
			if(self::$dataHelper->find('id','token',$token,TOKENS)) {
				if(self::$dataHelper->query("UPDATE ".TOKENS." SET revoked='1' WHERE token=':t'",array(':t'=>$token))) {
					if(self::$dataHelper->commit()) {
						return 'Token Revoked';
					}
				}
			}
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	}

	/**
	* Gets a token for id
	*
	* @param $id Int Unique ID
	* @return Object 
	*/
	public function getTokenUnique($uID) {
		if($result = self::$dataHelper->find('*','u_id',$uID,TOKENS)) {
			return self::getTokenData($result);
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	}

	/**
	* Gets a token for id
	*
	* @param $id Int Token ID
	* @return
	*/
	public function getToken($id) {
		if($result = self::$dataHelper->find('*','id',$id,TOKENS)) {
			return self::getTokenData($result);
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
 	}

 	/**
	* Gets all tokens
	*
	* @return array | Error
	*/
	public function getTokens() {
		$o = DatabaseHelper::getOffset(getSinceID(),getMaxID(),TOKENS,'id',getLimit());

		if($results = self::$dataHelper->query("SELECT * FROM ".TOKENS." WHERE ".$o[0],$o[1])) {
			$tokens = array();

			while($token = $results->fetch()) {
				$tokens[] = self::getTokenData($token);
			}

			return $tokens;
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	}

	/**
	* Gets token data
	*
	* @param $token Object Token
	* @return Object
	*/
	private function getTokenData($token) {
		if(!empty($token['date'])) 
			$token['string_date'] = formatDate($token['date']);

		if(!empty($token['exp_date']))
			$token['string_exp_date'] = formatDate($token['exp_date']);

		return $token;
	}
}
?>
