# Web Research: Event Organizer (Chart of Goodness) Feature

## Search Terms Used
- "PHP 8 iCalendar ICS file generation library 2025 2026"
- "eluceo ical PHP 8 composer library features RFC 5545"
- "spatie icalendar-generator PHP library features composer"
- "ICS iCalendar file format VEVENT structure example RFC 5545 basics"
- "OpenStreetMap Nominatim API free alternative to Google Maps location input 2025"
- "Google Maps API pricing free tier limits 2025 2026"
- "PHP email reminder scheduling cron queue best practices 2025"
- "PHPMailer Symfony Mailer PHP 8.2 email sending library comparison 2025"
- "peppeocchi php-cron-scheduler cron job PHP without framework"
- "event scheduling availability polling UI UX patterns Doodle When2meet 2025"
- "Doodle When2meet availability grid matrix UI how it works design"

---

## Key Findings

### 1. ICS/iCalendar File Format (RFC 5545)

**Summary:** The iCalendar format (RFC 5545) is a text-based standard for exchanging calendar data. Files use the `.ics` extension and contain VCALENDAR objects wrapping VEVENT (event), VTODO (task), or VJOURNAL components.

**Basic VEVENT structure:**
```
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Gravitycar//Event Organizer//EN
BEGIN:VEVENT
UID:unique-id@gravitycar.com
DTSTAMP:20260401T120000Z
DTSTART:20260415T180000Z
DTEND:20260415T210000Z
SUMMARY:Game Night
DESCRIPTION:Weekly board game session
LOCATION:123 Main St
END:VEVENT
END:VCALENDAR
```

**Required VEVENT properties:** UID, DTSTAMP. DTSTART is required unless the calendar specifies a METHOD property.

**Common optional properties:** DTEND, SUMMARY, DESCRIPTION, LOCATION, ORGANIZER, ATTENDEE, RRULE (recurring), VALARM (reminders).

**Sources:**
- [RFC 5545 Specification](https://datatracker.ietf.org/doc/html/rfc5545)
- [iCalendar.org - VEVENT Component](https://icalendar.org/iCalendar-RFC-5545/3-6-1-event-component.html)
- [iCalendar.org - Examples](https://icalendar.org/iCalendar-RFC-5545/4-icalendar-object-examples.html)

**Recommendation:** Use a PHP library (see section 5) rather than hand-building ICS strings. The format has many edge cases around timezone handling, line folding, and character escaping.

---

### 2. Location Handling: Free Alternatives to Google Maps

#### Google Maps API Pricing (as of March 2025)

Google eliminated the $200/month free credit in March 2025, replacing it with per-SKU free usage caps:

| SKU Category | Free Monthly Events | Cost After Free Tier |
|---|---|---|
| Essentials (Dynamic Maps, Geocoding, Static Maps) | 10,000 | $2-$7 per 1,000 |
| Pro (Street View, traffic-aware routing) | 5,000 | Higher |
| Enterprise (3D Tiles, Fleet Routing) | 1,000 | Higher still |
| Maps Embed API, Mobile SDKs | Unlimited | Free |

**Verdict:** Google Maps API does cost money. The free tier is limited to 10,000 geocoding requests/month for basic use. For a small event organizer feature, this could suffice, but it requires billing setup and API key management.

**Sources:**
- [Google Maps Platform Pricing](https://developers.google.com/maps/billing-and-pricing/pricing)
- [Google Maps Pricing Overview](https://developers.google.com/maps/billing-and-pricing/overview)
- [Google Maps API Pricing 2026 Breakdown](https://nicolalazzari.ai/articles/understanding-google-maps-apis-a-comprehensive-guide-to-uses-and-costs)

#### Free/Low-Cost Alternatives

**OpenStreetMap + Nominatim (Recommended)**
- Nominatim is the geocoding engine behind OpenStreetMap
- Supports forward geocoding (address to coordinates) and reverse geocoding
- Free to use with the public API (rate-limited to 1 request/second, must include User-Agent)
- Can be self-hosted for unlimited usage at zero API cost
- No API key required for public instance
- Sources: [Nominatim.org](https://nominatim.org/), [Nominatim API Docs](https://nominatim.org/release-docs/latest/api/Search/)

**LocationIQ**
- Uses Nominatim-compatible API format (easy migration)
- Free tier: 5,000 requests/day
- Paid plans from $49/month
- Source: [Mappr Google Places Alternatives](https://www.mappr.co/google-places-api-alternatives/)

**Simple Text Field Approach (Simplest)**
- For the Chart of Goodness feature, a plain text field for location may be sufficient
- Users type a free-text address or venue name
- No geocoding API needed at all
- Can optionally add a "View on Map" link using `https://www.openstreetmap.org/search?query={encoded_address}`

**Recommendation for Gravitycar:** Start with a simple text field for location. Most events in a friend-group scheduling tool have known locations ("Mike's house", "The usual bar"). Add optional Nominatim-powered address autocomplete as a future enhancement if needed.

---

### 3. Email Reminder Patterns in PHP

#### Architecture Options

**Option A: Cron + Direct Send (Simplest)**
- Single cron job runs every minute (or every 5 minutes)
- PHP script queries DB for reminders due to be sent
- Sends emails directly using PHPMailer or Symfony Mailer
- Marks reminders as sent in DB
- Pros: Simple, no extra infrastructure
- Cons: Can be slow for large volumes; no retry on failure without custom logic
- Source: [Cronitor - PHP Cron Jobs](https://cronitor.io/guides/php-cron-jobs)

**Option B: Cron + Queue Table (Recommended)**
- Cron job runs every minute
- Checks for events needing reminders, inserts email jobs into a queue table
- Separate cron job (or same script, second pass) processes queue table
- Failed sends stay in queue for retry
- Configurable send rate to avoid overwhelming mail server
- Source: [emailqueue on GitHub](https://github.com/tin-cat/emailqueue)

**Option C: PHP Cron Scheduler Library**
- `peppeocchi/php-cron-scheduler` - framework-agnostic, Laravel-inspired
- Define scheduled jobs in PHP code instead of crontab entries
- Only one crontab entry needed (runs scheduler.php every minute)
- Supports background execution, output logging, error handling
- Requires PHP >= 7.3 (works with 8.2+)
- Source: [php-cron-scheduler on GitHub](https://github.com/peppeocchi/php-cron-scheduler)

#### Email Sending Libraries

**PHPMailer (Recommended for Gravitycar)**
- Most widely used PHP email library
- Supports SMTP, DKIM signing, OAuth2 authentication
- No framework dependency
- PHP 8.4+ supported, experimental PHP 8.5 support
- Composer: `phpmailer/phpmailer`
- Source: [PHPMailer on GitHub](https://github.com/PHPMailer/PHPMailer)

**Symfony Mailer**
- Modern, well-maintained
- Built-in support for SendGrid, Mailgun, etc.
- Twig template integration
- Heavier dependency footprint (pulls in Symfony components)
- Composer: `symfony/mailer`
- Source: [Symfony Mailer Docs](https://symfony.com/doc/current/mailer.html)

#### Best Practices
- Validate and sanitize all email inputs to prevent injection attacks
- Use fully-qualified paths in crontab entries
- Log all email send attempts (success and failure)
- Implement rate limiting to avoid being flagged as spam
- Store email credentials in environment variables, not code
- Track reminder state in DB to avoid duplicate sends

**Recommendation for Gravitycar:** Use Option B (cron + queue table) with PHPMailer. PHPMailer has no framework dependencies, aligns with the project's non-framework PHP approach, and is well-documented. A simple `email_queue` table with status tracking provides reliability without complex infrastructure.

---

### 4. Event Scheduling UI/UX Patterns

#### Two Main Paradigms

**When2Meet Style: Availability Grid**
- Visual grid: columns = time slots, rows = days (or vice versa)
- Users click/drag to highlight when they are available
- Overlapping availability shown via color intensity (darker green = more people available)
- Pros: Very intuitive for finding overlapping free time; visual at a glance
- Cons: Drag-select is frustrating on mobile; requires many time slots to be useful
- Source: [kiera.design - When2Meet Analysis](http://www.kiera.design/when2meet.html)

**Doodle Style: Voting on Proposed Dates**
- Organizer proposes specific date/time options
- Participants vote: Yes / No / If-need-be on each option
- Results shown as a table with vote counts
- Pros: Works well on mobile; clear decision-making; fewer options to evaluate
- Cons: Less granular than a full availability grid; organizer must pre-select options
- Source: [Doodle vs When2Meet Comparison](https://www.usecarly.com/blog/doodle-vs-when2meet)

#### The "Chart of Goodness" Pattern (Gravitycar's Existing Approach)

The old Chart of Goodness is closest to the **Doodle model**:
- Organizer proposes specific dates/times
- Invited guests mark which dates work for them (binary: available or not)
- Grid shows dates as columns, guests as rows
- Color coding: green = can make it, red = cannot
- "Most Popular Meeting Time" calculation highlights the winning date

**Key UX Recommendations for the New Implementation:**

1. **Color coding is essential:** Green for available, red/gray for unavailable. Darker green for "most popular" dates. The color green has strong association with availability and clickability.
2. **Mobile-first considerations:** Avoid drag-select. Use tap-to-toggle checkboxes instead (which the old COG already does). Consider a card-based layout for narrow screens where each date is a card users can tap.
3. **Progressive disclosure:** Show the "Most Popular Date" prominently at the top. Show the full grid below for details.
4. **Real-time feedback:** When a user toggles their availability, update the "most popular" calculation immediately.
5. **Guest-only editing:** Users should only be able to modify their own row. Other rows are read-only visual indicators.
6. **Notes section:** Allow guests to leave comments (the old system had "witty remarks").

**Sources:**
- [Time Picker UX Best Practices 2025](https://www.eleken.co/blog-posts/time-picker-ux)
- [Calendar Design UX/UI Tips](https://pageflows.com/resources/exploring-calendar-design/)
- [WhenAvailable - Modern Group Scheduling](https://whenavailable.com/)
- [When2Meet vs Doodle Comparison](https://koalendar.com/blog/when2meet-vs-doodle)

---

### 5. PHP Libraries for iCalendar (.ics) Generation

| Library | Composer Package | PHP Requirement | Stars | Key Features |
|---|---|---|---|---|
| **spatie/icalendar-generator** | `spatie/icalendar-generator` | PHP ^8.1 | ~800 | Fluent API, RFC 5545 + RFC 7986, timezones, attendees, alarms |
| **eluceo/ical** | `eluceo/ical` | PHP ^7.4/^8.0 | ~1.5k | Domain-driven design, RRULE support, mature/stable |
| **markuspoerschke/iCal** | `markuspoerschke/ical` | PHP ^8.1 | ~400 | Fork/rewrite of eluceo, modern API |
| **calendar/icsfile** | `calendar/icsfile` | PHP ^7.4 | Small | Lightweight, simple API |

#### Detailed Comparison

**spatie/icalendar-generator (Recommended)**
- Fluent, expressive API typical of Spatie packages
- Supports: events, todos, alarms, attendees, organizers, timezones, images
- Implements both RFC 5545 and RFC 7986 extensions
- Requires `ext-mbstring`
- MIT License
- Active maintenance
- Source: [GitHub - spatie/icalendar-generator](https://github.com/spatie/icalendar-generator)

**eluceo/ical**
- Most downloaded iCal library on Packagist
- Clean domain/presentation separation (Domain objects + Presentation layer)
- Supports complex RRULE (recurring events)
- V2 is a complete rewrite (not backward compatible with V1)
- Source: [Packagist - eluceo/ical](https://packagist.org/packages/eluceo/ical), [Documentation](https://ical.poerschke.nrw/docs/)

**Recommendation for Gravitycar:** Use `spatie/icalendar-generator`. It has a clean fluent API, supports PHP 8.1+, is actively maintained, and covers all needed features (events with dates, locations, descriptions, attendees, alarms/reminders). Spatie packages are known for quality documentation and consistent API design.

---

## Recommended Approaches

### For the Event Organizer Feature Overall

1. **ICS Generation:** Use `spatie/icalendar-generator` via Composer. Generate `.ics` files on-demand when users want to "Add to Calendar". Serve as a downloadable file attachment or inline in email.

2. **Location:** Start with a simple TextField for location in the Event model metadata. No map API needed initially. Optionally link to OpenStreetMap search for "view on map" functionality. Avoid Google Maps API cost/complexity.

3. **Email Reminders:** 
   - Use PHPMailer for sending (no framework dependency)
   - Create an `email_queue` database table for reliable delivery
   - Single cron job every minute to process the queue
   - Attach `.ics` file to invitation/reminder emails so recipients can add events to their calendars with one click

4. **Chart of Goodness UI:**
   - Follow the Doodle-style voting model (matches the existing feature design)
   - React grid component: columns = proposed dates, rows = invited guests
   - Tap-to-toggle checkboxes for the current user's row
   - Green/red color coding for availability
   - "Most Popular Date" prominently displayed
   - Mobile-responsive: consider horizontal scroll or card layout for many dates
   - Notes/comments section below the chart

### Technology Stack Alignment
- All recommended libraries (spatie/icalendar-generator, PHPMailer, peppeocchi/php-cron-scheduler) are installable via Composer
- All support PHP 8.2+
- None require a specific PHP framework, fitting Gravitycar's custom framework approach

---

## Potential Pitfalls

1. **ICS Timezone Handling:** Timezone bugs are the most common issue with ICS files. Always use DateTimeImmutable with explicit timezone objects. Let the library handle VTIMEZONE component generation.

2. **Email Deliverability:** Emails from new domains often land in spam. Use proper SPF/DKIM/DMARC records. Consider using a transactional email service (SendGrid, Mailgun) for production.

3. **Cron Reliability:** Cron jobs can fail silently. Log every run. Consider a dead-man's switch (e.g., Cronitor) for production monitoring.

4. **Mobile Grid UX:** The availability grid can be hard to use on small screens. Test thoroughly on mobile. Consider a simplified view for narrow viewports.

5. **Duplicate Reminders:** Without careful state tracking (a `sent_at` timestamp on reminder records), cron jobs can send duplicate emails. Always mark reminders as sent in the same transaction as the send.

6. **Race Conditions in Commitments:** If two users toggle availability simultaneously, the old "delete-all-then-reinsert" pattern can cause data loss. Use per-cell updates (INSERT ON DUPLICATE KEY UPDATE or individual row updates) instead.

---

## Libraries/Services to Consider

| Library/Service | Purpose | Install | Why Consider |
|---|---|---|---|
| `spatie/icalendar-generator` | ICS file generation | `composer require spatie/icalendar-generator` | Clean API, PHP 8.1+, RFC 5545 compliant |
| `phpmailer/phpmailer` | Email sending | `composer require phpmailer/phpmailer` | No framework dependency, SMTP support, widely used |
| `peppeocchi/php-cron-scheduler` | Cron job scheduling | `composer require peppeocchi/php-cron-scheduler` | Framework-agnostic, define schedules in PHP code |
| OpenStreetMap/Nominatim | Geocoding (optional) | Free API, no key needed | Zero cost, no billing setup |
| Leaflet.js | Map display (optional) | npm/CDN | Free, open-source map rendering if map display is ever needed |
