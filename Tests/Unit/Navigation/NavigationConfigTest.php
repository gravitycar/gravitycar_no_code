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
        // Create temporary config file for testing
        $this->testConfigDir = 'src/Navigation';
        $this->testConfigFile = $this->testConfigDir . '/navigation_config.php';
        
        if (!is_dir($this->testConfigDir)) {
            mkdir($this->testConfigDir, 0755, true);
        }
        
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
        
        $this->navigationConfig = new NavigationConfig();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->testConfigFile)) {
            unlink($this->testConfigFile);
        }
        
        // Remove directory if empty
        if (is_dir($this->testConfigDir) && count(scandir($this->testConfigDir)) === 2) {
            rmdir($this->testConfigDir);
        }
        
        // Remove src directory if empty and exists
        if (is_dir('src') && count(scandir('src')) === 2) {
            rmdir('src');
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
        // Remove the config file
        unlink($this->testConfigFile);
        
        $this->expectException(NavigationBuilderException::class);
        $this->expectExceptionMessage('Navigation config file not found');
        
        new NavigationConfig();
    }

    public function testInvalidConfigFile(): void
    {
        // Create invalid config file (not returning array)
        file_put_contents($this->testConfigFile, '<?php return "invalid";');
        
        $this->expectException(NavigationBuilderException::class);
        $this->expectExceptionMessage('Navigation config file must return an array');
        
        new NavigationConfig();
    }

    public function testEmptyRoleFilter(): void
    {
        $pages = $this->navigationConfig->getCustomPagesForRole('nonexistent');
        $this->assertIsArray($pages);
        $this->assertCount(1, $pages); // Should still get universal pages (*)
        $this->assertEquals('dashboard', $pages[0]['key']);
    }
}