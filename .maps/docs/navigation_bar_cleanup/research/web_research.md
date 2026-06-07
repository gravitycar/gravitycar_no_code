# Web Research: Navigation Bar Cleanup

## Search Terms Used

- "React Tailwind CSS collapsible sidebar navigation accordion no UI library 2024 2025"
- "ARIA accessible expandable navigation menu aria-expanded aria-controls sidebar React"
- "React TypeScript collapsible sidebar menu useState CSS transition Tailwind functional component"
- "HTML details summary element sidebar navigation accessibility React alternative to useState accordion"
- "WAI-ARIA sidebar navigation tree role treeitem expanded keyboard accessible best practices"
- "WAI-ARIA disclosure navigation pattern sidebar button aria-expanded vs tree treeitem"
- "PHP metadata-driven navigation grouping structured cache array nav_group hierarchical sidebar"
- "Tailwind CSS sidebar nav group header collapsible submenu chevron rotate transition React no library"

---

## Key Findings

### 1. Disclosure Navigation Pattern — The Right ARIA Pattern for This Project

**Summary:** The W3C WAI-ARIA Authoring Practices Guide (APG) distinguishes between the **Disclosure (Show/Hide) pattern** and the full **Tree/Treeitem pattern** for sidebar navigation. For a typical site sidebar with expandable groups of links, **the Disclosure pattern is recommended** because the Tree pattern requires complex keyboard interactions (arrow key navigation, type-ahead, roving tabindex) that are unnecessary for simple expandable nav sections.

**Sources:**
- [Disclosure Navigation Menu Example | APG | WAI | W3C](https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/examples/disclosure-navigation/)
- [Tree View Pattern | APG | WAI | W3C](https://www.w3.org/WAI/ARIA/apg/patterns/treeview/)

**Recommendation:** Use the Disclosure pattern. Each expandable group header is a `<button>` with `aria-expanded` and `aria-controls`. The sub-item list is a `<ul>` with a matching `id`. Do NOT use `role="tree"` / `role="treeitem"` — this is overkill for a nav sidebar and requires complex keyboard management.

---

### 2. ARIA Disclosure Pattern — Correct Attribute Usage

**Summary:** The canonical disclosure navigation pattern works like this:

```html
<nav>
  <ul>
    <li>
      <button aria-expanded="false" aria-controls="event-submenu">
        Event Organizer
      </button>
      <ul id="event-submenu">
        <li><a href="/Events">Events</a></li>
        <li><a href="/EventCommitments">Event Commitments</a></li>
      </ul>
    </li>
    <li>
      <a href="/Books" aria-current="page">Books</a>
    </li>
  </ul>
</nav>
```

Key rules:
- `aria-expanded="true"` when the submenu is open, `"false"` when closed
- `aria-controls` points to the `id` of the controlled submenu element
- `aria-current="page"` marks the currently active link
- Do NOT use `aria-haspopup` (it implies a popup widget, not a disclosure)
- Use CSS `[aria-expanded="false"] + ul { display: none }` or `hidden` attribute to hide sub-items
- Escape key should close an open group and return focus to the button

**Sources:**
- [aria-expanded attribute — MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Attributes/aria-expanded)
- [Practical Guide on aria-expanded — The A11Y Collective](https://www.a11y-collective.com/blog/aria-expanded/)
- [Marking elements expandable using aria-expanded — ADG](https://www.accessibility-developer-guide.com/examples/sensible-aria-usage/expanded/)

**Recommendation:** The button controlling expansion must be the group label element itself. Avoid `aria-haspopup`. Visual show/hide state should be driven by CSS attribute selectors on `aria-expanded` so the visual state always matches the accessibility state.

---

### 3. React + TypeScript Implementation Pattern for Collapsible Sidebar Groups

**Summary:** The recommended pattern for a collapsible group in a React + Tailwind sidebar (no external UI library) uses:

1. **Per-group `useState`** to track open/closed state
2. **`max-h-0` / `max-h-screen` trick** with `overflow-hidden transition-all` for animated reveal
3. **Chevron rotation** via `rotate-90` class conditioned on state
4. **Recursive or two-level component** for group header + sub-items

**Core pattern (TypeScript):**

```tsx
// Types
interface NavSubItem {
  name: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavAction[];
}

interface NavGroup {
  groupLabel: string;
  items: NavSubItem[];
}

interface NavItem {
  name: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavAction[];
  group?: string; // present only when item belongs to a group
}

// Group component
interface NavGroupProps {
  label: string;
  items: NavSubItem[];
}

const NavGroupSection: React.FC<NavGroupProps> = ({ label, items }) => {
  const [isOpen, setIsOpen] = useState(false);
  const contentId = `nav-group-${label.replace(/\s+/g, '-').toLowerCase()}`;

  return (
    <li>
      <button
        aria-expanded={isOpen}
        aria-controls={contentId}
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center w-full px-3 py-2 text-sm font-medium rounded-md hover:bg-gray-100"
      >
        <span className="flex-1 text-left">{label}</span>
        <svg
          className={`w-4 h-4 transition-transform duration-200 ${isOpen ? 'rotate-90' : ''}`}
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
        </svg>
      </button>
      <ul
        id={contentId}
        className={`ml-4 overflow-hidden transition-all duration-300 ${isOpen ? 'max-h-screen' : 'max-h-0'}`}
      >
        {items.map(item => (
          <li key={item.name}>
            <a href={item.url} className="block px-3 py-1.5 text-sm rounded-md hover:bg-gray-100">
              {item.title}
            </a>
          </li>
        ))}
      </ul>
    </li>
  );
};
```

**Sources:**
- [How to Create a Collapsible Sidebar in React/NextJS using TailwindCSS — ReactHustle](https://reacthustle.com/blog/nextjs-react-responsive-collapsible-sidebar-tailwind)
- [Create a Collapsible Sidebar Menu with React, Tailwind CSS, and Next.js — CodingEasyPeasy](https://www.codingeasypeasy.com/blog/create-a-collapsible-sidebar-menu-with-react-tailwind-css-and-nextjs)
- [Building a Responsive Sidebar with sub menu in ReactTS & Tailwind CSS — Medium](https://medium.com/@muhammadbinkashif7/building-a-responsive-sidebar-with-typescript-reactjs-and-tailwind-css-084b647ced9d)
- [Expand the Content Inclusively — DEV Community](https://dev.to/eevajonnapanula/expand-the-content-inclusively-building-an-accessible-accordion-with-react-2ded)

**Recommendation:** Use `useState` per group with the `max-h-0`/`max-h-screen` trick. This avoids any JS height calculations and works purely with Tailwind utility classes. The `transition-all duration-300` class gives a smooth expand/collapse animation. Pair with proper ARIA attributes for accessibility.

---

### 4. `<details>` / `<summary>` as an Alternative

**Summary:** Native HTML `<details>` and `<summary>` elements provide zero-JavaScript accordion functionality. Browser handles all toggle state, keyboard interaction, and ARIA semantics automatically. As of 2025-2026, animated `<details>` with CSS `transition-behavior: allow-discrete` is supported in Chrome, Firefox, and Safari.

**When to use `<details>`/`<summary>`:**
- Simpler to implement (no useState, no ARIA attributes needed)
- Browser-native keyboard support (Enter/Space to toggle)
- Acceptable for simple use cases

**Drawbacks for this project:**
- Less control over animation (CSS height transition needs extra work)
- Styling the summary marker is inconsistent across browsers
- Less flexible for custom icons/chevrons integrated with Tailwind classes
- Cannot easily control which groups start open/closed based on URL matching

**Sources:**
- [Accessible accordions using details and summary — Hassell Inclusion](https://www.hassellinclusion.com/blog/accessible-accordions-part-2-using-details-summary/)
- [details HTML element — MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/details)
- [Animated Accordions with Details Element & CSS — Builder.io](https://www.builder.io/blog/animated-css-accordions)

**Recommendation:** Stick with `useState` + `<button>` for this project. The existing sidebar already uses React state for other expand/collapse behavior (actions sub-menu), and Tailwind transitions are cleaner with explicit state. `<details>` is viable but harder to style consistently.

---

### 5. PHP Metadata-Driven Navigation Grouping — Cache Array Structure

**Summary:** The Filament PHP framework (a leading PHP admin panel) is the closest reference project to Gravitycar's metadata-driven pattern. Filament's navigation grouping approach:

- Each resource class has a static property `protected static ?string $navigationGroup = 'Settings';`
- The framework collects all resources, reads their `$navigationGroup`, and groups them when building the nav array
- The resulting nav structure uses `NavigationGroup` objects with a `label` and an `items()` array
- Groups can be collapsed by default via `->collapsed()`

**For Gravitycar's PHP cache, the two structural options for `models` in the cache are:**

**Option A — Flat array with `group` field (frontend groups on render):**
```php
'models' => [
    ['name' => 'Events', 'title' => 'Events', 'url' => '/Events', 'group' => 'Event Organizer', ...],
    ['name' => 'EventCommitments', 'title' => 'Event Commitments', 'url' => '/EventCommitments', 'group' => 'Event Organizer', ...],
    ['name' => 'Books', 'title' => 'Books', 'url' => '/Books', 'group' => null, ...],
]
```
- Pros: Backward compatible; cache structure unchanged
- Cons: Frontend must group before rendering; sorting/ordering harder

**Option B — Pre-grouped nested array (backend groups, frontend renders directly):**
```php
'models' => [
    [
        'type' => 'group',
        'label' => 'Event Organizer',
        'items' => [
            ['name' => 'Events', 'title' => 'Events', 'url' => '/Events', ...],
            ['name' => 'EventCommitments', 'title' => 'Event Commitments', ...],
        ]
    ],
    [
        'type' => 'item',
        'name' => 'Books', 'title' => 'Books', 'url' => '/Books', ...
    ],
]
```
- Pros: Frontend render is simpler and more direct; group ordering is explicit
- Cons: Breaking change to cache schema; TypeScript types must be updated

**Sources:**
- [Filament PHP Navigation — Navigation Groups](https://filamentphp.com/docs/2.x/admin/navigation)
- [Filament Navigation Groups tutorial — FilamentExamples](https://filamentexamples.com/tutorial/navigation-group-customization-main-things-to-know)
- [Dynamic Navigation Tree using PHP Multi-Level Array — SourceCodester](https://www.sourcecodester.com/php/15830/dynamic-navigation-tree-using-php-multi-level-array-source-code.html)

**Recommendation:** Option B (pre-grouped nested array) is preferred. The backend knows the grouping at cache-build time and the cache is rebuilt on demand, not on every page load. A pre-grouped structure makes the React rendering straightforward: iterate over `models`, check `type === 'group'` vs `type === 'item'`, and render accordingly. The TypeScript discriminated union type for this is clean:

```typescript
interface NavModelItem {
  type: 'item';
  name: string;
  title: string;
  url: string;
  icon?: string;
  actions?: NavAction[];
  permissions?: Record<string, boolean>;
}

interface NavModelGroup {
  type: 'group';
  label: string;
  items: NavModelItem[];
}

type NavModelEntry = NavModelItem | NavModelGroup;
```

---

### 6. Frontend Rendering Strategy — Discriminated Union Pattern

**Summary:** Using a TypeScript discriminated union (`type: 'group' | 'item'`) is the idiomatic React + TypeScript approach for heterogeneous lists that render differently. React's `Array.map()` with a type guard (`entry.type === 'group'`) cleanly separates group rendering from leaf item rendering without complex logic.

**Pattern:**
```tsx
{navigationData.models.map((entry: NavModelEntry) =>
  entry.type === 'group'
    ? <NavGroupSection key={entry.label} label={entry.label} items={entry.items} />
    : <NavItemRow key={entry.name} item={entry} />
)}
```

This exactly mirrors how `groupCustomPages()` already works in `navigationUtils.ts` — it transforms a flat array into grouped structure. For models, if we pre-group in the PHP cache, no client-side transform function is needed.

---

### 7. Chevron Rotation — The Standard Tailwind Pattern

The community-standard Tailwind pattern for chevron rotation on expand/collapse:

```tsx
<svg
  className={`transition-transform duration-200 ${isOpen ? 'rotate-90' : 'rotate-0'}`}
>
```

For a right-pointing chevron that rotates to point down when open, use `rotate-90`. This is used by shadcn/ui, Flowbite, Preline, and all major Tailwind component collections. The `transition-transform` class scopes the transition to the transform property only, which is more performant than `transition-all`.

**Sources:**
- [shadcn/ui Sidebar Collapsible Items](https://www.shadcn.io/blocks/sidebar-collapsible-items)
- [shadcn/ui Nested Collapsible Sidebar](https://www.shadcn.io/blocks/sidebar-nested-collapsible)

---

## Recommended Approach for This Project

### Backend (PHP NavigationBuilder)

1. Add `navigation_bar` key to model metadata files:
   - `false` → skip model entirely
   - `null` / absent → show as ungrouped item
   - `'Group Label'` → place under named group

2. In `buildModelNavigation()`:
   - Call `MetadataEngine::getModelMetadata($modelName)` per model
   - Read `$metadata['navigation_bar']`
   - Skip if `false`
   - Accumulate into two buckets: `$groups['Group Label'][]` and `$ungrouped[]`

3. Build cache `models` array as pre-grouped nested structure:
   - Each group becomes `['type' => 'group', 'label' => '...', 'items' => [...]]`
   - Each ungrouped item becomes `['type' => 'item', ...]`
   - Maintain alphabetical ordering within groups; groups ordered by first-appearance or a defined order

### Frontend (NavigationSidebar.tsx)

1. Update TypeScript types in `navigation.ts`:
   - Add `NavModelItem`, `NavModelGroup`, `NavModelEntry` (discriminated union)
   - Update `NavigationData.models` from `NavigationItem[]` to `NavModelEntry[]`

2. Create a `NavGroupSection` component:
   - Uses `useState<boolean>(false)` for expand/collapse
   - Button with `aria-expanded={isOpen}` and `aria-controls={contentId}`
   - Sub-item list with `id={contentId}` and `max-h-0`/`max-h-screen` Tailwind animation
   - Chevron SVG with `rotate-90` class conditioned on `isOpen`

3. Update `NavigationSidebar.tsx` models render loop:
   - Map over `navigationData.models` checking `entry.type`
   - Render `<NavGroupSection>` for groups, existing item render for leaf items

4. No utility function needed for grouping (pre-done in PHP cache)

---

## Potential Pitfalls

1. **`max-h-screen` animation jank**: `max-h-screen` gives a jarring animation on short lists because it transitions from 0 to 100vh. For a small number of sub-items, use a fixed `max-h` (e.g., `max-h-64`) instead for a smoother feel.

2. **Focus management**: When a group collapses, if the currently focused link is inside that group, focus is lost. Ensure the collapse button retains focus after toggle.

3. **Active state detection**: Track the current URL (`useLocation()` from React Router) to apply `aria-current="page"` to the active link, and auto-expand the group containing the active item on initial render.

4. **Cache rebuild ordering**: The PHP cache must be rebuilt after updating metadata files. The existing `POST /navigation/cache/rebuild` endpoint should handle this; the frontend `navigationService.clearCache()` must be called after rebuild.

5. **TypeScript breaking change**: Changing `NavigationData.models` from `NavigationItem[]` to `NavModelEntry[]` is a breaking type change. Any other component consuming `navigationData.models` directly must be updated.

6. **Metadata property name**: The spec uses `'navigation_bar'` as either a boolean `false` or an array `['Group Name']`. A string (not array) would be simpler since the spec shows only single-level grouping. Consider whether to accept string `'Group Name'` (simpler) or array `['Group Name']` (future multi-level). Since only one level is planned, a string is cleaner.

7. **`var_export` and nested arrays**: PHP's `var_export()` handles nested arrays correctly, so the new pre-grouped structure will serialize/deserialize without issues.

---

## Libraries/Services to Consider

- **None required** — all patterns are implementable with React hooks + Tailwind utilities + native HTML
- **shadcn/ui sidebar patterns** (reference only, not for installation): Their open-source block code for collapsible sidebars provides good reference implementations without importing the library
- **WAI-ARIA APG examples** (https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/): Official W3C reference implementation for the disclosure navigation pattern; use as accessibility checklist
