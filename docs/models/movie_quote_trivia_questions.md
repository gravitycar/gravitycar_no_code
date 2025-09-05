# Movie Quote Trivia Questions

## Overview
This is a model for questions in a movie-quote themed trivia game. 

This model class will be a component in a soon-to-be implemented Movie Quote Trivia Game app.

## Fields
- `answers`: A Radio button group that displays the 3 possible answers for the movie quote triva question. Each button's value will be the ID of a movie record and the label will be the title of that movie. The correct and incorrect answers should be randomly disributed among the 3 options for every question.
- `correct_answer` - hidden field. The ID of the movie that is linked to the movie_quote record.
- `movie_quote_id` - a RelatedRecord Field linking to the movie quote.
- `answered_correctly` - a boolean field not directly displayed in the UI.


## Backend Behavior
Each question will be linked to 1 movie quote and 3 movies. One of those movies will be the movie the quote is linked to. The other two will be chosen randomly when the movie quote trivia question record is created. Each movie quote trivia question will need to know its own correct answer. The movie quote triva question the have 3 options. Each option will represent 1 movie. To answer a movie quote trivia question, the user will select 1 of the 3 options. If the user-selected option represents the correct answer, the movie quote trivia question will report itself as being answered correctly. Otherwise, the movie quote trivia question will report itself as being answered incorrectly.

When a Movie Quote Triva Question is created, the Move_Quote_Trivia_Question class will:
    - select 1 random movie_quote.
    - retrieve the movie that is linked to the movie_quote.
    - select 2 random movies.
    - assign the 3 movies to the 3 'answers' radio buttons in random order
    - assign the 'correct_answer' field the ID of the movie linked to the movie_quote.

Movie_Quote_Trivia_Questions need to be able to tell another class if the question has been answered correctly or not.

## UI Behavior
This model will very rarely be interacted with on its own. Normally, it will be generated and displayed by the soon-to-be-implemented Movie Quote Trivia Game. Only basic CRUD operations from the UI should be supported for now. 
