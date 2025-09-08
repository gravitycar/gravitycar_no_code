<?php
// Relationship metadata for Movie_Quote_Trivia_Games to Movie_Quote_Trivia_Questions (OneToMany)
return [
    'name' => 'movie_quote_trivia_games_movie_quote_trivia_questions',
    'type' => 'OneToMany',
    'modelOne' => 'Movie_Quote_Trivia_Games',
    'modelMany' => 'Movie_Quote_Trivia_Questions',
    'constraints' => [],
    'additionalFields' => [],
];
