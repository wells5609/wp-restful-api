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

1. `Api_Main` is the top-level object and resides in `$GLOBALS['api']` (or `global $api`).
2. `Api_Request` (`Api_Main::$request`) holds information about the request, such as parameters and headers.
3. `Api_Router` (`Api_Main::$router`) parses routes and matches query vars. Rarely called by user.
4. `Api_Response` (`Api_Main::$response`) holds information about the outgoing response, such as headers and output (body).
5. `Api_Authorization` (`Api_Main::$auth`) authorizes requests using headers, API keys, or nonces. It must be enabled and requires that you separately setup an auth object.


##URIs

Consistent with the RESTful style, URIs reflect a hierarchical or tree-like structure. Most URIs can and should be constructed using the following terms and definitions: 

**HTTP verbs** are interpreted to mean the following:

 * **`GET`** - Retrieve information about a resource object (or collection of objects)
 * **`POST`** - Create a new object
 * **`PUT`** - Update object data
 * **`DELETE`** - Delete an object

The **URI base** determines whether the URI is an API call and will be parsed for routing. Default is "api" and can be changed via the `APIBASE` constant.

**Resources** are top-level objects that determine the routes and callbacks available, as well as the type of data.

Additional URI components - let's call them **Identifiers** and **Modifiers** - may then further describe the request.


####Examples

REST is a an architectural style, not a strict structure or protocol, so the terms/definitions above have only loose meaning.

Consider the following:

`GET api/organizations/southwest-us` - This could translate to a request for information about an organization called "Southwest US", but it also might translate to a request for information about all organizations in the southwest US.  

More meaningful ("RESTful") URIs might then be:

 * `GET api/organizations/entity/southwest-us` for a single organization called "Southwest US", and
 * `GET api/organizations/us/southwest` or `GET api/organizations/countries/us/regions/southwest` for a listing of organizations in the southwest US.


Also note the importance of verbs (HTTP request methods) to the meaning of the request:

 * `GET api/fruits/apple/empire` - Get information on `empire` apples.
 * `POST api/fruits/apple/empire` - Create a new `empire` apple using `POST` data.
 * `PUT api/fruits/apple/empire` - Update `empire` apples using `PUT` data.
 * `DELETE api/fruits/apple/empire` - Delete the record for `empire` apples.


##Controllers

Each Controller represents a resource. Controllers define routes which map to callback functions.
