<?php
namespace Gravitycar\Models\users;

use Gravitycar\Models\ModelBase;

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
}
