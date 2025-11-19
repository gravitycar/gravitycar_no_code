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
import { useNotifications } from '../contexts/NotificationContext';

const DnDChatPage: React.FC = () => {
  const {
    question,
    setQuestion,
    answer,
    answerFormat,
    lastQuestion,
    diagnostics,
    loading,
    rateLimitInfo,
    costInfo,
    submitQuestion,
  } = useDnDChat();
  
  const { showNotification } = useNotifications();
  const [debugExpanded, setDebugExpanded] = useState(false);
  const [lastLoadingQuoteIndex, setLastLoadingQuoteIndex] = useState<number | undefined>(undefined);
  
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
              <div
                id="answer"
                role="region"
                aria-label="Answer from Dungeon Master"
                className="flex-1 w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-white overflow-y-auto min-h-[200px] max-h-[400px] focus-within:ring-2 focus-within:ring-blue-500 text-left"
              >
                {!answer && !lastQuestion && (
                  <p className="text-gray-400 italic">
                    The Dungeon Master's answer will appear here...
                  </p>
                )}
                
                {lastQuestion && (
                  <div className="mb-4 pl-4 py-2 bg-gray-100 rounded-md border-l-4 border-blue-400">
                    <p className="text-sm text-gray-600 font-semibold mb-1">Your Question:</p>
                    <p className="text-sm text-gray-700 italic">{lastQuestion}</p>
                  </div>
                )}
                
                {answer && (
                  <div className="max-w-none prose prose-sm">
                    {(() => {
                      console.log('[DnDChatPage] answerFormat:', answerFormat);
                      console.log('[DnDChatPage] answer length:', answer.length);
                      console.log('[DnDChatPage] answer preview:', answer.substring(0, 100));
                      return answerFormat === 'html' ? (
                        <div 
                          className="text-gray-800"
                          dangerouslySetInnerHTML={{ __html: answer }} 
                        />
                      ) : (
                        <p className="text-gray-800 whitespace-pre-wrap">{answer}</p>
                      );
                    })()}
                  </div>
                )}
              </div>
              
              {answer && (
                <div className="mt-2 flex justify-end">
                  <button
                    type="button"
                    onClick={() => {
                      const fullText = lastQuestion 
                        ? `Q: ${lastQuestion}\n\nA: ${answer}` 
                        : answer;
                      navigator.clipboard.writeText(fullText);
                      showNotification('Copied to clipboard', 'success');
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
      <LoadingQuotes 
        isActive={loading}
        previousQuoteIndex={lastLoadingQuoteIndex}
        onQuoteChange={setLastLoadingQuoteIndex}
      />
    </div>
  );
};

export default DnDChatPage;
