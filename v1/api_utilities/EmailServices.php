<?php
/**
* Email Services Class
*
* @version 1.0
*/

require_once('./Constants.php');
require_once('DatabaseHelper.php');

class EmailServices {

	protected static $dataHelper;

	/**
	* Constructor
	*/
	public function __construct() {
		self::$dataHelper = new DatabaseHelper(URL,USER,PASSWORD,DB);
	}

	/**
	* Saves an email template
	*
	* @param $name string Template Name
	* @param $template string Email Template
	* @param $userID int User ID
	* @return string | Exception
	*/
	public function createTemplate($name, $template, $userID) {
		if(!empty(EMAIL_TEMPLATES)) {
			if(!self::$dataHelper->find('id','body,name',$template.','.$name,EMAIL_TEMPLATES,'Email Template')) {
				if(self::$dataHelper->query("INSERT INTO ".EMAIL_TEMPLATES." SET name=:n,body=:b,user_id=:uID",array(':n'=>$name,':b'=>$template,':uID'=>$userID))) {
					return 'Template Created';
				}
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception("Email Templates Table does not exist", 500);
	}

	/**
	* Edits a template
	*
	* @param $name string Template Name
	* @param $template string Email Template
	* @param $userID int User ID
	* @return string | Exception
	*/
	public function editTemplate($templateID, $name, $template, $userID) {
		if(!empty(EMAIL_TEMPLATES)) {
			if(self::$dataHelper->beginTransaction()) {
				if($r = self::$dataHelper->find('id','id',$templateID,EMAIL_TEMPLATES,'Email Template')) {
					if(self::$dataHelper->query("INSERT INTO ".EMAIL_TEMPLATE_EDITS." SET user_id=:uID,body=:b,name=:n,template_id=:tID",array(':tID'=>$r['id'],':n'=>$name,':b'=>$template,':uID'=>$userID))) {
						if(self::$dataHelper->query("UPDATE ".EMAIL_TEMPLATES." SET body=:b,name=:n WHERE id=:tID",array(':tID'=>$templateID,':n'=>$name,':b'=>$template))) {
							if(self::$dataHelper->commit()) {
								return 'Template Edited';
							}
						}
					}
				}
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception("Email Templates Table does not exist", 500);
	}

	/**
	* Gets email template edots
	*
	* @param $templateID int Template ID
	* @param $sinceID int Since ID
	* @param $maxID int Max ID
	* @param $limit int fetch Limit
	* @param $deleted string Deleted Status
	* @return array | Exception
	*/
	public function getEmailTemplateEdits($templateID, $sinceID, $maxID, $limit, $deleted) {
		$o = self::$dataHelper->getOffset($sinceID,$maxID,EMAIL_TEMPLATE_EDITS,'id',$limit);
		
		$queryString = "SELECT * FROM ".EMAIL_TEMPLATE_EDITS.' WHERE template_id=:tID AND ';

		$params = array(':tID'=>$templateID);

		if(!empty($deleted)) { 
			$queryString .= 'deleted=:d ';
			$params[':d'] = $deleted;
		}

		if($r = self::$dataHelper->query($queryString.$o[0],array_merge($params,$o[1]))) {
			$templates = array();

			while($t = $r->fetch()) {
				try {
					$templates[] = $this->getTemplateData($t);
				} catch(Exception $e) {
					throw $e;
				}
			}

			return $templates;
		}
		throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
	}

	/**
	* Deletes an email template
	*
	* @param $templateID int Template id
	* @return string | Exception
	*/
	public function deleteTemplate($templateID) {
		if(!empty(EMAIL_TEMPLATES)) {
			if(self::$dataHelper->find('*','id,deleted',$templateID.',0',EMAIL_TEMPLATES,'Email Template')) {
				if(self::$dataHelper->query("UPDATE ".EMAIL_TEMPLATES." SET deleted='1' WHERE id=:id",array(':id'=>$templateID))) {
					return 'Template Deleted';
				}
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception("Email Templates Table does not exist", 500);
	}

	/**
	* Gets a template
	*
	* @param $templateID int Template ID
	* @return object | Exception
	*/
	public function getTemplate($templateID) {
		if(!empty(EMAIL_TEMPLATES)) {
			if($result = self::$dataHelper->find('*','id',$templateID,EMAIL_TEMPLATES,'Email Template')) {
				return $this->getTemplateData($result);
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception("Email Templates Table does not exist", 500);
	}

	/**
	* Gets total number of email templates
	*
	* @param $deleted string Deleted Status
	* @return int
	*/
	public function getTotalEmailTemplates($deleted) {
		$queryString = "SELECT id FROM ".EMAIL_TEMPLATES;

		$params = array();

		if(!empty($deleted)) {
			$queryString .= " AND deleted=:d";
			$params[':d'] = $deleted;
		}

		if($r = self::$dataHelper->query($queryString,$params)) {
			return $r->rowCount();
		}
		throw new Exception(self::$dataHelper->errorMessage, self::$dataHelper->errorCode);
	}

	/**
	* Gets templates
	*
	* @param $sinceID int Since ID
	* @param $maxID int Max ID
	* @param $deleted string Deleted status
	* @return array | exception
	*/
	public function getTemplates($sinceID=0, $maxID=0, $limit, $deleted) {	
		if(!empty(EMAIL_TEMPLATES)) {
			$o = self::$dataHelper->getOffset($sinceID,$maxID,EMAIL_TEMPLATES,'id',$limit);
		
			$queryString = "SELECT * FROM ".EMAIL_TEMPLATES.' WHERE ';

			if(!empty($deleted)) 
				$queryString .= 'deleted='.$deleted.' AND ';

			if($result = self::$dataHelper->query($queryString.$o[0],$o[1])) {
				$templates = array();

				while($temp = $result->fetch()) {
					$templates[] = $this->getTemplateData($temp);
				}

				return $templates;
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception("Email Templates Table does not exist", 500);
	}

	/**
	* Gets data for a template object
	*
	* @param object $template Email Template object
	* @return object
	*/
	public function getTemplateData($template) {
		if(!empty($template['date']))
			$template['string_date'] = formatDate($template['date']);

		//if(!empty(@$template['body']))
			//$template['body'] = htmlspecialchars_decode($template['body'], ENT_QUOTES);

		return $template;
	}

	/**
	* Fills out an email template with supplied tokens
	*
	* @param array $tokens Tokens to fill
	* @param $template string Email template to fill out
	* @return string | exception 
	*/
	public static function fillTemplate($tokens, $template) {
		if(!empty($tokens) && !empty($template)) {
			$pattern = '{{%s}}';
		
			$map = array();

			foreach ($tokens as $var => $value) {
				$map[sprintf($pattern, $var)] = $value;
			}

			return $output = strtr($template, $map);
		} else {
			throw new Exception('Template and or Tokens cannot be empty', 500);
		}
	}

	// subscirbers

	/**
	* Gets the total number of email subscribers
	*
	* @param $unsubscribed string Unsibscribe status
	* @return int | exception
	*/
	public function getEmailSubscriptionTotal($unsubscribed) {
		if(!empty(EMAIL_SUBSCRIPTIONS)) {
			$queryString = "SELECT id FROM ".EMAIL_SUBSCRIPTIONS;

			if(!empty(@$unsubscribed)) {
				if($unsubscribed == '1' || $unsubscribed == '0') {
					$queryString .= ' WHERE subscriber='.$unsubscribed;
				}
			}

			if($r = self::$dataHelper->query($queryString)) {
				return $r->rowCount();
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception('Email subscriptions does not exist',500);
	}

	/**
	* Gets email subscribers
	*
	* @param $sinceID int Since ID
	* @param $maxID int Max ID
	* @param $deleted string Deleted status
	* @return array | Exception
	*/
	public function getEmailSubscribers($sinceID, $maxID, $limit, $deleted='') {
		if(!empty(EMAIL_SUBSCRIPTIONS)) {
			$queryString = "SELECT * FROM ".EMAIL_SUBSCRIPTIONS;

			$o = self::$dataHelper->getOffset($sinceID,$maxID,EMAIL_SUBSCRIPTIONS,'id',$limit);
	
			if(!empty($deleted)) {
				$queryString .= 'deleted=:d AND ';
				$o[1][':d'] = $deleted;
			}

			if($r = self::$dataHelper->query($queryString.$o[0],$o[1])) {
				$subs = array();

				while($sub = $r->fetch()) {
					$subs[] = getSubscriptionData($sub);
				}

				return $subs;
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception('Email subscriptions does not exist',500);
	}

	/**
	* Gets subsciption data
	*
	* @param $sub object Subscription object
	* @return object
	*/
	private function getSubscriptionData($sub) {
		if(!empty($sub['date']))
			$sub['string_date'] = formatDate($sub['date']);

		return $sub;
	}

	/**
	* Adds a email subscriber
	*
	* @param $email string Email to add
	* @return string | exception
	*/
	public function addSubscriber($email) {
		if(!empty(EMAIL_SUBSCRIPTIONS)) {
			if($r = self::$dataHelper->find('id','email',$email,EMAIL_SUBSCRIPTIONS)) {
				if(self::$dataHelper->query('UPDATE '.EMAIL_SUBSCRIPTIONS." SET subscriber='1' WHERE id=".$r['id'])) {
					return 'Subscribed';
				}
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception('Email subscriptions does not exist',500);
	}

	/**
	* Removes an email subscriber
	*
	* @param $email string Email to remove
	* @return string | exception
	*/
	public function removeSubscriber($email) {
		if(!empty(EMAIL_SUBSCRIPTIONS)) {
			if($r = self::$dataHelper->find('id','email',$email,EMAIL_SUBSCRIPTIONS)) {
				if(self::$dataHelper->query('UPDATE '.EMAIL_SUBSCRIPTIONS." SET subscriber='0' WHERE id=".$r['id'])) {
					return 'Unsubscribed';
				}
			}
			throw new Exception(self::$dataHelper->errorMessage,self::$dataHelper->errorCode);
		}
		throw new Exception('Email subscriptions does not exist',500);
	}
}
?>
