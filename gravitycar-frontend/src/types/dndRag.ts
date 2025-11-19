/**
 * TypeScript type definitions for D&D RAG Chat API
 * 
 * These interfaces define the request/response structure for communication
 * with the Flask-based D&D RAG Chat server.
 */

/**
 * Rate limit information from API response
 */
export interface RateLimitInfo {
  /** Remaining queries in burst capacity (max 15) */
  remaining_burst: number;
  /** Remaining queries for the day (max 30) */
  daily_remaining: number;
  /** Seconds to wait before retrying (only present in rate limit errors) */
  retry_after?: number | null;
}

/**
 * Cost information from API response
 */
export interface CostInfo {
  /** Cost of the current query in USD */
  query_cost: number;
  /** Total cost for the day in USD */
  daily_total: number;
  /** Daily budget limit in USD */
  daily_budget: number;
  /** Percentage of daily budget used (only in error responses) */
  percent_used?: number;
}

/**
 * Budget information from 503 error response
 */
export interface BudgetInfo {
  /** Total cost for the day in USD */
  daily_total: number;
  /** Daily budget limit in USD */
  daily_budget: number;
  /** Percentage of daily budget used */
  percent_used: number;
}

/**
 * Metadata included in successful responses
 */
export interface ResponseMeta {
  /** User ID from JWT token */
  user_id: string;
  /** Rate limit status */
  rate_limit: RateLimitInfo;
  /** Cost tracking information */
  cost: CostInfo;
}

/**
 * Token usage statistics from OpenAI
 */
export interface UsageInfo {
  /** Number of tokens in the prompt */
  prompt_tokens: number;
  /** Number of tokens in the completion */
  completion_tokens: number;
  /** Total tokens used */
  total_tokens: number;
}

/**
 * Request payload for D&D RAG query
 */
export interface DnDQueryRequest {
  /** The D&D rules question to answer (required) */
  question: string;
  /** Whether to include diagnostic information (default: true) */
  debug?: boolean;
  /** Number of document chunks to retrieve (default: 15, range: 5-50) */
  k?: number;
}

/**
 * Successful response from D&D RAG query
 */
export interface DnDQueryResponse {
  /** The AI-generated answer to the question */
  answer: string;
  /** Format of the answer content (html or text) */
  answer_format?: 'html' | 'text';
  /** Diagnostic information about the query process */
  diagnostics: string[];
  /** Any errors that occurred (non-fatal) */
  errors: string[];
  /** Metadata about the request and response */
  meta: ResponseMeta;
  /** Token usage statistics */
  usage: UsageInfo;
}

/**
 * Error response from D&D RAG API
 * Used for HTTP status codes >= 400
 */
export interface DnDErrorResponse {
  /** Error code or type */
  error: string;
  /** Human-readable error message */
  message?: string;
  /** Additional error details */
  details?: string;
  /** Rate limit information (for 429 errors) */
  rate_info?: RateLimitInfo;
  /** Budget information (for 503 errors) */
  budget_info?: BudgetInfo;
}

/**
 * Health check response
 */
export interface HealthCheckResponse {
  /** Service status (should be 'ok') */
  status: string;
  /** Service name */
  service: string;
  /** API version */
  version: string;
}
