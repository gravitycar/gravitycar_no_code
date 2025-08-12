# API Path Registration and Scoring System

## Status: Phase 3 Complete ✅
- **Phase 1**: Core Scoring Infrastructure ✅ 
- **Phase 2**: Enhanced Route Registry ✅ 
- **Phase 3**: ModelBase Route Registration ✅ (COMPLETED)
- **Phase 4**: Router Integration (NEXT)

## Definition of terms:
- Method: the http verb of request: GET, POST, PUT, DELETE, etc. 
- Path: the REST API path for our framework, i.e. /Users, or /Movies/<movie_id>
- Client Path: the path specified by the inbound REST API request.
- Registered Path: the path registered by a ModelBase or an APIControllerBase sub-class. All - - -  - Registered paths will include a APIClass and an APIMethod.
- Path components: the pieces of the path when its split on the / character. 
- Path length: the number of components in a path.
- APIClass: the class used to process the REST API request and produce a response.
- APIMethod: the method on the APIClass that will be called to process the request and produce a response.
- Score: a numeric value expressing how similar a client path is to any given registered path. The Highest-scoring registerd path will be used to determine which APIClass and APIMethod will be called to responsd to the REST API rquest.


## Registering paths
ModelBase and APIControllerBase classes can register paths. Registering a path will specify which 
APIController and method should handle requests that match the path (see 'Scoring Paths' below). 

Registered paths can use the question-mark as a wildcard to match any value. 

ModelBase classes will be able to register paths in their metadata or with a registerRoutes() method. APIControllerBase classes will need to call their registerRoutes() method.

When an APIControllerBase class or a ModelBase class wants to register an API, they will need to specify:
- http method
- the path (including any wildcards)
- the APIControllerBsae class 
- the class method to handle the API request

Examples of data necessary to register a path. The question-mark character 
is a "wildcard" that will match any value up to the / delimiter.

    // sets a password for a specific user
	Method => 'PUT'
	path => '/Users/?/setPassword'
	APIClass - 'UsersAPI'
	APIMethod - 'setUserPassword'
	
    // gets a list of all movie quotes linked to a movie
	Method => 'GET'
	path => '/Movies/?/link/movies_movie_quotes'
	APIClass - 'Movies'
	APIMethod - 'getLinkedMovieQuotes'

    // links a movie quote to a movie
	Method => 'POST'
	path => '/Movies/?/link/movies_movie_quotes/?'
	APIClass - 'Movies'
	APIMethod - 'linkMovieQuote'
	
Registered paths will not include the domain, since we are only handling
requests into one domain.


## Scoring Paths
Scoring means comparing the HTTP method and path from an inbound REST API request to registered paths with the same length and method and decidiing which registered path most closely matches the client path. The highest scoring registered path will then have the router use its APIClass and APIMethod to respond to the request.

Registered paths should be grouped by HTTP Method and path length. 

Only registered paths with the same length and http method as the client path should be considered and scored.  

Score client paths by how similar the method and path of the request are to 
APIControllers that have registered specific methods and paths. This means using the 
Resource-based routing pattern we currently have in APIControllerBase and expanding that 
to use a composite scoring pattern that will use Weighted Token Matching and 
Position-Aware Scoring. 

The Weighted token matching will provide a higher score to path components that match exactly a component in a registered path at the same position, a lower score for paths where the registered path contains a wildcard at that position, and no score if there is no 
match and no wildcard.

The Position-Aware Scoring will provide a higher score for matches on the first
component of the patch, a lower score for matches on the second component, etc.

The scoring formula for each component is:

`((pathLength - component_index) * (2 for exact match, 1 for wildcard, 0 for no match))`


## Scoring examples
For example, here are some registered paths with a length of 2:
```PHP
[
	'getUser' => [
		'Method' => 'GET',
		'Path' => "/Users/?"
		'APIClass' => 'Users'
		'APIMethod' => 'read'
	],
	'getMovie' => [
		'Method' => 'GET',
		'Path' => "/Movies/?"
		'APIClass' => 'Movies'
		'APIMethod' => 'read'
	],
	'deleteUser' => [
		'Method' => 'DELETE',
		'Path' => "/Users/?"
		'APIClass' => 'Users'
		'APIMethod' => 'read'
	],
]
```

A GET request with this path: "/Users/abc-123" would be scored against 'getUser' and 'getMovie'. It would not be scored against 'deleteUser' because that registered path has a different http verb.

The components in the 'getUser' registered path would score:

| Component Index | Component value | Component Score | Explanation |
| ----------------|-----------------| ------------ | ------------ |
| 0 | Users | 4 | Matches exactly the path component at the same position in the client request |
| 1 | ? | 1 | Matches wildcard |

The total score in this example is 5.


The components in the 'getMovies' registered path would score:

| Component Index | Component value | Component Score | Explanation |
| ----------------|-----------------|------------ |------------ |
| 0 | Movies | 0 | Does not match path component in client request at that position and is not a wildcard |
| 1 | ? | 1 | Matches wildcard |

The total score in this example is 1.

Since 'getUsers' had a higher score than 'getMovies', the API Scoring system woudld return 'Users' and 'read' for the APIClass and APIMethod, respectively.

## Phase 2 Implementation Complete ✅

### Enhanced APIRouteRegistry Features
- **Route Discovery**: Automatically discovers API controllers and ModelBase routes
- **Route Validation**: Comprehensive validation with detailed error messages  
- **Route Grouping**: Groups routes by HTTP method and path length for efficient scoring
- **Controller Resolution**: Hybrid strategy for resolving controller class names
- **Caching**: Persistent caching of discovered routes for performance
- **Testing**: 24 comprehensive unit tests with 35 assertions

### Key Components Implemented
1. **APIRouteRegistry Class** (`src/Api/APIRouteRegistry.php`)
   - Route discovery from API controllers and ModelBase classes
   - Route validation with GCException error handling
   - Route grouping by method and path length
   - Controller class name resolution with multiple strategies
   - Route caching for performance optimization

2. **Route Validation** 
   - Required field validation (method, path, apiClass, apiMethod)
   - HTTP method validation (GET, POST, PUT, DELETE, PATCH)
   - Path format validation (must start with '/')
   - API class and method existence validation
   - Parameter name count validation

3. **Controller Resolution Strategies**
   - Fully qualified class names (with namespace)
   - Model-based convention (`Gravitycar\Models\{Model}\Api\{Controller}`)
   - Fallback to discovered controllers registry

4. **Route Grouping and Access**
   - Routes grouped by HTTP method and path length
   - Efficient access via `getRoutesByMethodAndLength()`
   - Support for both flat and grouped route access

### Test Coverage
- 24 unit tests covering all public methods
- Route validation edge cases and error conditions
- Path component parsing and path length calculation
- Controller class name resolution scenarios
- Route grouping and retrieval functionality

### Integration Points
- Ready for Phase 3: ModelBase route registration
- Supports existing APIController route discovery
- Compatible with scoring system from Phase 1
- Prepared for Router integration in Phase 4

## Next: Phase 3 - ModelBase Route Registration

## Phase 3 Implementation Complete ✅

### ModelBase Route Registration Features
- **Metadata Integration**: Models load API routes from metadata files automatically
- **Route Registration Method**: `registerRoutes()` method added to ModelBase class
- **Metadata Structure**: Standardized `apiRoutes` metadata format with validation
- **Controller Resolution**: Hybrid resolution strategy for API controller classes
- **Parameter Extraction**: Named parameters for dynamic path components
- **Testing**: 8 ModelBase tests + 5 integration tests with 127 total assertions

### Key Components Implemented
1. **ModelBase Enhancement** (`src/Models/ModelBase.php`)
   - Added `registerRoutes()` method that reads from metadata
   - Integrates with existing metadata loading system
   - Logging support for route discovery debugging
   - Returns routes from `$this->metadata['apiRoutes']` if available

2. **Metadata Format** (e.g., `src/Models/users/users_metadata.php`)
   ```php
   'apiRoutes' => [
       [
           'method' => 'GET',
           'path' => '/Users/?',
           'apiClass' => 'UsersAPIController',
           'apiMethod' => 'read',
           'parameterNames' => ['userId']
       ],
       // ... additional routes
   ]
   ```

3. **Route Discovery Integration**
   - APIRouteRegistry automatically discovers ModelBase classes
   - Calls `registerRoutes()` on each model during discovery
   - Validates and registers routes with full error handling
   - Groups routes by method and path length for efficient scoring

### Demonstration Results
Phase 3 demonstration test shows:
- **13 total routes**: 6 from Users model + 7 from Movies model
- **Route grouping**: GET (5), POST (3), PUT (3), DELETE (2) routes
- **Perfect scoring**: All test requests match correct controllers
- **Score examples**: `/Users` (score: 2), `/Users/123` (score: 5), `/Users/456/setPassword` (score: 10)

### Test Coverage
- **ModelBase Tests**: 8 tests covering route registration, metadata integration, edge cases
- **Integration Tests**: 5 tests covering registry integration, validation, multi-model scenarios
- **Demonstration Test**: Complete workflow test with real metadata-defined routes
- **Total Assertions**: 127 assertions across all Phase 1-3 components

### Integration Points
- Compatible with all Phase 1 and Phase 2 components
- Works with existing metadata system and MetadataEngine
- Ready for Phase 4: Router Integration
- Maintains backward compatibility with existing ModelBase functionality

## Next: Phase 4 - Router Integration