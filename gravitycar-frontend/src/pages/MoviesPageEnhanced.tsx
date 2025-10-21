import React, { useState } from 'react';
import GenericCrudPage from '../components/crud/GenericCrudPage';
import RelatedItemsSection from '../components/relationships/RelatedItemsSection';
import Modal from '../components/ui/Modal';
import type { Movie } from '../types';

/**
 * Enhanced Movies Management Page with TMDB Integration and Quote Management
 * 
 * This page demonstrates the One-to-Many relationship management between
 * Movies and Movie_Quotes using our new relationship UI components.
 */
const MoviesPage: React.FC = () => {
  const [selectedMovie, setSelectedMovie] = useState<Movie | null>(null);
  const [isQuotesModalOpen, setIsQuotesModalOpen] = useState(false);

  const handleCloseQuotesModal = () => {
    setSelectedMovie(null);
    setIsQuotesModalOpen(false);
  };

  return (
    <>
      <GenericCrudPage
        modelName="Movies"
        title="Movies Management"
        description="Manage your movie collection with TMDB integration and memorable quotes"
        defaultDisplayMode="table"
      />

      {/* Movie Quotes Management Modal */}
      {isQuotesModalOpen && selectedMovie && (
        <Modal
          isOpen={isQuotesModalOpen}
          onClose={handleCloseQuotesModal}
          title={`Manage Quotes: ${selectedMovie.name}`}
          size="lg"
        >
          <div className="max-h-[70vh] overflow-y-auto">
            <RelatedItemsSection
              title="Movie Quotes"
              parentModel="Movies"
              parentId={selectedMovie.id}
              relationship="quotes"
              relatedModel="Movie_Quotes"
              displayColumns={['quote']}
              actions={['create', 'edit', 'delete']}
              createFields={['quote']}
              editFields={['quote']}
              allowInlineCreate={true}
              allowInlineEdit={true}
              permissions={{
                canCreate: true,
                canEdit: true,
                canDelete: true,
                canReorder: false
              }}
            />
          </div>
          
          <div className="mt-6 flex justify-end">
            <button
              onClick={handleCloseQuotesModal}
              className="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300"
            >
              Close
            </button>
          </div>
        </Modal>
      )}
    </>
  );
};

export default MoviesPage;
