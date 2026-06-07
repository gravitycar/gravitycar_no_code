# Navigation Bar Cleanup

## Overview
The navigation bar is crowded and messy. There are too many models to display easily in the space we have. Our goal here is to make changes to the way we display links in the navigation bar, and filter the links we display to conserve space and hide potentially sensitive models.

## Two Approaches
There are two things we can do to improve our navigation bar

### Restrict some models from being displayed at all.
Some models, specifically the googleauthtokens and jwtrefreshtokens, don't need to be accessed via the UI ever. They are strictly backend records for authentication. So we can keep them out of the navigation bar. We can accomplish this by adding an optional property to the metadata files for this models, 'navigation_bar', and set that value to false. 

### Group related models in sub-menus
Models like Events, EventReminders, EventProposedDates, EventCommittments, are all related to each other. These items could all be easily placed in a single menu item, "Events". Opening that menu item would display all of the related models. Each related model could have its own options sub-menu items as they do today, i.e. for the "create new" link. So instead of:
- Events
- Event Commitments
- Event Reminders
- Event Proposed Dates

We would have:
- Event Organizer
  -> Events
  -> Event Commitments
  -> Event Reminders
  -> Event Proposed Dates

We can use the optional metadata property, 'navigation_bar', as an associative array to represent where this model shows up in the navigation bar. For example, in `events_metadata.php` we would set:
'navigation_bar' => ['Event Organizer']


## Updating the NavigationBuilder
Our navigation bar is driven by cached files i.e. `cache/navigation_cache_<role>.php`. These files are built by the NavigationBuilder::buildModelNavigation() method. They contain an associative array, including a 'models' array, that includes all of the data we need to display the given model for the given role, under the `models` element of that associative array. What we don't have is a means of organizing those models into groups. That's what the 'navigation_bar' property in the model's metadata files should do for us. 

Currently, the buildModelNavigation() method adds each models data to an array:
$modelNavigation[] = $modelItem;

To allow grouping, we would first need the metadata for each model. That should be easy: 
$modelMetadata = MetadataEngine::getModelMetadata($modelName);

Then, we can retrieve the model's 'navigation_bar' property:
$navigationBarGroup = $modelMetadata['navigation_bar'];

Then, buildModelNavigation() would return:
$modelNavigation[$navigationBarGroup][] = $modelItem;

If the 'navigation_bar' property is Boolean false, skip it.
If the 'navigation_bar' property is empty, the model is not grouped and is listed in the navigation bar by itself.

## Frontend Updates
We'll also need to update the frontend code for the navigation bar to handle this new scheme.