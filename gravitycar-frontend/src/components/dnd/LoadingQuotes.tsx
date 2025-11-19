/**
 * LoadingQuotes Component
 * 
 * Displays humorous D&D-themed quotes while waiting for query responses.
 * Quotes cycle every 6 seconds (2s display + 4s fade transition).
 * Automatically stops after 3 minutes (timeout).
 */

import { useState, useEffect, useRef } from 'react';
import { getRandomQuote } from '../../utils/dndQuotes';

interface LoadingQuotesProps {
  /** Whether the loading overlay is active */
  isActive: boolean;
  /** The last quote index shown (from previous session) */
  previousQuoteIndex?: number;
  /** Callback when a new quote is displayed */
  onQuoteChange?: (index: number) => void;
}

const LoadingQuotes: React.FC<LoadingQuotesProps> = ({ isActive, previousQuoteIndex, onQuoteChange }) => {
  const [currentQuote, setCurrentQuote] = useState<string>('');
  const [isFading, setIsFading] = useState<boolean>(false);
  const initialQuoteIndexRef = useRef<number | undefined>(previousQuoteIndex);
  const lastIndexRef = useRef<number | undefined>(previousQuoteIndex);
  const onQuoteChangeRef = useRef(onQuoteChange);
  
  // Keep callback ref up to date
  useEffect(() => {
    onQuoteChangeRef.current = onQuoteChange;
  }, [onQuoteChange]);
  
  useEffect(() => {
    if (!isActive) {
      // Don't reset lastIndex - parent will manage it
      setCurrentQuote('');
      setIsFading(false);
      return;
    }
    
    // Set initial quote, excluding previous session's last quote (only use on first activation)
    const { quote, index } = getRandomQuote(initialQuoteIndexRef.current);
    setCurrentQuote(quote);
    lastIndexRef.current = index;
    
    // Notify parent of quote change
    if (onQuoteChangeRef.current) {
      onQuoteChangeRef.current(index);
    }
    
    // Cycle quotes every 6 seconds (2s display + 4s fade)
    const quoteInterval = setInterval(() => {
      // Start fade out after 2 seconds of solid display
      setIsFading(true);
      
      // After 4 seconds of fading, change quote and fade in
      setTimeout(() => {
        const { quote: newQuote, index: newIndex } = getRandomQuote(lastIndexRef.current);
        setCurrentQuote(newQuote);
        lastIndexRef.current = newIndex;
        setIsFading(false);
        
        // Notify parent of quote change
        if (onQuoteChangeRef.current) {
          onQuoteChangeRef.current(newIndex);
        }
      }, 2000);
    }, 4000);
    
    // Auto-stop after 3 minutes (180000ms)
    const timeoutTimer = setTimeout(() => {
      setCurrentQuote('Still working on it... this is taking longer than expected.');
      clearInterval(quoteInterval);
    }, 180000);
    
    return () => {
      clearInterval(quoteInterval);
      clearTimeout(timeoutTimer);
    };
  }, [isActive]);
  
  if (!isActive) {
    return null;
  }
  
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm">
      <div className="max-w-2xl px-8 text-center">
        <div className="mb-8">
          {/* Animated loading spinner */}
          <div className="inline-block h-16 w-16 animate-spin rounded-full border-4 border-solid border-blue-500 border-r-transparent"></div>
        </div>
        
        <p
          className={`text-3xl font-bold text-white transition-opacity duration-[4000ms] ${
            isFading ? 'opacity-0' : 'opacity-100'
          }`}
        >
          {currentQuote}
        </p>
      </div>
    </div>
  );
};

export default LoadingQuotes;
