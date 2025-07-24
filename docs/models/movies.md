# Movies 
## Overview 
The movies model represents a collection of movies in the Gravitycar framework, allowing for CRUD operations and relationships with other models such as movie_quotes and movie_nights.

## Fields
In addition to the core fields defined in the metadata, the movie model includes the following fields:
- `name`: The title of the movie.
- `synopsis`: A brief description of the movie's plot. This is a read-only field.
- `poster_url`: A url to a poster image for the movie. This is a read-only field.
- `poster`: A non-db field that will display the poster image in the UI.


## Features
When movies are created, only the title can be set. 
The movie model will send a API call to OMDB (Open Movie Database, http://www.omdbapi.com/) to fetch the synopsis and 
poster image based on the movie title. The synopsis and poster fields will be automatically 
populated with the data retrieved from IMDB. Use this API key to access the IMDB API: '19a9f496'.
If no matching movie is found, throw a GCException with the message "Movie {title} not found in IMDB".

## Permissions
- Any user can view any movie record.
- Only users with the admin type can create, update, or delete movie records.