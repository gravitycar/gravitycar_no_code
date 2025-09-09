<?php
namespace Gravitycar\Models\users;

use Gravitycar\Models\ModelBase;
use Gravitycar\Core\ServiceLocator;

/**
 * Users model for Gravitycar framework
 */
class Users extends ModelBase {

    /**
     * Hash password before saving
     */
    public function create(): bool {
        // Hash password if it's not already hashed
        if ($this->get('password') && !$this->isPasswordHashed($this->get('password'))) {
            $this->set('password', password_hash($this->get('password'), PASSWORD_DEFAULT));
        }

        // Copy username to email if email is empty
        if (!$this->get('email') && $this->get('username')) {
            $this->set('email', $this->get('username'));
        }

        return parent::create();
    }

    /**
     * Hash password before updating
     */
    public function update(): bool {
        // Hash password if it's been changed and not already hashed
        if ($this->get('password') && !$this->isPasswordHashed($this->get('password'))) {
            $this->set('password', password_hash($this->get('password'), PASSWORD_DEFAULT));
        }

        return parent::update();
    }

    /**
     * Check if password is already hashed
     */
    private function isPasswordHashed(string $password): bool {
        // Password hashes typically start with $2y$ for bcrypt
        return strlen($password) >= 60 && str_starts_with($password, '$2y$');
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): bool {
        $this->set('last_login', date('Y-m-d H:i:s'));
        return $this->update();
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool {
        return $this->get('user_type') === 'admin';
    }

    /**
     * Check if user is manager
     */
    public function isManager(): bool {
        return $this->get('user_type') === 'manager';
    }

    /**
     * Get full name
     */
    public function getFullName(): string {
        return trim($this->get('first_name') . ' ' . $this->get('last_name'));
    }
    
    // ===================================================================
    // PHASE 3: React Library Configuration Implementation
    // ===================================================================
    
    /**
     * Configure searchable fields for React components
     * 
     * Optimized for user search functionality across common user fields.
     */
    protected function getSearchableFields(): array
    {
        return ['first_name', 'last_name', 'email', 'username'];
    }
    
    /**
     * Configure sortable fields for React components
     * 
     * Only allow sorting on indexed or efficient-to-sort fields.
     */
    protected function getSortableFields(): array
    {
        return ['id', 'email', 'username', 'first_name', 'last_name', 'created_at', 'updated_at', 'last_login'];
    }
    
    /**
     * Configure default sorting for React components
     * 
     * Default to most recently created users first.
     */
    protected function getDefaultSort(): array
    {
        return [['field' => 'created_at', 'direction' => 'desc']];
    }
    
    /**
     * Custom filter validation for business rules
     * 
     * Implements security rules for user data filtering.
     */
    protected function validateCustomFilters(array $filters): array
    {
        // Get current user context (if available)
        $currentUser = null;
        try {
            $currentUser = $this->getCurrentUser();
        } catch (\Exception $e) {
            // No current user context available
        }
        
        // Security rules for user filtering
        if ($currentUser && method_exists($currentUser, 'get')) {
            $userType = $currentUser->get('user_type') ?? '';
            $isAdmin = ($userType === 'admin');
            $isManager = ($userType === 'manager');
            
            // Non-admin users cannot filter by sensitive fields
            if (!$isAdmin) {
                $restrictedFields = ['password', 'salary', 'ssn', 'bank_account'];
                
                $originalCount = count($filters);
                $filters = array_filter($filters, function($filter) use ($restrictedFields) {
                    return !in_array($filter['field'] ?? '', $restrictedFields);
                });
                
                if ($originalCount !== count($filters)) {
                    $this->logger->info("Applied security filtering for non-admin user", [
                        'user_id' => $currentUser->get('id'),
                        'original_filter_count' => $originalCount,
                        'filtered_count' => count($filters),
                        'restricted_fields' => $restrictedFields
                    ]);
                }
            }
            
            // Regular users can only see active users (unless admin/manager)
            if (!$isAdmin && !$isManager) {
                // Add implicit filter for active users only
                $hasStatusFilter = false;
                foreach ($filters as $filter) {
                    if (($filter['field'] ?? '') === 'status') {
                        $hasStatusFilter = true;
                        break;
                    }
                }
                
                if (!$hasStatusFilter) {
                    $filters[] = [
                        'field' => 'status',
                        'operator' => 'equals',
                        'value' => 'active'
                    ];
                    
                    $this->logger->info("Added implicit active user filter for regular user", [
                        'user_id' => $currentUser->get('id'),
                        'user_type' => $userType
                    ]);
                }
            }
        }
        
        return $filters;
    }
    
    /**
     * Configure pagination for React components
     * 
     * Optimized for user management interfaces.
     */
    protected function getPaginationConfig(): array
    {
        return [
            'defaultPageSize' => 25, // Good balance for user management
            'maxPageSize' => 500,    // Reasonable limit for user lists
            'allowedPageSizes' => [10, 25, 50, 100, 250, 500]
        ];
    }
    
    /**
     * Configure React library compatibility
     * 
     * Optimized for modern React data fetching patterns.
     */
    protected function getReactCompatibility(): array
    {
        return [
            'supportedFormats' => ['standard', 'ag-grid', 'mui', 'tanstack-query', 'swr', 'infinite-scroll'],
            'defaultFormat' => 'tanstack-query', // Preferred for user management UIs
            'optimizedFor' => ['tanstack-query', 'ag-grid'], // Best performance with these libraries
            'cursorPagination' => true,  // Enable for large user datasets
            'realTimeUpdates' => false,  // Disable for security (user data is sensitive)
            'cacheStrategy' => 'standard' // Conservative caching for user data
        ];
    }
    
    // ===================================================================
    // Business Logic Methods
    // ===================================================================
    
    /**
     * Check if user has role
     */
    public function hasRole(string $role): bool
    {
        $userRole = $this->get('user_type') ?? $this->get('role');
        return strtolower($userRole) === strtolower($role);
    }
    
    /**
     * Check if user can access admin features
     */
    public function canAccessAdmin(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }
    
    /**
     * Get user display name (for UI components)
     */
    public function getDisplayName(): string
    {
        $fullName = $this->getFullName();
        if (!empty($fullName)) {
            return $fullName;
        }
        
        return $this->get('username') ?? $this->get('email') ?? 'Unknown User';
    }
    
    /**
     * Get user status for filtering/display
     */
    public function getStatus(): string
    {
        return $this->get('status') ?? 'active';
    }
    
    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->getStatus() === 'active';
    }
}
