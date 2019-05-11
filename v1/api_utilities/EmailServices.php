<?php
/**
* Email Services Class
*
* @version 1.0
*/

require_once('APIHelper.php');

class EmailServices extends APIHelper {

	/**
	* Constructor
	*
	* @param bool $expose Expose API functions
	* @return void
	*/
	public function __construct($expose=false) {
		parent::__construct();

		if($expose) {
			$this->exposeAPI();
		}
	}

	/**
	* Exposes API functions
	*
	* @return void
	*/
	public function exposeAPI() {
		// templates
		Router::get('/email/templates', function($req, $res) {
			try {
				$res->output($this->getEmailTemplates());
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_templates');

		Router::get('/emails/templates/count/number', function($req, $res) {
			try {
				$res->output($this->getEmailTemplatesTotal(@$_GET['deleted']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_templates');

		Router::get('/email/template/:id', function($req, $res) {
			try {
				$res->output($this->getTemplate($req->params['id']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_templates');

		Router::delete('/email/template/:id', function($req, $res) {
			try {
				$res->output($this->deleteTemplate($req->params['id']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_templates');

		Router::put('/email/template', function($req, $res) {
			try {
				$res->output($this->createTemplate());
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_templates');

		Router::delete('/email/template/:id', function($req, $res) {
			try {
				$res->output($this->deleteTemplate($req->params['id']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_templates');

		// subscriptions

		Router::put('/email/subscribe/:email', function($req, $res) {
			try {
				$res->output($this->addSubscriber($req->params['email']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_subscriptions');

		Router::delete('/email/unsubscribe/:email', function($req, $res) {
			try {
				$res->output($this->removeSubscriber($req->params['email']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_subscriptions');

		Router::get('/email/subscriptions', function($req, $res) {
			try {
				$res->output($this->getEmailSubscribers(0,0,30));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_subscriptions');

		Router::get('/email/subscription/:id', function($req, $res) {
			try {
				$res->output($this->getEmailSubscriber($req->params['id']));
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_subscriptions');

		Router::get('/email/subscriptions/search/:query', function($req, $res) {
			try {
				$res->output($this->searchEmails($req->params['query'])); 
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_subscriptions');

		Router::get('/email/subscriptions/count/number', function($req, $res) {
			try {
				$res->output($this->getEmailSubscriptionTotal());
			} catch (Exception $e) {
				$res->output($e);
			}
		}, 'email_subscriptions');
	}

	/**
	* Saves an email template
	*
	* @param string $name Template Name
	* @param string $template Email Template
	* @param int $userID User ID
	* @return string
	* @throws Exception
	*/
	public function createTemplate($name, $template, $userID) {
		try {
			if(!empty(@$this->tables['email_templates'])) {
				if(!self::$dataHelper->find('id', array('body'=>@$template,'name'=>@$name), $this->tables['email_templates'],'Email Template')) {
					if(self::$dataHelper->query("INSERT INTO ".$this->tables['email_templates']." SET name=:n,body=:b,user_id=:uID",array(':n'=>$name,
						':b'=>$template,
						':uID'=>$userID))) {

						if(self::$dataHelper->query("INSERT INTO ".$this->tables['email_templates']." SET name=:n,body=:b,user_id=:uID,template_id=:tID", array(
								':tID'=>self::$dataHelper->insertID,
								':n'=>$name,
								':b'=>$template,
								':uID'=>$userID))) {

							return 'Template Created';
						}
					}
				}
			} else {
				throw new Exception("Email Templates Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Edits a template
	*
	* @param string $name Template Name
	* @param string $template Email Template
	* @param int $userID User ID
	* @return string
	* @throws Exception
	*/
	public function editTemplate($templateID, $name, $template, $userID) {
		try {
			if(!empty(@$this->tables['email_template_edits']) && !empty(@$this->tables['email_templates'])) {
				if(self::$dataHelper->beginTransaction()) {
					if($r = self::$dataHelper->find('id', array('id'=>@$templateID), $this->tables['email_templates'],'Email Template')) {
						if(self::$dataHelper->query("INSERT INTO ".$this->tables['email_template_edits']." SET user_id=:uID,body=:b,name=:n,template_id=:tID",array(':tID'=>$r['id'],':n'=>$name,':b'=>$template,':uID'=>$userID))) {
							if(self::$dataHelper->query("UPDATE ".$this->tables['email_templates']." SET body=:b,name=:n WHERE id=:tID",array(':tID'=>$templateID,':n'=>$name,':b'=>$template))) {
								if(self::$dataHelper->commit()) {
									return 'Template Edited';
								}
							}
						}
					}
				}
			} else {
				throw new Exception("Email Templates or Email Template Edits Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets email template edots
	*
	* @param int $templateID Template ID
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param int $limit fetch Limit
	* @param string $deleted Deleted Status
	* @return array
	* @throws Exception
	*/
	public function getEmailTemplateEdits($templateID, $sinceID=0, $maxID=0, $limit, $deleted) {
		try {
			if(!empty(@$this->tables['email_template_edits'])) {
				$o = self::$dataHelper->getOffset($this->tables['email_template_edits'], 'id', $sinceID, $maxID, @$deleted, @$limit);
				
				$queryString = "SELECT * FROM ".$this->tables['email_template_edits'].' WHERE template_id=:tID AND ';
				$params = array(':tID'=>$templateID);

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
			} else {
				throw new Exception("Email Template Edits Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Deletes an email template
	*
	* @param int $templateID Template id
	* @return string
	* @throws Exception
	*/
	public function deleteTemplate($templateID) {
		try {
			if(!empty(@$this->tables['email_templates'])) {
				if(self::$dataHelper->find('*', array('id'=>@$templateID,'deleted'=>'0'), $this->tables['email_templates'],'Email Template')) {
					if(self::$dataHelper->query("UPDATE ".$this->tables['email_templates']." SET deleted='1' WHERE id=:id",array(':id'=>$templateID))) {
						return 'Template Deleted';
					}
				}
			} else {
				throw new Exception("Email Templates Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets a template
	*
	* @param int $templateID Template ID
	* @return object
	* @throws Exception
	*/
	public function getTemplate($templateID) {
		try {
			if(!empty(@$this->tables['email_templates'])) {
				if($result = self::$dataHelper->find('*', array('id'=>@$templateID), $this->tables['email_templates'],'Email Template')) {
					return $this->getTemplateData($result);
				}
			} else {
				throw new Exception("Email Templates Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets total number of email templates
	*
	* @param string $deleted Deleted Status
	* @return int
	* @throws Exception
	*/
	public function getTotalEmailTemplates($deleted) {
		try {
			if(!empty(@$this->tables['email_templates'])) {
				$queryString = "SELECT id FROM ".$this->tables['email_templates'];

				$params = array();

				if(!empty(@$deleted)) {
					$queryString .= " AND deleted=:d";
					$params[':d'] = $deleted;
				}

				if($r = self::$dataHelper->query($queryString,$params)) {
					return $r->rowCount();
				}
			} else {
				throw new Exception("Email Templates Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets templates
	*
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param string $deleted Deleted status
	* @return array
	* @throws Exception
	*/
	public function getTemplates($sinceID=0, $maxID=0, $limit=35, $deleted='') {	
		try {
			if(!empty(@$this->tables['email_templates'])) {
				$o = self::$dataHelper->getOffset($this->tables['email_templates'], 'id', $sinceID, $maxID, @$deleted, @$limit);
			
				$queryString = "SELECT * FROM ".$this->emailTemplates.' WHERE ';

				if($result = self::$dataHelper->query($queryString.$o[0],$o[1])) {
					$templates = array();

					while($temp = $result->fetch()) {
						$templates[] = $this->getTemplateData($temp);
					}

					return $templates;
				}
			} else {
				throw new Exception("Email Templates Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
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
	* @param string $template Email template to fill out
	* @return string
	* @throws Exception 
	*/
	public static function fillTemplate($tokens, $template) {
		if(!empty(@$tokens) && !empty(@$template)) {
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

	// Email Subscribers

	/**
	* Gets the total number of email subscribers
	*
	* @param string $unsubscribed Unsubscribe status
	* @return int
	* @throws Exception
	*/
	public function getEmailSubscriptionTotal($unsubscribed=null) {
		try {
			if(!empty(@$this->tables['email_subscriptions'])) {
				$queryString = "SELECT id FROM ".$this->tables['email_subscriptions'];

				if(!empty(@$unsubscribed)) {
					if($unsubscribed == '1' || $unsubscribed == '0') {
						$queryString .= ' WHERE subscriber='.$unsubscribed;
					}
				}

				if($r = self::$dataHelper->query($queryString)) {
					return $r->rowCount();
				}
				
			} else {
				throw new Exception('Email subscriptions does not exist',500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets email subscribers
	*
	* @param int $sinceID Since ID
	* @param int $maxID Max ID
	* @param string $deleted Deleted status
	* @return array
	* @throws Exception
	*/
	public function getEmailSubscribers($sinceID=0, $maxID=0, $limit=35, $deleted='') {
		try {
			if(!empty(@$this->tables['email_subscriptions'])) {
				$queryString = "SELECT * FROM ".$this->tables['email_subscriptions']." WHERE ";

				$o = self::$dataHelper->getOffset($this->tables['email_subscriptions'], 'id', $sinceID, $maxID, @$deleted, @$limit);

				if($r = self::$dataHelper->query($queryString.$o[0],$o[1])) {
					$subs = array();

					while($sub = $r->fetch()) {
						$subs[] = $this->getSubscriptionData($sub);
					}

					return $subs;
				}
			} else {
				throw new Exception('Email subscriptions does not exist',500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Searches email subscribers
	*
	* @param string $query Search query
	* @return array
	* @throws Exception
	*/
	public function searchEmails($query, $offset=0) {
		try {
			if(!empty(@$this->tables['email_subscriptions'])) {
				$queryString = "SELECT * FROM ".$this->tables['email_subscriptions']." WHERE email LIKE CONCAT('%',:q,'%')";
				$params = array(':q'=>$query);

				if(!empty(@$offset)) {
					$queryString .= " OFFSET :offset";
					$params = array(':offset'=>$offset);
				}

				if($results = self::$dataHelper->query($queryString, $params)) {
					$emails = array();

					while($email = $results->fetch()) {
						$emails[] = $this->getSubscriptionData($email);			
					}

					return $emails;
				}
			} else {
				throw new Exception("Email Subscriptions Table does not exist", 500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets subsciption data
	*
	* @param object $sub Subscription object
	* @return object
	*/
	private function getSubscriptionData($sub) {
		if(!empty($sub['date']))
			$sub['string_date'] = $this->formatDate($sub['date']);

		return $sub;
	}

	/**
	* Adds a email subscriber
	*
	* @param string $email Email to add
	* @return string
	* @throws Exception
	*/
	public function addSubscriber($email) {
		try {
			if(!empty(@$this->tables['email_subscriptions'])) {
				if($r = self::$dataHelper->find('id', array('email'=>@$email), $this->tables['email_subscriptions'])) {
					self::$dataHelper->query('UPDATE '.$this->tables['email_subscriptions']." SET subscriber='1' WHERE id=:id",array(':id'=>$r['id']));
				} else {
					self::$dataHelper->query("INSERT INTO ".$this->tables['email_subscriptions']." SET email=:e,subscriber=1",array(':e'=>$email));
				}
				return 'Subscribed';
			} else {
				throw new Exception('Email subscriptions does not exist',500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Removes an email subscriber
	*
	* @param string $email Email to remove
	* @return string
	* @throws Exception
	*/
	public function removeSubscriber($email) {
		try {
			if(!empty(@$this->tables['email_subscriptions'])) {
				if($r = self::$dataHelper->find('id', array('email'=>@$email), $this->tables['email_subscriptions'])) {
					if(self::$dataHelper->query('UPDATE '.$this->tables['email_subscriptions']." SET subscriber='0' WHERE id=".$r['id'])) {
						return 'Unsubscribed';
					}
				}
				
			} else {
				throw new Exception('Email subscriptions table does not exist',500);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}
}
?>
