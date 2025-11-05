/**
 * DnDChatPage Component
 * 
 * Main page for D&D RAG Chat interface.
 * Allows users to ask D&D rules questions and receive AI-generated answers
 * backed by Advanced Dungeons & Dragons 1st Edition source material.
 */

import React, { useState } from 'react';
import { useDnDChat } from '../hooks/useDnDChat';
import LoadingQuotes from '../components/dnd/LoadingQuotes';
import DebugPanel from '../components/dnd/DebugPanel';
import RateLimitDisplay from '../components/dnd/RateLimitDisplay';

const DnDChatPage: React.FC = () => {
  const {
    question,
    setQuestion,
    answer,
    diagnostics,
    loading,
    rateLimitInfo,
    costInfo,
    submitQuestion,
  } = useDnDChat();
  
  const [debugExpanded, setDebugExpanded] = useState(false);
  
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await submitQuestion();
  };
  
  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Submit on Ctrl+Enter or Cmd+Enter
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      submitQuestion();
    }
  };
  
  return (
    <div className="min-h-screen bg-gray-50 p-4 md:p-8">
      {/* Title Section */}
      <div className="max-w-7xl mx-auto mb-8">
        <h1 className="text-3xl md:text-4xl font-bold text-center text-gray-800 mb-2">
          Advanced Dungeons & dRAGons
        </h1>
        <p className="text-center text-gray-600 text-lg">
          RAG Chat for D&D 1st Edition Rules
        </p>
      </div>
      
      {/* Main Content */}
      <div className="max-w-7xl mx-auto">
        <form onSubmit={handleSubmit}>
          {/* Question and Answer Section */}
          <div className="flex flex-col md:flex-row gap-4 mb-4">
            {/* Question Section (30% on desktop, full width on mobile) */}
            <div className="w-full md:w-[30%] flex flex-col">
              <label htmlFor="question" className="block text-sm font-medium text-gray-700 mb-2">
                Your Question
              </label>
              <textarea
                id="question"
                value={question}
                onChange={(e) => setQuestion(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder="Enter your D&D question here..."
                rows={8}
                disabled={loading}
                className="flex-1 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
              
              {/* Submit Button */}
              <button
                type="submit"
                disabled={loading || !question.trim()}
                className="mt-4 w-full px-6 py-3 bg-blue-600 text-white font-semibold rounded-md shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
              >
                {loading ? 'Consulting the Tomes...' : 'Ask the Dungeon Master'}
              </button>
              
              <p className="mt-2 text-xs text-gray-500 text-center">
                Tip: Press Ctrl+Enter to submit
              </p>
            </div>
            
            {/* Answer Section (60% on desktop, full width on mobile) */}
            <div className="w-full md:w-[60%] flex flex-col">
              <label htmlFor="answer" className="block text-sm font-medium text-gray-700 mb-2">
                Answer
              </label>
              <textarea
                id="answer"
                value={answer}
                placeholder="The Dungeon Master's answer will appear here..."
                rows={8}
                readOnly
                className="flex-1 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 resize-none focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              
              {answer && (
                <div className="mt-2 flex justify-end">
                  <button
                    type="button"
                    onClick={() => {
                      navigator.clipboard.writeText(answer);
                      // Could add a toast notification here
                    }}
                    className="text-sm text-blue-600 hover:text-blue-800 underline"
                  >
                    Copy to Clipboard
                  </button>
                </div>
              )}
            </div>
          </div>
        </form>
        
        {/* Debug Panel */}
        <DebugPanel
          diagnostics={diagnostics}
          isExpanded={debugExpanded}
          onToggle={() => setDebugExpanded(!debugExpanded)}
        />
        
        {/* Rate Limit Display */}
        <RateLimitDisplay
          rateLimitInfo={rateLimitInfo}
          costInfo={costInfo}
        />
      </div>
      
      {/* Loading Overlay with Quotes */}
      <LoadingQuotes isActive={loading} />
    </div>
  );
};

export default DnDChatPage;
