# Trivia Game UI Layout Update

## Changes Made

### 1. Score Display Layout Update
- **Moved score to center**: The score is now prominently displayed in the center of the screen below the header
- **Increased font size**: Score now uses 48px font (text-6xl class) for better visibility
- **Removed time countdown**: Eliminated the timer display to focus on score

### 2. Dynamic Score Coloring
- **Red color**: Score appears in red when below 100 points
- **Green color**: Score appears in green when above 150 points  
- **Blue color**: Default blue color for scores between 100-150

### 3. Time-Based Scoring System
- **Continuous countdown**: Score decreases by 1 point every second
- **Base score updates**: When correct answers are submitted, the base score updates from the server
- **Time reset**: Timer resets to 0 when a new answer is submitted, allowing full score potential per question

### 4. Implementation Details

#### TriviaGameBoard.tsx Changes:
- Removed `TriviaScoreDisplay` component import and usage
- Added `baseScore` and `timeElapsed` state tracking
- Implemented timer that decrements score every second
- Centralized score display with dynamic color coding
- Score calculation: `currentScore = Math.max(0, baseScore - timeElapsed)`

#### Layout Structure:
```
Header: Game title and question progress
Centered Score: Large 48px score with color coding
Game Content: Question and answer options
```

### 5. Behavior
- Game starts with server-provided base score
- Score decreases 1 point per second while question is active
- When answer is submitted, base score updates and timer resets
- Score color provides immediate visual feedback on performance
- Minimum score is 0 (cannot go negative)

## Files Modified
- `/gravitycar-frontend/src/components/trivia/TriviaGameBoard.tsx`

## Testing
The implementation maintains all existing game functionality while providing the requested visual enhancements and scoring behavior.
