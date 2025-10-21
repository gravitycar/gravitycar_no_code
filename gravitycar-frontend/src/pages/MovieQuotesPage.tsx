import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';

/**
 * Movie Quotes Management Page - Uses the generic metadata-driven CRUD component
 */
const MovieQuotesPage: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Movie_Quotes"
      title="Movie Quotes Management"
      description="Manage memorable quotes from your movies"
      defaultDisplayMode="table"
    />
  );
};

export default MovieQuotesPage;
