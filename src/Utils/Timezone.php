<?php
namespace Gravitycar\Utils;

/**
 * Timezone utility class providing ISO 8601 compliant timezone options
 * with human-friendly display names for the Users model timezone field.
 */
class Timezone {
    
    /**
     * Get timezone options for the Users model timezone field
     * 
     * Returns an array with ISO 8601 timezone identifiers as keys
     * and human-friendly display names as values
     * 
     * @return array Array of timezone options [timezone_id => display_name]
     */
    public static function getTimezones(): array {
        return [
            // UTC
            'UTC' => 'UTC - Coordinated Universal Time (+00:00)',
            
            // North America
            'America/New_York' => 'New York, USA (Eastern Time) (-05:00/-04:00)',
            'America/Chicago' => 'Chicago, USA (Central Time) (-06:00/-05:00)',
            'America/Denver' => 'Denver, USA (Mountain Time) (-07:00/-06:00)',
            'America/Los_Angeles' => 'Los Angeles, USA (Pacific Time) (-08:00/-07:00)',
            'America/Anchorage' => 'Anchorage, USA (Alaska Time) (-09:00/-08:00)',
            'Pacific/Honolulu' => 'Honolulu, USA (Hawaii Time) (-10:00)',
            'America/Toronto' => 'Toronto, Canada (Eastern Time) (-05:00/-04:00)',
            'America/Vancouver' => 'Vancouver, Canada (Pacific Time) (-08:00/-07:00)',
            'America/Mexico_City' => 'Mexico City, Mexico (-06:00/-05:00)',
            
            // South America
            'America/Sao_Paulo' => 'São Paulo, Brazil (-03:00/-02:00)',
            'America/Argentina/Buenos_Aires' => 'Buenos Aires, Argentina (-03:00)',
            'America/Santiago' => 'Santiago, Chile (-04:00/-03:00)',
            'America/Lima' => 'Lima, Peru (-05:00)',
            'America/Bogota' => 'Bogotá, Colombia (-05:00)',
            'America/Caracas' => 'Caracas, Venezuela (-04:00)',
            
            // Europe
            'Europe/London' => 'London, UK (GMT/BST) (+00:00/+01:00)',
            'Europe/Dublin' => 'Dublin, Ireland (GMT/IST) (+00:00/+01:00)',
            'Europe/Paris' => 'Paris, France (CET/CEST) (+01:00/+02:00)',
            'Europe/Berlin' => 'Berlin, Germany (CET/CEST) (+01:00/+02:00)',
            'Europe/Amsterdam' => 'Amsterdam, Netherlands (CET/CEST) (+01:00/+02:00)',
            'Europe/Rome' => 'Rome, Italy (CET/CEST) (+01:00/+02:00)',
            'Europe/Madrid' => 'Madrid, Spain (CET/CEST) (+01:00/+02:00)',
            'Europe/Zurich' => 'Zurich, Switzerland (CET/CEST) (+01:00/+02:00)',
            'Europe/Vienna' => 'Vienna, Austria (CET/CEST) (+01:00/+02:00)',
            'Europe/Prague' => 'Prague, Czech Republic (CET/CEST) (+01:00/+02:00)',
            'Europe/Warsaw' => 'Warsaw, Poland (CET/CEST) (+01:00/+02:00)',
            'Europe/Stockholm' => 'Stockholm, Sweden (CET/CEST) (+01:00/+02:00)',
            'Europe/Copenhagen' => 'Copenhagen, Denmark (CET/CEST) (+01:00/+02:00)',
            'Europe/Oslo' => 'Oslo, Norway (CET/CEST) (+01:00/+02:00)',
            'Europe/Helsinki' => 'Helsinki, Finland (EET/EEST) (+02:00/+03:00)',
            'Europe/Athens' => 'Athens, Greece (EET/EEST) (+02:00/+03:00)',
            'Europe/Istanbul' => 'Istanbul, Turkey (TRT) (+03:00)',
            'Europe/Moscow' => 'Moscow, Russia (MSK) (+03:00)',
            
            // Africa
            'Africa/Cairo' => 'Cairo, Egypt (EET) (+02:00)',
            'Africa/Johannesburg' => 'Johannesburg, South Africa (SAST) (+02:00)',
            'Africa/Lagos' => 'Lagos, Nigeria (WAT) (+01:00)',
            'Africa/Nairobi' => 'Nairobi, Kenya (EAT) (+03:00)',
            'Africa/Casablanca' => 'Casablanca, Morocco (+01:00/+00:00)',
            'Africa/Algiers' => 'Algiers, Algeria (CET) (+01:00)',
            
            // Asia
            'Asia/Dubai' => 'Dubai, UAE (GST) (+04:00)',
            'Asia/Riyadh' => 'Riyadh, Saudi Arabia (AST) (+03:00)',
            'Asia/Tehran' => 'Tehran, Iran (IRST/IRDT) (+03:30/+04:30)',
            'Asia/Kolkata' => 'Mumbai/Delhi, India (IST) (+05:30)',
            'Asia/Dhaka' => 'Dhaka, Bangladesh (BST) (+06:00)',
            'Asia/Kathmandu' => 'Kathmandu, Nepal (NPT) (+05:45)',
            'Asia/Colombo' => 'Colombo, Sri Lanka (SLST) (+05:30)',
            'Asia/Bangkok' => 'Bangkok, Thailand (ICT) (+07:00)',
            'Asia/Jakarta' => 'Jakarta, Indonesia (WIB) (+07:00)',
            'Asia/Singapore' => 'Singapore (SGT) (+08:00)',
            'Asia/Kuala_Lumpur' => 'Kuala Lumpur, Malaysia (MYT) (+08:00)',
            'Asia/Manila' => 'Manila, Philippines (PHT) (+08:00)',
            'Asia/Hong_Kong' => 'Hong Kong (HKT) (+08:00)',
            'Asia/Shanghai' => 'Beijing/Shanghai, China (CST) (+08:00)',
            'Asia/Taipei' => 'Taipei, Taiwan (CST) (+08:00)',
            'Asia/Seoul' => 'Seoul, South Korea (KST) (+09:00)',
            'Asia/Tokyo' => 'Tokyo, Japan (JST) (+09:00)',
            
            // Australia & Pacific
            'Australia/Perth' => 'Perth, Australia (AWST) (+08:00)',
            'Australia/Adelaide' => 'Adelaide, Australia (ACST/ACDT) (+09:30/+10:30)',
            'Australia/Darwin' => 'Darwin, Australia (ACST) (+09:30)',
            'Australia/Brisbane' => 'Brisbane, Australia (AEST) (+10:00)',
            'Australia/Sydney' => 'Sydney, Australia (AEST/AEDT) (+10:00/+11:00)',
            'Australia/Melbourne' => 'Melbourne, Australia (AEST/AEDT) (+10:00/+11:00)',
            'Australia/Hobart' => 'Hobart, Australia (AEST/AEDT) (+10:00/+11:00)',
            'Pacific/Auckland' => 'Auckland, New Zealand (NZST/NZDT) (+12:00/+13:00)',
            'Pacific/Fiji' => 'Suva, Fiji (FJT/FJST) (+12:00/+13:00)',
            'Pacific/Tahiti' => 'Tahiti, French Polynesia (TAHT) (-10:00)',
            'Pacific/Guam' => 'Guam (ChST) (+10:00)',
            
            // Additional useful timezones
            'Atlantic/Azores' => 'Azores, Portugal (AZOT/AZOST) (-01:00/+00:00)',
            'Atlantic/Cape_Verde' => 'Cape Verde (CVT) (-01:00)',
            'Indian/Mauritius' => 'Mauritius (MUT) (+04:00)',
            'Indian/Maldives' => 'Maldives (MVT) (+05:00)',
        ];
    }
    
    /**
     * Get timezone display name for a given timezone identifier
     * 
     * @param string $timezone Timezone identifier (e.g., 'America/New_York')
     * @return string|null Display name or null if not found
     */
    public static function getTimezoneDisplayName(string $timezone): ?string {
        $timezones = self::getTimezones();
        return $timezones[$timezone] ?? null;
    }
    
    /**
     * Validate if a timezone identifier exists in our supported list
     * 
     * @param string $timezone Timezone identifier to validate
     * @return bool True if timezone is supported, false otherwise
     */
    public static function isValidTimezone(string $timezone): bool {
        return array_key_exists($timezone, self::getTimezones());
    }
    
    /**
     * Get timezone offset information for a given timezone
     * Uses PHP's DateTimeZone to get current offset information
     * 
     * @param string $timezone Timezone identifier
     * @return array|null Array with offset info or null if invalid
     */
    public static function getTimezoneOffset(string $timezone): ?array {
        try {
            $tz = new \DateTimeZone($timezone);
            $now = new \DateTime('now', $tz);
            $offset = $tz->getOffset($now);
            
            // Convert offset to hours and minutes
            $hours = intval($offset / 3600);
            $minutes = abs(($offset % 3600) / 60);
            
            return [
                'timezone' => $timezone,
                'offset_seconds' => $offset,
                'offset_hours' => $hours,
                'offset_minutes' => $minutes,
                'formatted_offset' => sprintf('%+03d:%02d', $hours, $minutes),
                'display_name' => self::getTimezoneDisplayName($timezone)
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
