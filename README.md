# php-rest-api
A REST API framework in PHP

## Use

Deploy the contents of the directory, v1 folder and the .htaccess file into the host of your choice.  
The .htaccess file is important to make the api work.

### API File

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

### Cross Origin Support

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

### API Security

An auth route is provided to do any authentication at the top level,  
such as checking api keys or credentials.

```php
Router::auth('/', function($req) {
  // check api key or something here...
});

```

Finally...  Run the API!

```php
$api = new API();
$api->run();
```

### DataHelper

### Utilities

### Partial Response

### Output types

Currently the only output type supported is JSON.  

To specify an output type, include it in your header by setting the content  
type.  

Example: ```'Content-Type':'application/json'```

OR

Specify the output type by including it in the URL.  

Example: ```https://api.yourdomain.com/v1/someresource.json```

### User Tokens

one-legged tokens are can be generated for users of the api.

To generate a token:

```

```

Custom token data.

### Email Services

EmailServices manages email subscriptions and an email template system to save email templates and fill the with data.



### Notifications
