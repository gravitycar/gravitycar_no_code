<?php
return [
    'name' => 'movies_movie_quotes',
    'type' => 'OneToMany',
    'modelOne' => 'Movies',
    'modelMany' => 'Movie_Quotes',
    'constraints' => [],
    'additionalFields' => [],
];