/**
 * DebugPanel Component
 * 
 * Collapsible panel that displays diagnostic information from D&D RAG queries.
 * Defaults to collapsed state. Click header to expand/collapse.
 */

import React from 'react';

interface DebugPanelProps {
  /** Array of diagnostic messages */
  diagnostics: string[];
  /** Whether the panel is expanded */
  isExpanded: boolean;
  /** Callback when panel is toggled */
  onToggle: () => void;
}

const DebugPanel: React.FC<DebugPanelProps> = ({ diagnostics, isExpanded, onToggle }) => {
  return (
    <div className="w-full mt-4 border border-gray-300 rounded-md bg-white">
      {/* Collapsible Header */}
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between px-4 py-3 bg-gray-50 hover:bg-gray-100 transition-colors rounded-t-md focus:outline-none focus:ring-2 focus:ring-blue-500"
        aria-expanded={isExpanded}
        aria-controls="debug-panel-content"
      >
        <span className="font-semibold text-gray-700">
          Debug Information {diagnostics.length > 0 && `(${diagnostics.length} items)`}
        </span>
        
        <svg
          className={`w-5 h-5 text-gray-600 transition-transform duration-200 ${
            isExpanded ? 'transform rotate-180' : ''
          }`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      
      {/* Collapsible Content */}
      <div
        id="debug-panel-content"
        className={`overflow-hidden transition-all duration-300 ease-in-out ${
          isExpanded ? 'max-h-96' : 'max-h-0'
        }`}
      >
        <div className="p-4 bg-gray-50 border-t border-gray-200">
          {diagnostics.length === 0 ? (
            <p className="text-gray-500 italic">No diagnostic information available yet.</p>
          ) : (
            <div className="space-y-2 max-h-80 overflow-y-auto text-left">
              {diagnostics.map((diagnostic, index) => (
                <div
                  key={index}
                  className="flex items-start space-x-2 text-sm text-left"
                >
                  <span className="text-blue-600 font-mono shrink-0">›</span>
                  <span className="text-gray-700 font-mono break-words flex-1 text-left">{diagnostic}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default DebugPanel;
