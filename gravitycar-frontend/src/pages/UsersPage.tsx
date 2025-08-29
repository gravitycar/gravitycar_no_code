import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';

/**
 * Users Management Page - Now uses the generic metadata-driven CRUD component
 * This ensures consistent UI patterns across all models
 */
const UsersPage: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Users"
      title="Users Management"
      description="Manage user accounts and permissions"
      defaultDisplayMode="table"
    />
  );
};

export default UsersPage;
