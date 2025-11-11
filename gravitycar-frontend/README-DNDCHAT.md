# D&D RAG Chat UI

**Advanced Dungeons & dRAGons** - RAG-powered chat interface for querying D&D 1st Edition rules using AI.

---

## Overview

The D&D RAG Chat UI allows users to ask questions about Advanced Dungeons & Dragons 1st Edition rules and receive AI-generated answers backed by authentic source material. The system uses Retrieval-Augmented Generation (RAG) to provide accurate, context-aware responses.

### Key Features

- **Intelligent Question Answering**: AI-powered responses based on AD&D 1st Edition source material
- **Rate Limiting**: 15 burst queries + 30 daily queries per user
- **Cost Tracking**: Real-time display of API costs and budget usage
- **Diagnostic Information**: Optional detailed query diagnostics
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Loading Animation**: Humorous D&D-themed quotes while waiting for responses

---

## Architecture

```
React UI (localhost:3000 or react.gravitycar.com)
    ↓
dndRagService.ts (Service Layer)
    ↓
    ├─→ Local: http://localhost:5000 (Direct Flask connection)
    └─→ Production: https://dndchat.gravitycar.com (Apache + PHP Proxy → Flask)
```

### Environment Detection

The application automatically detects whether it's running in local development or production:

- **Local**: Uses `http://localhost:5000` for direct Flask connection
- **Production**: Uses `https://dndchat.gravitycar.com` (standard HTTPS port 443, routed through Apache + PHP proxy)

No manual configuration is required - environment detection is based on `window.location.hostname`.

---

## Environment Configuration

### Environment Variables

The application uses Vite environment variables for configuration.

#### Development (`.env`)

```env
# Backend API URL
VITE_API_BASE_URL=http://localhost:8081

# D&D RAG Chat Server URLs
VITE_DND_RAG_API_URL_LOCAL=http://localhost:5000
VITE_DND_RAG_API_URL_PRODUCTION=https://dndchat.gravitycar.com
```

#### Production (`.env.production`)

```env
# Backend API URL
VITE_API_BASE_URL=https://api.gravitycar.com

# D&D RAG Chat Server
VITE_DND_RAG_API_URL_PRODUCTION=https://dndchat.gravitycar.com
```

### Variable Details

| Variable | Purpose | Local Value | Production Value |
|----------|---------|-------------|------------------|
| `VITE_DND_RAG_API_URL_LOCAL` | Local Flask server URL | `http://localhost:5000` | N/A |
| `VITE_DND_RAG_API_URL_PRODUCTION` | Production PHP proxy URL | `https://dndchat.gravitycar.com` | `https://dndchat.gravitycar.com` |

**Important Notes:**
- All environment variables must start with `VITE_` to be accessible in the browser
- Production URL does NOT include port `:5000` (uses standard HTTPS port 443)
- Local development requires the Flask server running on `localhost:5000`

---

## Development Setup

### Prerequisites

1. **Authentication**: Valid JWT token from Gravitycar authentication system
2. **Flask Server** (local development only): D&D RAG Chat Flask server running on `localhost:5000`
3. **Node.js**: Version 18+ recommended
4. **npm**: For package management

### Installation

```bash
cd gravitycar-frontend
npm install
```

### Running Locally

1. **Start the Flask server** (in separate terminal):
   ```bash
   # Navigate to Flask project directory
   cd /path/to/dnd-rag-chat
   
   # Start Flask server
   python app.py
   # or
   flask run --port 5000
   ```

2. **Start the React development server**:
   ```bash
   npm run dev
   ```

3. **Access the application**:
   - Navigate to `http://localhost:3000`
   - Log in with valid credentials
   - Navigate to the D&D Chat page

---

## Usage

### Basic Workflow

1. **Enter your question** in the left text area (30% of screen width)
2. **Click "Ask the Dungeon Master"** or press `Ctrl+Enter`
3. **View the answer** in the right text area (60% of screen width)
4. **Review diagnostics** (optional) by expanding the Debug Information panel
5. **Monitor usage** with the rate limit and cost display

### UI Components

#### Question Input
- Text area for entering D&D rules questions
- Placeholder: "Enter your D&D question here..."
- Keyboard shortcut: `Ctrl+Enter` or `Cmd+Enter` to submit

#### Answer Display
- Read-only text area showing AI-generated response
- Copy to clipboard button
- Scrollable for long answers

#### Debug Information Panel
- Collapsible panel showing query diagnostics
- Click header to expand/collapse
- Displays:
  - Document retrieval information
  - Chunk count and adaptive filtering details
  - AI model used (gpt-4o-mini)

#### Usage Information
- **Burst Capacity**: Shows remaining queries from burst pool (max 15)
  - Refills at 1 per minute
  - Color-coded: Green (10+), Yellow (5-9), Red (<5)
  
- **Daily Queries**: Shows remaining queries for the day (max 30)
  - Resets at midnight UTC
  - Color-coded: Green (20+), Yellow (10-19), Red (<10)
  
- **Daily Cost**: Shows API costs
  - Current day's total cost
  - Daily budget limit ($1.00)
  - Last query cost
  - Color-coded based on budget percentage

#### Loading Animation
- Displays humorous D&D-themed quotes while waiting
- Quotes change every 5 seconds with fade transition
- Examples:
  - "Girding our loins"
  - "Mapping the catacombs"
  - "Beholding the beholder"
  - "Ugh, Rot-grubs!"

---

## API Response Handling

### Success Response (HTTP 200)

The application receives and displays:

```json
{
  "answer": "AI-generated answer text...",
  "diagnostics": ["Diagnostic message 1", "Diagnostic message 2"],
  "errors": [],
  "meta": {
    "user_id": "uuid",
    "rate_limit": {
      "remaining_burst": 14,
      "daily_remaining": 29
    },
    "cost": {
      "query_cost": 0.000324,
      "daily_total": 0.0006,
      "daily_budget": 1.0
    }
  },
  "usage": {
    "prompt_tokens": 863,
    "completion_tokens": 324,
    "total_tokens": 1187
  }
}
```

### Error Responses

The application handles all error scenarios gracefully with user-friendly messages:

| Error Code | User Message |
|------------|--------------|
| **400** | "Invalid request: [details]" |
| **401** | "Your session expired. Please log in again." |
| **429 (burst)** | "You've asked too many questions too quickly. Please wait X seconds." |
| **429 (daily)** | "You've reached your daily limit of 30 questions. Try again tomorrow!" |
| **500** | "Something went wrong (HTTP 500)" |
| **503** | "We've hit our daily budget limit. The service will be back at midnight UTC." |
| **Network** | "We're sorry, the Dungeons and dRAGons chat server is down. Please try again later." |

---

## Troubleshooting

### Common Issues

#### 1. "Unable to connect to D&D RAG Chat server"

**Symptoms**: Health check fails, cannot reach API

**Solutions**:
- **Local Development**:
  - Verify Flask server is running on `localhost:5000`
  - Check Flask console for errors
  - Ensure no firewall blocking port 5000
  
- **Production**:
  - Check internet connection
  - Verify production URL: `https://dndchat.gravitycar.com`
  - Contact system administrator if issue persists

#### 2. "Authentication token not found. Please log in."

**Symptoms**: Cannot submit queries, 401 errors

**Solutions**:
- Log out and log back in to refresh JWT token
- Check browser localStorage for `auth_token`
- Verify token hasn't expired (1-hour expiration)
- Clear browser cache and try again

#### 3. "You've asked too many questions too quickly."

**Symptoms**: Rate limit error after 15-16 queries

**Solutions**:
- Wait 60 seconds for burst capacity to refill
- Rate limit refills at 1 query per minute
- Maximum burst capacity: 15 queries
- Check "Burst Capacity" display for remaining queries

#### 4. "You've reached your daily limit of 30 questions."

**Symptoms**: Daily rate limit exceeded

**Solutions**:
- Wait until midnight UTC for reset
- Daily limit: 30 queries per user
- Check "Daily Queries" display for remaining count
- Plan questions to stay within limit

#### 5. CORS Errors in Production

**Symptoms**: Browser console shows CORS policy errors

**Solutions**:
- Verify you're accessing from allowed origin:
  - `https://react.gravitycar.com`
  - `https://gravitycar.com`
- Check browser dev tools Network tab for CORS headers
- Expected header: `Access-Control-Allow-Origin: https://react.gravitycar.com`
- Contact backend team if issue persists

#### 6. No Diagnostics Displayed

**Symptoms**: Debug panel shows "No diagnostic information available"

**Solutions**:
- This is expected before first query
- Diagnostics only appear after successful query
- Verify query completed successfully
- Check for errors in browser console

#### 7. Loading Quotes Not Changing

**Symptoms**: Same quote displays repeatedly

**Solutions**:
- Quotes change every 5 seconds (normal behavior)
- After 3 minutes, shows timeout message
- Refresh page if loading state persists after query completes

---

## CORS Configuration

### Local Development

Flask server configured to accept requests from:
- `http://localhost:3000`
- `http://localhost:3001`

### Production

Apache + PHP proxy configured to accept requests from:
- `https://react.gravitycar.com`
- `https://gravitycar.com`

**Note**: Production uses PHP reverse proxy to handle CORS. Flask's CORS headers are stripped by the proxy to prevent duplicate headers.

---

## Testing

### Manual Testing Checklist

#### Health Check
```bash
# Local
curl http://localhost:5000/health

# Production
curl https://dndchat.gravitycar.com/health
```

Expected response:
```json
{
  "status": "ok",
  "service": "dnd_rag",
  "version": "1.0.0"
}
```

#### Query Test (Local)
```bash
curl -X POST http://localhost:5000/api/query \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -d '{"question": "What is a beholder?"}'
```

#### Rate Limit Test
Make 16 rapid requests to test burst limit. The 16th should return HTTP 429.

---

## File Structure

```
gravitycar-frontend/src/
├── components/
│   └── dnd/
│       ├── DebugPanel.tsx          # Collapsible diagnostics display
│       ├── LoadingQuotes.tsx       # Loading animation with quotes
│       └── RateLimitDisplay.tsx    # Usage information display
├── hooks/
│   └── useDnDChat.ts               # State management hook
├── pages/
│   └── DnDChatPage.tsx             # Main chat page component
├── services/
│   └── dndRagService.ts            # API communication service
├── types/
│   └── dndRag.ts                   # TypeScript interfaces
└── utils/
    └── dndQuotes.ts                # Loading quotes data
```

---

## API Documentation

For complete API documentation, see:
- [D&D RAG Chat UI Integration Guide](../docs/dnd_rag_chat_ui_integration.md)

---

## Support

For issues or questions:
1. Check this troubleshooting guide
2. Review browser console for errors
3. Check Flask server logs (local development)
4. Contact development team

---

## Version History

- **v1.0.0** (November 2025) - Initial release
  - Basic query functionality
  - Rate limiting and cost tracking
  - Diagnostic information display
  - Production PHP proxy support
