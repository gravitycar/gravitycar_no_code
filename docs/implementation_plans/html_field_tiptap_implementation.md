# Implementation Plan: TipTap-Based HTML Field

**Feature**: Add HTML/Rich Text field type to Gravitycar Framework using TipTap editor  
**Date**: November 19, 2025  
**Status**: Planning  
**Branch**: `feature/html_field`

---

## 1. Feature Overview

Add a new `HTMLField` type to the Gravitycar Framework that provides rich text editing capabilities for blog posts and other content-heavy features. The field will use TipTap (a modern, React-first rich text editor built on ProseMirror) to provide a user-friendly WYSIWYG editing experience with support for:

- Text formatting (bold, italic, underline, strikethrough)
- Headings (H1-H6)
- Lists (ordered and unordered)
- Links
- Images (embedded via URL or upload)
- Code blocks
- Blockquotes
- Horizontal rules
- Tables (optional)
- Text alignment
- Undo/redo functionality

### Purpose in Framework
The HTMLField enables content-rich models (blogs, articles, documentation, rich profiles) without requiring developers to write custom editors. It seamlessly integrates with Gravitycar's metadata-driven architecture, providing automatic CRUD operations, validation, and React UI generation.

### Problems It Solves
1. **Content Management**: No built-in way to create formatted content in the framework
2. **Developer Productivity**: Eliminates need for custom editor implementations per project
3. **User Experience**: Provides intuitive WYSIWYG editing instead of raw HTML/Markdown
4. **Security**: Built-in HTML sanitization to prevent XSS attacks
5. **Consistency**: Maintains Gravitycar's pattern of metadata → backend → frontend flow

---

## 2. Requirements

### 2.1 Functional Requirements

**Backend (PHP)**
- [ ] Create `HTMLField` class extending `FieldBase`
- [ ] Store HTML content in database as TEXT/MEDIUMTEXT
- [ ] Validate HTML content for security (XSS prevention)
- [ ] Support configurable maximum content length
- [ ] Generate proper OpenAPI schema for HTML fields
- [ ] Auto-register field type in metadata cache
- [ ] Support all standard field metadata properties (required, readOnly, etc.)

**Frontend (React/TypeScript)**
- [ ] Create `RichTextEditor` component using TipTap
- [ ] Implement toolbar with formatting controls
- [ ] Support image insertion (URL and upload)
- [ ] Provide preview mode for rendered HTML
- [ ] Ensure responsive design (mobile-friendly)
- [ ] Handle read-only mode for display
- [ ] Support Tailwind CSS styling
- [ ] Register component in `FieldComponent.tsx` mapping

**Validation**
- [ ] Create `SafeHTMLValidation` rule to sanitize malicious content
- [ ] Support `Required` validation for HTML fields
- [ ] Validate maximum content length
- [ ] Ensure valid HTML structure (optional strict mode)

**Database Schema**
- [ ] Map HTMLField to appropriate MySQL TEXT type based on maxLength
  - maxLength < 65,535: `TEXT`
  - maxLength < 16,777,215: `MEDIUMTEXT`
  - maxLength >= 16,777,215: `LONGTEXT`

### 2.2 Non-Functional Requirements

**Performance**
- [ ] TipTap bundle should not exceed 150KB (gzipped)
- [ ] Editor initialization under 200ms on modern devices
- [ ] Lazy-load editor on field focus (optional optimization)
- [ ] Efficient HTML sanitization without blocking UI

**Security**
- [ ] HTML sanitization on both client and server side
- [ ] Whitelist approach for allowed HTML tags and attributes
- [ ] Remove JavaScript event handlers (onclick, onerror, etc.)
- [ ] Sanitize embedded images (restrict protocols to http/https)
- [ ] Prevent style injection attacks

**Usability**
- [ ] Intuitive toolbar with icons and tooltips
- [ ] Keyboard shortcuts (Ctrl+B for bold, etc.)
- [ ] Mobile-responsive touch controls
- [ ] Accessible (ARIA labels, keyboard navigation)

**Compatibility**
- [ ] React 19.x compatible
- [ ] TypeScript type-safe
- [ ] Works with existing Gravitycar field architecture
- [ ] No breaking changes to existing fields

---

## 3. Design

### 3.1 Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     Gravitycar Framework                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Metadata Layer                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ blog_posts_metadata.php                             │  │
│  │   'content' => [                                     │  │
│  │     'type' => 'HTML',                                │  │
│  │     'label' => 'Post Content',                       │  │
│  │     'maxLength' => 100000,                           │  │
│  │     'validationRules' => ['Required', 'SafeHTML']    │  │
│  │   ]                                                  │  │
│  └──────────────────────────────────────────────────────┘  │
│                          ↓                                  │
│  Backend (PHP)                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ HTMLField extends FieldBase                          │  │
│  │   - type: 'HTML'                                     │  │
│  │   - reactComponent: 'RichTextEditor'                 │  │
│  │   - maxLength: 100000 (configurable)                 │  │
│  │   - validationRules: ['SafeHTML']                    │  │
│  │   - operators: ['equals', 'isNull', 'isNotNull']     │  │
│  │                                                       │  │
│  │ SafeHTMLValidation extends ValidationRuleBase        │  │
│  │   - sanitize() - Remove dangerous HTML               │  │
│  │   - validate() - Check sanitized content             │  │
│  └──────────────────────────────────────────────────────┘  │
│                          ↓                                  │
│  Database (MySQL)                                           │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ CREATE TABLE blog_posts (                            │  │
│  │   content MEDIUMTEXT,  -- Auto-mapped from HTMLField │  │
│  │   ...                                                │  │
│  │ )                                                    │  │
│  └──────────────────────────────────────────────────────┘  │
│                          ↓                                  │
│  Frontend (React/TypeScript)                                │
│  ┌──────────────────────────────────────────────────────┐  │
│  │ RichTextEditor.tsx                                   │  │
│  │   - TipTap Editor with StarterKit                    │  │
│  │   - Custom Toolbar component                         │  │
│  │   - Image extension with upload support              │  │
│  │   - Preview/Edit mode toggle                         │  │
│  │   - Tailwind CSS styling                             │  │
│  │                                                       │  │
│  │ FieldComponent.tsx                                   │  │
│  │   componentMap['RichTextEditor'] = RichTextEditor    │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Component Interactions

**Field Creation Flow:**
1. Developer defines HTML field in model metadata
2. MetadataEngine scans and registers HTMLField type in cache
3. FieldFactory instantiates HTMLField from metadata
4. SchemaGenerator maps HTMLField to MySQL TEXT column
5. React frontend loads RichTextEditor component via FieldComponent mapping

**Content Save Flow:**
1. User types content in TipTap editor (frontend)
2. TipTap converts content to HTML string
3. Client-side validation (length, basic sanitization)
4. POST request to backend API with HTML content
5. Server-side SafeHTMLValidation sanitizes and validates
6. ModelBase saves sanitized HTML to database
7. Success response returned to frontend

**Content Load Flow:**
1. Frontend requests record via API
2. Backend retrieves HTML content from database
3. Content returned in API response (already sanitized)
4. RichTextEditor renders content in TipTap editor
5. User can view or edit based on permissions

### 3.3 Database Schema Mapping

The `SchemaGenerator` will map HTML fields to MySQL TEXT types:

```php
// In SchemaGenerator::getDoctrineTypeForField()
case 'HTML':
    $maxLength = $fieldMeta['maxLength'] ?? 65535;
    if ($maxLength < 65535) {
        return Types::TEXT; // 64KB
    } elseif ($maxLength < 16777215) {
        return Types::TEXT; // Should be MEDIUMTEXT but Doctrine limitation
    } else {
        return Types::TEXT; // Should be LONGTEXT but Doctrine limitation
    }
```

**Note**: Doctrine DBAL uses `Types::TEXT` for all text lengths. MySQL automatically chooses TEXT/MEDIUMTEXT/LONGTEXT based on defined length.

### 3.4 Security Design

**HTML Sanitization Strategy:**
- Use **DOMPurify** (or PHP equivalent: HTML Purifier) for sanitization
- Whitelist approach: only allow safe tags and attributes
- Remove all JavaScript (inline scripts, event handlers)
- Validate URLs in links and images (http/https only)
- Strip dangerous CSS (expression, behavior, etc.)

**Allowed HTML Elements:**
```
p, br, strong, em, u, s, h1, h2, h3, h4, h5, h6,
ul, ol, li, blockquote, code, pre, a, img, hr,
table, thead, tbody, tr, th, td
```

**Allowed Attributes:**
```
href (on <a>), src (on <img>), alt (on <img>),
class (for styling), align (for alignment)
```

**Sanitization Points:**
1. Client-side (TipTap configuration - prevent entry)
2. Server-side validation (SafeHTMLValidation - enforcement)
3. Display (escape output if needed - defense in depth)

---

## 4. Implementation Steps

### Phase 1: Backend Foundation (PHP)

#### Step 1.1: Create HTMLField Class
**File**: `src/Fields/HTMLField.php`

**Tasks:**
- [ ] Create class extending `FieldBase`
- [ ] Set protected properties:
  - `type = 'HTML'`
  - `reactComponent = 'RichTextEditor'`
  - `maxLength = 65535` (default to TEXT size)
  - `allowImages = true`
  - `allowTables = false`
  - `sanitizeOnSave = true`
  - `operators = ['equals', 'notEquals', 'isNull', 'isNotNull']`
- [ ] Override `generateOpenAPISchema()` to return HTML-specific schema
- [ ] Add PHPDoc comments with usage examples

**Estimated Time**: 2 hours

#### Step 1.2: Create SafeHTMLValidation Rule
**File**: `src/Validation/SafeHTMLValidation.php`

**Tasks:**
- [ ] Create class extending `ValidationRuleBase`
- [ ] Implement `validate($value, $model)` method
- [ ] Add HTML sanitization logic (use HTML Purifier or custom)
- [ ] Configure whitelist of allowed tags/attributes
- [ ] Log sanitization actions for security auditing
- [ ] Provide clear error messages for rejected content
- [ ] Add static `$description` property
- [ ] Handle empty/null values gracefully

**Estimated Time**: 4 hours (includes sanitization library research)

#### Step 1.3: Update SchemaGenerator for HTML Field Mapping
**File**: `src/Schema/SchemaGenerator.php`

**Tasks:**
- [ ] Add case for 'HTML' in `getDoctrineTypeForField()` method
- [ ] Map to appropriate Doctrine TEXT type based on maxLength
- [ ] Ensure proper column definition for MySQL
- [ ] Add unit tests for HTML field schema generation

**Estimated Time**: 2 hours

#### Step 1.4: Update MetadataEngine Field Discovery
**File**: `src/Metadata/MetadataEngine.php`

**Tasks:**
- [ ] Verify `scanAndLoadFieldTypes()` auto-discovers HTMLField
- [ ] Test metadata cache regeneration includes HTML type
- [ ] Ensure React component mapping is correct
- [ ] Add logging for HTMLField registration

**Estimated Time**: 1 hour

#### Step 1.5: Backend Testing
**Files**: 
- `Tests/Unit/Fields/HTMLFieldTest.php`
- `Tests/Unit/Validation/SafeHTMLValidationTest.php`
- `Tests/Integration/HTMLFieldIntegrationTest.php`

**Tasks:**
- [ ] Unit tests for HTMLField class
  - Constructor and metadata ingestion
  - Value setting and getting
  - Validation integration
  - OpenAPI schema generation
- [ ] Unit tests for SafeHTMLValidation
  - Safe HTML passes validation
  - Dangerous HTML is sanitized
  - Malicious scripts are removed
  - Empty values handled correctly
- [ ] Integration test with ModelBase
  - Create model with HTML field
  - Save and retrieve HTML content
  - Test validation enforcement
- [ ] Test schema generation for HTML fields

**Estimated Time**: 6 hours

---

### Phase 2: Frontend Foundation (React/TypeScript)

#### Step 2.1: Install TipTap Dependencies
**File**: `gravitycar-frontend/package.json`

**Tasks:**
- [ ] Install core TipTap packages:
  ```bash
  npm install @tiptap/react @tiptap/pm @tiptap/starter-kit
  ```
- [ ] Install TipTap extensions:
  ```bash
  npm install @tiptap/extension-image @tiptap/extension-link @tiptap/extension-placeholder
  ```
- [ ] Install DOMPurify for client-side sanitization:
  ```bash
  npm install dompurify @types/dompurify
  ```
- [ ] Verify no dependency conflicts with React 19
- [ ] Document package versions in README

**Estimated Time**: 1 hour

**Important Note on File Uploads:**
The Gravitycar framework currently does NOT have:
- File upload API endpoint
- File storage directory structure
- FileUpload field type
- Image upload functionality in ImageField (URL-only currently)

See Phase 4 for file upload implementation as a separate feature.

#### Step 2.2: Create RichTextEditor Component
**File**: `gravitycar-frontend/src/components/fields/RichTextEditor.tsx`

**Tasks:**
- [ ] Create React component implementing `FieldComponentProps`
- [ ] Initialize TipTap editor with StarterKit
- [ ] Configure extensions (Bold, Italic, Heading, Lists, Link, Image)
- [ ] Handle value prop (initial content)
- [ ] Handle onChange callback (sync editor content to parent)
- [ ] Support disabled/readOnly props
- [ ] Add error display for validation errors
- [ ] Support required field indicator
- [ ] Apply Tailwind CSS styling
- [ ] Add TypeScript type definitions

**Estimated Time**: 6 hours

#### Step 2.3: Create Toolbar Component
**File**: `gravitycar-frontend/src/components/fields/RichTextToolbar.tsx`

**Tasks:**
- [ ] Create toolbar component with formatting buttons
- [ ] Implement button groups:
  - Text formatting (Bold, Italic, Underline, Strikethrough)
  - Headings (H1-H6 dropdown)
  - Lists (Ordered, Unordered)
  - Insert (Link, Image, Horizontal Rule)
  - Alignment (Left, Center, Right, Justify)
  - Undo/Redo
- [ ] Style buttons with Tailwind (active states, hover effects)
- [ ] Add tooltips with keyboard shortcuts
- [ ] Make toolbar sticky on scroll (optional)
- [ ] Handle editor state (enable/disable buttons based on selection)
- [ ] Responsive design for mobile

**Estimated Time**: 4 hours

#### Step 2.4: Add Image Upload Support
**File**: `gravitycar-frontend/src/components/fields/RichTextImageDialog.tsx`

**Tasks:**
- [ ] Create modal dialog for image insertion
- [ ] Support URL input for remote images
- [ ] **NOTE**: File upload NOT initially supported (see Phase 4 for FileUpload feature)
- [ ] Image preview before insertion
- [ ] Alt text input for accessibility
- [ ] Validate image URLs (http/https only)
- [ ] Handle validation errors gracefully
- [ ] Integrate with TipTap Image extension

**Estimated Time**: 3 hours (reduced - URL-only initially)

#### Step 2.5: Register Component in FieldComponent
**File**: `gravitycar-frontend/src/components/fields/FieldComponent.tsx`

**Tasks:**
- [ ] Import RichTextEditor component
- [ ] Add to componentMap: `'RichTextEditor': RichTextEditor`
- [ ] Verify component receives correct props from field metadata
- [ ] Test component selection logic

**Estimated Time**: 30 minutes

#### Step 2.6: Add TypeScript Types
**File**: `gravitycar-frontend/src/types/index.ts` (or field-specific types file)

**Tasks:**
- [ ] Define `HTMLFieldMetadata` interface extending `FieldMetadata`
- [ ] Add TipTap-specific type definitions
- [ ] Document custom props for RichTextEditor

**Estimated Time**: 1 hour

#### Step 2.7: Frontend Testing
**Manual Testing** (Frontend test framework TBD)

**Tasks:**
- [ ] Test editor initialization and rendering
- [ ] Test all toolbar buttons and formatting
- [ ] Test image insertion (URL and upload)
- [ ] Test read-only mode
- [ ] Test validation error display
- [ ] Test responsive design (mobile/tablet/desktop)
- [ ] Test with GenericCrudPage create/edit forms
- [ ] Test content persistence (save and reload)
- [ ] Browser compatibility (Chrome, Firefox, Safari, Edge)

**Estimated Time**: 4 hours

---

### Phase 3: Integration & Documentation

#### Step 3.1: Create Example Blog Model
**File**: `src/Models/blog_posts/blog_posts_metadata.php`

**Tasks:**
- [ ] Create Blog_Posts model demonstrating HTMLField usage
- [ ] Include fields:
  - title (Text)
  - slug (Text, unique)
  - content (HTML) ← New field type
  - excerpt (BigText)
  - featured_image (Image)
  - author_id (RelatedRecord → Users)
  - published_at (DateTime)
  - status (Enum: draft, published, archived)
- [ ] Configure UI metadata for CRUD operations
- [ ] Add RBAC permissions

**Estimated Time**: 2 hours

#### Step 3.2: Create Blog_Posts Model Class
**File**: `src/Models/blog_posts/Blog_Posts.php`

**Tasks:**
- [ ] Create class extending ModelBase
- [ ] Add custom methods (e.g., publish(), getExcerpt())
- [ ] Demonstrate HTML field usage in business logic

**Estimated Time**: 2 hours

#### Step 3.3: Run Setup and Test End-to-End
**Tasks:**
- [ ] Run `php setup.php` to rebuild cache and schema
- [ ] Verify blog_posts table created with MEDIUMTEXT content column
- [ ] Test creating blog post via API
- [ ] Test frontend create form with RichTextEditor
- [ ] Test HTML sanitization (try XSS attacks)
- [ ] Test save/retrieve cycle
- [ ] Test edit form with existing HTML content

**Estimated Time**: 2 hours

#### Step 3.4: Write Documentation
**Files**: 
- `docs/Fields/HTMLField.md`
- `docs/implementation_plans/html_field_tiptap_implementation.md` (this file)
- Update `README.md` or `CLAUDE.md` with HTMLField examples

**Tasks:**
- [ ] Document HTMLField PHP class API
- [ ] Document SafeHTMLValidation rule
- [ ] Provide metadata configuration examples
- [ ] Document RichTextEditor component props
- [ ] Document image upload integration
- [ ] Document security considerations
- [ ] Add troubleshooting section
- [ ] Include screenshots/GIFs of editor in action

**Estimated Time**: 4 hours

#### Step 3.5: Update .github/copilot-instructions.md
**File**: `.github/copilot-instructions.md`

**Tasks:**
- [ ] Add HTMLField to list of supported field types
- [ ] Add RichTextEditor to component examples
- [ ] Document SafeHTMLValidation in validation section
- [ ] Update field type discovery section

**Estimated Time**: 1 hour

---

### Phase 4: Advanced Features (Optional Enhancements)

#### Step 4.1: Implement File Upload Infrastructure ⚠️ **CRITICAL DEPENDENCY**
**Files:**
- `src/Fields/FileUploadField.php`
- `src/Api/FileUploadController.php` (or add to ModelBaseAPIController)
- `gravitycar-frontend/src/components/fields/FileUpload.tsx`
- `config.php` (add upload directory configuration)

**Tasks:**
- [ ] Create FileUploadField extending FieldBase
- [ ] Implement file upload API endpoint
  - **Decision Point**: Add to ModelBaseAPIController or separate controller?
  - **Recommended**: Add `POST /?/upload` route to ModelBaseAPIController
  - Handles multipart/form-data requests
  - Validates file types, sizes, and security
  - Moves files to configured upload directory
  - Returns file URL/path for storage in database
- [ ] Create file storage directory structure
  - Default: `public/uploads/{model_name}/{field_name}/`
  - Configurable via `config.php`
  - Proper permissions and .htaccess rules
- [ ] Add file upload validation rules
  - FileTypeValidation (whitelist: images, PDFs, etc.)
  - FileSizeValidation (max size limits)
  - SecureFilenameValidation (prevent path traversal)
- [ ] Create FileUpload React component
  - Drag-and-drop support
  - Progress indicator
  - Error handling
  - File preview (images)
- [ ] Update ImageField to support actual uploads
  - Integrate with FileUpload component
  - Migrate from URL-only to URL + upload
- [ ] Add file cleanup on record deletion
  - Delete orphaned files when records deleted
  - Configurable retention policy

**Security Considerations:**
- Validate file MIME types (not just extensions)
- Generate unique filenames to prevent overwrites
- Store files outside web root if possible
- Scan for malicious content (optional: virus scanning)
- Rate limiting on upload endpoint

**Estimated Time**: 12 hours

**Why This Should Be Phase 4.1:**
Without file upload infrastructure, the HTMLField can only use remote image URLs. This limits usability significantly. File upload is a foundational feature that other fields (Image, Video, Document) would also benefit from.

#### Step 4.2: Add Image Upload to RichTextEditor
**Tasks:**
- [ ] Update RichTextImageDialog to support file uploads
- [ ] Integrate with FileUploadController API
- [ ] Handle upload progress and errors
- [ ] Update TipTap Image extension configuration

**Estimated Time**: 3 hours
**Depends On**: Step 4.1 (File Upload Infrastructure)

#### Step 4.3: Add Content Preview Mode
**Tasks:**
- [ ] Toggle between edit and preview modes
- [ ] Render HTML safely in preview (sanitized)
- [ ] Match frontend styling in preview

**Estimated Time**: 2 hours

#### Step 4.4: Add Markdown Import/Export
**Tasks:**
- [ ] Add button to import Markdown content
- [ ] Convert Markdown to HTML using library
- [ ] Add button to export as Markdown

**Estimated Time**: 3 hours

#### Step 4.5: Add Table Support
**Tasks:**
- [ ] Install @tiptap/extension-table
- [ ] Add table controls to toolbar
- [ ] Test table creation and editing

**Estimated Time**: 2 hours

#### Step 4.6: Add Word Count Display
**Tasks:**
- [ ] Add character/word counter below editor
- [ ] Update live as user types
- [ ] Warn when approaching maxLength

**Estimated Time**: 1 hour

#### Step 4.7: Add Content Templates
**Tasks:**
- [ ] Create predefined HTML templates
- [ ] Add "Insert Template" button
- [ ] Store templates in metadata or database

**Estimated Time**: 3 hours

---

## 5. Testing Strategy

### 5.1 Unit Tests (PHPUnit)

**HTMLField Tests** (`Tests/Unit/Fields/HTMLFieldTest.php`)
- [ ] Test field construction with various metadata configs
- [ ] Test value setting and retrieval
- [ ] Test maxLength property handling
- [ ] Test OpenAPI schema generation
- [ ] Test operators list
- [ ] Test isDBField() returns true
- [ ] Test integration with FieldFactory

**SafeHTMLValidation Tests** (`Tests/Unit/Validation/SafeHTMLValidationTest.php`)
- [ ] Test safe HTML passes validation
- [ ] Test script tags are removed
- [ ] Test event handlers (onclick, onerror) are removed
- [ ] Test dangerous protocols (javascript:, data:) are removed
- [ ] Test allowed tags and attributes pass through
- [ ] Test empty/null values
- [ ] Test maxLength enforcement (if applicable)
- [ ] Test error message generation

### 5.2 Integration Tests (PHPUnit)

**HTMLField Integration Test** (`Tests/Integration/HTMLFieldIntegrationTest.php`)
- [ ] Test creating model with HTML field
- [ ] Test saving HTML content to database
- [ ] Test retrieving HTML content from database
- [ ] Test validation enforcement on save
- [ ] Test XSS prevention (inject malicious scripts)
- [ ] Test content sanitization end-to-end
- [ ] Test schema generation for HTML field

**Blog_Posts Model Test** (`Tests/Feature/BlogPostsCrudTest.php`)
- [ ] Test full CRUD lifecycle with HTML content
- [ ] Test API endpoints (list, retrieve, create, update, delete)
- [ ] Test HTML content in API responses
- [ ] Test validation errors returned correctly

### 5.3 Frontend Tests (Manual)

**RichTextEditor Component Tests**
- [ ] Test editor renders correctly
- [ ] Test toolbar buttons work
- [ ] Test formatting (bold, italic, headings, lists)
- [ ] Test image insertion (URL and upload)
- [ ] Test link insertion and editing
- [ ] Test undo/redo functionality
- [ ] Test disabled/readOnly modes
- [ ] Test error display for validation failures
- [ ] Test content persistence on form submission
- [ ] Test mobile responsiveness
- [ ] Test keyboard shortcuts
- [ ] Test accessibility (screen reader, keyboard navigation)

**Integration with GenericCrudPage**
- [ ] Test create form with HTML field
- [ ] Test edit form with existing HTML content
- [ ] Test validation error display
- [ ] Test form submission and success flow
- [ ] Test form cancellation (unsaved changes warning?)

### 5.4 Security Tests

**XSS Prevention Tests**
- [ ] Test `<script>alert('XSS')</script>` is removed
- [ ] Test `<img src=x onerror="alert('XSS')">` is sanitized
- [ ] Test `<a href="javascript:alert('XSS')">` is blocked
- [ ] Test CSS injection `<style>body{display:none}</style>` is removed
- [ ] Test event handlers `<div onclick="alert('XSS')">` are removed
- [ ] Test iframe injection `<iframe src="evil.com">` is removed

**Content Injection Tests**
- [ ] Test SQL injection attempts in HTML content
- [ ] Test path traversal in image URLs
- [ ] Test large payloads (DoS prevention)

### 5.5 Performance Tests

**Load Time Tests**
- [ ] Measure TipTap bundle size (target < 150KB gzipped)
- [ ] Measure editor initialization time (target < 200ms)
- [ ] Test with large HTML content (50KB+)
- [ ] Test multiple editors on same page

**Database Performance**
- [ ] Test query performance with TEXT/MEDIUMTEXT columns
- [ ] Test full-text search on HTML content (if implemented)

---

## 6. Documentation

### 6.1 Developer Documentation

**HTMLField API Documentation** (`docs/Fields/HTMLField.md`)
- Overview and purpose
- Constructor parameters
- Properties and methods
- Metadata configuration options
- OpenAPI schema output
- Database mapping details
- Security considerations
- Code examples

**SafeHTMLValidation Documentation** (in ValidationEngine docs or separate file)
- Purpose and security rationale
- Allowed tags and attributes list
- Configuration options
- Usage examples
- Testing recommendations

**RichTextEditor Component Documentation** (in component docs or README)
- Component API (props, events)
- Toolbar customization
- Image upload integration
- Styling and theming
- Accessibility features
- Browser compatibility

### 6.2 User Documentation

**Blog Feature Guide** (for end users)
- How to create a blog post
- Using the rich text editor
- Formatting text
- Inserting images
- Preview and publish

### 6.3 Code Examples

**Example 1: Simple Blog Post**
```php
// src/Models/blog_posts/blog_posts_metadata.php
'content' => [
    'type' => 'HTML',
    'label' => 'Post Content',
    'required' => true,
    'maxLength' => 100000,
    'validationRules' => ['Required', 'SafeHTML']
]
```

**Example 2: Limited Formatting (Comments)**
```php
// src/Models/comments/comments_metadata.php
'comment_text' => [
    'type' => 'HTML',
    'label' => 'Comment',
    'required' => true,
    'maxLength' => 5000,
    'allowImages' => false,      // Custom metadata for restricted editing
    'allowHeadings' => false,
    'validationRules' => ['Required', 'SafeHTML']
]
```

**Example 3: Full-Featured Article**
```php
// src/Models/articles/articles_metadata.php
'body' => [
    'type' => 'HTML',
    'label' => 'Article Body',
    'required' => true,
    'maxLength' => 500000,       // MEDIUMTEXT size
    'allowImages' => true,
    'allowTables' => true,
    'validationRules' => ['Required', 'SafeHTML']
]
```

### 6.4 Update Existing Documentation

Files to update:
- [ ] `.github/copilot-instructions.md` - Add HTMLField to supported types
- [ ] `README.md` - Add HTMLField to features list
- [ ] `docs/architecture.md` - Update field types section
- [ ] `docs/CRUD_Implementation_Guide.md` - Add HTML field examples

---

## 7. Dependencies

### 7.1 External Dependencies

**NPM Packages (Frontend)**
- `@tiptap/react` - Core TipTap React integration
- `@tiptap/pm` - ProseMirror dependencies
- `@tiptap/starter-kit` - Common extensions bundle
- `@tiptap/extension-image` - Image support
- `@tiptap/extension-link` - Link support
- `@tiptap/extension-placeholder` - Placeholder text
- `dompurify` - Client-side HTML sanitization
- `@types/dompurify` - TypeScript types for DOMPurify

**PHP Libraries (Backend)**
- Consider: `ezyang/htmlpurifier` - Robust PHP HTML sanitization
- Alternative: Custom sanitization using DOMDocument

### 7.2 Internal Dependencies

**Existing Gravitycar Components**
- `FieldBase` - Parent class for HTMLField
- `ValidationRuleBase` - Parent class for SafeHTMLValidation
- `FieldFactory` - Creates HTMLField instances
- `MetadataEngine` - Registers and discovers HTMLField
- `SchemaGenerator` - Maps HTMLField to database schema
- `ModelBase` - Uses HTMLField in models
- `FieldComponent.tsx` - Renders RichTextEditor
- Generic CRUD API - Handles HTML field save/retrieve

### 7.3 Framework Requirements

**Backend**
- PHP 8.1+
- Doctrine DBAL for database abstraction
- PSR-3 Logger (Monolog)
- Existing validation framework

**Frontend**
- React 19.x
- TypeScript 5.x
- Vite build system
- Tailwind CSS for styling

---

## 8. Risks and Mitigations

### Risk 1: XSS Vulnerabilities
**Impact**: High - Malicious HTML could execute scripts in user browsers  
**Probability**: Medium - HTML fields inherently risky if not sanitized  
**Mitigation**:
- Implement robust server-side sanitization (HTML Purifier or equivalent)
- Use whitelist approach for allowed tags/attributes
- Client-side sanitization with DOMPurify as first defense
- Regular security audits of sanitization logic
- Add automated tests for common XSS vectors
- Document security best practices for developers

### Risk 2: Bundle Size Impact
**Impact**: Medium - Large TipTap bundle could slow page loads  
**Probability**: Low - TipTap is relatively lightweight  
**Mitigation**:
- Use modular TipTap imports (only needed extensions)
- Implement code splitting to lazy-load editor
- Monitor bundle size in CI/CD pipeline
- Optimize TipTap configuration for minimal footprint
- Consider loading editor on field focus (lazy initialization)

### Risk 3: Browser Compatibility
**Impact**: Medium - Editor might not work in older browsers  
**Probability**: Low - TipTap supports modern browsers  
**Mitigation**:
- Test in all major browsers (Chrome, Firefox, Safari, Edge)
- Document minimum supported browser versions
- Provide fallback textarea for unsupported browsers
- Use polyfills for missing features (if needed)

### Risk 4: Image Upload Not Available Initially
**Impact**: Medium - Users limited to remote URLs for images  
**Probability**: High - File upload infrastructure does not exist in framework  
**Mitigation**:
- **Phase 1-3**: Implement URL-only image insertion (remote images)
- **Phase 4**: Implement complete file upload infrastructure as separate feature
- Document clearly that initial version is URL-only
- Provide clear path to adding upload support later
- Consider external image hosting services as interim solution
- URL insertion still provides significant value for many use cases

### Risk 5: File Upload Infrastructure Complexity
**Impact**: High - File upload is complex with security implications  
**Probability**: High - No existing upload infrastructure in framework  
**Mitigation** (if implementing file upload in Phase 4):
- Follow OWASP file upload security best practices
- Implement comprehensive validation (MIME type, size, content)
- Store files outside web root when possible
- Generate unique filenames to prevent conflicts
- Add rate limiting and quotas
- Thoroughly test upload endpoint security
- Document security considerations for developers

### Risk 6: Performance with Large Content
**Impact**: Medium - Editor sluggish with very large HTML documents  
**Probability**: Low - Most content under 100KB  
**Mitigation**:
- Set reasonable maxLength defaults (100KB for blogs)
- Warn users when approaching content limits
- Optimize TipTap configuration for performance
- Consider pagination for very long content
- Test with large documents during QA

### Risk 7: Inconsistent Styling
**Impact**: Low - Rendered HTML might not match editor preview  
**Probability**: Medium - CSS conflicts are common  
**Mitigation**:
- Scope editor styles with unique CSS classes
- Use Tailwind's @layer directive to manage specificity
- Provide preview mode that matches frontend rendering
- Document styling considerations for developers
- Test with various Tailwind configurations

### Risk 8: Mobile Usability Issues
**Impact**: Medium - Touch controls might be clunky  
**Probability**: Medium - Rich text editors often struggle on mobile  
**Mitigation**:
- Design mobile-first toolbar with touch-friendly buttons
- Test extensively on mobile devices (iOS, Android)
- Implement responsive toolbar (collapse to menu on small screens)
- Consider simplified mobile editor (fewer formatting options)
- Provide native keyboard for better UX

### Risk 9: Breaking Changes in Existing Fields
**Impact**: High - Could break existing Gravitycar functionality  
**Probability**: Very Low - HTMLField is additive, not modifying existing code  
**Mitigation**:
- Follow Gravitycar's established patterns exactly
- Avoid modifying FieldBase or core field classes
- Comprehensive testing of existing field types after integration
- Keep HTMLField implementation isolated
- Run full test suite before merging

### Risk 10: Schema Migration Issues
**Impact**: Medium - Database schema updates could fail  
**Probability**: Low - SchemaGenerator is robust  
**Mitigation**:
- Test schema generation thoroughly in development
- Backup database before running setup.php in production
- Add rollback capability for schema changes
- Document schema mapping logic clearly
- Test with various MySQL versions

### Risk 11: Content Loss During Editing
**Impact**: High - Users lose work if content not saved  
**Probability**: Medium - Browser crashes, network issues, etc.  
**Mitigation**:
- Implement auto-save functionality (save draft every 30 seconds)
- Use browser localStorage for unsaved changes backup
- Warn users before leaving page with unsaved changes
- Provide clear "Save Draft" and "Publish" buttons
- Show last saved timestamp

---

## 9. Timeline Estimates

### Summary by Phase

| Phase | Description | Estimated Time |
|-------|-------------|----------------|
| Phase 1 | Backend Foundation (PHP) | 15 hours |
| Phase 2 | Frontend Foundation (React) | 19.5 hours |
| Phase 3 | Integration & Documentation | 11 hours |
| Phase 4 | Advanced Features (Optional) | 26 hours |
| **Total** | **Core Implementation (URL images only)** | **45.5 hours** |
| **Total** | **With File Upload Support** | **60.5 hours** |
| **Total** | **With All Optional Features** | **71.5 hours** |

### Detailed Breakdown

**Phase 1: Backend Foundation** - 15 hours
- HTMLField class: 2 hours
- SafeHTMLValidation: 4 hours
- SchemaGenerator updates: 2 hours
- MetadataEngine verification: 1 hour
- Backend testing: 6 hours

**Phase 2: Frontend Foundation** - 19.5 hours
- TipTap installation: 1 hour
- RichTextEditor component: 6 hours
- Toolbar component: 4 hours
- Image URL support (no upload): 3 hours
- FieldComponent registration: 0.5 hours
- TypeScript types: 1 hour
- Frontend testing: 4 hours

**Phase 3: Integration & Documentation** - 11 hours
- Example Blog model: 2 hours
- Blog_Posts class: 2 hours
- End-to-end testing: 2 hours
- Documentation: 4 hours
- Copilot instructions update: 1 hour

**Phase 4: Advanced Features (Optional)** - 26 hours
- **File upload infrastructure: 12 hours** ⚠️
- Image upload integration: 3 hours
- Preview mode: 2 hours
- Markdown import/export: 3 hours
- Table support: 2 hours
- Word count: 1 hour
- Content templates: 3 hours

### Development Schedule

**Week 1: Backend & Core Frontend**
- Days 1-2: Backend implementation (HTMLField, SafeHTMLValidation)
- Days 3-5: Frontend implementation (RichTextEditor, Toolbar)

**Week 2: Integration & Polish**
- Days 1-2: Testing and bug fixes
- Days 3-4: Blog example and documentation
- Day 5: Code review and refinements

**Week 3: Advanced Features (Optional)**
- Days 1-5: Implement optional enhancements based on priority

---

## 10. Success Criteria

### 10.1 Functional Success Criteria

- [ ] HTMLField class successfully extends FieldBase
- [ ] SafeHTMLValidation removes all dangerous HTML
- [ ] SchemaGenerator creates appropriate MySQL TEXT columns
- [ ] RichTextEditor renders and functions in React frontend
- [ ] All toolbar formatting buttons work correctly
- [ ] Image insertion (URL and upload) works reliably
- [ ] Content saves to database and retrieves correctly
- [ ] Validation errors display properly in UI
- [ ] Read-only mode displays content without editor
- [ ] GenericCrudPage works with HTML fields (create/edit)

### 10.2 Security Success Criteria

- [ ] All XSS test vectors are blocked
- [ ] Sanitization whitelist properly configured
- [ ] Client and server-side sanitization in place
- [ ] No JavaScript execution from user content
- [ ] No CSS injection vulnerabilities
- [ ] Image URLs restricted to safe protocols

### 10.3 Performance Success Criteria

- [ ] TipTap bundle size under 150KB (gzipped)
- [ ] Editor initialization under 200ms
- [ ] No noticeable lag with 50KB+ content
- [ ] Page load time increase < 100ms with editor
- [ ] No memory leaks in editor component

### 10.4 Usability Success Criteria

- [ ] Editor intuitive for non-technical users
- [ ] Toolbar icons clear and recognizable
- [ ] Mobile editing functional on phones/tablets
- [ ] Keyboard shortcuts work as expected
- [ ] Error messages clear and actionable
- [ ] Responsive design works on all screen sizes

### 10.5 Code Quality Success Criteria

- [ ] All PHPUnit tests pass (100% pass rate)
- [ ] Code follows Gravitycar conventions
- [ ] TypeScript strict mode enabled, no errors
- [ ] ESLint passes with no warnings
- [ ] Code coverage > 80% for new code
- [ ] Documentation complete and accurate

### 10.6 Integration Success Criteria

- [ ] No breaking changes to existing fields
- [ ] Metadata cache includes HTMLField type
- [ ] FieldFactory creates HTMLField instances
- [ ] API endpoints handle HTML content correctly
- [ ] OpenAPI documentation updated automatically
- [ ] Example Blog model works end-to-end

---

## 11. Future Enhancements

### Potential Future Work (Beyond Initial Implementation)

1. **Collaborative Editing**
   - Real-time collaboration using WebSockets
   - See other users' cursors and edits
   - Conflict resolution for simultaneous edits

2. **Version Control**
   - Track content revisions
   - Diff viewer for comparing versions
   - Rollback to previous versions

3. **Content Localization**
   - Multi-language content support
   - Translation workflow integration
   - Language-specific formatting rules

4. **Advanced Media Handling**
   - Video embeds (YouTube, Vimeo)
   - Audio embeds
   - File attachments
   - Gallery/slideshow support

5. **AI-Powered Features**
   - Grammar and style checking
   - Content suggestions
   - Auto-summarization
   - SEO optimization hints

6. **Accessibility Enhancements**
   - Screen reader optimization
   - Keyboard-only navigation mode
   - High contrast themes
   - Focus indicators

7. **Custom Extensions**
   - Plugin system for custom TipTap extensions
   - Per-model editor configuration
   - Industry-specific formatting (legal, scientific)

8. **Content Import/Export**
   - Import from Word documents
   - Export to PDF
   - Markdown round-trip support
   - HTML email templates

9. **Analytics & Insights**
   - Reading time estimation
   - Readability scoring
   - SEO keyword density
   - Content performance metrics

10. **Advanced Formatting**
    - Code syntax highlighting
    - Math equations (LaTeX)
    - Footnotes and citations
    - Collapsible sections

---

## 12. Notes and Considerations

### Design Decisions

**Why TipTap over alternatives?**
- Modern, React-first architecture (not wrapper around legacy editor)
- Headless design allows full styling control with Tailwind
- Modular bundle size (only include needed extensions)
- Active development and strong community
- Excellent TypeScript support
- ProseMirror foundation (battle-tested, used by NYT, Atlassian, etc.)

**Why server-side sanitization is critical?**
- Client-side sanitization can be bypassed via API
- Defense in depth: sanitize at every entry point
- Trust no user input, even from authenticated users
- Regulatory compliance (GDPR, CCPA) may require server-side validation

**Why TEXT/MEDIUMTEXT instead of VARCHAR?**
- Rich HTML content typically exceeds VARCHAR(255) limit
- TEXT type more appropriate for content (not indexed by default)
- Allows for flexibility in content length
- Better performance for large text blobs

**Why not use Markdown instead of HTML?**
- HTML provides richer formatting options
- WYSIWYG editing more intuitive for non-technical users
- Easier image embedding and styling
- Better compatibility with existing web content
- Can always add Markdown import/export later

### Technical Debt to Avoid

- **Don't**: Create separate HTML sanitization logic in multiple places
  - **Do**: Centralize in SafeHTMLValidation, reuse everywhere
- **Don't**: Hardcode allowed tags in component code
  - **Do**: Make whitelist configurable via metadata
- **Don't**: Mix editor logic with field component logic
  - **Do**: Keep RichTextEditor pure, composable
- **Don't**: Skip server-side validation assuming client-side is enough
  - **Do**: Always validate and sanitize on backend
- **Don't**: Load entire TipTap bundle on every page
  - **Do**: Code-split and lazy-load where possible

### Accessibility Considerations

- Editor must be fully keyboard navigable (no mouse required)
- All toolbar buttons need ARIA labels
- Screen reader announcements for formatting changes
- Focus indicators clearly visible
- Sufficient color contrast for buttons and text
- Alt text required for images
- Test with NVDA, JAWS, VoiceOver

### Internationalization (i18n)

- Toolbar button labels should be translatable
- Error messages should support multiple languages
- RTL (right-to-left) language support
- Date/time formatting based on locale
- Consider TipTap i18n extensions for future

### Monitoring and Logging

- Log all sanitization actions (security audit trail)
- Track editor load times (performance monitoring)
- Log validation failures (debugging)
- Monitor image upload success/failure rates
- Track feature usage (which formatting tools used most)

---

## 13. Approval and Sign-off

### Stakeholders

- **Developer**: [Your Name] - Implementation
- **Reviewer**: [Reviewer Name] - Code review and approval
- **QA**: [QA Name] - Testing and validation
- **Security**: [Security Name] - Security review

### Review Checklist

- [ ] Architecture design reviewed and approved
- [ ] Security considerations reviewed and approved
- [ ] Performance impact assessed and acceptable
- [ ] Testing strategy reviewed and comprehensive
- [ ] Documentation plan reviewed and sufficient
- [ ] Timeline estimates reviewed and reasonable
- [ ] Risk mitigation strategies reviewed and adequate

### Approval Status

- **Plan Status**: Draft - Awaiting review
- **Date Created**: November 19, 2025
- **Last Updated**: November 19, 2025
- **Approved By**: Pending
- **Approval Date**: Pending

---

## 14. Appendices

### Appendix A: TipTap Extensions Reference

**Core Extensions (StarterKit)**
- Bold, Italic, Underline, Strike
- Code, CodeBlock
- Heading (H1-H6)
- Paragraph, HardBreak
- BulletList, OrderedList, ListItem
- Blockquote, HorizontalRule
- History (Undo/Redo)
- Gapcursor, Dropcursor

**Additional Extensions to Install**
- Image - Image insertion and display
- Link - Hyperlink support
- Placeholder - Placeholder text
- TextAlign - Text alignment (left, center, right, justify)
- Table - Table support (optional)
- Collaboration - Real-time collaboration (future)

### Appendix B: HTML Sanitization Whitelist

**Allowed Tags**
```
p, br, strong, em, u, s, 
h1, h2, h3, h4, h5, h6,
ul, ol, li, 
blockquote, code, pre,
a, img, hr,
div, span
```

**Allowed Attributes by Tag**
- `a`: href, title, target
- `img`: src, alt, width, height, title
- `div`, `span`, `p`: class, id (for styling)
- All: style (limited to safe CSS properties)

**Forbidden Tags**
```
script, iframe, object, embed, applet,
form, input, button, select, textarea,
style, link, meta, base
```

**Forbidden Attributes**
```
on* (onclick, onerror, onload, etc.)
```

**Forbidden URL Schemes**
```
javascript:, data:, vbscript:, file:
```

### Appendix C: Database Column Sizing

**MySQL TEXT Type Limits**
- TINYTEXT: 255 bytes (not used)
- TEXT: 65,535 bytes (~64KB) - Default for HTMLField
- MEDIUMTEXT: 16,777,215 bytes (~16MB) - For articles/long content
- LONGTEXT: 4,294,967,295 bytes (~4GB) - For very long content

**Recommended maxLength Values**
- Comments: 5,000 (TEXT)
- Blog posts: 100,000 (MEDIUMTEXT)
- Articles: 500,000 (MEDIUMTEXT)
- Documentation: 1,000,000 (MEDIUMTEXT)
- Books: 5,000,000 (LONGTEXT)

### Appendix D: References and Resources

**TipTap Documentation**
- https://tiptap.dev/docs/editor/introduction
- https://tiptap.dev/docs/editor/extensions
- https://tiptap.dev/docs/editor/api

**HTML Sanitization**
- DOMPurify: https://github.com/cure53/DOMPurify
- HTML Purifier: http://htmlpurifier.org/

**React Best Practices**
- React 19 Docs: https://react.dev/
- TypeScript Handbook: https://www.typescriptlang.org/docs/

**Security Resources**
- OWASP XSS Prevention: https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html
- Content Security Policy: https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP

---

## Revision History

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-11-19 | 1.0 | AI Assistant | Initial implementation plan created |

---

**End of Implementation Plan**
