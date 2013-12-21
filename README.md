wp-restful-api
==============

This plug-in allows you to create a public API for your WordPress-based site or application. 

###Features

 * Format responses in JSON, XML, or HTML
 * Make AJAX calls cleaner, faster, and more versatile (no more admin-ajax.php)
 * Define Controllers, their routes, and query variables (by route)
 * Authenticate and limit requests using API keys, HTTP headers, cookies, or nonces (requires set-up)


##Plug-in Structure

The plug-in is divided into a number of classes, described below:

1. `Api_Main` is the top-level object and resides in `$GLOBALS['api']`.
2. `Api_Request` (`Api_Main::$request`) holds information about the request, such as parameters and headers.
3. `Api_Router` (`Api_Main::$router`) parses routes and matches query vars. Rarely called by user.
4. `Api_Response` (`Api_Main::$response`) holds information about the outgoing response, such as headers and output (body).
5. `Api_Authorization` (`Api_Main::$auth`) authorizes requests using headers, API keys, or nonces. It must be enabled and requires that you separately setup an auth object.


##URIs

URIs reflect a hierarchical or tree-like structure and should gemerally be constructed using the following guidelines: 

**HTTP verbs** are interpreted to mean the following:

 * **`GET`** - Retrieve information about a resource object (or collection)
 * **`POST`** - Create a new object
 * **`PUT`** - Update object data
 * **`DELETE`** - Delete an object

The **URI base** determines whether the URI is an API call and will be parsed for routing. Default is "api" and can be changed via the `APIBASE` constant.

**Resources** are top-level objects that determine the routes and callbacks available, as well as the type of data.

Additional URI components may then further describe the request.


####Examples

`GET api/organizations/southwest-us` - This could translate to a request for information about an organization called "Southwest US", but it also might translate to a request for information about all organizations in the southwest US.  

More meaningful ("RESTful") URIs might then be:

 * `GET api/organizations/entity/southwest-us` for a single organization called "Southwest US", and
 * `GET api/organizations/us/southwest` or `GET api/organizations/countries/us/regions/southwest` for a listing of organizations in the southwest US.


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

