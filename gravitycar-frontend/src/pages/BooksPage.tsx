import React from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';

const BooksPage: React.FC = () => {
  return (
    <GenericCrudPage
      modelName="Books"
      title="Books Management"
    />
  );
};

export default BooksPage;
