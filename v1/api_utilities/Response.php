<?php
/**
* Response Class
*
* @copyright Foose Industries
* @version 1.0
*/
class Response {
	public $data;
	public $status;
	public static $errorArray = array(
		200=>'OK',
		201=>'Created',
		204=>'No Content',
		304=>'Not Modifed',
		400=>'Bad Request',
		401=>'Unauthorized',
		403=>'Forbidden',
		404=>'Not Found',
		405=>'Method Not Allowed',
		409=>'Conflict',
		500=>'Internal Server Error'
	);

	public function __construct() {
        $get_arguments = func_get_args();
        $number_of_arguments = func_num_args();

        if(method_exists($this, $method_name = '__construct'.$number_of_arguments)) {
            call_user_func_array(array($this, $method_name), $get_arguments);
        }
    }

    public function __construct1($status) {
    	$this->status = $status;
    	$this->data = $this->getMessage();
    }

    public function __construct2($status, $data) {
		$this->status = $status;
		$this->data = $data;
    }

    /**
    * Gets a messages for status code
    *
    * @return string
    */
	public function getMessage() {
		if(self::$errorArray[$this->status] != null) {
			return self::$errorArray[$this->status];
		} 
	}
}
?>
