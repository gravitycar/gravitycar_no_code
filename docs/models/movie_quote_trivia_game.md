# The Movie Quote Trivia Game

The Movie_Quote_Trivia_Game model represents one play-through of the Movie Quote Trivia Game. The game will show the user 15 famous movie quotes one-at-a-time. For each question, it will show a multiple-choice question allowing the user to choose which of the 3 movies the quote is from. Correct answers increase the score, wrong answers decrease the score. See the `Rules` section below. The score will be talleyed in the UI at all times. Each question (See the Movie_Quotes_Trivia_Questions class) will show each of the 3 options as a movie poster, the name and year of the movie (as set by the display_columns for the Movies model, see src/Models/movies/movies_metadata.php), and a radio button to select that movie as the user's answer.

**IMPORTANT**
The Movie Quote Trivia Game should have its own, unique page and interface. The behavior of that game interface is described below in the `Front-end Behavior` section. All models in the Gravitycar framework use the GenericCrudPage.tsx for ordinary CRUD operations. The GenericCrudPage.tsx file is expected to support ordinary CRUD operations for this model too. But playing the game isn't an ordinary CRUD operation. Playing the game requires its own, unique interface. Be sure that GenericCrudPage will work for this model. And remember that the UI for the Movie Quote Trivia Game will be its own page. It will need its own link in our navigation.


## Rules
- The score starts at 100. 
- The score is reduced by 1 for every 1 seconds the player is playing the game.
- The score will not fall below 0.
- Every wrong answer reduces the score by 3.
- Every correct answer increases the score by 3 + the obscurity_score value of the Movie Quote Trivia Question.
- When all 15 questions have been answered, the game is over and the final score will be saved.


## Backend Behavior
When the game starts, a new Movie Quote Trivia Game object will be instantiated on the server. It will generate 15 Movie Quote Trivia Questions and link those questions to this instance of the Movie Quote Trivia Game. An API call will return the the Movie_Quote_Trvia_Game model and its 15 linked Movie_Quote_Trivia_Questions models as JSON for the UI to consume.

## Front-end Behavior
The score will be shown constantly in the upper right corner, in large font. It will be red if under 100 and green if equal to or greater than 100.
The 15 questions will be displayed one at a time. Questions that haven't been answered will not be visible on the screen.
Each question will show the `quote` field of the Movie Quote, and the three possible answers provided by the Movie Quote Trivia Question class. Each of the 3 possible answers will display a movie poster, the name of the movie and a radio button to select one of the 3 possible answers. 
When a question is answered, it will have a "shrink" animation down to a small box. The small boxes represented answered questions. The should be arranged in a row beneath the current question.
Answering the question correctly should display a green border around the question. The UI should briefly flash the word "Right!" on the screen. As the question's shrink animation runs, the small box should be colored green. The score should be incremented according to the `Rules` section above.
Answering the question incorrectly should display a red border around the question. The UI should briefly flash the work "Wrong!" on the screen. As the question's shrink animation runs, the small box should be colored red. The score should be decremented according to the `Rules` section above.

## High Scores page
In addition to the Unique game-play page, we should also have a "High Scores" page that will show the top-ten scores from all players. 

## Fields
In addition to the regular audit fields provided by the CoreMetaData class, the Movie Quote Trivia Game model has these fields:
- **score**: Integer field that will store the final score of the game.
- **name**: A string field that will be automatically set to "<current user first and last name>'s game played on <month> <day>, <year>"

