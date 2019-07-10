<?php
/**
* Request Class
*
* copyright Foose Industries
* version 1.0
*/

class Request {
	/**
	* var string $endpoint Current API Endpoint
	*/
	public $endpoint = '';

	/**
	* var string $format Format for data to be returned to client in
	*/
	public $format = 'json';

	/**
	* var array $headers Headers to be sent backto the client
	*/
	public $headers = array();

	/**
	* pvar array $params Parameters that are sent from the client, $_GET params and or URL substitutions
	*/
	public $params = array();

	/**
	* var array $body Request body data sent from client
	*/
	public $body = array();

	/**
	* var array $token Decoded token data for authentication
	*/
	public $token;

	/**
	* Main Constructor
	*
	* param array $params Request URL Params
	* param array $body Request Body
	* param array $headers Request headers
	* param string $format Response format
	* param string $token Request token (optional)
	* return void
	*/
	public function __construct($params=null, $body=null, $headers=null, $format='json', $token=null) {
		if(!empty($params)) {
			$this->params = $params;
		}

		if(!empty($body)) {
			$this->body = $body;
		}

		if(!empty($headers)) {
			$this->headers = $headers;
		}

		if(!empty($format)) {
			$this->format = $format;
		}

		if(!empty($token)) {
			$this->token = $token;
		}
	}

}
?>
