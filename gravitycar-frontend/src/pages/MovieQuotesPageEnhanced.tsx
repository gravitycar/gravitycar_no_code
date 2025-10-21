import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';

/**
 * Enhanced Movie Quotes Management Page
 * 
 * This page uses the RelatedRecordField for movie selection,
 * allowing users to search and select movies when creating/editing quotes.
 */
const MovieQuotesPageEnhanced: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Movie_Quotes"
      title="Movie Quotes Management"
      description="Manage memorable quotes from your movies with enhanced movie selection"
      defaultDisplayMode="table"
    />
  );
};

export default MovieQuotesPageEnhanced;
