/**
 * RateLimitDisplay Component
 * 
 * Displays rate limit and cost information from D&D RAG queries.
 * Shows burst capacity, daily queries remaining, and cost tracking.
 * Color-coded indicators based on remaining capacity.
 */

import React from 'react';
import type { RateLimitInfo, CostInfo } from '../../types/dndRag';

interface RateLimitDisplayProps {
  /** Rate limit information */
  rateLimitInfo: RateLimitInfo | null;
  /** Cost tracking information */
  costInfo: CostInfo | null;
}

const RateLimitDisplay: React.FC<RateLimitDisplayProps> = ({ rateLimitInfo, costInfo }) => {
  // Calculate color based on remaining capacity
  const getBurstColor = (remaining: number): string => {
    if (remaining >= 10) return 'text-green-600';
    if (remaining >= 5) return 'text-yellow-600';
    return 'text-red-600';
  };
  
  const getDailyColor = (remaining: number): string => {
    if (remaining >= 20) return 'text-green-600';
    if (remaining >= 10) return 'text-yellow-600';
    return 'text-red-600';
  };
  
  const getBudgetColor = (used: number, total: number): string => {
    const percentage = (used / total) * 100;
    if (percentage < 60) return 'text-green-600';
    if (percentage < 85) return 'text-yellow-600';
    return 'text-red-600';
  };
  
  if (!rateLimitInfo && !costInfo) {
    return (
      <div className="w-full mt-4 p-4 bg-gray-50 border border-gray-200 rounded-md">
        <p className="text-sm text-gray-500 text-center">
          Rate limit and cost information will appear after your first query.
        </p>
      </div>
    );
  }
  
  return (
    <div className="w-full mt-4 p-4 bg-white border border-gray-300 rounded-md shadow-sm">
      <h3 className="text-sm font-semibold text-gray-700 mb-3">Usage Information</h3>
      
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Burst Capacity */}
        {rateLimitInfo && (
          <div className="flex flex-col">
            <span className="text-xs text-gray-500 mb-1">Burst Capacity</span>
            <span className={`text-2xl font-bold ${getBurstColor(rateLimitInfo.remaining_burst)}`}>
              {rateLimitInfo.remaining_burst}/15
            </span>
            <span className="text-xs text-gray-600 mt-1">
              Refills 1 per minute
            </span>
          </div>
        )}
        
        {/* Daily Queries */}
        {rateLimitInfo && (
          <div className="flex flex-col">
            <span className="text-xs text-gray-500 mb-1">Daily Queries</span>
            <span className={`text-2xl font-bold ${getDailyColor(rateLimitInfo.daily_remaining)}`}>
              {rateLimitInfo.daily_remaining}/30
            </span>
            <span className="text-xs text-gray-600 mt-1">
              Resets at midnight UTC
            </span>
          </div>
        )}
        
        {/* Cost Information */}
        {costInfo && (
          <div className="flex flex-col">
            <span className="text-xs text-gray-500 mb-1">Daily Cost</span>
            <span className={`text-2xl font-bold ${getBudgetColor(costInfo.daily_total, costInfo.daily_budget)}`}>
              ${costInfo.daily_total.toFixed(4)}
            </span>
            <span className="text-xs text-gray-600 mt-1">
              Budget: ${costInfo.daily_budget.toFixed(2)}
              {costInfo.query_cost > 0 && (
                <span className="block">
                  Last query: ${costInfo.query_cost.toFixed(6)}
                </span>
              )}
            </span>
          </div>
        )}
      </div>
      
      {/* Warning messages */}
      {rateLimitInfo && rateLimitInfo.remaining_burst < 3 && (
        <div className="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
          ⚠️ Low burst capacity. Wait a minute for tokens to refill.
        </div>
      )}
      
      {rateLimitInfo && rateLimitInfo.daily_remaining < 5 && (
        <div className="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-800">
          🚨 Approaching daily query limit!
        </div>
      )}
      
      {costInfo && (costInfo.daily_total / costInfo.daily_budget) > 0.85 && (
        <div className="mt-3 p-2 bg-orange-50 border border-orange-200 rounded text-sm text-orange-800">
          💰 Approaching daily budget limit.
        </div>
      )}
    </div>
  );
};

export default RateLimitDisplay;
