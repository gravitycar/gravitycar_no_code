# New project: DNDRag Chat UI
We're going to start a new project, DNDRag Chat UI.

## Summary
I want you to build an implementation plan for this project, based on the following specifications and existing documentation.

This project will be different from our other projects, in that it will not interact with our backend API. 
It will work with a separate application, DNDRag Chat server. This is a chatbot application written in python. 
This project will provide the UI to intereact with the DNDRag chat server, and the DND Rag Chat server will handle all of the logic.
This project will rely on our existing React compoents and code. Reivew our codebase carefully before you create any new React components or files for this project.

We will send the DNDRag chat server a question and the currently authenticated user's bearer token as an http request header. 
The DNDRag chat server will vaidate the user's authentication. We don't have to worry about that.
Assuming the user's authentication is good, the DNDRag Chat server will return an AI generated answer to our question, and diagnostic information we can optionally display or not.
If any errors occur on the DNDRag chat server's end, that data will be included in the response, which should have an http response code >= 400. 

## Environments:
We will have two environments we have to operate in: local development and remote production.
For local development, the DNDRag chat server is located at localhost:5000.
For remote production, the DNDRag chat server is located at https://dndchat.gravitycar.com.

These URL's should be stored in our .env file and our code should use the values in the .env file to retrieve the correct url to use for the DNDRag chat server.

## Existing documentation :
#fetch docs/dnd_rag_chat_ui_integration.md
Review this document, it should give you all the details you will need about how to contact the DND Rag Chat server and what to expect from its response.
IMPORTANT!!!! DO NOT COPY the Typescript or ReactJS code examples. Refer to them as guidance, not as usable code. Use our existing components and our codebase wherever possible. 
Implement this interface with the code we already have and depend on. 

## UI Components
The UI I want to build should have these components:
- Title:
	A Title across the top of the UI that says "Advanced Dungeons & dRAGons - Rag Chat for D&D"

- Question:
	A textarea input field that is @30% the width of the screen. The field's placeholder should read "Enter your D&D question here".

- Submit button:
	A blue submit button centered beneath the Question textarea. Format it like our other submit buttons. The label should say "Ask the Dungeon Master".
	
- Answer:
	A read-only textarea input field that is about 60% the width of the page and displayed next to the Question field.
	30% + 60% = 90% leaving 10% for various padding, margins, etc.
	When the DND Rag chat server responds to our question, the text for the answer in the response will be displayed here.
	
- Debug
	A read-only textarea input field that streches below the Question and Answer fields. The Debug textarea should stretch from the left border of the Question field to the right border of the Answer field.
	The Debug field should be collapsable, so we can hide it.
	When the Debug textarea is collapsed, the Question and Answer fields should stretch down most of the length of the window, leaving enough room for the collapsed Debug textarea field to be visible so it can be expanded by clicking on it. 
	When the Debug textarea is expanded, it should get @50% of the height of the window.
	
## Error handling	
If the response includes any error data, we need to display that using the same mechanisms our UI uses for non-200 responses from our backend API.


## Navigation
A link to this UI should be listed in our navigation UI. This link should only be visible to authenticated users.

## Funny Quotes
When the submit button is clicked, there will be some delay while the server works out its response. 
During this delay, our UI should display a funny, pithy quote superimposed over the window to pass the time.
The UI should randomly choose a single quote from a list. It should  not retrieve the same quote twice in a row.
The quotes should stay up for 5 seconds, then start to fade for 3 seconds, and then be replaced with a new quote.
This process should repeat until the response from the DNDRag chat server arrives, or 3 minutes. After 3 minutes we can assume a timeout has occurred.
Here is a list of the quotes:

Girding our loins
Gathering the party
Mapping the catacombs
Memorizing spells
Collecting loot
Saddling the pegasi
Feeding the manticore
Brandishing our blades
Remembering to pack torches and rope
Looking for our lockpicks
Consulting the Dungeon Master’s screen
Sharpening our daggers of warning
Rolling for initiative… slowly
Checking for traps (again)
Negotiating with the innkeeper
Gambling in the tavern
Plumbing the depths
Travelling the planes
Calming the owlbear
Copying spells into the grimoire
Silencing the shrieker 
Reprimanding the mimic
Deciphering ancient runes
Whispering to the familiars
Summoning the Rules Lawyer
Arguing with the Dungeon Master about line of sight
Converting gold pieces to experience points
Beholding the beholder
Polishing the dragon’s hoard (carefully)
Traversing the Underdark
Listening at doors
Making our saving throw
Ugh, Rot-grubs!



## Do you see any problems?
If you have any concerns, stop making the plan and tell me your concerns. 