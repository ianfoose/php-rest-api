# About
A REST API framework for PHP

## Use

Deploy the contents of the directory, v1 folder and the .htaccess file into the host of your choice.  
The .htaccess file is important to make the api work.

### Install

To make sure that all features are fully functional, build a database and run the script ```default_database.sql```  
in that database to build the required data tables for he framework.

### Constants

Located in the ```Constants.php``` file are the table names and a few other configuration strings required for the framework.  

While most are required some are optional.

### Required Constants

For accessing the database

URL
USER
PASSWORD 
DB 

### Optional Constants

For Email templates and subscriptions

EMAIL_TEMPLATES 
EMAIL_TEMPLATE_EDITS

For Push Notifications

PUSH_UUID 

For Tokens

TOKENS

For Error Logging

ERROR_URL
ERROR_USER 
ERROR_PASSWORD 
ERROR_DB 
ERRORS 

## API File

This file is where all the magic happens.  

Configuration of this file is important and unitented changes or modifcations to some parts may throw errors.  

The api.php file consists of a main class ```API``` which inherits from the ```APIHelper``` class.  

The ```APIHelper``` class takes care of all the processing and setup.

Include all of your other class files here that contain routes for the API.

Specify your scheme path, this is used for url rewriting.
Your scheme should be the path up to the api.php file.  

Example: '/api/'  

Construct the parent class after setting the scheme.

```parent::__construct(); ```

Be sure to turn off error reporting before releasing
Also be sure to set the desired timezone, this is important for proper time formating.  
By default it's set to UTC.

## Cross Origin Support

Put this as the first thing in the constructor.

```php  
Router::set('/',function() {
  header("Access-Control-Allow-Origin: *");
});
```

Put any other routes in the constructor method of the class.

A general catch all route for blank routes.

```php
Router::all('/',function() {
  return 'Chicken In A Shoebox API';
});
```

## API Security

An auth route is provided to do any authentication at the top level,  
such as checking api keys or credentials.

```php
Router::auth(function($req) {
  // check api key or something here...
});

```

Finally...  Run the API!

```php
$api = new API();
$api->run();
```

## DataHelper

The DatabaseHelper class is used to access a mysql database, and uses the PDO class to connect and perform databse operations. 

### Create a new DatabaseHelper Instance  

```php
$dataHelper = new DatabaseHelper($url, $username, $password, $database);
```

### Connecting To The Database

Will either return a db connection or false.

```php
if($db = connect()) {
  // use $db
}
```

### Get the current DB Connection

```php
if($db = $dataHelper->getDB()) {
  // use $db
}
```

### Query The Dataabse

Plain query.  

```php
$dataHelper->query($queryString);
```

Get query result

```php
if($result = $dataHelper->query("SELECT * FROM users")) {
  $result = $result->fetch();
  echo $result['id'];
} else { // error
  echo 'Code: '.$dataHelper->errorCode.' '.$dataHelper->errorMessage;
}
```

Query With Parameters

to prevent SQL injections, use the parameters array to pass parameters to the query.

```php
if($result = $dataHelper->query("SELECT * FROM users WHERE id=:id",array(':id'=>1)) {
  $result = $result->fetch();
  echo $result['id'];
} else { // error
  echo 'Code: '.$dataHelper->errorCode.' '.$dataHelper->errorMessage;
}
```

Error Handling,

To access error data, use the ```errorCode``` property for the associated HTTP status code for the error and  
the ```errorMessage``` for a an description.  

```php
if($result = $dataHelper->query($queryString,$params)) {
  $result->fetch();
} else {
  echo 'Code: '.$dataHelper->errorCode.' '.$dataHelper->errorMessage;
}
```

Finding one row of data,

To find a single row of data use the 'find' method.

```php
if($dataHelper->find('*','id',$id,$tableName,'Not found message')) {
  echo 'item exists';
} else {
  echo 'Code: '.$dataHelper->errorCode.' '.$dataHelper->errorMessage;
}
```

### Transactions

To start a transaction:

```php
$dataHelper->beginTransaction();
```

To rollback a transcation:

```php
$dataHelper->rollback();
```

To commit a transaction:

```php
$dataHelper->commit();
```

### Transactional Query

If a transaction is started and a query fails, the 'DataabseHelper' class with perform a rollback automatically.

```php
if($dataHelper->beginTransaction()) {
  if($users = $dataHelper->query("SELECT id FROM users")) {
    while($user = $users->fetch()) {
      if($dataHelper->query("DELETE FROM useres WHERE id=:id",array(':id'=>$user['id']))) {
        if($dataHelper->commit()) {
          echo 'Query Successful';
        }
      }
    }
  }
}
echo 'Code: '.$dataHelper->errorCode.' '.$dataHelper->errorMessage;
```

## Error Logging


## Utilities

Contains formatting utilities for different data types.  

### Dates

Used for formating a date. 

```php
$stringDate = formatDate($date);
```

A format can be passed as the second parameter for manual formating of the date.  

```php
$stringDate = formatDate($date, 'Y-m-d');
```

Time formatting

```php

```

### File Size

The last parameter, 'suffix' is used for putting a readable suffix on, such as 'mb' or 'GB'.  

```php
$size = formatSize($bytes, true);
```

### Bools

A string representing a bool from an int.

```php
$bool = getStringBoolFromInt(1);
```

A string value from a boolean.  

```php
$bool = getStringBoolFromBool(true);
```

A boolean value from an int.  

```php
$bool = getBoolFromInt(1); 
```

A bool from a string.  

```php
$bool = getBoolFromString('true');
```

An int value for a bool.  

```php
$bool = getIntFromBool(true);
```

### Query String Getters

Used to get some values from the query string such as limit,sinceID,maxID, and deleted.

## Limit

```php
$limit = getLimit();
```

## Since ID

```php
$sinceID = getSinceID();
```

## Max ID

```php
$maxID = getMaxID();
```

## Deleted

```php
$deleted = getDeleted();
```

## Partial Response

## Output Types

Currently the only output type supported is JSON.  

To specify an output type, include it in your header by setting the content  
type.  

Example: ```'Content-Type':'application/json'```

OR

Specify the output type by including it in the URL.  

Example: ```https://api.yourdomain.com/v1/someresource.json```

## User Tokens

Located under 'api_utilities' the 'TokenServices' class provides a means for generation, retrevial and validation of tokens/refresh tokens.

### Setup

Before use set a custom 'prefix' and 'secret' value for the tokens in ```Constants.php```.

```php
const TOKEN_SECRET  = 'your_secret';
const PREFIX = 'your_prefix';
```

BE VERY CAREFUL IN THE STORAGE OF THE 'SECRET'!!

one-legged tokens are can be generated for users of the api.

### Generate A Token

```$data``` is an array of user-defined data to be included with the token.

```php
$token = TokenServices::createToken($id, $data);
```

### Validate A Token

```php
if(TokenServices::validate($token)) {
  echo 'valid';
} else {
  echo 'invalid';
}
```

### Generate A Refresh Token

This method takes a parameter of ```$id``` which is a unique ID for a user and an optional  
parameter of ```$data``` for any user-defined body data.

```php
try {
  $refreshToken = $tokenServices->createRefreshToken($id, $data);
} catch(Exception $e) {
  echo 'Error '.$e->getMessage();
}
```

### Validate A Refresh Token

This method must be called with an instance of the 'TokenServices' class.

```php
if($tokenServices->validateRefreshToken($token)) {
  echo 'valid';
} else {
  echo 'invalid';  
}
```

### Refresh A Token

This method must be called with an instance of the 'TokenServices' class.

This method returns an array with a new token and refresh token if all conditions are met.

```php
try {
  $tokenSet = $tokenServices::refreshToken($token);
  
  echo $tokenSet['token']; // new token
  echo $tokenSet['refresh_token']; // new refresh token
} catch(Exception $e) { // error
  echo $e->getMessage();
}
```

### IP Services

IP Services are used for tracking of clients, sucha as source IP address and what client is being used.  
IP Services gets the info and can store and retrieve the info.

## Get IP Address

```php
$ip = IPServices::getIP()
```

## Get Client

```php
$client = IPServices::getClient();
```

### IP Storage Functions

Requires an instance object of ```IPServices``` class.

## Save Visitor

```php
$ipServices->logVistitor();
```

## Get Visitor Logs

Method can be called without any paramters and should be if being called the first time.  
Method takes parameters, ```$sinceID```,```$maxID```, and ```$limit```

```php
$logs = $ipServices->getVisitors();
```

## Get a Visitor

```php
$visitor = $ipServices->getVisitor($logID);
```

### Email Services

EmailServices manages email subscriptions and an email template system to save email templates and fill the with data.



### Notifications

This class provides a simple way to send emails and push notifications.

## Setup

When intializing this class there are a few settings that need to be passed to it.  
Depending on what platform(s) you need, you will need to setup up the appropriate  
configuration array for each.

```php
$email = array(
  'ssl'=>true
  'username'=>'user@server.com',
  'passsord'=>'emailpassword',
  'server'=>'mail@server.com',
  'port'=>465
);

$android = array(
  'key'=>'api_key',
  'url'=>'gcm_url'
);

$ios = array(
  'ssl'=>'path_to_cert',
  'passpharse'=>'cert_passphrase',
  'url'=>'apns_url'
);

$windows = array(
  'channel'=>'channel_name'
);

$notificationServices = new NotificationServices($email, $android, $ios, $windows);
```

## APNS

## Send Notification

```php

```

## Microsoft

## Send Notification

```php

```

## Google Messages

## Send Notification

Sending an android notification takesa few parameters.  
The first is the data to send, the second is the  
device id which you provide from some source, and the third  
determines if it's displayed on the lock screen as a notification  
or just a background notification if set to 'false'.

```php
try {
  $notificationServices->android($data, $reg_id, true);
} catch(Exception $e) {
  echo 'Error '.$e->getMessage();
}
```

## Email

## Send Email

```php

```
