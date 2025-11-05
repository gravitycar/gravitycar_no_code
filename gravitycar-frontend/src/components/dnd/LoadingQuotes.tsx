/**
 * LoadingQuotes Component
 * 
 * Displays humorous D&D-themed quotes while waiting for query responses.
 * Quotes cycle every 8 seconds (5s display + 3s fade transition).
 * Automatically stops after 3 minutes (timeout).
 */

import { useState, useEffect } from 'react';
import { getRandomQuote } from '../../utils/dndQuotes';

interface LoadingQuotesProps {
  /** Whether the loading overlay is active */
  isActive: boolean;
}

const LoadingQuotes: React.FC<LoadingQuotesProps> = ({ isActive }) => {
  const [currentQuote, setCurrentQuote] = useState<string>('');
  const [lastIndex, setLastIndex] = useState<number | undefined>(undefined);
  const [isFading, setIsFading] = useState<boolean>(false);
  
  useEffect(() => {
    if (!isActive) {
      // Reset state when not active
      setCurrentQuote('');
      setLastIndex(undefined);
      setIsFading(false);
      return;
    }
    
    // Set initial quote immediately
    const { quote, index } = getRandomQuote();
    setCurrentQuote(quote);
    setLastIndex(index);
    
    // Cycle quotes every 5 seconds (3s display + 2s fade)
    const quoteInterval = setInterval(() => {
      // Start fade out
      setIsFading(true);
      
      // After 2 seconds, change quote and fade in
      setTimeout(() => {
        const { quote: newQuote, index: newIndex } = getRandomQuote(lastIndex);
        setCurrentQuote(newQuote);
        setLastIndex(newIndex);
        setIsFading(false);
      }, 2000);
    }, 5000);
    
    // Auto-stop after 3 minutes (180000ms)
    const timeoutTimer = setTimeout(() => {
      setCurrentQuote('Still working on it... this is taking longer than expected.');
      clearInterval(quoteInterval);
    }, 180000);
    
    return () => {
      clearInterval(quoteInterval);
      clearTimeout(timeoutTimer);
    };
  }, [isActive, lastIndex]);
  
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
          className={`text-3xl font-bold text-white transition-opacity duration-1000 ${
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
