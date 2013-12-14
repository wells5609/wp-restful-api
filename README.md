wp-restful-api
==============

This plug-in allows you to create a public API for your WordPress-based site or application. 

###Features

 * Format responses in JSON, XML, or HTML
 * Make AJAX calls cleaner, faster, and more versatile (no more admin-ajax.php)
 * Define Controllers, their routes, and query variables (by route)
 * Authenticate and limit requests with API keys and/or HTTP headers


##Basics

Consistent with the REST "protocol", URIs reflect a "tree-like" or hierarchical relationship. 

**HTTP verbs** are interpreted in the following ways:

 * **`GET`** - Retrieve information about a resource object (or collection of objects)
 * **`POST`** - Create a new object
 * **`PUT`** - Update an object's information
 * **`DELETE`** - Deletes an object


The **URI base** determines whether the URI will be parsed for routing. Default is "api" and can be changed via the `APIBASE` constant.

**Resources** are top-level identifiers which determine the routes and callbacks available.

**Identifiers** usually follow the resource and describe which resource object the request is for.

**Modifiers** optionally follow the identifier and describe the operation to be performed or data to be retrieved.

Most URIs can be constructed using the above terms and conditions. Some overly simplified examples:

`GET api/fruits/apple/` - request from `fruit` (the "resource") a list of `apple` objects (the "identifier").
`POST api/fruits/apple/empire` - create a new `empire` (the "modifier") `apple` object (using passed `POST` data).
`PUT api/fruits/apple/empire/color` - update the `color` of `empire` `apple`s (using passed `PUT` data).
`DELETE api/fruits/apple/empire` - delete the record for `empire` `apple`s.


###Controllers

Each Controller represents a resource. Controllers define routes which map to callback functions.
