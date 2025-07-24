# Movie Quote Trivia

## Overview
The Movie Quote Trivia Game module allows users to play a trivia game based on quotes from movies. 
The game will present users with one quote at a time, and 3 possible movies to attribute the quote to. 
One of the movies will be the correct answer, while the other two will be incorrect and randomly selected from the list of movies in the system.
Each of the 3 possible answers will be displayed as a movie poster with the title of the movie below it. Clicking on a movie poster selects that movie as the answer.
The game will give the user real-time feedback on whether their answer is correct or incorrect.
The game will keep track of the user's score, which is the number of correct answers they have given.
There will be between 11 and 15 questions in the game, and the user will be able to see their score at the end of the game.
As each question is answered, the the answered movie quote and its possible answers will animate down the screen, making room at the top of the screen for the next question.
The score will be displayed at the top right of the screen, and will update in real-time as the user answers questions.

### Models
- movies
- movie_quotes

### Menu Links
- Movie Quote Trivia Game (links to the game view)
 
### Views
- Game view
  - Displays a quote and 3 movie posters as possible answers
  - Allows the user to select an answer by clicking on a movie poster
  - Provides real-time feedback on whether the answer is correct or incorrect
  - Displays the user's score at the end of the game