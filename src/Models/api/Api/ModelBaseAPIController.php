<?php
namespace Gravitycar\Models\Api\Api;

use Gravitycar\Api\Request;
use Gravitycar\Core\ServiceLocator;
use Gravitycar\Exceptions\GCException;
use Gravitycar\Exceptions\NotFoundException;
use Gravitycar\Exceptions\BadRequestException;
use Gravitycar\Exceptions\UnprocessableEntityException;
use Gravitycar\Exceptions\InternalServerErrorException;
use Gravitycar\Exceptions\ConflictException;
use Gravitycar\Models\ModelBase;
use Gravitycar\Factories\ModelFactory;
use Gravitycar\Relationships\RelationshipBase;
use Monolog\Logger;

/**
 * Generic API controller for all ModelBase classes.
 * Provides default CRUD and relationship operations using wildcard routing patterns.
 * Uses scoring-based routing where specific controllers automatically take precedence.
 */
class ModelBaseAPIController {
    
    protected Logger $logger;

    public function __construct(Logger $logger = null) {
        $this->logger = $logger ?? ServiceLocator::getLogger();
    }

    /**
     * Register wildcard routes for all model operations
     * Returns route definitions for APIRouteRegistry integration
     */
    public function registerRoutes(): array {
        return [
            // GET routes
            [
                'method' => 'GET',
                'path' => '/?',
                'parameterNames' => ['modelName'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'list'
            ],
            [
                'method' => 'GET',
                'path' => '/?/?',
                'parameterNames' => ['modelName', 'id'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'retrieve'
            ],
            [
                'method' => 'GET',
                'path' => '/?/deleted',
                'parameterNames' => ['modelName', ''],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'listDeleted'
            ],
            [
                'method' => 'GET',
                'path' => '/?/?/link/?',
                'parameterNames' => ['modelName', 'id', '', 'relationshipName'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'listRelated'
            ],
            
            // POST routes
            [
                'method' => 'POST',
                'path' => '/?',
                'parameterNames' => ['modelName'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'create'
            ],
            [
                'method' => 'POST',
                'path' => '/?/?/link/?',
                'parameterNames' => ['modelName', 'id', '', 'relationshipName'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'createAndLink'
            ],
            
            // PUT routes
            [
                'method' => 'PUT',
                'path' => '/?/?',
                'parameterNames' => ['modelName', 'id'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'update'
            ],
            [
                'method' => 'PUT',
                'path' => '/?/?/restore',
                'parameterNames' => ['modelName', 'id', ''],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'restore'
            ],
            [
                'method' => 'PUT',
                'path' => '/?/?/link/?/?',
                'parameterNames' => ['modelName', 'id', '', 'relationshipName', 'idToLink'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'link'
            ],
            
            // DELETE routes
            [
                'method' => 'DELETE',
                'path' => '/?/?',
                'parameterNames' => ['modelName', 'id'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'delete'
            ],
            [
                'method' => 'DELETE',
                'path' => '/?/?/link/?/?',
                'parameterNames' => ['modelName', 'id', '', 'relationshipName', 'idToLink'],
                'apiClass' => 'Gravitycar\Models\Api\Api\ModelBaseAPIController',
                'apiMethod' => 'unlink'
            ]
        ];
    }    /**
     * List all records for a model with enhanced pagination and filtering
     */
    public function list(Request $request): array {
        $modelName = $request->get('modelName');
        
        if (!$modelName) {
            throw new GCException("Missing required parameter: modelName");
        }
        
        $this->validateModelName($modelName);
        
        $this->logger->info('Listing records with enhanced filtering', [
            'model' => $modelName,
            'response_format' => $request->getResponseFormat()
        ]);
        
        try {
            // Get validated parameters from Router (pre-validated)
            $validatedParams = $request->getValidatedParams();
            $parsedParams = $request->getParsedParams();
            $filters = $validatedParams['filters'] ?? [];
            $sorting = $validatedParams['sorting'] ?? [];
            $pagination = $validatedParams['pagination'] ?? [];
            $search = $validatedParams['search'] ?? [];
            
            // Create model instance
            $model = ModelFactory::new($modelName);
            
            // Get database connector
            $databaseConnector = ServiceLocator::getDatabaseConnector();
            
            // Use enhanced DatabaseConnector method with validated parameters
            $records = $databaseConnector->findWithReactParams($model, $validatedParams, false);
            
            // Get total count for pagination metadata (if requested)
            $totalCount = null;
            $responseFormat = $request->getResponseFormat();
            if (in_array($responseFormat, ['ag-grid', 'mui', 'advanced']) || !empty($validatedParams['options']['includeTotal'])) {
                $totalCount = $databaseConnector->getCountWithValidatedCriteria($model, $validatedParams, false);
            }
            
            // Create React-compatible response structure
            $response = [
                'success' => true,
                'data' => $records,
                'meta' => [
                    'pagination' => [
                        'page' => $pagination['page'] ?? 1,
                        'pageSize' => $pagination['pageSize'] ?? 20,
                        'offset' => $pagination['offset'] ?? 0,
                        'total' => $totalCount,
                        'hasNextPage' => $totalCount !== null ? (($pagination['offset'] ?? 0) + ($pagination['pageSize'] ?? 20)) < $totalCount : null,
                        'hasPreviousPage' => ($pagination['page'] ?? 1) > 1
                    ],
                    'filters' => $filters,
                    'sorting' => $sorting,
                    'search' => $search,
                    'responseFormat' => $responseFormat,
                    'detectedFormat' => $parsedParams['meta']['detectedFormat'] ?? 'unknown'
                ],
                'timestamp' => date('c')
            ];
            
            // Add AG-Grid specific properties
            if ($responseFormat === 'ag-grid') {
                $response['lastRow'] = $totalCount; // AG-Grid expects lastRow for infinite scroll
                $response['secondaryColumns'] = null; // AG-Grid dynamic columns (not used)
            }
            
            // Add MUI DataGrid specific properties
            if ($responseFormat === 'mui') {
                $response['rowCount'] = $totalCount;
            }
            
            // Add enhanced metadata for advanced requests
            if ($responseFormat === 'advanced' || !empty($validatedParams['options']['includeAvailableFilters'])) {
                $filterCriteria = $request->getFilterCriteria();
                $searchEngine = $request->getSearchEngine();
                
                if ($filterCriteria) {
                    $response['meta']['availableFilters'] = $filterCriteria->getSupportedFilters($model);
                }
                
                if ($searchEngine) {
                    $response['meta']['availableSearchFields'] = $searchEngine->getSearchableFields($model);
                }
            }
            
            $this->logger->info('Records listed successfully with enhanced features', [
                'model' => $modelName,
                'count' => count($records),
                'total_count' => $totalCount,
                'filters_count' => count($filters),
                'sorts_count' => count($sorting),
                'has_search' => !empty($search['term'] ?? ''),
                'response_format' => $responseFormat
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to list records', [
                'model' => $modelName,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to list records', [
                'model' => $modelName,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Retrieve a specific record by ID
     */
    public function retrieve(Request $request): array {
        $modelName = $request->get('modelName');
        $id = $request->get('id');
        
        if (!$modelName) {
            throw new BadRequestException("Missing required parameter: modelName");
        }
        if (!$id) {
            throw new BadRequestException("Missing required parameter: id");
        }
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        
        $this->logger->info('Retrieving record', ['model' => $modelName, 'id' => $id]);
        
        try {
            // Use ModelFactory::retrieve() for direct database retrieval
            $model = ModelFactory::retrieve($modelName, $id);
            
            if (!$model) {
                throw new NotFoundException('Record not found', [
                    'model' => $modelName,
                    'id' => $id
                ]);
            }
            
            $this->logger->info('Record retrieved successfully', [
                'model' => $modelName,
                'id' => $id
            ]);
            
            return ['data' => $model->toArray()];
            
        } catch (NotFoundException $e) {
            throw $e; // Re-throw NotFoundException as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve record', [
                'model' => $modelName,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to retrieve record', [
                'model' => $modelName,
                'id' => $id,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * List soft-deleted records for a model
     */
    public function listDeleted(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $this->validateModelName($modelName);
        
        $this->logger->info('Listing deleted records', ['model' => $modelName]);
        
        try {
            // Create query instance using ModelFactory
            $queryInstance = ModelFactory::new($modelName);
            
            // Find soft-deleted records using criteria
            $models = $queryInstance->find(['deleted_at !=' => null]);
            
            // Convert to array for JSON response
            $records = array_map(fn($model) => $model->toArray(), $models);
            
            $this->logger->info('Deleted records listed successfully', [
                'model' => $modelName,
                'count' => count($records)
            ]);
            
            return ['data' => $records, 'count' => count($records)];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to list deleted records', [
                'model' => $modelName,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to list deleted records', [
                'model' => $modelName,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Create a new record
     */
    public function create(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $this->validateModelName($modelName);
        
        // Get request body data
        $data = $this->getRequestData($request);
        
        $this->logger->info('Creating record', ['model' => $modelName, 'data' => $data]);
        
        try {
            $model = ModelFactory::new($modelName);
            
            // Use ModelBase populateFromAPI method
            $model->populateFromAPI($data);
            
            // Check for validation errors before attempting to create
            $validationErrors = $model->getValidationErrors();
            if (!empty($validationErrors)) {
                throw UnprocessableEntityException::withValidationErrors($validationErrors);
            }
            
            // Use ModelBase create method
            $success = $model->create();
            
            if (!$success) {
                throw new InternalServerErrorException('Failed to create record', [
                    'model' => $modelName,
                    'data' => $data
                ]);
            }
            
            $this->logger->info('Record created successfully', [
                'model' => $modelName,
                'id' => $model->get('id')
            ]);
            
            return ['data' => $model->toArray(), 'message' => 'Record created successfully'];
            
        } catch (UnprocessableEntityException $e) {
            throw $e; // Re-throw validation exceptions as-is
        } catch (InternalServerErrorException $e) {
            throw $e; // Re-throw server error exceptions as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to create record', [
                'model' => $modelName,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new InternalServerErrorException('Failed to create record', [
                'model' => $modelName,
                'original_error' => $e->getMessage()
            ], $e);
        }
    }

    /**
     * Update an existing record
     */
    public function update(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $id = $params['id'] ?? null;
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        
        $model = $this->getValidModel($modelName, $id);
        
        // Get request body data
        $data = $this->getRequestData($request);
        
        $this->logger->info('Updating record', [
            'model' => $modelName,
            'id' => $id,
            'data' => $data
        ]);
        
        try {
            // Use ModelBase populateFromAPI method
            $model->populateFromAPI($data);
            
            // Use ModelBase update method
            $success = $model->update();
            
            if (!$success) {
                throw new GCException('Failed to update record', [
                    'model' => $modelName,
                    'id' => $id,
                    'data' => $data
                ], 500);
            }
            
            $this->logger->info('Record updated successfully', [
                'model' => $modelName,
                'id' => $id
            ]);
            
            return ['data' => $model->toArray(), 'message' => 'Record updated successfully'];
            
        } catch (GCException $e) {
            throw $e; // Re-throw GCExceptions as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to update record', [
                'model' => $modelName,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to update record', [
                'model' => $modelName,
                'id' => $id,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Soft delete a record
     */
    public function delete(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $id = $params['id'] ?? null;
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        
        $model = $this->getValidModel($modelName, $id);
        
        $this->logger->info('Deleting record', ['model' => $modelName, 'id' => $id]);
        
        try {
            // Use ModelBase soft delete
            $success = $model->delete();
            
            if (!$success) {
                throw new GCException('Failed to delete record', [
                    'model' => $modelName,
                    'id' => $id
                ], 500);
            }
            
            $this->logger->info('Record deleted successfully', [
                'model' => $modelName,
                'id' => $id
            ]);
            
            return ['message' => 'Record deleted successfully'];
            
        } catch (GCException $e) {
            throw $e; // Re-throw GCExceptions as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete record', [
                'model' => $modelName,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to delete record', [
                'model' => $modelName,
                'id' => $id,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Restore a soft-deleted record
     */
    public function restore(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $id = $params['id'] ?? null;
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        
        $this->logger->info('Restoring record', ['model' => $modelName, 'id' => $id]);
        
        try {
            // Create query instance using ModelFactory
            $queryInstance = ModelFactory::new($modelName);
            
            // Find record (including soft-deleted ones)
            $models = $queryInstance->find(['id' => $id, 'deleted_at !=' => null]);
            
            if (empty($models)) {
                throw new GCException('Deleted record not found', [
                    'model' => $modelName,
                    'id' => $id
                ], 404);
            }
            
            $model = $models[0];
            
            // Use ModelBase restore method
            $success = $model->restore();
            
            if (!$success) {
                throw new GCException('Failed to restore record', [
                    'model' => $modelName,
                    'id' => $id
                ], 500);
            }
            
            $this->logger->info('Record restored successfully', [
                'model' => $modelName,
                'id' => $id
            ]);
            
            return ['data' => $model->toArray(), 'message' => 'Record restored successfully'];
            
        } catch (GCException $e) {
            throw $e; // Re-throw GCExceptions as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to restore record', [
                'model' => $modelName,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to restore record', [
                'model' => $modelName,
                'id' => $id,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * List related records for a relationship
     */
    public function listRelated(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $id = $params['id'] ?? null;
        $relationshipName = $params['relationshipName'] ?? null;
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        $this->validateRelationshipName($relationshipName);
        
        $model = $this->getValidModel($modelName, $id);
        $relationship = $this->validateRelationshipExists($model, $relationshipName);
        
        $this->logger->info('Listing related records', [
            'model' => $modelName,
            'id' => $id,
            'relationship' => $relationshipName
        ]);
        
        try {
            // Use ModelBase getRelated method
            $relatedRecords = $model->getRelated($relationshipName);
            
            $this->logger->info('Related records listed successfully', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'count' => count($relatedRecords)
            ]);
            
            return ['data' => $relatedRecords, 'count' => count($relatedRecords)];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to list related records', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to list related records', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Create a new record and link it to an existing record via relationship
     */
    public function createAndLink(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $id = $params['id'] ?? null;
        $relationshipName = $params['relationshipName'] ?? null;
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        $this->validateRelationshipName($relationshipName);
        
        $model = $this->getValidModel($modelName, $id);
        $relationship = $this->validateRelationshipExists($model, $relationshipName);
        
        // Get request body data
        $data = $this->getRequestData($request);
        
        $this->logger->info('Creating and linking record', [
            'model' => $modelName,
            'id' => $id,
            'relationship' => $relationshipName,
            'data' => $data
        ]);
        
        try {
            // Get related model class using ModelBase method
            $relatedModelClass = $model->getRelatedModelClass($relationship);
            $relatedModelName = $this->extractModelNameFromClass($relatedModelClass);
            
            // Create new related model instance
            $relatedModel = ModelFactory::new($relatedModelName);
            $relatedModel->populateFromAPI($data);
            
            // Create the related record first
            $success = $relatedModel->create();
            if (!$success) {
                throw new GCException('Failed to create related record', [
                    'related_model' => $relatedModelName,
                    'data' => $data
                ], 500);
            }
            
            // Then link it to the main record
            $linkSuccess = $relationship->add($model, $relatedModel);
            if (!$linkSuccess) {
                throw new GCException('Failed to link records', [
                    'model' => $modelName,
                    'id' => $id,
                    'related_model' => $relatedModelName,
                    'related_id' => $relatedModel->get('id'),
                    'relationship' => $relationshipName
                ], 500);
            }
            
            $this->logger->info('Record created and linked successfully', [
                'model' => $modelName,
                'id' => $id,
                'related_model' => $relatedModelName,
                'related_id' => $relatedModel->get('id'),
                'relationship' => $relationshipName
            ]);
            
            return [
                'data' => $relatedModel->toArray(),
                'message' => 'Record created and linked successfully'
            ];
            
        } catch (GCException $e) {
            throw $e; // Re-throw GCExceptions as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to create and link record', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to create and link record', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Link two existing records via relationship
     */
    public function link(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $id = $params['id'] ?? null;
        $relationshipName = $params['relationshipName'] ?? null;
        $idToLink = $params['idToLink'] ?? null;
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        $this->validateRelationshipName($relationshipName);
        $this->validateId($idToLink);
        
        $model = $this->getValidModel($modelName, $id);
        $relationship = $this->validateRelationshipExists($model, $relationshipName);
        
        $this->logger->info('Linking records', [
            'model' => $modelName,
            'id' => $id,
            'relationship' => $relationshipName,
            'id_to_link' => $idToLink
        ]);
        
        try {
            // Get related model and record using ModelBase method
            $relatedModelClass = $model->getRelatedModelClass($relationship);
            $relatedModelName = $this->extractModelNameFromClass($relatedModelClass);
            
            // Use ModelFactory::retrieve() for direct database retrieval
            $relatedModel = ModelFactory::retrieve($relatedModelName, $idToLink);
            
            if (!$relatedModel) {
                throw new GCException('Related record not found', [
                    'related_model' => $relatedModelName,
                    'related_id' => $idToLink
                ], 404);
            }
            
            // Use RelationshipBase add method
            $success = $relationship->add($model, $relatedModel);
            
            if (!$success) {
                throw new GCException('Failed to link records', [
                    'model' => $modelName,
                    'id' => $id,
                    'related_model' => $relatedModelName,
                    'related_id' => $idToLink,
                    'relationship' => $relationshipName
                ], 500);
            }
            
            $this->logger->info('Records linked successfully', [
                'model' => $modelName,
                'id' => $id,
                'related_model' => $relatedModelName,
                'related_id' => $idToLink,
                'relationship' => $relationshipName
            ]);
            
            return ['message' => 'Records linked successfully'];
            
        } catch (GCException $e) {
            throw $e; // Re-throw GCExceptions as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to link records', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'id_to_link' => $idToLink,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to link records', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'id_to_link' => $idToLink,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Unlink two records (soft delete relationship)
     */
    public function unlink(Request $request, array $params = []): array {
        $modelName = $params['modelName'] ?? null;
        $id = $params['id'] ?? null;
        $relationshipName = $params['relationshipName'] ?? null;
        $idToUnlink = $params['idToLink'] ?? null; // Note: same parameter name as link
        
        $this->validateModelName($modelName);
        $this->validateId($id);
        $this->validateRelationshipName($relationshipName);
        $this->validateId($idToUnlink);
        
        $model = $this->getValidModel($modelName, $id);
        $relationship = $this->validateRelationshipExists($model, $relationshipName);
        
        $this->logger->info('Unlinking records', [
            'model' => $modelName,
            'id' => $id,
            'relationship' => $relationshipName,
            'id_to_unlink' => $idToUnlink
        ]);
        
        try {
            // Get related model and record using ModelBase method
            $relatedModelClass = $model->getRelatedModelClass($relationship);
            $relatedModelName = $this->extractModelNameFromClass($relatedModelClass);
            
            // Use ModelFactory::retrieve() for direct database retrieval
            $relatedModel = ModelFactory::retrieve($relatedModelName, $idToUnlink);
            
            if (!$relatedModel) {
                throw new GCException('Related record not found', [
                    'related_model' => $relatedModelName,
                    'related_id' => $idToUnlink
                ], 404);
            }
            
            // Use RelationshipBase remove method (soft delete)
            $success = $relationship->remove($model, $relatedModel);
            
            if (!$success) {
                throw new GCException('Failed to unlink records', [
                    'model' => $modelName,
                    'id' => $id,
                    'related_model' => $relatedModelName,
                    'related_id' => $idToUnlink,
                    'relationship' => $relationshipName
                ], 500);
            }
            
            $this->logger->info('Records unlinked successfully', [
                'model' => $modelName,
                'id' => $id,
                'related_model' => $relatedModelName,
                'related_id' => $idToUnlink,
                'relationship' => $relationshipName
            ]);
            
            return ['message' => 'Records unlinked successfully'];
            
        } catch (GCException $e) {
            throw $e; // Re-throw GCExceptions as-is
        } catch (\Exception $e) {
            $this->logger->error('Failed to unlink records', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'id_to_unlink' => $idToUnlink,
                'error' => $e->getMessage()
            ]);
            throw new GCException('Failed to unlink records', [
                'model' => $modelName,
                'id' => $id,
                'relationship' => $relationshipName,
                'id_to_unlink' => $idToUnlink,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    // Helper Methods

    /**
     * Validate model name format and existence
     */
    protected function validateModelName(?string $modelName): void {
        if (empty($modelName)) {
            throw new BadRequestException('Model name is required');
        }
        
        // Validate model name format
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modelName)) {
            throw new BadRequestException('Invalid model name format', [
                'model_name' => $modelName,
                'allowed_pattern' => '^[A-Za-z][A-Za-z0-9_]*$'
            ]);
        }
        
        // Check if model exists using ModelFactory
        try {
            $availableModels = ModelFactory::getAvailableModels();
            if (!in_array($modelName, $availableModels)) {
                throw new NotFoundException('Model not found', [
                    'model_name' => $modelName,
                    'available_models' => $availableModels
                ]);
            }
        } catch (\Exception $e) {
            // If we can't get available models, try to create an instance
            try {
                ModelFactory::new($modelName);
            } catch (\Exception $createException) {
                throw new NotFoundException('Model not found or cannot be instantiated', [
                    'model_name' => $modelName,
                    'original_error' => $createException->getMessage()
                ], $createException);
            }
        }
        
        $this->logger->debug('Model name validated', ['model_name' => $modelName]);
    }

    /**
     * Validate ID format
     */
    protected function validateId($id): void {
        if (empty($id)) {
            throw new BadRequestException('ID is required');
        }
        
        // For now, just check it's not empty - specific validation can be added later
        $this->logger->debug('ID validated', ['id' => $id]);
    }

    /**
     * Validate relationship name format
     */
    protected function validateRelationshipName(?string $relationshipName): void {
        if (empty($relationshipName)) {
            throw new BadRequestException('Relationship name is required');
        }
        
        // Validate relationship name format
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $relationshipName)) {
            throw new BadRequestException('Invalid relationship name format', [
                'relationship_name' => $relationshipName,
                'allowed_pattern' => '^[A-Za-z][A-Za-z0-9_]*$'
            ]);
        }
        
        $this->logger->debug('Relationship name validated', ['relationship_name' => $relationshipName]);
    }

    /**
     * Get a valid model instance by name and ID
     */
    protected function getValidModel(string $modelName, string $id): ModelBase {
        try {
            // Use ModelFactory::retrieve() for direct database retrieval
            $model = ModelFactory::retrieve($modelName, $id);
            
            if (!$model) {
                throw new GCException('Record not found', [
                    'model' => $modelName,
                    'id' => $id
                ], 404);
            }
            
            return $model;
            
        } catch (GCException $e) {
            throw $e; // Re-throw GCExceptions as-is
        } catch (\Exception $e) {
            throw new GCException('Failed to retrieve record', [
                'model' => $modelName,
                'id' => $id,
                'original_error' => $e->getMessage()
            ], 500, $e);
        }
    }

    /**
     * Validate relationship exists and return it
     */
    protected function validateRelationshipExists(ModelBase $model, string $relationshipName): RelationshipBase {
        $relationship = $model->getRelationship($relationshipName);
        
        if (!$relationship) {
            throw new GCException('Relationship not found', [
                'relationship_name' => $relationshipName,
                'model_class' => get_class($model),
                'available_relationships' => array_keys($model->getRelationships())
            ], 404);
        }
        
        return $relationship;
    }

    /**
     * Extract simple model name from full class name
     */
    protected function extractModelNameFromClass(string $className): string {
        // Extract model name from class name
        // 'Gravitycar\Models\users\Users' -> 'Users'
        return basename(str_replace('\\', '/', $className));
    }

    /**
     * Get request data from request object
     */
    protected function getRequestData($request): array {
        // This would depend on the actual request object implementation
        // For now, return empty array as placeholder
        if (is_array($request)) {
            return $request;
        }
        
        if (is_object($request) && method_exists($request, 'all')) {
            return $request->all();
        }
        
        if (is_object($request) && method_exists($request, 'getData')) {
            return $request->getData();
        }
        
        // Fallback - try to get from global $_POST or php://input
        if (!empty($_POST)) {
            return $_POST;
        }
        
        $input = file_get_contents('php://input');
        if ($input) {
            $data = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        return [];
    }
}
