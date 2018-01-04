# REST

[![Build Status](https://travis-ci.org/zozlak/rest.svg?branch=master)](https://travis-ci.org/zozlak/rest)
[![Coverage Status](https://coveralls.io/repos/github/zozlak/rest/badge.svg?branch=master)](https://coveralls.io/github/zozlak/rest?branch=master)

Set of PHP classes for REST APIs creation:

* `zozlak\rest\HttpController` provides routing to the right endpoint class and handles errors
* `zozlak\rest\HttpEndpoint` provides a base class for endpoints implementation

## Features

* Simple and explicit endpoints implementation.
* Builtin support for HTTP basic auth.
* Convenient support for bigger then allowed PHP memory JSON output.
* Automatic handling of OPTION method.

## Installation

The easiest way to install is by using composer:

* prepare a `composer.json` file:
```
{
    "require": {
        "zozlak/rest": "*"
    }
}
```
* obtain [the Composer](https://getcomposer.org/download/)
* run `php composer.phar install`  
  The library should be now installed in the `vendor` directory.
* Include Composer's autoloader in your code by adding the `require_once 'vendor/autoload.php';` line at the beginning of your code.

## Sample usage

Lets assume you want to create a RESTfull API providing following endpoints:

* `http://yourDomain/person` a collection supporting GET and POST methods
* `http://yourDomain/person/{id}` resources supporting GET, PUT and DELETE methods
* `http://yourDomain/project` a collection supporting GET and POST methods
* `http://yourDomain/project/{id}` resources supporting GET, PUT and DELETE methods

### HTTP server configuration

At first you will probably want to configure your HTTP server to redirect requests coming to your API to one PHP file.
Sample set of rules for Apache (to put into the `.htaccess` file or into a `<VirtualHost>` configuration directive) would be:

```
RewriteEngine on

# handle requests for existing files and directories as usual
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# redirect everyting else to index.php
RewriteRule ^(.*)$ index.php [QSA]
```

### Endpoint classes

`zozlak\rest\HttpEndpoint` is a base class for implementing REST API endpoints. 
It provides a default implementation (emit `HTTP 501 method not implemented` error code) for all HTTP methods for both resource and collection endpoints (e.g. both `http://yourDomain/person` and `http://yourDomain/person/{id}`). For resource endpoint method names simply follow HTTP method names (`get()`, `put()`, etc.) and for the collection endpoint they are suffixed with a `collection` (`getCollection()`, `postCollection()`, etc.).
The dfault implementation of the `OPTION` method checks which methods are implemented in your class and emits the `Allow` header value accordingly (and the `404 Not Found` if none implemented).

You should derive your class from the `zozlak\rest\HttpEndpoint` one and override methods with useful implementations.

A class name should follow the name of the last API endpoint segment (skipping `{id}` segments) converted to CameCase, e.g.:

* `http://yourDomain/person`, `http://yourDomain/person/{id}` or `http://yourDomain/pErSoN/{id}` will be handled by the `Person` class.
* `http://yourDomain/person/{id}/order` or `http://yourDomain/person/{id}/order/{id}` will be handled by the `Order` class.

This means we must create two classes: `Person` and `Project`.
Lets assume you will put their code into the `src` directory and follow the PSR-4 naming rules (meaning file name follows class name).

#### src\Person.php

```
<?php
namespace myRestEndpoint;

use \zozlak\rest\HttpEndpoint;
use \zozlak\rest\DataFormatter;
use \zozlak\rest\HeadersFormatter;

class Person extends HttpEndpoint {
    public function getCollection(DataFormatter $f, HeadersFormatter $h){
        $f->data('you executed a GET action on a person collection');
    }

    public function postCollectio(DataFormatter $f, HeadersFormatter $h){
        $f->data('you executed a POST action on a person collection');
    }

    public function get(DataFormatter $f, HeadersFormatter $h){
        $f->data('you executed a GET action on a person resource with id ' . $this->personId);
    }

    public function put(DataFormatter $f, HeadersFormatter $h){
        $f->data('you executed a PUT action on a person resource with id ' . $this->personId);
    }

    public function delete(DataFormatter $f, HeadersFormatter $h){
        $f->data('you executed a DELETE action on a person resource with id ' . $this->personId);
    }

}
```

#### src\Project.php

Just copy-paste-adapt the `src\Person.php`.

### index.php

```
<?php
namespace myRestEndpoint;

use \Throwable;
use \zozlak\rest\HttpController;

try{
    header('Access-Control-Allow-Origin: *');
    require_once 'vendor/autoload.php';
    // you should probably use autoloader but to make it simpler we will explicitely include them
    require_once 'src/Person.php';
    require_once 'src/Project.php';
    set_error_handler('\zozlak\rest\HttpController::errorHandler');
    $controller = new HttpController('myRestEndpoint');
    $controller->handleRequest();
}catch(Throwable $ex){
    HttpController::reportError($ex);
}
```

### Test your API

Try to access your API endpoints, e.g. with curl:

* `curl -i -X GET http://yourDomain/person` - 200 OK _"you executed a GET action on a person collection"_
* `curl -i -X GET http://yourDomain/person/5` - 200 OK _"you executed a GET action on a person resource with id 5"_
* `curl -i -X PUT http://yourDomain/person/5` - 200 OK _"you executed a PUT action on a person resource with id 5"_
* `curl -i -X PUT http://yourDomain/person` - 501 Not implemented

## Advanced topics

### Authorization

To check credentials provided by the user use `getAuthUser()` and `getAuthPswd()` in your endpoint's class code.
If the credentials are wrong simply throw the `zozlak\rest\UnauthorizedException` exception.

Example:

```
<?php
namespace myRestEndpoint;

use \zozlak\rest\HttpEndpoint;
use \zozlak\rest\DataFormatter;
use \zozlak\rest\HeadersFormatter;
use \zozlak\rest\UnauthorizedException;

class Person extends HttpEndpoint {
    static private $users = ['user1' => 'pswd1', 'user2' => 'pswd2'];

    public function getCollection(DataFormatter $f, HeadersFormatter $h) {
        $user = $this->getAuthUser();
        if (!isset(self::$users[$user]) || self::$users[$user] !== $this->getAuthPswd()) {
            throw new UnauthorizedException();
        }
        $f->data('Login successful');
    }
}
```

### Changing HTTP status code and headers

Use the `HeadersFormatter` object passed to all methods to alter response HTTP status and headers.

Lets assume you want to return for the POST method run on a collection with a `201 Created` response code and a `Location` header pointing to the newly created item:

```
<?php
namespace myRestEndpoint;

use \zozlak\rest\HttpEndpoint;
use \zozlak\rest\DataFormatter;
use \zozlak\rest\HeadersFormatter;

class Person extends HttpEndpoint {
    public function postCollection(DataFormatter $f, HeadersFormatter $h) {
        $id = rand();
        // ...everyting else to be done to create the new person...
        $h->setStatus(201);
        $h->addHeader('Location', $this->getUrl() . '/' . $id);
    }
}
```

There is also a shorthand for redirects:

```
<?php
namespace myRestEndpoint;

use \zozlak\rest\HttpEndpoint;
use \zozlak\rest\DataFormatter;
use \zozlak\rest\HeadersFormatter;

class Person extends HttpEndpoint {
    public function get(DataFormatter $f, HeadersFormatter $h) {
        $h->setRedirect('https://other/location', 302);
    }
}
```

### Returning non-JSON data

You shouldn't directly return data with `echo`, etc. because it can break some library features (precisely after printing more then PHP output buffer bytes the PHP will automatically issue HTTP 200 status code with default set of HTTP headers and all the settings made to the `HeadersFormatter` will be discarded).

There are two helper methods of the `DataFormatter` object passed all methods helping you to deal with non-JSON data:

* `raw($data, $contentType, $filename = null)` - returns an arbitrary string with a given `Content-Type` header. If the `$filename` parameter is provided, a corresponsing `Content-Disposition: attachment; filename="$filename"` header is emitted.
* `file($path, $contentType = null, $filename = null)` - returns a given file. By default the `Content-Type` header value is guessed with the `mime_content_type()` PHP function but you can override it by passing the `contentType` parameter value. If the `$filename` parameter is provided, a corresponsing `Content-Disposition: attachment; filename="$filename"` header is emitted.

To alter HTTP status code and/or add other headers, use the `setStatus()` and `setHeader()` methods on the `HeadersFormatter` object.

Example:


```
<?php
namespace myRestEndpoint;

use \zozlak\rest\HttpEndpoint;
use \zozlak\rest\DataFormatter;
use \zozlak\rest\HeadersFormatter;

class Person extends HttpEndpoint {
    public function postCollection(DataFormatter $f, HeadersFormatter $h) {
        // ...create a file...
        $path = '/tmp/tmpName.csv';
        $h->setStatus(201);
        $f->file($path, '', 'niceName.csv');
    }
}
```

