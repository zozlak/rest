# REST

Set of PHP classes for REST APIs creation:

* `zozlak\rest\HTTPController` provides routing to the right endpoint class and handles errors
* `zozlak\rest\HTTPEndpoint` provides a base class for endpoints implementation
* `zozlak\rest\JSONFormatter` provides effective JSON output formatter allowing to create output bigger the memory available to PHP

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

`zozlak\rest\HTTPEndpoint` is a base class for implementing REST API endpoints. 
It provides a default implementation (emit `HTTP 501 method not implemented` error code) for all HTTP methods for both resource and collection endpoints (e.g. both `http://yourDomain/person` and `http://yourDomain/person/{id}`). For resource endpoint method names simply follow HTTP method names (`get()`, `put()`, etc.) and for the collection endpoint they are suffixed with a `collection` (`getCollection()`, `postCollection()`, etc.).

You should derive your class from `zozlak\rest\HTTPEndpoint` one and override methods with useful implementations.

A class name should follow the name of the last API endpoint segment (skipping `{id}` segments) converted to CameCase, e.g.:

* `http://yourDomain/person`, `http://yourDomain/person/{id}` or `http://yourDomain/pErSoN/{id}` will be handled by the `Person` class.
* `http://yourDomain/person/{id}/order` or `http://yourDomain/person/{id}/order/{id}` will be handled by the `Order` class.

This means we must create two classes: `Person` and `Project`.
Lets assume you will put their code into the `src` directory and follow the PSR-4 naming rules (meaning file name follows class name).

#### src\Person.php

```
<?php
namespace myRESTEndpoint;

use \zozlak\rest\FormatterInterface;

class Person extends \zozlak\rest\HTTPEndpoint {
    public function getCollectio(FormatterInterface $f){
        $f->data('you executed a GET acction on aperson collection');
    }

    public function postCollectio(FormatterInterface $f){
        $f->data('you executed a POST acction on a person collection');
    }

    public function get(FormatterInterface $f){
        $f->data('you executed a GET acction on a person resource with id' . $this->personId);
    }

    public function put(FormatterInterface $f){
        $f->data('you executed a PUT acction on a person resource with id' . $this->personId);
    }

    public function delete(FormatterInterface $f){
        $f->data('you executed a DELETE acction on a person resource with id' . $this->personId);
    }

}
```

#### src\Project.php

Just adapt the `src\Person.php`.

### index.php

```
<?php
namespace myRESTEndpoint;
use \zozlak\rest\HTTPContoller;

try{
    header('Access-Control-Allow-Origin: *');
    require_once 'vendor/autoload.php';
    // you should probably use autoloader but to make it simpler we will explicitely include them
    require_once 'src/Person.php';
    require_once 'src/Project.php';
    set_error_handler('\zozlak\rest\HTTPContoller::errorHandler');
    $controller = new HTTPContoller('myRESTEndpoint');
    $endpointPath = filter_input(INPUT_SERVER, 'REDIRECT_URL');
    $controller->handleRequest($endpointPath);
}catch(\Throwable $e){
    HTTPContoller::HTTPCode($e->getMessage());
}
```

### Test your API

Try to access your API endpoints, e.g. with curl:

* `curl -i -XGET http://yourDomain/person`
* `curl -i -XGET http://yourDomain/person/5`
* `curl -i -XPUT http://yourDomain/person/5`
* `curl -i -XPUT http://yourDomain/person`
