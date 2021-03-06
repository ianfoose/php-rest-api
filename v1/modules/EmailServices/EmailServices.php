<?php
/**
* Email Services Class
*
* @version 1.0
*/

require_once(dirname(dirname(dirname(__FILE__))).'/api_utilities/APIHelper.php');

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
			$res->send($this->getTemplates($this->getRequestValue($_GET, 'deleted', 0), $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
		}, 'email_templates');

		Router::get('/email/templates/count/number', function($req, $res) {
			$res->send($this->getTotalEmailTemplates($this->getRequestValue($_GET, 'deleted', 0)));
		}, 'email_templates');

		Router::get('/email/template/:id', function($req, $res) {
			$res->send($this->getTemplate($req->params['id']));
		}, 'email_templates');

		Router::delete('/email/template/:id', function($req, $res) {
			$res->send($this->deleteTemplate($req->params['id']));
		}, 'email_templates');

		Router::put('/email/template', function($req, $res) {
			$res->send($this->createTemplate($req->body['name'], $req->body['template'], $req->body['user_id']));
		}, 'email_templates');

		Router::delete('/email/template/:id', function($req, $res) {
			$res->send($this->deleteTemplate($req->params['id']));
		}, 'email_templates');

		// subscriptions

		Router::put('/email/subscribe/:email', function($req, $res) {
			$res->send($this->addSubscriber($req->params['email'], $req->body['group']));
		}, 'email_subscriptions');

		Router::delete('/email/unsubscribe/:email', function($req, $res) {
			$res->send($this->removeSubscriber($req->params['email'], $req->body['group']));
		}, 'email_subscriptions');

		Router::get('/email/subscriptions', function($req, $res) {
			$filters = array('group'=>$this->getRequestValue($_GET, 'group', ''),
				'subscriber'=>$this->getRequestValue($_GET, 'subscriber', ''),
				'deleted'=>$this->getRequestValue($_GET, 'deleted', ''));

			$res->send($this->getEmailSubscribers($filters, $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
		}, 'email_subscriptions');

		Router::get('/email/subscription/:id', function($req, $res) {
			$res->send($this->getEmailSubscriber($req->params['id']));
		}, 'email_subscriptions');

		Router::get('/email/subscriptions/search/:query', function($req, $res) {
			$filters = array('group'=>$this->getRequestValue($_GET, 'group', ''),
				'subscriber'=>$this->getRequestValue($_GET, 'subscriber', ''),
				'deleted'=>$this->getRequestValue($_GET, 'deleted', ''));

			$res->send($this->searchEmails($req->params['query'], $filters, $this->getQueryDirection(), $this->getQueryOffset(), $this->getQueryLimit()));
		}, 'email_subscriptions');

		Router::get('/email/subscriptions/count/number', function($req, $res) {
			$filters = array('group'=>$this->getRequestValue($_GET, 'group', ''),
				'subscriber'=>$this->getRequestValue($_GET, 'subscriber', ''),
				'deleted'=>$this->getRequestValue($_GET, 'deleted', ''));

			$res->send($this->getEmailSubscriptionTotal($filters));
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
			if(!self::$db->find('id', array('body'=>$template,'name'=>$name), EMAIL_TEMPLATES, 'Email Template')) {
				self::$db->query("INSERT INTO ".EMAIL_TEMPLATES." SET name=:n,body=:b,user_id=:uID", array(':n'=>$name,
				':b'=>$template,
				':uID'=>$userID));

				self::$db->query("INSERT INTO ".EMAIL_TEMPLATE_EDITS." SET name=:n,body=:b,user_id=:uID,template_id=:tID", array(
					':tID'=>self::$db->insertID,
					':n'=>$name,
					':b'=>$template,
					':uID'=>$userID));

				return $this->getTemplate($templateID);
			}
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Edits a template
	*
	* @param int $templateID Template ID
	* @param string $name Template Name
	* @param string $template Email Template
	* @param int $userID User ID
	* @return string
	* @throws Exception
	*/
	public function editTemplate($templateID, $name, $template, $userID) {
		try {
			$r = self::$db->find('id', array('id'=>$templateID), EMAIL_TEMPLATES,'Email Template');
			self::$db->query("INSERT INTO ".EMAIL_TEMPLATE_EDITS." SET user_id=:uID,body=:b,name=:n,template_id=:tID",array(':tID'=>$r['id'],':n'=>$name,':b'=>$template,':uID'=>$userID));

			self::$db->query("UPDATE ".EMAIL_TEMPLATES." SET body=:b,name=:n WHERE id=:tID",array(':tID'=>$templateID,':n'=>$name,':b'=>$template));
				
			return $this->getTemplate($templateID);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets email template edits
	*
	* @param int $templateID Template ID
        * @param string $order Pagination order
	* @param int $offset Pagination offset
	* @param int $limit Pagination Limit
	* @return array
	* @throws Exception
	*/
	public function getEmailTemplateEdits($templateID, $direction='ASC', $offset=0, $limit=40) {
		try {
			$queryString = "SELECT * FROM ".EMAIL_TEMPLATE_EDITS.' WHERE template_id=:tID AND ';
			$params = array(':tID'=>$templateID, ':offset'=>$offset, ':limit'=>$limit);

			$r = self::$db->query($queryString." ORDER BY id $direction LIMIT :offset,:limit", $params);
			$templates = array();

			while($t = $r->fetch()) {
				try {
					$templates[] = $this->getTemplateData($t);
				} catch(Exception $e) {
					throw $e;
				}
			}

			return $templates;
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
			self::$db->find('*', array('id'=>$templateID, 'deleted'=>'0'), EMAIL_TEMPLATES, 'Email Template');
			self::$db->query("UPDATE ".EMAIL_TEMPLATES." SET deleted='1' WHERE id=:id", array(':id'=>$templateID));
			return $this->getTemplate($templateID);
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
			$result = self::$db->find('*', array('id'=>$templateID), EMAIL_TEMPLATES, 'Email Template');
			return $this->getTemplateData($result);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets total number of email templates
	*
	* @param string $deleted Deleted flag
	* @return int
	* @throws Exception
	*/
	public function getTotalEmailTemplates($deleted='') {
		try {
			$queryString = "SELECT id FROM ".EMAIL_TEMPLATES;
			$params = array();

			if(!empty($deleted)) {
				$queryString .= " AND deleted=:d";
				$params[':d'] = $deleted;
			}

			$r = self::$db->query($queryString, $params);
			return $r->rowCount();
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets templates
	*
	* @param string $deleted Deleted flag
	* @param string $order Pagination order
	* @param int $offset Pagination offset
	* @param int $limit Pagination limit
	* @return array
	* @throws Exception
	*/
	public function getTemplates($deleted='', $direction='ASC', $offset=0, $limit=40) {
		try {
			$queryString = "SELECT * FROM ".EMAIL_TEMPLATES.' WHERE ';
			$params = array(':deleted'=>$deleted, ':offset'=>$offset, ':limit'=>$limit);

			$result = self::$db->query($queryString." deleted=:deleted ORDER BY id $direction LIMIT :offset,:limit", $params);
			$templates = array();

			while($temp = $result->fetch()) {
				$templates[] = $this->getTemplateData($temp);
			}

			return $templates;
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
	public static function fillTemplate($tokens, $template, $delimeter='{{}}') {
		if(empty($tokens)) {
			throw new Exception('Substitution tokens cannot be empty!', 404);
		} else {
			if(!is_array($tokens)) {
				throw new Exception('Substitution tokens must be in a key value array!', 500);
			}
		}

		if(empty($template)) {
			throw new Exception('Template body cannot be empty!', 404);
		}


		// delimeters
		$delimeters = str_split($delimeter, (strlen($delimeter)/2));
		$forwardDelim = $delimeters[0];
		$backDelim = $delimeters[1];

		if(empty($forwardDelim) || empty($backDelim)) {
			throw new Exception("Delimeters cannot be empty!", 404);
		}

		$pattern = "$forwardDelim%s$backDelim";

		$map = array();

		foreach ($tokens as $var => $value) {
			$map[sprintf($pattern, $var)] = $value;
		}

		return $output = strtr($template, $map);
	}

	// Email Subscribers

	/**
	* Gets the total number of email subscribers
	*
	* @param array $filters Query filters
	* @return int
	* @throws Exception
	*/
	public function getEmailSubscriptionTotal($filters=array()) {
		try {
			$queryString = "SELECT id FROM ".EMAIL_SUBSCRIPTIONS;
            $params = array();

            $filters = array_filter($filters, 'strlen');
            if(!empty($filters)) {
                list($end) = array_keys(array_slice($filters, -1, 1, true));

                foreach($filters as $key => $value) {
                    if(!empty($value)) {

                        if(!strpos($queryString, 'WHERE')) {
                            $queryString .= ' WHERE ';
                        }

                        $queryString .= '`'.$key.'`=:'.$key;
                        $params[':'.$key] = $value;

                        // check if end of array
                        if($key !== $end) {
                            $queryString .= ' AND ';
                        }
                    }
                }
            }

			$r = self::$db->query($queryString, $params);
			return $r->rowCount();
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets email subscribers
	*
	* @param array $filters Query filters
	* @param string $order Pagination order
	* @param int $offset Pagination offset
	* @param string $limit Pagination limit
	* @return array
	* @throws Exception
	*/
	public function getEmailSubscribers($filters=array(), $direction='ASC', $offset=0, $limit=40) {
		try {
			$queryString = "SELECT * FROM ".EMAIL_SUBSCRIPTIONS;
			$params = array(':offset'=>$offset, ':limit'=>$limit);

            $filters = array_filter($filters, 'strlen');
            if(!empty($filters)) {
                list($end) = array_keys(array_slice($filters, -1, 1, true));

                foreach($filters as $key => $value) {
                    if(!empty($value)) {

                        if(!strpos($queryString, 'WHERE')) {
                            $queryString .= ' WHERE ';
                        }

                        $queryString .= '`'.$key.'`=:'.$key;
                        $params[':'.$key] = $value;

                        // check if end of array
                        if($key !== $end) {
                            $queryString .= ' AND ';
                        }
                    }
                }
            }

        	$r = self::$db->query($queryString." ORDER BY id $direction LIMIT :offset,:limit", $params);
			$subs = array();

			while($sub = $r->fetch()) {
				$subs[] = $this->getSubscriptionData($sub);
			}

			return $subs;
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Searches email subscribers
	*
	* @param string $query Search query
	* @param array $filters Query filters
	* @param string $order Pagination order
	* @param int $offset Pagination offset
	* @param int $limit Pagination limit
	* @return array
	* @throws Exception
	*/
	public function searchEmails($query, $filters=array(), $direction='ASC', $offset=0, $limit=40) {
		try {
			$queryString = "SELECT * FROM ".EMAIL_SUBSCRIPTIONS." WHERE email LIKE CONCAT('%',:q,'%')";
			$params = array(':q'=>$query, ':group'=>$group, ':offset'=>$offset, ':limit'=>$limit);

            $filters = array_filter($filters, 'strlen');
            if(!empty($filters)) {
                list($end) = array_keys(array_slice($filters, -1, 1, true));

                foreach($filters as $key => $value) {
                    if(!empty($value)) {

                        if(!strpos($queryString, 'WHERE')) {
                            $queryString .= ' WHERE ';
                        }

                        $queryString .= '`'.$key.'`=:'.$key;
                        $params[':'.$key] = $value;

                        // check if end of array
                        if($key !== $end) {
                            $queryString .= ' AND ';
                        }
                    }
                }
            }

			$results = self::$db->query($queryString." ORDER BY id $direction LIMIT :offset,:limit", $params);
			$emails = array();

			while($email = $results->fetch()) {
				$emails[] = $this->getSubscriptionData($email);			
			}

			return $emails;
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Gets subscription data
	*
	* @param object $sub Subscription object
	* @return object
	*/
	private function getSubscriptionData($sub) {
		if(!empty($sub['date'])) {
			$sub['string_date'] = $this->formatDate($sub['date']);
		}

		return $sub;
	}

	/**
	* Adds a email subscriber
	*
	* @param string $email Email to add
	* @param string $group Group name for email subscription
	* @return string
	* @throws Exception
	*/
	public function addSubscriber($email, $group='Default') {
		try {
			if($r = self::$db->find('id', array('email'=>$email, 'group'=>$group), EMAIL_SUBSCRIPTIONS, false)) {
				self::$db->query('UPDATE '.EMAIL_SUBSCRIPTIONS." SET subscriber='1' WHERE id=:id", array(':id'=>$r['id']));
			} else {
				self::$db->query("INSERT INTO ".EMAIL_SUBSCRIPTIONS." SET email=:e,group=:group,subscriber=1", array(':e'=>$email, ':group'=>$group));
			}
			return 'Subscribed';
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	* Removes an email subscriber
	*
	* @param string $email Email to remove
	* @param string $group Subscription group name
	* @return string
	* @throws Exception
	*/
	public function removeSubscriber($email, $group='Default') {
		try {
			$r = self::$db->find('id', array('email'=>$email, 'group'=>$group), EMAIL_SUBSCRIPTIONS);
			self::$db->query('UPDATE '.EMAIL_SUBSCRIPTIONS." SET subscriber='0' WHERE id=".$r['id']);
			return 'Unsubscribed';
		} catch (Exception $e) {
			throw $e;
		}
	}
}
?>
