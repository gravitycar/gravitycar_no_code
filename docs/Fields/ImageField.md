# Image Field

## Overview
The Image Field type is used to store and display image file paths or URLs.
This field type handles both locally stored images and remote image URLs but does NOT provide file upload functionality.
The field stores either a file path (for local images) or a URL (for remote images) in the database as a string.
This field type validates that the provided value is either a valid file path or a valid URL.
The field supports displaying images in the UI using HTML img tags with optional width and height attributes.
This field type is for display and storage of image locations only - file upload must be handled separately.

## UI
- The Image Field will be rendered as an image preview in the user interface when viewing records.
- For editing, the field displays a text input where users can enter file paths or URLs manually.
- When a valid image path/URL is provided, a preview of the image is displayed below the input field.
- If width and height are specified in the metadata, these dimensions are applied to the img tag in the HTML.
- If the image cannot be loaded (invalid path/URL), a placeholder or error message is displayed.
- The field validates URLs and file paths before accepting them, showing error messages for invalid entries.
- Images are displayed with responsive design considerations unless specific dimensions are provided.
- The field includes a "View Full Size" option to open the image in a modal or new tab.

## Properties, their default values and descriptions
- `name`: '' - The name of the field, used to identify it in the database and in the UI. When instantiating an Image Field, the 'name' value must be present in the metadata passed in from the model or relationship and cannot be empty.
- `label`: '' - The label displayed in the UI for the field. Default is the same as the `name` value.
- `type`: 'Image' - The type of the field, which is 'Image' for this field type.
- `required`: false - A boolean indicating whether the field is required (user must provide an image path/URL).
- `maxLength`: 500 - The maximum length of the stored path or URL string.
- `width`: null - Optional width in pixels for the img tag. If null, natural image width is used.
- `height`: null - Optional height in pixels for the img tag. If null, natural image height is used.
- `altText`: '' - Default alt text for the image for accessibility purposes.
- `allowLocal`: true - Whether to allow local file paths. If false, only URLs are accepted.
- `allowRemote`: true - Whether to allow remote URLs. If false, only local paths are accepted.
- `placeholder`: 'Enter image path or URL' - Placeholder text for the input field.
- `ValidationRules`: ['Image', 'MaxLength'] - An array of strings that map to subclasses of the ValidationRuleBase class. These rules define the validation logic for the field.