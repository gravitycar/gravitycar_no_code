<?php

namespace Tests\Unit\Navigation;

use PHPUnit\Framework\TestCase;
use Gravitycar\Navigation\NavigationConfig;
use Gravitycar\Exceptions\NavigationBuilderException;

class NavigationConfigTest extends TestCase
{
    private NavigationConfig $navigationConfig;
    private string $testConfigDir;
    private string $testConfigFile;

    protected function setUp(): void
    {
        // Create test-specific config directory in temp - NEVER touch source files!
        $this->testConfigDir = sys_get_temp_dir() . '/gravitycar_test_nav_config_' . uniqid();
        mkdir($this->testConfigDir, 0755, true);
        $this->testConfigFile = $this->testConfigDir . '/navigation_config.php';
        
        $testConfig = [
            'custom_pages' => [
                [
                    'key' => 'dashboard',
                    'title' => 'Dashboard',
                    'url' => '/dashboard',
                    'icon' => '📊',
                    'roles' => ['*']
                ],
                [
                    'key' => 'admin_only',
                    'title' => 'Admin Panel',
                    'url' => '/admin',
                    'icon' => '🔧',
                    'roles' => ['admin']
                ]
            ],
            'navigation_sections' => [
                [
                    'key' => 'main',
                    'title' => 'Main Navigation'
                ],
                [
                    'key' => 'models',
                    'title' => 'Data Management'
                ]
            ]
        ];
        
        file_put_contents($this->testConfigFile, '<?php return ' . var_export($testConfig, true) . ';');
        
        $this->navigationConfig = new NavigationConfig($this->testConfigFile);
    }

    protected function tearDown(): void
    {
        // Clean up test config directory - this is OUR test directory, safe to delete
        if (is_dir($this->testConfigDir)) {
            $files = glob($this->testConfigDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testConfigDir);
        }
    }

    public function testGetCustomPagesForRole(): void
    {
        // Test admin role gets all pages
        $adminPages = $this->navigationConfig->getCustomPagesForRole('admin');
        $this->assertCount(2, $adminPages);
        
        // Verify both pages are present
        $pageKeys = array_column($adminPages, 'key');
        $this->assertContains('dashboard', $pageKeys);
        $this->assertContains('admin_only', $pageKeys);
        
        // Test user role only gets pages with '*' or 'user' role
        $userPages = $this->navigationConfig->getCustomPagesForRole('user');
        $this->assertCount(1, $userPages);
        $this->assertEquals('dashboard', $userPages[0]['key']);
        
        // Test guest role only gets universal pages
        $guestPages = $this->navigationConfig->getCustomPagesForRole('guest');
        $this->assertCount(1, $guestPages);
        $this->assertEquals('dashboard', $guestPages[0]['key']);
    }

    public function testGetConfigValue(): void
    {
        // Test getting custom pages
        $customPages = $this->navigationConfig->get('custom_pages');
        $this->assertIsArray($customPages);
        $this->assertCount(2, $customPages);
        
        // Test getting navigation sections
        $sections = $this->navigationConfig->get('navigation_sections');
        $this->assertIsArray($sections);
        $this->assertCount(2, $sections);
        
        // Test default value for non-existent key
        $nonExistent = $this->navigationConfig->get('non_existent', 'default');
        $this->assertEquals('default', $nonExistent);
        
        // Test nested key access
        $firstPageKey = $this->navigationConfig->get('custom_pages.0.key');
        $this->assertEquals('dashboard', $firstPageKey);
    }

    public function testGetNavigationSections(): void
    {
        $sections = $this->navigationConfig->getNavigationSections();
        
        $this->assertIsArray($sections);
        $this->assertCount(2, $sections);
        
        // Check structure of sections
        $this->assertArrayHasKey('key', $sections[0]);
        $this->assertArrayHasKey('title', $sections[0]);
        $this->assertEquals('main', $sections[0]['key']);
        $this->assertEquals('Main Navigation', $sections[0]['title']);
    }

    public function testConfigFileNotFound(): void
    {
        // Remove the test config file
        unlink($this->testConfigFile);
        
        $this->expectException(NavigationBuilderException::class);
        $this->expectExceptionMessage('Navigation config file not found');
        
        // Try to create NavigationConfig with non-existent file
        new NavigationConfig($this->testConfigFile);
    }

    public function testInvalidConfigFile(): void
    {
        // Create invalid config file (not returning array)
        file_put_contents($this->testConfigFile, '<?php return "invalid";');
        
        $this->expectException(NavigationBuilderException::class);
        $this->expectExceptionMessage('Navigation config file must return an array');
        
        // Try to create NavigationConfig with invalid file
        new NavigationConfig($this->testConfigFile);
    }

    public function testEmptyRoleFilter(): void
    {
        $pages = $this->navigationConfig->getCustomPagesForRole('nonexistent');
        $this->assertIsArray($pages);
        $this->assertCount(1, $pages); // Should still get universal pages (*)
        $this->assertEquals('dashboard', $pages[0]['key']);
    }
}