/**
 * useDnDChat Hook
 * 
 * State management hook for D&D RAG Chat functionality.
 * Handles question submission, loading state, and error handling.
 */

import { useState } from 'react';
import { dndRagService } from '../services/dndRagService';
import type { DnDQueryResponse, RateLimitInfo, CostInfo } from '../types/dndRag';
import { useNotifications } from '../contexts/NotificationContext';

interface UseDnDChatReturn {
  /** Current question text */
  question: string;
  /** Set the question text */
  setQuestion: (question: string) => void;
  /** AI-generated answer */
  answer: string;
  /** The last question that was submitted */
  lastQuestion: string;
  /** Diagnostic information from the query */
  diagnostics: string[];
  /** Loading state during query */
  loading: boolean;
  /** Error message if query failed */
  error: string | null;
  /** Rate limit information */
  rateLimitInfo: RateLimitInfo | null;
  /** Cost information */
  costInfo: CostInfo | null;
  /** Full response metadata */
  response: DnDQueryResponse | null;
  /** Submit the current question */
  submitQuestion: () => Promise<void>;
  /** Clear answer and reset state */
  clearAnswer: () => void;
}

/**
 * Custom hook for managing D&D RAG Chat state
 */
export function useDnDChat(): UseDnDChatReturn {
  const { showNotification } = useNotifications();
  
  // State management
  const [question, setQuestion] = useState<string>('');
  const [answer, setAnswer] = useState<string>('');
  const [lastQuestion, setLastQuestion] = useState<string>('');
  const [diagnostics, setDiagnostics] = useState<string[]>([]);
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [response, setResponse] = useState<DnDQueryResponse | null>(null);
  const [rateLimitInfo, setRateLimitInfo] = useState<RateLimitInfo | null>(null);
  const [costInfo, setCostInfo] = useState<CostInfo | null>(null);
  
  /**
   * Submit the current question to the D&D RAG Chat server
   */
  const submitQuestion = async (): Promise<void> => {
    if (!question.trim()) {
      showNotification('Please enter a question', 'warning');
      return;
    }
    
    setLoading(true);
    setError(null);
    
    // Store the question before clearing
    const currentQuestion = question.trim();
    setLastQuestion(currentQuestion);
    
    try {
      const result = await dndRagService.query({
        question: currentQuestion,
        debug: true,
        k: 15
      });
      
      // Update state with response data
      setResponse(result);
      setAnswer(result.answer);
      setDiagnostics(result.diagnostics || []);
      setRateLimitInfo(result.meta.rate_limit);
      setCostInfo(result.meta.cost);
      
      // Clear the question input for next query
      setQuestion('');
      
      // Show any non-fatal errors from the response
      if (result.errors && result.errors.length > 0) {
        result.errors.forEach(err => {
          showNotification(err, 'warning');
        });
      }
      
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Unknown error occurred';
      setError(errorMessage);
      showNotification(errorMessage, 'error');
      
      // Clear previous results on error
      setAnswer('');
      setDiagnostics([]);
      setLastQuestion('');
      
    } finally {
      setLoading(false);
    }
  };
  
  /**
   * Clear the answer and reset state
   */
  const clearAnswer = (): void => {
    setAnswer('');
    setDiagnostics([]);
    setError(null);
    setResponse(null);
    setLastQuestion('');
    // Keep rate limit and cost info to show historical data
  };
  
  return {
    question,
    setQuestion,
    answer,
    lastQuestion,
    diagnostics,
    loading,
    error,
    rateLimitInfo,
    costInfo,
    response,
    submitQuestion,
    clearAnswer,
  };
}

export default useDnDChat;
