import React from 'react';
import { ProjectsListView } from '../components/projects/ProjectsListView';

/**
 * Projects Page Component
 * Full page wrapper for the Projects Showcase
 * Integrates with the main application layout and navigation
 */
const ProjectsPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <ProjectsListView />
    </div>
  );
};

export default ProjectsPage;
