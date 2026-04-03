/**
 * Shared timezone utility functions for converting between UTC and user timezones.
 *
 * Uses the Intl.DateTimeFormat API exclusively -- no third-party dependencies.
 * The 'en-CA' locale is used internally for formatToParts because it produces
 * YYYY-MM-DD format, which simplifies parsing. Display formatting uses the
 * user's locale.
 */

/**
 * Get the user's configured timezone, falling back to browser default.
 */
export const getUserTimezone = (userTimezone?: string | null): string => {
  return userTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
};

/**
 * Convert a "YYYY-MM-DDTHH:mm" string (interpreted in targetTimezone) to a UTC Date.
 *
 * Uses Intl.DateTimeFormat to determine the offset of targetTimezone at that moment,
 * then adjusts the naive date accordingly.
 */
export const localDateTimeToUTC = (localDateTimeStr: string, targetTimezone: string): Date => {
  // Step 1: Parse as if it were UTC (just to get a reference point)
  const naiveDate = new Date(localDateTimeStr + 'Z');

  // Step 2: Format that same instant in the target timezone to find offset
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: targetTimezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });

  const parts = formatter.formatToParts(naiveDate);
  const get = (type: string): number =>
    parseInt(parts.find(p => p.type === type)?.value || '0', 10);

  const tzDate = new Date(Date.UTC(
    get('year'),
    get('month') - 1,
    get('day'),
    get('hour'),
    get('minute'),
    get('second'),
  ));

  // The offset (in ms) is the difference between the UTC instant and how it
  // looks in targetTimezone
  const offsetMs = tzDate.getTime() - naiveDate.getTime();

  // Step 3: Subtract the offset from the naive-UTC interpretation to get true UTC
  const localAsUTC = new Date(localDateTimeStr + 'Z');
  return new Date(localAsUTC.getTime() - offsetMs);
};

/**
 * Format a UTC ISO date string for display in the given timezone.
 * Returns a human-readable string using Intl.DateTimeFormat with the user's locale.
 */
export const formatDateTimeInTimezone = (
  utcDateStr: string,
  timezone: string,
  options?: Intl.DateTimeFormatOptions,
): string => {
  // Normalize bare "Y-m-d H:i:s" strings to ISO 8601 with Z so they parse as UTC
  const normalized = utcDateStr.includes('T') || utcDateStr.endsWith('Z')
    ? utcDateStr
    : utcDateStr.replace(' ', 'T') + 'Z';
  const date = new Date(normalized);
  if (isNaN(date.getTime())) return String(utcDateStr);

  const defaultOptions: Intl.DateTimeFormatOptions = {
    timeZone: timezone,
    dateStyle: 'medium',
    timeStyle: 'short',
  };

  return new Intl.DateTimeFormat(undefined, { ...defaultOptions, ...options }).format(date);
};

/**
 * Format a UTC date for use in a datetime-local input (YYYY-MM-DDTHH:mm) in the
 * given timezone.
 */
export const formatDateTimeForInput = (utcDateStr: string, timezone: string): string => {
  // The backend stores dates as "Y-m-d H:i:s" (no timezone indicator).
  // new Date() treats such strings as local time, but they are actually UTC.
  // Normalize to ISO 8601 with Z suffix so the Date constructor parses as UTC.
  const normalized = utcDateStr.includes('T') || utcDateStr.endsWith('Z')
    ? utcDateStr
    : utcDateStr.replace(' ', 'T') + 'Z';
  const date = new Date(normalized);
  if (isNaN(date.getTime())) return '';

  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  });

  const parts = formatter.formatToParts(date);
  const get = (type: string): string => parts.find(p => p.type === type)?.value || '';
  return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
};
