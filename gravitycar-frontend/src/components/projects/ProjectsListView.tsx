import React, { useState, useEffect, useRef, useCallback } from 'react';
import apiService from '../../services/api';
import ProjectDetailModal from './ProjectDetailModal';
import { Project } from './types';

interface ProjectTileProps {
  project: Project;
  imgError: boolean;
  onImgError: (id: string) => void;
  onClick: (project: Project) => void;
  tileRef: (el: HTMLDivElement | null) => void;
}

function getInitials(title: string): string {
  return title
    .split(' ')
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join('');
}

function ProjectTile({ project, imgError, onImgError, onClick, tileRef }: ProjectTileProps): React.ReactElement {
  const handleKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onClick(project);
    }
  };

  return (
    <div
      ref={tileRef}
      role="button"
      tabIndex={0}
      aria-label={project.title}
      className="relative h-[300px] overflow-hidden rounded-xl cursor-pointer group shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
      onClick={() => onClick(project)}
      onKeyDown={handleKeyDown}
    >
      {imgError ? (
        <div className="absolute inset-0 bg-gray-300 flex items-center justify-center">
          <span className="text-4xl font-bold text-gray-600">
            {getInitials(project.title)}
          </span>
        </div>
      ) : (
        <img
          src={project.screenshot}
          alt={project.title}
          className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          onError={() => onImgError(project.id)}
        />
      )}

      <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/70 pointer-events-none" />

      <p className="absolute top-0 left-0 right-0 p-3 text-white text-xl font-bold drop-shadow-md line-clamp-2 pointer-events-none">
        {project.title}
      </p>

      <p className="absolute bottom-0 left-0 right-0 p-3 text-white text-sm drop-shadow-md line-clamp-2 pointer-events-none">
        {project.tag_line}
      </p>
    </div>
  );
}

export function ProjectsListView(): React.ReactElement {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedProject, setSelectedProject] = useState<Project | null>(null);
  const [imgErrors, setImgErrors] = useState<Record<string, boolean>>({});
  const tileRefs = useRef<Record<string, HTMLDivElement | null>>({});

  const fetchProjects = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await apiService.getList<Project>('Projects', 1, 1000);
      if (response.success) {
        setProjects(response.data ?? []);
      } else {
        setError(response.message ?? 'Failed to load projects.');
      }
    } catch {
      setError('Failed to load projects. Please try again later.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchProjects();
  }, [fetchProjects]);

  const handleImgError = useCallback((projectId: string) => {
    setImgErrors((prev) => ({ ...prev, [projectId]: true }));
  }, []);

  const handleTileClick = useCallback((project: Project) => {
    setSelectedProject(project);
  }, []);

  const handleModalClose = useCallback(() => {
    const closingProjectId = selectedProject?.id;
    setSelectedProject(null);
    if (closingProjectId) {
      setTimeout(() => {
        tileRefs.current[closingProjectId]?.focus();
      }, 0);
    }
  }, [selectedProject]);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <p className="text-gray-500 text-lg">Loading...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <p className="text-red-500 text-lg">{error}</p>
      </div>
    );
  }

  if (projects.length === 0) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <p className="text-gray-500 text-lg">No projects yet.</p>
      </div>
    );
  }

  return (
    <div className="w-full px-4 py-6">
      <h1 className="text-3xl font-bold text-gray-900 mb-6">Projects</h1>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {projects.map((project) => (
          <ProjectTile
            key={project.id}
            project={project}
            imgError={imgErrors[project.id] ?? false}
            onImgError={handleImgError}
            onClick={handleTileClick}
            tileRef={(el) => { tileRefs.current[project.id] = el; }}
          />
        ))}
      </div>

      <ProjectDetailModal
        project={selectedProject}
        onClose={handleModalClose}
      />
    </div>
  );
}
