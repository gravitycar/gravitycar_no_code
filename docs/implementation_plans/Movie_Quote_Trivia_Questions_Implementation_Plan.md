# Movie Quote Trivia Questions Implementation Plan

**Date**: September 4, 2025  
**Purpose**: Implementation plan for Movie Quote Trivia Questions model in the Gravitycar Framework

## Feature Overview

The Movie Quote Trivia Questions feature implements a model for trivia questions in a movie-quote themed trivia game. Each question presents users with a movie quote and three movie title options, where one is correct and two are random distractors. This model serves as a foundational component for a future comprehensive Movie Quote Trivia Game.

### Core Functionality
- Link trivia questions to existing movie quotes
- Generate randomized multiple-choice answers from movie titles
- Track correct answers and user responses
- Support basic CRUD operations via the framework's GenericCrudPage

## Requirements

### Functional Requirements
1. **Question Generation**: Automatically create trivia questions linked to movie quotes with randomized answer choices
2. **Answer Validation**: Determine if user-selected answers are correct
3. **Data Integrity**: Ensure each question has exactly 3 answer options with proper movie references
4. **CRUD Operations**: Support create, read, update, delete operations through the standard UI
5. **Random Selection**: Randomly select movie quotes and distractor movies when creating questions

### Non-Functional Requirements
1. **Performance**: Efficient database queries for random movie selection
2. **Scalability**: Support for large numbers of questions and movies
3. **Maintainability**: Follow framework conventions for metadata-driven development
4. **Consistency**: Use existing field types and patterns from the framework

## Design

### Database Schema
**Table Name**: `movie_quote_trivia_questions`

| Field Name | Type | Description | Constraints |
|------------|------|-------------|-------------|
| id | UUID (Primary Key) | Unique identifier | Required, Auto-generated |
| movie_quote_id | UUID (Foreign Key) | Reference to movie_quotes.id | Required |
| correct_answer | UUID | ID of the correct movie | Required |
| answer_option_1 | UUID | First movie option | Required |
| answer_option_2 | UUID | Second movie option | Required |
| answer_option_3 | UUID | Third movie option | Required |
| answered_correctly | Boolean | User's answer status | Default: false |
| created_at | DateTime | Record creation timestamp | Auto-generated |
| updated_at | DateTime | Last update timestamp | Auto-generated |
| Standard audit fields | Various | Standard Gravitycar audit trail | As per framework |

### Metadata Configuration

#### Field Definitions
1. **movie_quote_id**: RelatedRecord field linking to Movie_Quotes model
2. **answers**: RadioButtonSet field with dynamic options from the three answer movies
3. **correct_answer**: ID field (hidden from UI) storing the correct movie ID  
4. **answered_correctly**: Boolean field for tracking answer status

#### Field Types and Properties
- **RadioButtonSet**: Custom implementation that dynamically loads movie titles as options
- **RelatedRecord**: Standard relationship to Movie_Quotes model
- **Boolean**: Standard framework boolean field
- **ID**: Hidden field for internal logic

### Model Architecture

#### Class Structure
```php
namespace Gravitycar\Models\movie_quote_trivia_questions;

class Movie_Quote_Trivia_Questions extends ModelBase {
    // Custom creation logic for generating randomized questions
    // Answer validation methods
    // Random movie selection utilities
}
```

#### Key Methods
1. **create()**: Override to implement automatic question generation
2. **validateAnswer()**: Method to check if selected answer is correct
3. **generateRandomOptions()**: Private method to select random distractor movies
4. **getAnswerOptions()**: Method to return formatted answer options for UI

### Frontend Integration

#### CRUD Interface
- Utilizes existing `GenericCrudPage.tsx` component
- Radio button interface for answer selection
- Standard list/table view for question management
- Modal forms for create/edit operations

#### UI Behavior
- Display movie quote text from related record
- Show three movie title options as radio buttons
- Indicate correct/incorrect status with visual feedback
- Basic search and pagination functionality

## Implementation Steps

### Phase 1: Database Foundation (Day 1)
1. **Create Model Class**
   - Create `/src/Models/movie_quote_trivia_questions/Movie_Quote_Trivia_Questions.php`
   - Extend ModelBase with framework conventions
   - Implement basic structure without custom logic

2. **Define Metadata Structure**
   - Create metadata configuration following framework patterns
   - Define field types and relationships
   - Configure validation rules

3. **Database Schema Generation**
   - Run schema generator to create database table
   - Verify foreign key relationships
   - Test basic model instantiation

### Phase 2: Core Business Logic (Day 2-3)
1. **Implement Question Generation**
   - Override `create()` method to auto-generate questions
   - Implement random movie quote selection
   - Create algorithm for random distractor movie selection
   - Ensure no duplicate movies in answer options

2. **Answer Validation Logic**
   - Implement `validateAnswer()` method
   - Create logic to update `answered_correctly` field
   - Add methods for checking answer correctness

3. **Data Integrity Checks**
   - Validate movie quote exists and is linked to a movie
   - Ensure sufficient movies exist for distractor generation
   - Add error handling for edge cases

### Phase 3: Field Implementation (Day 4)
1. **RadioButtonSet Field Enhancement**
   - Extend RadioButtonSetField for dynamic movie options
   - Implement option loading from database
   - Configure proper labeling (movie titles) and values (movie IDs)

2. **Metadata Configuration**
   - Complete field metadata definitions
   - Configure display properties and validation rules
   - Set up relationship configurations

3. **Frontend Field Rendering**
   - Ensure RadioButtonSet renders correctly in GenericCrudPage
   - Test field interactions and data binding
   - Verify hidden field behavior

### Phase 4: API Integration (Day 5)
1. **API Endpoint Configuration**
   - Verify ModelBaseAPIController handles the new model
   - Test CRUD endpoints via REST API
   - Validate request/response formats

2. **Frontend Integration**
   - Test GenericCrudPage functionality with new model
   - Verify create/edit modal operations
   - Test list view and pagination

3. **Data Relationships**
   - Test RelatedRecord field for movie_quote_id
   - Verify cascade behavior and data integrity
   - Test relationship queries and joins

### Phase 5: Testing and Validation (Day 6)
1. **Unit Testing**
   - Create test cases for question generation logic
   - Test answer validation methods
   - Test edge cases and error conditions

2. **Integration Testing**
   - Test full CRUD workflow via API
   - Test frontend integration end-to-end
   - Verify database schema and constraints

3. **Data Validation Testing**
   - Test with various movie quote and movie combinations
   - Verify random selection distribution
   - Test performance with larger datasets

## Testing Strategy

### Unit Tests
- **MovieQuoteTriviaQuestionsTest.php**: Test core business logic
- **QuestionGenerationTest.php**: Test random selection algorithms
- **AnswerValidationTest.php**: Test answer checking logic

### Integration Tests
- **MovieQuoteTriviaQuestionsAPITest.php**: Test REST API endpoints
- **DatabaseIntegrationTest.php**: Test database operations and relationships
- **FrontendIntegrationTest.php**: Test UI functionality

### Test Scenarios
1. **Question Creation**: Generate questions with valid movie quotes
2. **Answer Selection**: Test all three answer options
3. **Data Integrity**: Ensure referential integrity with related models
4. **Edge Cases**: Handle insufficient movies, missing quotes, etc.
5. **Performance**: Test random selection performance with large datasets

## Documentation

### API Documentation
- Endpoint documentation for CRUD operations
- Field schemas and validation rules
- Example request/response payloads

### User Guide
- How to create and manage trivia questions
- Understanding the question generation process
- Using the CRUD interface effectively

### Developer Documentation
- Model architecture and design decisions
- Customization points for future enhancements
- Integration patterns for the future trivia game

## Risks and Mitigations

### Risk 1: Insufficient Movie Data
**Risk**: Not enough movies in database to generate diverse questions
**Mitigation**: Add validation to ensure minimum movie count before question creation

### Risk 2: Performance Issues with Random Selection
**Risk**: Random movie selection queries may be slow with large datasets
**Mitigation**: Implement efficient random selection algorithms; consider caching strategies

### Risk 3: Data Consistency Issues
**Risk**: Referenced movies or quotes may be deleted, breaking questions
**Mitigation**: Implement proper foreign key constraints and cascade behaviors

### Risk 4: Complex UI Requirements
**Risk**: RadioButtonSet field may need extensive customization
**Mitigation**: Start with standard implementation; enhance incrementally based on requirements

### Risk 5: Future Game Integration Complexity
**Risk**: Current design may not support all future game requirements
**Mitigation**: Design with extensibility in mind; document integration points clearly

## Dependencies

### Framework Dependencies
- **ModelBase**: Core model functionality and CRUD operations
- **RelatedRecordField**: For movie_quote_id relationship
- **RadioButtonSetField**: For answer option selection
- **GenericCrudPage**: Frontend CRUD interface
- **ModelBaseAPIController**: REST API endpoints

### Model Dependencies
- **Movies Model**: Required for answer options and correct answers
- **Movie_Quotes Model**: Required for question content and linking
- **Database Schema**: Existing tables for movies and movie_quotes

### External Dependencies
- **PHP 8.1+**: Framework requirement
- **MySQL/MariaDB**: Database storage
- **React/TypeScript**: Frontend framework
- **PHPUnit**: Testing framework

## Future Considerations

### Game Integration Points
- **Scoring System**: Interface for tracking player scores
- **Timer Functionality**: Support for timed questions
- **Difficulty Levels**: Categorization of questions by difficulty
- **Statistics Tracking**: Player performance analytics

### Potential Enhancements
- **Custom Question Creation**: Allow manual question creation without random generation
- **Multiple Question Types**: Support for different question formats
- **Question Categories**: Group questions by movie genre, year, etc.
- **Image Support**: Include movie posters or stills in questions

### Scalability Considerations
- **Caching Strategies**: Cache frequently accessed movie data
- **Database Optimization**: Indexes for efficient random selection
- **API Rate Limiting**: Protect against excessive question generation requests
- **Background Processing**: Move expensive operations to queued jobs

This implementation plan provides a comprehensive roadmap for implementing the Movie Quote Trivia Questions feature while maintaining consistency with the Gravitycar Framework's metadata-driven architecture and ensuring seamless integration with existing components.
