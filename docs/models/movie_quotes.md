# Movie Quotes

## Overview
The movie quotes model represents a collection of quotes from movies in the Gravitycar framework. It allows for CRUD operations and relationships with other models such as movies.

## Fields
In addition to the core fields defined in the metadata, the movie quotes model includes the following fields:
- `quote`: The text of the movie quote.
- `movie_id`: A reference to the movie from which the quote is taken. This is a foreign key relationship to the movies model.
- `movie`: A non-db field that will display the movie title in the UI. This field is read-only and is populated based on the movie_id relationship.
- `movie_poster`: A non-db field that will display the movie poster image in the UI. This field is read-only and is populated based on the movie_id relationship.

## Features
Movie quotes must be associated with a movie. When a movie quote is created, the `movie_id` must be provided to link it to the corresponding movie record. The `movie` and `movie_poster` fields will be automatically populated based on the `movie_id` relationship.

## Permissions
- Any user can view any movie quote record.
- Only users with the admin type can create, update, or delete movie quote records.