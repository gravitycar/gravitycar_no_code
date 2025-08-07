<?php
return [
    'name' => 'MoviesMovieQuotes',
    'type' => 'OneToMany',
    'modelOne' => 'Movies',
    'modelMany' => 'MovieQuotes',
    'constraints' => [],
    'additionalFields' => [],
];