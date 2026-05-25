# Web Research: Projects Model

## Search Terms Used

- portfolio grid layout CSS Grid image overlay text Tailwind CSS 2025 best practices
- Tailwind CSS image overlay text superimposed on image tile hover effect 2025
- Tailwind CSS fixed width tile card 400px image overlay gradient text positioning 2024 2025
- portfolio grid tile always visible overlay title tagline bottom gradient CSS not on hover
- React portfolio grid card fixed height title top tagline bottom absolute positioning always visible 2024
- React modal overlay grid portfolio detail view 2025 accessibility best practices
- React accessible modal dialog keyboard focus trap close on escape pattern without library 2025
- Tailwind CSS modal backdrop z-index fixed inset-0 scroll lock pattern 2025
- URL href field type web framework validation display link noopener noreferrer 2025
- image as conditional link React wrapper component href optional accessible pattern 2024
- PHP URL field type filter_var FILTER_VALIDATE_URL optional field validation best practice 2024
- ACF URL field WordPress validation pattern PHP extensible field type 2024
- PHP framework extensible field type metadata-driven URL link field implementation pattern

---

## Key Findings

### 1. Portfolio Grid Layout with Tailwind CSS

**Summary**: A 2-column fixed-tile grid is straightforward with Tailwind. Use `grid grid-cols-2 gap-4` for the outer container. Each tile should use `w-full h-[300px]` (or `h-72` for 288px ≈ close enough) with `relative overflow-hidden rounded-lg` to contain the layered image + overlay. For exact 400px widths, Tailwind supports arbitrary values: `w-[400px]`.

**Key Classes**:
- Grid container: `grid grid-cols-2 gap-4`
- Tile container: `relative overflow-hidden rounded-lg cursor-pointer group`
- Image (fills tile): `w-full h-full object-cover`
- Always-visible bottom gradient overlay: `absolute inset-0 bg-gradient-to-t from-black/80 to-transparent`
- Title (top): `absolute top-0 left-0 right-0 p-3 text-white text-lg font-bold`
- Tagline (bottom): `absolute bottom-0 left-0 right-0 p-3 text-white text-sm`

**CSS Grid alternative**: CSS-Tricks describes using `grid-template-areas` where all children share the same named area, then `place-self` to position title to `start` and tagline to `end`. This avoids absolute positioning entirely and is direction-aware.

**Sources**:
- [Building a Responsive Grid Gallery with Tailwind and React](https://tryhoverify.com/blog/building-a-responsive-grid-gallery-with-tailwind-and-react/)
- [Building an Image Card with Hover Overlay | Steve Kinney](https://stevekinney.com/courses/tailwind/building-an-image-card-hover-overlay)
- [Image card with overlay text overlay - Tailwindflex](https://tailwindflex.com/@santos78/image-card-with-overlay)
- [Positioning Overlay Content with CSS Grid | CSS-Tricks](https://css-tricks.com/positioning-overlay-content-with-css-grid/)
- [Smarative: Image Background and Overlay Gradient using Tailwind](https://smarative.com/blog/how-to-apply-image-background-and-overlay-gradient-using-tailwind-css)

**Recommendation**: Use the absolute positioning approach (simpler, more explicit). The outer tile div uses `relative`, the image is `absolute inset-0 w-full h-full object-cover`, a gradient overlay is `absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-black/50`, and text layers sit on top as `absolute top-3 left-3` (title) and `absolute bottom-3 left-3` (tagline). Both are always visible, not just on hover. Add `group` + `group-hover:scale-105 transition-transform duration-300` on the image for an engaging hover effect.

---

### 2. Always-Visible Text Over Image (Not Hover-Only)

**Summary**: The spec requires title across the top and tagline across the bottom — always visible, not revealed on hover. This differs from the common "hover overlay" pattern. The approach is simple: use a persistent gradient overlay (no opacity-0 default) with the text positioned at top and bottom.

**Pattern**:
```jsx
<div className="relative w-full h-[300px] overflow-hidden rounded-lg cursor-pointer group">
  {/* Image */}
  <img
    src={project.screenshot}
    alt={project.title}
    className="absolute inset-0 w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
  />
  {/* Gradient: dark at top and bottom, transparent in middle */}
  <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-transparent to-black/70 pointer-events-none" />
  {/* Title: top */}
  <div className="absolute top-0 left-0 right-0 p-3">
    <h3 className="text-white text-xl font-bold drop-shadow-md line-clamp-2">{project.title}</h3>
  </div>
  {/* Tagline: bottom */}
  <div className="absolute bottom-0 left-0 right-0 p-3">
    <p className="text-white text-sm drop-shadow-md line-clamp-2">{project.tag_line}</p>
  </div>
</div>
```

**Key notes**:
- `drop-shadow-md` improves text legibility when the image has light areas
- `line-clamp-2` truncates long text gracefully (requires `@tailwindcss/line-clamp` plugin or Tailwind v3.3+)
- `pointer-events-none` on the gradient overlay prevents it from blocking click events
- The gradient uses `bg-gradient-to-b` (top-to-bottom) with dark at both ends: `from-black/60 via-transparent to-black/70`

**Sources**:
- [How to add a subtle gradient on top of an image using CSS | theburningmonk.com](https://theburningmonk.com/2022/07/how-to-add-a-subtle-gradient-effect-on-top-of-video/)
- [Portfolio grid – gradient overlay](https://www.devplusteam.com/portfolio/portfolio-grid/7-portfolio-grid-gradient-overlay/)

---

### 3. React Modal / Detail View Pattern

**Summary**: The existing `Modal.tsx` component in the project can be reused or extended. For a custom Project Detail modal, the pattern is: fixed full-viewport backdrop (`fixed inset-0 bg-black/50 z-50`) with centered content card (`relative bg-white rounded-xl max-w-2xl w-full mx-4 overflow-y-auto max-h-[90vh]`).

**Accessibility requirements**:
1. `role="dialog"` + `aria-modal="true"` + `aria-labelledby` pointing to the title
2. Close on Escape key: `useEffect` with `keydown` listener checking `e.key === 'Escape'`
3. Focus trap: focus moves into modal on open, returns to trigger on close
4. Backdrop click closes the modal (click on `fixed inset-0` div, not the content card)
5. Close button: at least 44x44px, `aria-label="Close"`, positioned `absolute top-3 right-3`
6. While modal is open: set `overflow: hidden` on `document.body` to prevent background scroll

**Tailwind classes for modal structure**:
```jsx
{/* Backdrop */}
<div
  className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
  onClick={onClose}
>
  {/* Modal content card */}
  <div
    role="dialog"
    aria-modal="true"
    aria-labelledby="project-title"
    className="relative bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
    onClick={e => e.stopPropagation()}
  >
    {/* Close button */}
    <button
      onClick={onClose}
      className="absolute top-3 right-3 w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600"
      aria-label="Close"
    >
      ×
    </button>
    {/* Content */}
  </div>
</div>
```

**Sources**:
- [Building the Perfect React Modal: From Portals to Accessibility | Medium](https://medium.com/@dlrnjstjs/building-the-perfect-react-modal-from-portals-to-accessibility-a567006ae169)
- [How to Build an Accessible Modal (Dialog) in React | Medium](https://medium.com/@katr.zaks/how-to-build-an-accessible-modal-dialog-in-react-7ac85cb87119)
- [How to Build Accessible Modals with Focus Traps (2026 Guide) | UXPin](https://www.uxpin.com/studio/blog/how-to-build-accessible-modals-with-focus-traps/)
- [Modals and accessibility - DEV Community](https://dev.to/miasalazar/modals-and-accessibility-111e)
- [Tailwind CSS Modal | Flowbite](https://flowbite.com/docs/components/modal/)

**Recommendation**: Reuse the existing `Modal.tsx` component where possible, but since the Project Detail view has a distinct layout (image-dominant, specific field arrangement), a dedicated `ProjectDetailModal.tsx` is cleaner. The close button (small X, top-right) matches the spec. Use `useEffect` to lock body scroll when open and restore on close.

---

### 4. URL / Link Field Type

**Summary**: Multiple frameworks implement URL/link fields as a dedicated text input that stores a URL string. The standard approach in PHP (Symfony, ACF, Gravitycar pattern) is:
- Backend: store the URL as a VARCHAR(256) string
- Validation: use `filter_var($value, FILTER_VALIDATE_URL)` for PHP validation; allow empty for optional fields (`if (empty($value) || filter_var($value, FILTER_VALIDATE_URL))`)
- Frontend: `<input type="url">` for edit mode; `<a href={value} target="_blank" rel="noopener noreferrer">` for display mode
- Security: always add `rel="noopener noreferrer"` to external links opened in a new tab

**Symfony UrlType** (closest comparable):
- Renders as `<input type="url">` when `default_protocol` is null (browser validates)
- Trims whitespace automatically
- Has `invalid_message` override: "Please enter a valid URL"

**ACF URL field** (WordPress):
- Validates format only (checks for `://` or starts with `//`)
- Returns plain string value; developer must wrap in `<a href>` with `esc_attr()` for output
- Has a required setting; optional by default

**PHP validation for optional URL field**:
```php
// In LinkField or validation rule:
if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
    throw new InvalidFieldValueException("Link must be a valid URL.");
}
// Also check scheme to prevent XSS:
$scheme = parse_url($value, PHP_URL_SCHEME);
if (!in_array($scheme, ['http', 'https'])) {
    throw new InvalidFieldValueException("Link must use http or https.");
}
```

**Sources**:
- [UrlType Field (Symfony Docs)](https://symfony.com/doc/current/reference/forms/types/url.html)
- [ACF | URL - Advanced Custom Fields](https://www.advancedcustomfields.com/resources/url/)
- [rel="noopener noreferrer" | MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/rel/noopener)
- [PHP FILTER_VALIDATE_URL | W3Schools](https://www.w3schools.com/php/filter_validate_url.asp)
- [PHP Filter FILTER_VALIDATE_URL Limitations | #! code](https://www.hashbangcode.com/article/php-filter-filtervalidateurl-limitations)

**Recommendation for LinkField.php**:
- `$type = 'Link'`
- `$reactComponent = 'LinkInput'`
- `$maxLength = 256`
- `$placeholder = 'https://...'`
- `$required = false` (the link field is optional in the Projects spec)
- Validation: allow empty OR valid URL with http/https scheme
- The Gravitycar framework auto-discovers field types by scanning `src/Fields/`, so no manual registration is needed

---

### 5. Image-as-Conditional-Link Pattern (React)

**Summary**: When a link is optional, the best accessible approach is to conditionally render either an `<a>` wrapper or no wrapper (not an `<a>` with empty `href`). A `ConditionalWrapper` component is the cleanest reusable pattern.

**ConditionalWrapper pattern**:
```tsx
const ConditionalWrapper = ({
  condition,
  wrapper,
  children,
}: {
  condition: boolean;
  wrapper: (children: React.ReactNode) => React.ReactNode;
  children: React.ReactNode;
}) => (condition ? wrapper(children) : <>{children}</>);
```

**Usage in ProjectDetailModal**:
```tsx
<ConditionalWrapper
  condition={!!project.link}
  wrapper={(children) => (
    <a
      href={project.link}
      target="_blank"
      rel="noopener noreferrer"
      className="block"
    >
      {children}
    </a>
  )}
>
  <img
    src={project.screenshot}
    alt={project.title}
    className="w-full rounded-lg object-cover"
  />
</ConditionalWrapper>
```

**Alternative** (simpler, no separate component):
```tsx
{project.link ? (
  <a href={project.link} target="_blank" rel="noopener noreferrer">
    <img src={project.screenshot} alt={project.title} className="w-full rounded-lg" />
  </a>
) : (
  <img src={project.screenshot} alt={project.title} className="w-full rounded-lg" />
)}
```

**Accessibility note**: An `<a>` without `href` is not focusable and not part of the tab order, so conditionally omitting the `<a>` tag when there is no link is the correct approach — not rendering `<a href="">`.

**Sources**:
- [Conditional wrapping in React - DEV Community](https://dev.to/dailydevtips1/conditional-wrapping-in-react-46o5)
- [How to Render `<a>` with Optional href in React | Pluralsight](https://www.pluralsight.com/resources/blog/guides/how-to-render-a-with-optional-href-in-react)
- [How to Use an Image as a Link in React | bobbyhadz](https://bobbyhadz.com/blog/react-image-link)

**Recommendation**: Use the simple ternary approach for the ProjectDetailModal (it's the only usage site). Define `ConditionalWrapper` as a utility component only if the pattern is needed in 2+ places.

---

### 6. GenericCrudPage Link Field Rendering

**Summary**: The existing `renderFieldValue()` in `GenericCrudPage.tsx` already has cases for Image, Boolean, DateTime, Email, Video. A `Link` case needs to be added that renders as a clickable anchor.

**Pattern to add**:
```tsx
case 'Link':
  return stringValue ? (
    <a
      href={stringValue}
      target="_blank"
      rel="noopener noreferrer"
      className="text-blue-600 hover:text-blue-800 underline break-all"
    >
      {stringValue}
    </a>
  ) : null;
```

**Why `break-all`**: Long URLs in table cells can overflow. `break-all` forces the URL to wrap within the cell width.

**Sources**:
- [All You Need to Know About rel="noopener noreferrer"](https://linkbuilder.io/rel-noopener-noreferrer/)
- [What Does rel="noopener noreferrer" Mean for a Link?](https://respona.com/blog/noopener-noreferrer/)

---

## Recommended Approaches

### Grid Layout
Use `grid grid-cols-2 gap-6` for the 2-column tile grid. Each tile is a `relative` container with `h-[300px] overflow-hidden rounded-xl cursor-pointer shadow-md hover:shadow-xl transition-shadow`. The tile's image, gradient overlay, and text layers all use `absolute` positioning or CSS Grid `place-self`. **Always-visible** title (top) and tagline (bottom) use a persistent gradient — no hover-only opacity trick.

### Modal Detail View
Build `ProjectDetailModal.tsx` as a standalone component (not reusing `Modal.tsx`) because the layout is image-dominant and does not match the generic modal's form-based layout. Use `fixed inset-0 bg-black/50 z-50` for backdrop, `stopPropagation` on the content card, Escape key listener in `useEffect`, and body scroll lock.

### LinkField (new field type)
Follow the existing `VideoField` → `VideoInput` pattern:
1. `src/Fields/LinkField.php` — `$type = 'Link'`, `$reactComponent = 'LinkInput'`, optional, max 256 chars, validate `http`/`https` schemes
2. `gravitycar-frontend/src/components/fields/LinkInput.tsx` — `<input type="url">` in edit mode, `<a href target="_blank" rel="noopener noreferrer">` in read-only mode
3. Register in `FieldComponent.tsx` componentMap
4. Add `case 'Link':` to `GenericCrudPage.tsx` `renderFieldValue()`

### Image-as-Optional-Link
Use simple ternary conditional rendering. When `project.link` is non-empty, wrap image in `<a href={link} target="_blank" rel="noopener noreferrer">`. When empty, render just the `<img>`. Do not render `<a href="">`.

---

## Potential Pitfalls

1. **Always-visible overlay vs. hover-only**: The spec says title/tagline are always visible (not revealed on hover). Using the common `opacity-0 group-hover:opacity-100` pattern would be wrong. The overlay must be persistent.

2. **Gradient readability**: A gradient that is too dark obscures the screenshot image, which is the primary visual. Use `from-black/60` at edges, transparent in the middle, to preserve image visibility while making text legible.

3. **Text overflow on tiles**: Long project titles or taglines will overflow their bounds on a ~400px tile. Use `line-clamp-2` to cap at 2 lines and add `overflow-hidden`.

4. **URL validation XSS risk**: `filter_var($value, FILTER_VALIDATE_URL)` accepts data URIs and other schemes (e.g. `javascript:`). Always additionally validate the scheme: `in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'])`.

5. **Empty href on anchor**: Rendering `<a href="">` makes a clickable non-navigation link. Always conditionally render the `<a>` wrapper itself, not just the `href` attribute.

6. **Modal scroll lock**: Without `document.body.style.overflow = 'hidden'` while the modal is open, the user can scroll the background list behind the modal. Add this in the `useEffect` open handler and restore on close.

7. **Focus management in modal**: After opening, focus should move to the modal (first interactive element or the modal container). After closing, focus should return to the tile that was clicked. Use `useRef` to track the opener element.

8. **`noopener noreferrer` analytics**: Using `noreferrer` removes referral data in analytics. If tracking project link clicks matters, use only `noopener` and not `noreferrer`. For a personal portfolio, this is acceptable.

9. **Fixed tile height with long titles**: `h-[300px]` is a hard constraint — very long titles will be cut off by `line-clamp-2`. This is a UX trade-off inherent to the fixed-tile design. The full title is always shown in the detail modal.

---

## Libraries/Services to Consider

- **None required**: All patterns are achievable with Tailwind CSS utilities only, React state/hooks, and no external libraries. This matches the project's no-component-library constraint.

- **`@tailwindcss/line-clamp`**: Provides `line-clamp-{n}` utilities for clamping text to N lines. This is built into Tailwind CSS v3.3+ (no plugin needed if using v3.3+). Check which Tailwind version the project uses.

- **`filter_var` (PHP built-in)**: For URL validation in `LinkField.php` — no library needed.

- **HTML `<dialog>` element**: The native dialog element provides built-in focus trapping and Escape handling for free. However, the existing project uses a custom `Modal.tsx` component, so continuing that pattern is recommended for consistency.
