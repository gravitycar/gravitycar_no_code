# OpenAPI Improvements

Our OpenApi documentation is good, but needs to be better to support A.I. tools like MCP servers that want to talk to our API.

We need to improve our openapi documentation with specific data for every route for every model that we want to expose to the OpenApi documentation. 

We also need to hide routes that regular users would not have access to based on our RBAC implementation. The only routes we should document are routes that are available to regular users (users who have the 'user' Role).

## Current Beahvior and Desired Behavior

**Current Behavior #1**: We don't list explicity Model routes.
No explicit model routes are listed in our openapi documentation because that documentation is based off of routes from api_routes.php, and those routes only include the dynamic wildcard-based route handling for Models in the ModelBaseApiController class.

The dynamic routes are listed, but the available data is not expansive or human/A.I. friendly:
```json
			"\/?": { // dynamic route for getting a list of records
                "get": {
                    "summary": "List api records",
                    "operationId": "get_?",
                    "tags": [
                        "api"
                    ],
                    "responses": {..some responses, not all}
                    },
				},
```                    
                    
**Desired Behavior #1**: List every route for every model

Do not include any dynamic routes in the openapi documenation.

Instead, generate documentation for every route for every Model based on the dynamic routes.

Include much more information about each model and its fields.

The data for the routes should be more human and A.I. friendly, so it needs natural language descriptions.
```json
			"\/Movies": { // explicit route for getting a list of Movies records
                "get": {
                    "summary": "Retrieve Movies records from the gravitycar api with optional search parameters in the query string.",
                    "operationId": "get_Movies",
                    "parameters": [
						{
						  "name": "search",
						  "in": "query", 
						  "description": "Retrieve Movies records from the gravitycar api with optional filtering on the name or title of the movie",
						  "schema": {"type": "string"},
						  "example": "Star Wars"
						},
						// include other parameters we support, i.e. page and pageSize.
                    ],
                    "tags": [
                        "api",
                        "Movies",
                    ],
                    "responses": {
							"200": {
								"description": "List of Movies matching optional filter criteria",
								"content": {
								  "application/json": {
									"schema": {
									  "type": "object",
									  "properties": {
										"success": {"type": "boolean"},
										"data": {
										  "type": "array",
										  "items": {"$ref": "#/components/schemas/Movies"}
										},
										"pagination": {"$ref": "#/components/schemas/Pagination"}
									  }
									}
								  }
								}
							  }
                    // Should include 401, 403, 404, 421, 500}
                    },
				},
```


**Current Behavior #2**: Every route is listed.
Every route that exists is listed in the openapi.json documentation.

**Desired Behavior #2**: Use RBAC to restrict which routes are documented
Only list routes that regular users could access.

Q: How do we determine if we want to expose a route to the openapi documentation?
A: Use AuthorizationService::hasPermissionForRoute(). 
- Use the route we are creating documentation for as the route to pass as the first argument.
- Create a new Request object to pass as the second argument. See Router::route() for an example of how to create a new Request. 
- Retieve the Users model for username = 'jane@example.com' and pass that as the third argument.

If hasPermissionForRoute() returns true for a user with the 'user' role, we include it in the openapi documentation.

We should do this for all routes, not just dynamic model routes.



**Current Behavior #3**
Very terse, generated summaries

**Desired Behavior #3**
Much more rich, specific summaries.

We can do this in two ways:
1) Manually enter a summary as part of the route data that is stored in the array returned by various APIControllerBase::registerRoutes() implementations.
2) Generate better summaries with what we know to be true about the endpoints. This will be necessary for endpoints.

For #2, We can generate `Retrieve <model_name> records from the gravitycar api with optional search parameters in the query string.' easily enough.
But let's consider if we can expand that even further with greater detail. If we can, we should, but if we can't, we can accept the simple option.


**Current Behavior #4**: No listed intent
We do not include any intent data in our openapi. Perhaps we can change that?

**Desired Behavior #4**: Add intent data.
```json
"x-gravitycar-intent": "search",
"x-gravitycar-entity": "Movies", 
"x-gravitycar-database": "internal", // would be 'external' for tmdb, google books api, etc.
"x-gravitycar-examples": [
  {
    "description": "Find specific Movie by name",
    "parameters": {"search": "Star Wars"}
  }
]
```
I don't think that "intent" is a formal part of the openapi spec, but perhaps we can make use of it.