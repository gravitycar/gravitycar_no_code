import React, { useState } from 'react';

interface VideoEmbedProps {
  value: string;
  onChange: (value: string) => void;
  width?: number;
  height?: number;
  readOnly?: boolean;
  label?: string;
  placeholder?: string;
}

export const VideoEmbed: React.FC<VideoEmbedProps> = ({
  value,
  onChange,
  width = 560,
  height = 315,
  readOnly = false,
  label = 'Video URL',
  placeholder = 'Enter YouTube or Vimeo URL'
}) => {
  const [showPreview, setShowPreview] = useState(false);
  const [validationError, setValidationError] = useState<string>('');
  
  const getEmbedUrl = (url: string): string | null => {
    if (!url) return null;
    
    // YouTube patterns
    const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
    if (youtubeMatch) {
      return `https://www.youtube.com/embed/${youtubeMatch[1]}`;
    }
    
    // Vimeo patterns
    const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) {
      return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
    }
    
    return null;
  };
  
  const validateVideoUrl = (url: string): boolean => {
    if (!url) {
      setValidationError('');
      return true;
    }
    
    const embedUrl = getEmbedUrl(url);
    if (!embedUrl) {
      setValidationError('Invalid video URL. Please enter a valid YouTube or Vimeo URL.');
      return false;
    }
    
    setValidationError('');
    return true;
  };
  
  const handleUrlChange = (newUrl: string) => {
    onChange(newUrl);
    validateVideoUrl(newUrl);
  };
  
  const embedUrl = getEmbedUrl(value);
  const isValid = validateVideoUrl(value);
  
  return (
    <div className="space-y-3">
      {label && (
        <label className="block text-sm font-medium text-gray-700">
          {label}
        </label>
      )}
      
      {!readOnly && (
        <div>
          <input
            type="url"
            value={value}
            onChange={(e) => handleUrlChange(e.target.value)}
            placeholder={placeholder}
            className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
              validationError ? 'border-red-300' : 'border-gray-300'
            }`}
          />
          {validationError && (
            <p className="mt-1 text-sm text-red-600">{validationError}</p>
          )}
        </div>
      )}
      
      {value && embedUrl && isValid && (
        <div>
          {showPreview ? (
            <div className="relative inline-block">
              <iframe
                src={embedUrl}
                width={Math.min(width, 800)} // Responsive max width
                height={Math.min(height, 450)} // Responsive max height
                frameBorder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
                className="rounded shadow-lg"
                title="Video preview"
              />
              <button
                onClick={() => setShowPreview(false)}
                className="absolute top-2 right-2 bg-black bg-opacity-50 text-white p-1 rounded text-sm hover:bg-opacity-70 transition-opacity"
                type="button"
              >
                ✕ Hide
              </button>
            </div>
          ) : (
            <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded border">
              <div className="flex-1">
                <p className="text-sm text-gray-600 font-medium">Video URL:</p>
                <p className="text-sm text-gray-800 break-all">{value}</p>
              </div>
              <button
                onClick={() => setShowPreview(true)}
                className="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors flex-shrink-0"
                type="button"
              >
                ▶ Preview
              </button>
            </div>
          )}
        </div>
      )}
      
      {readOnly && value && (
        <div className="p-3 bg-gray-50 rounded border">
          <p className="text-sm text-gray-600">Video URL: {value}</p>
          {embedUrl && (
            <a
              href={value}
              target="_blank"
              rel="noopener noreferrer"
              className="text-blue-600 hover:text-blue-800 text-sm"
            >
              Open in new tab →
            </a>
          )}
        </div>
      )}
    </div>
  );
};
