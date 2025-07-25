<?php

namespace Gravitycar\Api;

use Gravitycar\Core\GCException;
use Gravitycar\Core\ModelBase;

/**
 * Base API Controller for handling RESTful operations
 *
 * Provides standard CRUD operations that can be used by all models
 * in the Gravitycar framework.
 */
abstract class ApiController
{
    protected string $modelClass;
    protected array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
    protected bool $requiresAuth = false;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function handleRequest(string $method, array $params = [], array $data = []): array
    {
        if (!in_array($method, $this->allowedMethods)) {
            throw new GCException("Method {$method} not allowed", 405);
        }

        if ($this->requiresAuth && !$this->isAuthenticated()) {
            throw new GCException("Authentication required", 401);
        }

        switch ($method) {
            case 'GET':
                return isset($params['id']) ? $this->show($params['id']) : $this->index($params);
            case 'POST':
                return $this->create($data);
            case 'PUT':
                if (!isset($params['id'])) {
                    throw new GCException("ID required for PUT request", 400);
                }
                return $this->update($params['id'], $data);
            case 'DELETE':
                if (!isset($params['id'])) {
                    throw new GCException("ID required for DELETE request", 400);
                }
                return $this->delete($params['id']);
            default:
                throw new GCException("Unsupported method: {$method}", 405);
        }
    }

    protected function index(array $params): array
    {
        $limit = $params['limit'] ?? 50;
        $offset = $params['offset'] ?? 0;
        $conditions = $this->buildConditions($params);

        $models = $this->modelClass::findAll($conditions, $limit, $offset);

        return [
            'success' => true,
            'data' => array_map(fn($model) => $model->toArray(), $models),
            'meta' => [
                'total' => count($models),
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    protected function show(mixed $id): array
    {
        $model = $this->modelClass::find($id);

        if (!$model) {
            throw new GCException("Record not found", 404);
        }

        return [
            'success' => true,
            'data' => $model->toArray()
        ];
    }

    protected function create(array $data): array
    {
        $model = new $this->modelClass($data);

        if (!$model->validate()) {
            return [
                'success' => false,
                'errors' => $model->getValidationErrors()
            ];
        }

        if ($model->save()) {
            return [
                'success' => true,
                'data' => $model->toArray()
            ];
        }

        throw new GCException("Failed to create record", 500);
    }

    protected function update(mixed $id, array $data): array
    {
        $model = $this->modelClass::find($id);

        if (!$model) {
            throw new GCException("Record not found", 404);
        }

        foreach ($data as $field => $value) {
            try {
                $model->set($field, $value);
            } catch (GCException $e) {
                // Skip invalid fields
                continue;
            }
        }

        if (!$model->validate()) {
            return [
                'success' => false,
                'errors' => $model->getValidationErrors()
            ];
        }

        if ($model->save()) {
            return [
                'success' => true,
                'data' => $model->toArray()
            ];
        }

        throw new GCException("Failed to update record", 500);
    }

    protected function delete(mixed $id): array
    {
        $model = $this->modelClass::find($id);

        if (!$model) {
            throw new GCException("Record not found", 404);
        }

        if ($model->delete()) {
            return [
                'success' => true,
                'message' => 'Record deleted successfully'
            ];
        }

        throw new GCException("Failed to delete record", 500);
    }

    protected function buildConditions(array $params): array
    {
        $conditions = [];

        // Remove pagination and system parameters
        $excludeParams = ['limit', 'offset', 'sort', 'order'];

        foreach ($params as $key => $value) {
            if (!in_array($key, $excludeParams) && !empty($value)) {
                $conditions[$key] = $value;
            }
        }

        return $conditions;
    }

    protected function isAuthenticated(): bool
    {
        // Basic authentication check - override in child classes for specific auth logic
        return isset($_SESSION['user_id']) || $this->hasValidApiKey();
    }

    protected function hasValidApiKey(): bool
    {
        // Check for API key in headers
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
        // Implement API key validation logic here
        return !empty($apiKey);
    }

    public function setRequiresAuth(bool $requiresAuth): void
    {
        $this->requiresAuth = $requiresAuth;
    }
}
