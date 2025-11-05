# D&D RAG Chat UI - Implementation Plan

**Feature**: Advanced Dungeons & dRAGons Chat Interface  
**Created**: November 4, 2025  
**Status**: Planning Phase  
**Branch**: feature/dndchat

---

## 1. Feature Overview

The D&D RAG Chat UI provides a React-based frontend interface for querying the D&D RAG Chat server (Python Flask application running on port 5000 locally, or https://dndchat.gravitycar.com in production). This feature is unique in that it does **not** interact with the Gravitycar backend API at all - it only communicates with the external D&D RAG service.

### Key Characteristics:
- **External API Integration**: Communicates with Flask-based D&D RAG service (not Gravitycar backend)
- **JWT Authentication**: Reuses existing Gravitycar auth tokens for API authorization
- **Environment-Aware**: Auto-detects local vs production environment
- **User-Friendly UX**: Loading animations with humorous D&D-themed quotes
- **Collapsible Debug View**: Optional diagnostic information display

---

## 2. Requirements

### Functional Requirements

#### FR1: Environment Configuration
- Store D&D RAG Chat server URLs in `.env` file
- Auto-detect environment (localhost vs production) based on `window.location.hostname`
- Local: `http://localhost:5000`
- Production: `https://dndchat.gravitycar.com`

#### FR2: User Interface Layout
- **Title**: "Advanced Dungeons & dRAGons - Rag Chat for D&D"
- **Question Input**: Textarea (30% width), placeholder: "Enter your D&D question here"
- **Submit Button**: Blue button, label: "Ask the Dungeon Master", styled like existing buttons
- **Answer Display**: Read-only textarea (60% width), displays server response
- **Debug Panel**: Collapsible read-only textarea below Question/Answer fields
  - When collapsed: Takes minimal space, Question/Answer stretch vertically
  - When expanded: Takes ~50% of window height
  - Displays diagnostic information from server

#### FR3: Authentication & Authorization
- Requires authenticated user (reuse existing `useAuth` hook)
- Send JWT token from `localStorage.getItem('auth_token')` in Authorization header
- Handle 401 errors by redirecting to login (already handled by existing error interceptors)

#### FR4: API Communication
- **Health Check**: `GET /health` (no auth required)
- **Query Endpoint**: `POST /api/query` with JSON body:
  ```json
  {
    "question": "string (required)",
    "debug": true,
    "k": 15
  }
  ```
- **Request Headers**:
  - `Content-Type: application/json`
  - `Authorization: Bearer ${token}`
- **Response Handling**: Parse JSON response with fields:
  - `answer` (string): Display in Answer textarea
  - `diagnostics` (string[]): Display in Debug panel (always requested via debug: true)
  - `errors` (string[]): Display as error notifications
  - `meta.rate_limit`: Show remaining queries
  - `meta.cost`: Show query cost information
- **Note**: Debug is always set to `true` in requests so diagnostic information is available for the collapsible Debug panel. User controls visibility via UI, not API parameter.

#### FR5: Loading State with Quotes
- Display random D&D-themed quotes during server processing
- Quote behavior:
  - Display for 5 seconds
  - Fade out for 3 seconds
  - Replace with new random quote (no repeats)
  - Continue until response arrives or 3-minute timeout
- Quotes list provided (32 total quotes - see specs)

#### FR6: Error Handling
- Reuse existing error handling mechanisms (`NotificationContext`, `ApiError`)
- Handle HTTP error codes:
  - **400**: Bad request (show error message)
  - **401**: Unauthorized (redirect to login - already handled)
  - **429**: Rate limit exceeded (show wait time or daily limit message)
  - **500**: Server error (generic error message)
  - **503**: Budget exceeded (show budget exhausted message)
- Display user-friendly error messages via notification system

#### FR7: Navigation Integration
- Add "D&D Chat" link to navigation sidebar
- Only visible to authenticated users
- Route: `/dnd-chat`

### Non-Functional Requirements

#### NFR1: Performance
- Typical query response: 3-8 seconds
- Show loading indicator immediately on submit
- Handle slow queries (up to 15 seconds)

#### NFR2: User Experience
- Clear visual feedback during loading
- Responsive layout (Question + Answer = 90% width)
- Smooth transitions for Debug panel expand/collapse
- Accessible UI components (keyboard navigation, screen readers)

#### NFR3: Code Quality
- Reuse existing React components wherever possible
- Follow Gravitycar TypeScript patterns and conventions
- Type-safe API communication (TypeScript interfaces)
- Comprehensive error handling

#### NFR4: Maintainability
- Environment variables for configuration
- Separation of concerns (API service, UI component, hook)
- Clear documentation in code comments

---

## 3. Design

### 3.1 Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    React Frontend                        │
│  ┌───────────────────────────────────────────────────┐  │
│  │           DnDChatPage.tsx (Main UI)               │  │
│  │  - Question/Answer TextAreas                      │  │
│  │  - Submit Button                                  │  │
│  │  - Debug Panel (Collapsible)                      │  │
│  │  - Loading Overlay (Quotes)                       │  │
│  └─────────────┬─────────────────────────────────────┘  │
│                │                                          │
│                ▼                                          │
│  ┌───────────────────────────────────────────────────┐  │
│  │           useDnDChat() Hook                       │  │
│  │  - State management (question, answer, loading)   │  │
│  │  - Query submission logic                         │  │
│  │  - Error handling                                 │  │
│  └─────────────┬─────────────────────────────────────┘  │
│                │                                          │
│                ▼                                          │
│  ┌───────────────────────────────────────────────────┐  │
│  │         dndRagService.ts                          │  │
│  │  - API endpoint configuration                     │  │
│  │  - HTTP request/response handling                 │  │
│  │  - Environment detection                          │  │
│  │  - JWT token injection                            │  │
│  └─────────────┬─────────────────────────────────────┘  │
│                │                                          │
└────────────────┼──────────────────────────────────────────┘
                 │
                 │ HTTP POST /api/query
                 │ Authorization: Bearer <token>
                 ▼
┌─────────────────────────────────────────────────────────┐
│           D&D RAG Chat Server (Flask)                    │
│  - Validates JWT token                                   │
│  - Queries ChromaDB vector database                      │
│  - Generates AI response via OpenAI                      │
│  - Returns answer + diagnostics                          │
└─────────────────────────────────────────────────────────┘
```

### 3.2 File Structure

```
gravitycar-frontend/
├── .env                              # Add VITE_DND_RAG_API_URL
├── src/
│   ├── pages/
│   │   └── DnDChatPage.tsx          # Main page component (NEW)
│   │
│   ├── services/
│   │   └── dndRagService.ts         # API service for D&D RAG (NEW)
│   │
│   ├── hooks/
│   │   └── useDnDChat.ts            # State management hook (NEW)
│   │
│   ├── components/
│   │   ├── dnd/                     # D&D-specific components (NEW)
│   │   │   ├── LoadingQuotes.tsx    # Quote overlay component
│   │   │   ├── DebugPanel.tsx       # Collapsible debug view
│   │   │   └── RateLimitDisplay.tsx # Rate limit status
│   │   │
│   │   └── fields/
│   │       └── TextArea.tsx         # Reuse existing component
│   │
│   ├── types/
│   │   └── dndRag.ts                # TypeScript interfaces (NEW)
│   │
│   ├── utils/
│   │   └── dndQuotes.ts             # Quote list and logic (NEW)
│   │
│   └── App.tsx                      # Add route for /dnd-chat
```

### 3.3 Component Hierarchy

```
<DnDChatPage>
  ├── <div className="title">Advanced Dungeons & dRAGons</div>
  │
  ├── <div className="main-layout">
  │   ├── <div className="question-section" style="width: 30%">
  │   │   ├── <TextArea> (Question input)
  │   │   └── <button> (Submit)
  │   │
  │   └── <div className="answer-section" style="width: 60%">
  │       └── <TextArea> (Answer display, read-only)
  │
  ├── <DebugPanel isExpanded={debugExpanded}>
  │   └── <TextArea> (Diagnostics, read-only)
  │
  ├── <RateLimitDisplay meta={response?.meta} /> (Always visible, below debug)
  │
  └── {loading && <LoadingQuotes />}
      └── <div className="quote-overlay">
          └── <p className="quote">{currentQuote}</p>
```

### 3.4 Data Flow

```
User Action: Click "Ask the Dungeon Master"
    ↓
1. DnDChatPage sets loading state to true
    ↓
2. LoadingQuotes component starts cycling quotes
    ↓
3. useDnDChat hook calls dndRagService.query()
    ↓
4. dndRagService constructs HTTP request:
   - URL: VITE_DND_RAG_API_URL + '/api/query'
   - Headers: Authorization, Content-Type
   - Body: { question, debug, k }
    ↓
5. Flask server processes request (3-15 seconds)
    ↓
6. Response arrives (or timeout after 3 minutes)
    ↓
7. useDnDChat hook parses response:
   - Success: Set answer, diagnostics, meta
   - Error: Call NotificationContext.showNotification()
    ↓
8. DnDChatPage updates UI:
   - Stop LoadingQuotes
   - Display answer in Answer textarea
   - Populate Debug panel
   - Show rate limit info
```

---

## 4. Implementation Steps

### Phase 1: Configuration & Setup (Priority: High)

#### Step 1.1: Environment Variables
- **File**: `gravitycar-frontend/.env`
- **Action**: Add D&D RAG API URL configuration
```bash
# D&D RAG Chat Server
VITE_DND_RAG_API_URL_LOCAL=http://localhost:5000
VITE_DND_RAG_API_URL_PRODUCTION=https://dndchat.gravitycar.com
```

#### Step 1.2: TypeScript Type Definitions
- **File**: `gravitycar-frontend/src/types/dndRag.ts` (NEW)
- **Content**: Define interfaces for API request/response
  - `DnDQueryRequest` interface
  - `DnDQueryResponse` interface
  - `DnDErrorResponse` interface
  - `RateLimitInfo` interface
  - `CostInfo` interface

### Phase 2: API Service Layer (Priority: High)

#### Step 2.1: Create D&D RAG Service
- **File**: `gravitycar-frontend/src/services/dndRagService.ts` (NEW)
- **Implementation**:
  - Environment detection function (localhost vs production)
  - `getDnDRagApiUrl()`: Returns correct base URL
  - `healthCheck()`: GET /health endpoint
  - `query(request: DnDQueryRequest)`: POST /api/query endpoint
    - Always sends `debug: true` in request body
    - User controls debug visibility via UI, not API parameter
  - Error handling for all HTTP status codes
  - JWT token retrieval from localStorage
  - Do NOT use axios interceptors (separate service)

**Key Design Decision**: This service will **not** use the existing `apiService` instance because:
1. Different base URL (Flask server, not Gravitycar backend)
2. Different response format (no Gravitycar wrapper)
3. No need for XDEBUG_TRIGGER parameter
4. Separate error handling logic

**Debug Parameter**: Always set to `true` to ensure diagnostics are available. The Debug panel UI controls whether the user sees this information.

### Phase 3: State Management Hook (Priority: High)

#### Step 3.1: Create useDnDChat Hook
- **File**: `gravitycar-frontend/src/hooks/useDnDChat.ts` (NEW)
- **State Variables**:
  - `question: string`
  - `answer: string`
  - `diagnostics: string[]`
  - `loading: boolean`
  - `error: string | null`
  - `rateLimitInfo: RateLimitInfo | null`
  - `costInfo: CostInfo | null`
- **Functions**:
  - `submitQuestion()`: Calls dndRagService.query()
  - `clearAnswer()`: Resets answer/diagnostics
  - Error handling integration with NotificationContext

### Phase 4: UI Components (Priority: Medium)

#### Step 4.1: Loading Quotes Component
- **File**: `gravitycar-frontend/src/components/dnd/LoadingQuotes.tsx` (NEW)
- **Features**:
  - Full-screen overlay with semi-transparent background
  - Display quote text in large, readable font
  - Cycle quotes every 8 seconds (5s display + 3s fade)
  - No repeat quotes (track last quote index)
  - Auto-stop after 3 minutes (timeout)
- **Props**: `isActive: boolean`

#### Step 4.2: Quote Data
- **File**: `gravitycar-frontend/src/utils/dndQuotes.ts` (NEW)
- **Content**: Export array of 32 D&D-themed quotes
- **Functions**:
  - `getRandomQuote(excludeIndex?: number): { quote: string, index: number }`

#### Step 4.3: Debug Panel Component
- **File**: `gravitycar-frontend/src/components/dnd/DebugPanel.tsx` (NEW)
- **Features**:
  - Collapsible section (click header to expand/collapse)
  - Display diagnostics array as formatted text
  - Smooth height transition animation
  - Header shows "Debug Info" + expand/collapse icon
- **Props**: `diagnostics: string[], isExpanded: boolean, onToggle: () => void`

#### Step 4.4: Rate Limit Display Component
- **File**: `gravitycar-frontend/src/components/dnd/RateLimitDisplay.tsx` (NEW)
- **Features**:
  - Show burst capacity remaining (X/15)
  - Show daily queries remaining (X/30)
  - Show query cost and daily total
  - Color-coded indicators (green/yellow/red based on limits)
- **Props**: `rateLimitInfo: RateLimitInfo | null, costInfo: CostInfo | null`

### Phase 5: Main Page Component (Priority: High)

#### Step 5.1: Create DnDChatPage
- **File**: `gravitycar-frontend/src/pages/DnDChatPage.tsx` (NEW)
- **Layout** (top to bottom):
  1. Title section (full width)
  2. Main section (flex container):
     - Left column (30%): Question textarea + Submit button
     - Right column (60%): Answer textarea (read-only)
  3. Debug panel section (full width, below main)
  4. **Rate limit display (full width, below debug panel)** ✅
  5. Loading overlay (when loading state is true, covers entire page)
- **Reuse Components**:
  - `TextArea` from `src/components/fields/TextArea.tsx` (for question/answer)
  - Existing button styling (blue submit button)
  - `LoadingQuotes` component
  - `DebugPanel` component
  - `RateLimitDisplay` component (always visible, positioned below debug panel)
- **State Management**: Use `useDnDChat` hook
- **Error Handling**: Use `useNotifications` hook for error display

### Phase 6: Routing & Navigation (Priority: Medium)

#### Step 6.1: Add Route to App.tsx
- **File**: `gravitycar-frontend/src/App.tsx`
- **Action**: Add protected route for `/dnd-chat`
```tsx
<Route
  path="/dnd-chat"
  element={
    <ProtectedRoute>
      <Layout>
        <DnDChatPage />
      </Layout>
    </ProtectedRoute>
  }
/>
```

#### Step 6.2: Update Navigation Config
- **File**: `src/Navigation/navigation_config.php`
- **Action**: Add entry to `custom_pages` array
```php
[
    'key' => 'dnd_chat',
    'title' => 'D&D Chat',
    'url' => '/dnd-chat',
    'icon' => '⚔️',
    'roles' => ['admin', 'user'] // Authenticated users only
]
```
- **Placement**: Add after 'trivia' entry
- **Cache Clear**: May need to clear navigation cache after adding

### Phase 7: Styling (Priority: Low)

#### Step 7.1: CSS Styling
- **Primary Approach**: Use Tailwind CSS classes (consistent with existing components)
- **Requirements**:
  - **Desktop Layout**: 
    - Question: `md:w-[30%]`
    - Answer: `md:w-[60%]`
    - Debug: Full width below
  - **Mobile Layout** (< 768px):
    - All fields: `w-full`
    - Order: Question → Answer → Debug (stacked vertically)
  - **Button Styling**: Blue submit button matching existing app buttons
  - **Quote Overlay**: 
    - Semi-transparent dark background
    - Large readable text (text-2xl or text-3xl)
    - Smooth fade animations (transition-opacity duration-1000)

---

## 5. Testing Strategy

### 5.1 Unit Tests

#### Test: dndRagService.ts
- **File**: `gravitycar-frontend/src/services/__tests__/dndRagService.test.ts`
- **Cases**:
  - Environment detection (localhost vs production)
  - API URL construction
  - JWT token retrieval from localStorage
  - Request header construction
  - Response parsing (success case)
  - Error response parsing (400, 401, 429, 500, 503)
  - Network error handling

#### Test: useDnDChat Hook
- **File**: `gravitycar-frontend/src/hooks/__tests__/useDnDChat.test.ts`
- **Cases**:
  - Initial state values
  - `submitQuestion()` success flow
  - `submitQuestion()` error flow
  - Loading state transitions
  - `clearAnswer()` resets state

#### Test: LoadingQuotes Component
- **File**: `gravitycar-frontend/src/components/dnd/__tests__/LoadingQuotes.test.tsx`
- **Cases**:
  - Renders when `isActive={true}`
  - Hides when `isActive={false}`
  - Displays a quote from the quotes array
  - Cycles to new quote after 8 seconds
  - Does not repeat last quote
  - Auto-stops after 3 minutes

#### Test: DebugPanel Component
- **File**: `gravitycar-frontend/src/components/dnd/__tests__/DebugPanel.test.tsx`
- **Cases**:
  - Renders diagnostics array
  - Expands/collapses on header click
  - Shows correct icon based on expanded state

### 5.2 Integration Tests

#### Test: DnDChatPage Integration
- **File**: `gravitycar-frontend/src/pages/__tests__/DnDChatPage.test.tsx`
- **Cases**:
  - Page renders with all sections (title, question, answer, debug, submit)
  - Submit button is disabled when question is empty
  - Submit button triggers query submission
  - Loading overlay appears during query
  - Answer displays after successful response
  - Debug panel populates with diagnostics
  - Error notification appears on failed response
  - Rate limit info displays correctly

#### Test: API Service Integration (Manual)
- **Local Development**:
  1. Start Flask server: `./scripts/start_flask.sh`
  2. Start React dev server: `npm run dev`
  3. Log in to Gravitycar app
  4. Navigate to `/dnd-chat`
  5. Submit test question: "What is a beholder?"
  6. Verify response displays in Answer textarea
  7. Expand Debug panel and verify diagnostics
  8. Submit 16 questions rapidly to test rate limiting
  9. Verify rate limit error appears on 16th request

### 5.3 Manual Testing Checklist

- [ ] Environment detection works (local vs production)
- [ ] JWT token is sent in Authorization header
- [ ] Question textarea accepts input
- [ ] Submit button is styled correctly (blue)
- [ ] Loading quotes appear during query processing
- [ ] Quotes cycle every 8 seconds
- [ ] No quote repeats consecutively
- [ ] Query timeout occurs after 3 minutes
- [ ] Answer displays in read-only textarea
- [ ] Debug panel collapses/expands smoothly
- [ ] Diagnostics format correctly in debug panel
- [ ] Rate limit info displays correctly
- [ ] Error notifications appear for failed queries
- [ ] 401 error redirects to login
- [ ] Navigation link appears for authenticated users
- [ ] Navigation link hidden for unauthenticated users
- [ ] Page layout is responsive (mobile/tablet)

---

## 6. Documentation

### 6.1 User Documentation
- **File**: `docs/features/dnd_chat_user_guide.md` (NEW)
- **Content**:
  - What is D&D Chat?
  - How to access the feature
  - How to ask questions
  - Understanding rate limits
  - Interpreting debug information
  - Troubleshooting common issues

### 6.2 Developer Documentation
- **File**: `docs/features/dnd_chat_developer_guide.md` (NEW)
- **Content**:
  - Architecture overview
  - API integration details
  - Component structure
  - State management
  - Adding/modifying quotes
  - Error handling patterns
  - Testing guidelines

### 6.3 Code Comments
- All new files should have comprehensive JSDoc comments
- Complex functions should have inline comments explaining logic
- TypeScript interfaces should have descriptive property comments

---

## 7. Risks and Mitigations

### Risk 1: Flask Server Availability
- **Risk**: D&D RAG server may be down or unreachable
- **Mitigation**: 
  - Implement health check on page load
  - Display clear error message if server unavailable
  - Add retry mechanism with exponential backoff
  - Consider caching last successful connection status

### Risk 2: Rate Limiting Impact
- **Risk**: Users may hit rate limits frequently during testing
- **Mitigation**:
  - Display prominent rate limit warnings
  - Show remaining query count in UI
  - Educate users about daily limits (30 queries)
  - Consider admin override for testing purposes

### Risk 3: Slow Query Response Times
- **Risk**: Queries taking 15+ seconds may frustrate users
- **Mitigation**:
  - Loading quotes provide entertainment during wait
  - Show "Still working..." message after 10 seconds
  - Implement 3-minute timeout with clear error message
  - Consider adding "Cancel" button to abort request

### Risk 4: JWT Token Expiration
- **Risk**: Token expires during active session
- **Mitigation**:
  - Already handled by existing auth system (redirects to login)
  - Consider adding token refresh mechanism
  - Show clear "Session expired" message on 401 errors

### Risk 5: Environment Configuration Errors
- **Risk**: Wrong API URL used in production
- **Mitigation**:
  - Add validation for environment variables on app startup
  - Log current API URL in console (dev mode only)
  - Add health check endpoint to verify correct server connection
  - Document environment setup clearly

### Risk 6: CORS Issues
- **Risk**: Browser blocks requests due to CORS policy
- **Mitigation**:
  - Flask server already configured for CORS (per integration doc)
  - Verify CORS headers in network tab during testing
  - Add fallback error message for CORS failures
  - Document CORS requirements for production deployment

### Risk 7: Quote List Maintenance
- **Risk**: Quotes become stale or inappropriate
- **Mitigation**:
  - Store quotes in separate file for easy updates
  - Add comments explaining quote selection criteria
  - Consider making quotes configurable via backend
  - Plan for community-contributed quotes in future

### Risk 8: Navigation Cache ✅ RESOLVED
- **Risk**: Navigation changes may not appear immediately due to caching
- **Mitigation**:
  - Clear navigation cache after adding entry
  - Document cache clearing process
  - Test navigation visibility for different user roles (admin, user)
  - Verify navigation order matches config file order

---

## 8. Open Questions

### Q1: Navigation System Implementation ✅ RESOLVED
**Question**: How is navigation currently managed? Is it database-driven or config file?
**Answer**: Config file at `src/Navigation/navigation_config.php`
**Action**: Add entry to `custom_pages` array with key, title, url, icon, and roles.

### Q2: Icon Selection ✅ RESOLVED
**Question**: What icon should represent D&D Chat in navigation?
**Answer**: Use ⚔️ (sword) icon
**Action**: Add `'icon' => '⚔️'` in navigation config.

### Q3: Debug Panel Default State ✅ RESOLVED
**Question**: Should Debug panel be expanded or collapsed by default?
**Answer**: Collapsed by default
**Implementation**: 
  - Initial state: `debugExpanded = false`
  - Optional: Remember user preference in localStorage for future visits

### Q4: Mobile Responsiveness ✅ RESOLVED
**Question**: How should 30%/60% layout adapt for mobile screens?
**Answer**: Stack vertically with all fields full width
**Layout Order** (mobile):
  1. Question (full width)
  2. Answer (full width)
  3. Debug (full width)
**Implementation**: Use Tailwind responsive classes (md:w-30%, w-full)

### Q5: Rate Limit Display Placement ✅ RESOLVED
**Question**: Where should rate limit info be displayed?
**Answer**: Below debug panel, always visible
**Implementation**:
  - Position below DebugPanel component in DnDChatPage
  - Always rendered (not conditional on having data)
  - Shows "No data yet" or similar placeholder before first query
  - Updates after each successful query with rate limit and cost information

### Q6: Quote Accessibility - DEFERRED
**Question**: How should quotes be accessible to screen readers?
**Status**: Deferred - Not a priority for MVP
**Future Consideration**: 
  - ARIA live region for quote updates
  - Skip button for users who find quotes annoying
  - Alternative loading indicator for accessibility mode

---

## 9. Implementation Timeline

### Sprint 1 (Week 1)
- **Days 1-2**: Phase 1 (Configuration & Setup) + Phase 2 (API Service)
- **Days 3-4**: Phase 3 (State Management Hook) + Unit tests
- **Day 5**: Phase 4.1-4.2 (LoadingQuotes component + Quote data)

### Sprint 2 (Week 2)
- **Days 1-2**: Phase 4.3-4.4 (DebugPanel + RateLimitDisplay components)
- **Days 3-4**: Phase 5 (Main Page Component)
- **Day 5**: Phase 6 (Routing & Navigation)

### Sprint 3 (Week 3)
- **Days 1-2**: Phase 7 (Styling) + Refinements
- **Days 3-4**: Testing (Unit + Integration)
- **Day 5**: Manual testing + Bug fixes

### Sprint 4 (Week 4)
- **Days 1-2**: Documentation (User + Developer guides)
- **Days 3-4**: Final testing + Edge case handling
- **Day 5**: Code review + Deployment preparation

**Total Estimated Time**: 15-20 business days

---

## 10. Success Criteria

### Must-Have (MVP)
- ✅ User can submit D&D questions and receive answers
- ✅ Loading quotes display during query processing
- ✅ Errors are handled gracefully with notifications
- ✅ JWT authentication works correctly
- ✅ Environment detection works (local/production)
- ✅ Navigation link visible to authenticated users
- ✅ Debug panel shows diagnostics
- ✅ Rate limit info displays correctly

### Should-Have
- ✅ Quote cycling works smoothly (no repeats)
- ✅ 3-minute timeout handled properly
- ✅ Debug panel expand/collapse animation
- ✅ Responsive layout for mobile/tablet
- ✅ All HTTP error codes handled specifically
- ✅ Health check on page load

### Nice-to-Have
- 🔲 Remember debug panel state in localStorage
- 🔲 Copy answer to clipboard button
- 🔲 Query history (recent questions)
- 🔲 Share answer functionality
- 🔲 Keyboard shortcuts (Ctrl+Enter to submit)
- 🔲 Dark mode support

---

## 11. Dependencies

### External Dependencies
- **D&D RAG Flask Server**: Must be running and accessible
- **ChromaDB**: Required by Flask server for vector search
- **OpenAI API**: Required by Flask server for answer generation

### Internal Dependencies
- **Authentication System**: JWT tokens from Gravitycar auth
- **Navigation System**: Backend navigation metadata
- **Notification System**: NotificationContext for error display
- **Existing Components**: TextArea, Button, Layout, etc.

### Development Tools
- **Vite**: Build tool (already in use)
- **React 18**: UI framework (already in use)
- **TypeScript**: Type safety (already in use)
- **Tailwind CSS**: Styling (already in use)

---

## 12. Post-Implementation

### Future Enhancements
1. **Query History**: Store recent questions/answers in localStorage
2. **Favorites**: Allow users to save useful answers
3. **Share Links**: Generate shareable links to specific answers
4. **Multi-Edition Support**: Query different D&D editions (2e, 3e, 5e)
5. **Citation Display**: Show source books for answers
6. **Advanced Filters**: Filter by monster type, spell level, etc.
7. **Voice Input**: Speech-to-text for questions
8. **Export Answers**: Download answers as PDF/text file

### Monitoring & Analytics
- Track query success/failure rates
- Monitor average query response times
- Track rate limit hits per user
- Measure feature adoption (page visits, queries per user)
- Identify most common error types

### Maintenance Plan
- Weekly review of error logs
- Monthly review of query performance metrics
- Quarterly update of quote list
- Regular testing of Flask server connectivity
- Monitor OpenAI API cost trends

---

## 13. Rollback Plan

### If Critical Issues Arise
1. **Disable Navigation Link**: Remove from navigation metadata
2. **Add Feature Flag**: Conditionally render route based on config
3. **Display Maintenance Message**: Show "Feature temporarily unavailable"
4. **Rollback Code**: Revert to previous commit on main branch

### Rollback Triggers
- Flask server consistently unreachable (>1 hour downtime)
- Critical security vulnerability discovered
- Severe performance issues affecting main app
- Data corruption or user data exposure

---

## Appendix A: D&D Quotes List

```typescript
export const dndQuotes = [
  "Girding our loins",
  "Gathering the party",
  "Mapping the catacombs",
  "Memorizing spells",
  "Collecting loot",
  "Saddling the pegasi",
  "Feeding the manticore",
  "Brandishing our blades",
  "Remembering to pack torches and rope",
  "Looking for our lockpicks",
  "Consulting the Dungeon Master's screen",
  "Sharpening our daggers of warning",
  "Rolling for initiative… slowly",
  "Checking for traps (again)",
  "Negotiating with the innkeeper",
  "Gambling in the tavern",
  "Plumbing the depths",
  "Travelling the planes",
  "Calming the owlbear",
  "Copying spells into the grimoire",
  "Silencing the shrieker",
  "Reprimanding the mimic",
  "Deciphering ancient runes",
  "Whispering to the familiars",
  "Summoning the Rules Lawyer",
  "Arguing with the Dungeon Master about line of sight",
  "Converting gold pieces to experience points",
  "Beholding the beholder",
  "Polishing the dragon's hoard (carefully)",
  "Traversing the Underdark",
  "Listening at doors",
  "Making our saving throw",
  "Ugh, Rot-grubs!"
];
```

---

## Appendix B: API Response Examples

### Success Response
```json
{
  "answer": "A beholder is a floating spherical creature with a large central eye and ten smaller eyestalks...",
  "diagnostics": [
    "Retrieving documents from collection: adnd_1e",
    "Query: What is a beholder?",
    "Retrieved 8 chunks (adaptive filtering applied)",
    "Generating answer with gpt-4o-mini..."
  ],
  "errors": [],
  "meta": {
    "user_id": "b25af775-7be1-4e9a-bd3b-641dfdd8c51c",
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

### Rate Limit Error (429)
```json
{
  "error": "burst_exhausted",
  "message": "Rate limit exceeded. Please wait 60 seconds before trying again.",
  "rate_info": {
    "daily_remaining": 25,
    "retry_after": 60
  }
}
```

### Budget Exceeded Error (503)
```json
{
  "error": "budget_exceeded",
  "message": "Daily budget exceeded. Service will resume at midnight UTC.",
  "budget_info": {
    "daily_total": 1.05,
    "daily_budget": 1.0,
    "percent_used": 105
  }
}
```

---

**End of Implementation Plan**
