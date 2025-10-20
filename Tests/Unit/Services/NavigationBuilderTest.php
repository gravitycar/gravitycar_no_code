<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Gravitycar\Services\NavigationBuilder;
use Gravitycar\Contracts\MetadataEngineInterface;
use Gravitycar\Services\AuthorizationService;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Factories\ModelFactory;
use Psr\Log\LoggerInterface;

class NavigationBuilderTest extends TestCase
{
    private NavigationBuilder $navigationBuilder;
    /** @var LoggerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $mockLogger;
    /** @var MetadataEngineInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $mockMetadataEngine;
    /** @var AuthorizationService|\PHPUnit\Framework\MockObject\MockObject */
    private $mockAuthorizationService;
    /** @var NavigationConfig|\PHPUnit\Framework\MockObject\MockObject */
    private $mockNavigationConfig;
    /** @var ModelFactory|\PHPUnit\Framework\MockObject\MockObject */
    private $mockModelFactory;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->mockMetadataEngine = $this->createMock(MetadataEngineInterface::class);
        $this->mockAuthorizationService = $this->createMock(AuthorizationService::class);
        $this->mockNavigationConfig = $this->createMock(NavigationConfig::class);
        $this->mockModelFactory = $this->createMock(ModelFactory::class);

        $this->navigationBuilder = new NavigationBuilder(
            /** @phpstan-ignore-next-line */
            $this->mockLogger,
            /** @phpstan-ignore-next-line */
            $this->mockMetadataEngine,
            /** @phpstan-ignore-next-line */
            $this->mockAuthorizationService,
            /** @phpstan-ignore-next-line */
            $this->mockNavigationConfig,
            /** @phpstan-ignore-next-line */
            $this->mockModelFactory
        );
    }

    public function testBuildNavigationForRole(): void
    {
        // Mock available models
        $this->mockMetadataEngine->expects($this->once())
            ->method('getAvailableModels')
            ->willReturn(['Users', 'Movies']);

        // Mock custom pages
        $this->mockNavigationConfig->expects($this->once())
            ->method('getCustomPagesForRole')
            ->with('admin')
            ->willReturn([
                ['key' => 'dashboard', 'title' => 'Dashboard', 'url' => '/dashboard']
            ]);

        // Mock navigation sections
        $this->mockNavigationConfig->expects($this->once())
            ->method('getNavigationSections')
            ->willReturn([
                ['key' => 'main', 'title' => 'Main Navigation']
            ]);

        // Mock role model creation and finding
        $mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $mockRoleModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Roles')
            ->willReturn($mockRoleModel);

        $mockRoleModel->expects($this->once())
            ->method('find')
            ->with(['name' => 'admin'])
            ->willReturn([$mockRole]);

        // Mock permission checks - admin has all permissions
        $this->mockAuthorizationService->expects($this->atLeastOnce())
            ->method('roleHasPermission')
            ->willReturnCallback(function($role, $permission, $model) {
                // Admin has all permissions for both models
                return true;
            });

        $result = $this->navigationBuilder->buildNavigationForRole('admin');

        // Verify structure
        $this->assertIsArray($result);
        $this->assertEquals('admin', $result['role']);
        $this->assertArrayHasKey('custom_pages', $result);
        $this->assertArrayHasKey('models', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('generated_at', $result);
        
        // Verify content
        $this->assertCount(1, $result['custom_pages']);
        $this->assertCount(2, $result['models']); // Users and Movies
        $this->assertCount(1, $result['sections']);
        
        // Verify models have correct permissions
        foreach ($result['models'] as $model) {
            $this->assertTrue($model['permissions']['list']);
            $this->assertTrue($model['permissions']['create']);
            $this->assertTrue($model['permissions']['update']);
            $this->assertTrue($model['permissions']['delete']);
            $this->assertCount(1, $model['actions']); // Should have create action
        }
    }

    public function testBuildModelNavigationFiltersUnauthorizedModels(): void
    {
        $modelNames = ['Users', 'Movies', 'RestrictedModel'];

        // Mock role creation and finding
        $mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $mockRoleModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        
        $this->mockModelFactory->method('new')
            ->willReturn($mockRoleModel);

        $mockRoleModel->method('find')
            ->willReturn([$mockRole]);

        // Mock permissions - Users and Movies allowed, RestrictedModel denied
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturnCallback(function($role, $permission, $model) {
                if ($model === 'RestrictedModel') {
                    return false; // No access to RestrictedModel
                }
                return $permission === 'list'; // Only list permission for others
            });

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod('buildModelNavigation');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->navigationBuilder, [$modelNames, 'user']);

        // Should only have 2 models (Users and Movies), RestrictedModel filtered out
        $this->assertCount(2, $result);
        $modelNames = array_column($result, 'name');
        $this->assertContains('Users', $modelNames);
        $this->assertContains('Movies', $modelNames);
        $this->assertNotContains('RestrictedModel', $modelNames);
        
        // Verify permissions structure
        foreach ($result as $model) {
            $this->assertTrue($model['permissions']['list']);
            $this->assertFalse($model['permissions']['create']); // No create permission
            $this->assertFalse($model['permissions']['update']);
            $this->assertFalse($model['permissions']['delete']);
            $this->assertEmpty($model['actions']); // No actions without create permission
        }
    }

    public function testGetRoleByName(): void
    {
        $mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $mockRoleModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Roles')
            ->willReturn($mockRoleModel);

        $mockRoleModel->expects($this->once())
            ->method('find')
            ->with(['name' => 'admin'])
            ->willReturn([$mockRole]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod('getRoleByName');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->navigationBuilder, ['admin']);
        
        $this->assertSame($mockRole, $result);
    }

    public function testGetRoleByNameNotFound(): void
    {
        $mockRoleModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        
        $this->mockModelFactory->expects($this->once())
            ->method('new')
            ->with('Roles')
            ->willReturn($mockRoleModel);

        $mockRoleModel->expects($this->once())
            ->method('find')
            ->with(['name' => 'nonexistent'])
            ->willReturn([]); // No role found

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod('getRoleByName');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->navigationBuilder, ['nonexistent']);
        
        $this->assertNull($result);
    }

    public function testGenerateModelTitle(): void
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod('generateModelTitle');
        $method->setAccessible(true);

        // Test various model name formats
        $this->assertEquals('Users', $method->invokeArgs($this->navigationBuilder, ['Users']));
        $this->assertEquals('Movie Quotes', $method->invokeArgs($this->navigationBuilder, ['MovieQuotes']));
        $this->assertEquals('Movie Quotes', $method->invokeArgs($this->navigationBuilder, ['Movie_Quotes']));
        $this->assertEquals('U S A Model', $method->invokeArgs($this->navigationBuilder, ['USAModel']));
    }

    public function testGetModelIcon(): void
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->navigationBuilder);
        $method = $reflection->getMethod('getModelIcon');
        $method->setAccessible(true);

        // Test known icons
        $this->assertEquals('👥', $method->invokeArgs($this->navigationBuilder, ['Users']));
        $this->assertEquals('🎬', $method->invokeArgs($this->navigationBuilder, ['Movies']));
        $this->assertEquals('💬', $method->invokeArgs($this->navigationBuilder, ['Movie_Quotes']));
        $this->assertEquals('📚', $method->invokeArgs($this->navigationBuilder, ['Books']));
        
        // Test default icon for unknown model
        $this->assertEquals('📋', $method->invokeArgs($this->navigationBuilder, ['UnknownModel']));
    }

    public function testBuildAllRoleNavigationCaches(): void
    {
        // Mock the individual role building
        $this->mockMetadataEngine->method('getAvailableModels')
            ->willReturn(['Users']);
        
        $this->mockNavigationConfig->method('getCustomPagesForRole')
            ->willReturn([]);
            
        $this->mockNavigationConfig->method('getNavigationSections')
            ->willReturn([]);

        // Mock successful role creation for all roles
        $mockRole = $this->createMock(\Gravitycar\Models\ModelBase::class);
        $mockRoleModel = $this->createMock(\Gravitycar\Models\ModelBase::class);
        
        $this->mockModelFactory->method('new')
            ->willReturn($mockRoleModel);
            
        $mockRoleModel->method('find')
            ->willReturn([$mockRole]);
            
        $this->mockAuthorizationService->method('roleHasPermission')
            ->willReturn(true);

        $result = $this->navigationBuilder->buildAllRoleNavigationCaches();

        // Should have results for all 4 roles
        $this->assertCount(4, $result);
        $this->assertArrayHasKey('admin', $result);
        $this->assertArrayHasKey('manager', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('guest', $result);

        // Each result should have success status and cache info
        foreach ($result as $role => $cacheResult) {
            $this->assertTrue($cacheResult['success']);
            $this->assertArrayHasKey('cache_file', $cacheResult);
            $this->assertArrayHasKey('items_count', $cacheResult);
            $this->assertStringContainsString("navigation_cache_{$role}.php", $cacheResult['cache_file']);
        }
    }
}