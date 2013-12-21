wp-restful-api
==============

Create a public API for your WordPress-based site or application.

###Features

 * Respond with JSON, XML, or HTML
 * Make AJAX calls cleaner, faster, and more versatile (no more admin-ajax.php)
 * Define Controller query variables by route
 * Authenticate and limit requests using cookies, headers, browser keys, or nonces (requires set-up)


##Plug-in Structure

The plug-in is divided into a number of classes, described below:

1. `Api_Main` is the top-level object and resides in `$GLOBALS['api']`.
2. `Api_Request` (`Api_Main::$request`) holds information about the request, such as parameters and headers.
3. `Api_Router` (`Api_Main::$router`) parses routes and matches query vars. Rarely called by user.
4. `Api_Response` (`Api_Main::$response`) holds information about the outgoing response, such as headers and output (body).
5. `Api_Authorization` (`Api_Main::$auth`) authorizes requests using headers, API keys, or nonces. It must be enabled and requires that you separately setup an auth object.


##URIs

**HTTP verbs** are interpreted to mean the following:

 * **`GET`** - Retrieve information about a resource object (or collection)
 * **`POST`** - Create a new object
 * **`PUT`** - Update object data
 * **`DELETE`** - Delete an object

The **URI base** determines whether the URI is an API call and will be parsed for routing. Default is "api" and can be changed via the `APIBASE` constant.

URIs should be **hierarchical** or tree-like in structure.

**Resources** are top-level objects that determine the routes and callbacks available, and potentially the type of data. The resource name comes after the URI base like `yoursite.com/api/RESOURCE/`.

Additional URI components may then further describe the request.


####Example

`GET api/organizations/southwest` - Bad URI

This could translate to a request for information about an organization called "Southwest", but it also might translate to a request for information about organizations in the southwest of a country.

More meaningful ("RESTful") URIs might be:

 * `GET api/organizations/entity/southwest` for a single organization called "Southwest", and
 * `GET api/organizations/countries/us/regions/southwest` for a listing of organizations in the southwest US.

In any case, the name of the resource would be `organizations` with a class name `Organizations_ApiController`.

##Controllers

Each Controller represents a resource. Controllers define routes which map to callback functions.

Each Controller has a corresponding class using the naming convention `{Resource}_ApiController`, where 'Resource' is the name of the resource with the first letter capitalized. Controllers extend the `Api_Controller` class.


###Variables

Controllers have the following variables:

 * **`$name`** _string_ (public) - lowercased resource name slug
 * **`$routes`** _array_ (public) - Associative array of routes and callbacks (see below)
 * **`$protected_methods`** _array_ (public) - Indexed array of class methods which require authorization (if enabled)
 * **`$route_priority`** _integer_ (public) - Determines relative order in which API routes will be parsed. Priority is for the group of $routes.


###Methods

Controllers inherit the following methods from `Api_Controller`:

 * **`register_routes()`** (public, static) - Called during API init to register the routes with the API Router. Only called if controller is registered.
 * **`method_requires_auth( string $method )`** (public) - Whether the passed method requires authorization (i.e. its in `$protected_routes`).
 * **`get_protected_methods()`** (public) - Returns array of protected methods

Controllers are singleton-like and thus will also need to define the following variable and method (copy-and-paste):

```php

	static protected $_instance;
		
	static function instance(){
		if ( !isset(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
```


####Example

```php

class Taxonomy_ApiController extends Api_Controller {
  
  public $name = 'taxonomy';
  
  public $routes = array();
  
  public $protected_methods = array();
}

```

