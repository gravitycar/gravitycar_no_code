<?php
use Gravitycar\Api\ApiControllerBase;

/**
 * Mock API Controller for testing purposes
 */
class MockApiController extends ApiControllerBase
{
    public function registerRoutes(): array
    {
        return []; // No routes for testing purposes
    }
    
    public function getUsers()
    {
        return 'success';
    }
    
    public function getUser()  
    {
        return 'success';
    }
    
    public function nonexistentMethod()
    {
        return 'success';
    }
}
