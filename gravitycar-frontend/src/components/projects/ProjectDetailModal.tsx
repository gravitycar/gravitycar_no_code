import React, { useState, useEffect, useRef } from 'react';
import { Project } from './types';

const STATUS_STYLES: Record<string, string> = {
  'Planned': 'bg-gray-200 text-gray-700',
  'In Progress': 'bg-amber-100 text-amber-800',
  'Complete': 'bg-green-100 text-green-800',
};

interface ProjectDetailModalProps {
  project: Project | null;
  onClose: () => void;
}

const TITLE_ID = 'project-detail-title';

function getInitials(title: string): string {
  return title
    .split(' ')
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join('');
}

function renderScreenshot(
  project: Project,
  imgError: boolean,
  setImgError: (v: boolean) => void
): React.ReactElement {
  if (imgError) {
    return (
      <div
        className="w-full max-h-[50vh] flex items-center justify-center bg-gray-200 rounded-lg"
        style={{ minHeight: '200px' }}
      >
        <span className="text-4xl font-bold text-gray-500">
          {getInitials(project.title)}
        </span>
      </div>
    );
  }

  return (
    <img
      src={project.screenshot}
      alt={project.title}
      className="w-full max-h-[50vh] object-contain rounded-lg"
      onError={() => setImgError(true)}
    />
  );
}

/**
 * ProjectDetailModal
 *
 * Displays full project details in a modal overlay.
 * Renders nothing when project is null (closed state).
 */
export default function ProjectDetailModal({
  project,
  onClose,
}: ProjectDetailModalProps): React.ReactElement | null {
  const [imgError, setImgError] = useState<boolean>(false);
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  // Reset image error state whenever a new project is opened
  useEffect(() => {
    setImgError(false);
  }, [project]);

  // Register Escape key listener while modal is open
  useEffect(() => {
    if (!project) return;

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [project, onClose]);

  // Lock body scroll while modal is open; restore on close
  useEffect(() => {
    if (!project) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [project]);

  // Move focus to close button when modal opens
  useEffect(() => {
    if (!project) return;
    closeButtonRef.current?.focus();
  }, [project]);

  if (!project) return null;

  const hasLink = Boolean(project.link && project.link.trim() !== '');

  return (
    <div
      className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
      onClick={onClose}
      aria-hidden="true"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby={TITLE_ID}
        className="relative bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Close button */}
        <button
          ref={closeButtonRef}
          onClick={onClose}
          aria-label="Close"
          className="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 text-lg leading-none"
        >
          &times;
        </button>

        {/* Title */}
        <h2
          id={TITLE_ID}
          className="text-2xl font-bold text-gray-900 text-center pr-8 mb-1"
        >
          {project.title}
        </h2>

        {/* Status badge */}
        {project.status && (
          <div className="flex justify-center mb-2">
            <span className={`text-xs font-semibold px-3 py-1 rounded-full ${STATUS_STYLES[project.status] ?? 'bg-gray-200 text-gray-700'}`}>
              {project.status}
            </span>
          </div>
        )}

        {/* Tag line */}
        <p className="text-center text-gray-500 text-sm mb-4">
          {project.tag_line}
        </p>

        {/* Screenshot — wrapped in <a> only when link is set */}
        {hasLink ? (
          <a
            href={project.link}
            target="_blank"
            rel="noopener noreferrer"
            className="block mb-4 cursor-pointer"
          >
            {renderScreenshot(project, imgError, setImgError)}
          </a>
        ) : (
          <div className="mb-4">
            {renderScreenshot(project, imgError, setImgError)}
          </div>
        )}

        {/* Description */}
        <p className="text-gray-700 text-sm leading-relaxed text-left mb-4 whitespace-pre-wrap">
          {project.description}
        </p>

        {/* "Check it out" button — only when link is set */}
        {hasLink && (
          <div className="flex justify-center">
            <a
              href={project.link}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg transition-colors"
            >
              Check it out
            </a>
          </div>
        )}
      </div>
    </div>
  );
}
