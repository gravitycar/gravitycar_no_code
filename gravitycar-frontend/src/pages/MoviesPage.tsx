import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';

/**
 * Movies Management Page - Uses the generic metadata-driven CRUD component
 */
const MoviesPage: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Movies"
      title="Movies Management"
      description="Manage your movie collection"
      defaultDisplayMode="table"
    />
  );
};

export default MoviesPage;
