# Projects model

## Overview
The "Projects" model is a new model we're going to add to the Gravitycar Framework.

The purpose of the Projects model will be showcase projects that I have worked on or am working on. I want users to be able to easily get a list of projects and then get more details about them.

## Fields
All fields are required unless otherwise noted.
- `Title` string 256 characters max length. A short name for the project.
- `Tag Line` string 1024 characters max length. A short description of the project.
- `Description` string 4K characters max length. Description of the project.
- `Screenshot` image - an image that shows what the project looks like.
- `Link` string 256 characters max length. A URL to see the project. Optional.

These fields are in addition to the core metadata fields.

## UI
For creating/updating/deleting Projects records, the standard GenericCRUD UI will be used. 

### Custom Projects List View interface criteria:
- For displaying the projects, we need a custom interface similar in concept (but not appearance) to the Movie Quote Trivia Game.
- The custom Projects interface is linked to in the main navigation bar, in the top section.
- The interface is laid out in a grid, 2 tiles wide. Each tile should be approximately 400px wide by 300px high.
- Each Project occupies 1 tile in the grid.
- Each tile shows the Project `Title` across the top in large text, the `Tag line` across the bottom. This text should be superimposed over the `Screenshot` image. 
- Clicking on a tile should open a "Custom Project Detail" view.

### Custom Project Detail view criteria:
- Opens over the "Custom Projects List View".
- Has a small 'X' in the top right-hand corner to close the Custom Project Detail view.
- Shows the `Title` text, centered.
- Shows the `Tag line` text below the title, centered.
- Shows the `Screenshot` image, enlarged to consume the available space.
- If the `Link` field is filled in, the image is a link to url in the `Link` field.
- Shows the `Description` field, right-justified, below the image.
- Shows a "Check it out" button below the `Description`, centered.

## Considerations
- We don't currently have a "link" or "href" field type. We need one for this feature set, so let's create a new field type for that.

- The "ImageField" field type supports displaying an image only. We don't have an "upload image" or any file upload support. I think that the right way to really support file uploads is to create a FileUploads model, and that's out-of-scope here. We can just upload the screenshot images manually to the server, and set the `Screenshot` field to point to the absolute URL for the screenshot on the web server. Confirm if you think that will work.

## RBAC
- Admin users can create, read, list, update and delete Projects records.
- All users can read and list Projects records.
- All users can access the "Custom Projects List View" and the "Custom Project Detail View", regardless of whether they are authenticated or not.