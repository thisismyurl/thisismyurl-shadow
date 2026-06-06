<?php
/**
 * Base Diagnostic Class
 *
 * All diagnostic checks should extend this class.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Define the runtime contract for all diagnostic checks.
 *
 * Diagnostics are the read-only analysis layer of Christopher Ross Shadow. Each concrete
 * diagnostic answers one question about a site, such as whether a setting is
 * risky, a page is missing, or a performance threshold is being exceeded. This
 * base class standardizes how those checks are described, scheduled, enabled,
 * executed, and exposed to the rest of the plugin.
 */
abstract class Diagnostic_Base {

/**
 * The diagnostic slug/ID.
 *
 * @var string
 */
protected static $slug = '';

/**
 * The diagnostic title.
 *
 * @var string
 */
protected static $title = '';

/**
 * The diagnostic description.
 *
 * @var string
 */
protected static $description = '';

/**
 * The family this diagnostic belongs to.
 *
 * Used to group related diagnostics on the dashboard.
 * Example: 'performance', 'security', 'seo'
 *
 * @var string
 */
protected static $family = '';

/**
 * Display name for the family.
 *
 * Example: 'Performance', 'Security'
 *
 * @var string
 */
protected static $family_label = '';

/**
 * Severity level of this diagnostic finding.
 *
 * Drives dashboard colour coding, KPI bucketing, and notification priority.
 * Values: 'critical' | 'high' | 'medium' | 'low'
 *
 * @var string
 */
protected static $severity = 'medium';

/**
 * How often this diagnostic should be executed.
 *
 * Values:
 *   'always'    - run on every scan (fast, stateless checks)
 *   'on-change' - re-run after plugin/theme activation or update
 *   'daily'     - at most once per day
 *   'weekly'    - at most once per week
 *   'monthly'   - at most once per month
 *
 * @var string
 */
protected static $scan_frequency = 'daily';

/**
 * Whether this diagnostic should only run during off-peak hours.
 *
 * Set true for checks that execute expensive DB queries or enumerate
 * large tables, so they are deferred and do not impact live traffic.
 *
 * @var bool
 */
protected static $off_hours = false;

/**
 * Estimated minutes to resolve this finding once detected.
 *
 * Used by the KPI tracker to calculate time-saved metrics when a
 * treatment is applied or the finding is marked resolved.
 *
 * @var int
 */
protected static $time_to_fix_minutes = 15;

/**
 * One-line business impact statement displayed in the dashboard.
 *
 * Example: 'Unencrypted traffic exposes visitor data and harms SEO.'
 *
 * @var string
 */
protected static $impact = '';

/**
 * Whether this diagnostic is part of the core trusted set.
 *
 * Core diagnostics are universally applicable, high-confidence, and high-impact.
 * They are recommended for all sites and designed to minimize false positives.
 *
 * @var bool
 */
protected static $is_core = false;

/**
 * Confidence level of this diagnostic.
 *
 * Values:
 *   'high'     - Well-tested, universally applicable, low false-positive rate
 *   'standard' - Active, implemented, works in most scenarios
 *   'low'      - Beta/experimental, context-dependent, or not yet fully validated
 *
 * Defaults to 'standard'. Core diagnostics should be 'high' or 'standard'.
 *
 * @var string
 */
protected static $confidence = 'standard';

/**
 * Run the diagnostic check.
 *
 * @return array|null Returns an array of findings if issues found, null otherwise.
 */
abstract public static function check();

/**
 * Return a stable run key used for timestamp storage.
 *
 * Derived from the diagnostic's slug, falling back to a sanitised form of the
 * short class name so every subclass always has a valid key without needing
 * to declare a slug.
 *
 * @return string Sanitised run key.
 */
public static function get_run_key(): string {
	$slug = static::get_slug();
	if ( '' !== $slug ) {
		return sanitize_key( $slug );
	}
	$short = str_replace( 'ThisIsMyURL\\Shadow\\Diagnostics\\', '', get_called_class() );
	return sanitize_key( strtolower( str_replace( '_', '-', $short ) ) );
}

/**
 * Check whether this diagnostic is currently enabled by the site admin.
 *
 * Uses the same option and filter that Diagnostic_Registry uses so the check
 * is always consistent regardless of which code path calls execute().
 *
 * @return bool True when the diagnostic may run; false when it has been disabled.
 */
public static function is_enabled(): bool {
	$class    = get_called_class();
	$disabled = get_option( 'thisismyurl_shadow_disabled_diagnostic_classes', array() );
	$disabled = is_array( $disabled ) ? array_map( 'strval', $disabled ) : array();
	$short    = str_replace( 'ThisIsMyURL\\Shadow\\Diagnostics\\', '', $class );
	$enabled  = ! in_array( $class, $disabled, true ) && ! in_array( $short, $disabled, true );
	/** @see Diagnostic_Registry::is_diagnostic_enabled() */
	return (bool) apply_filters( 'thisismyurl_shadow_diagnostic_enabled', $enabled, $class );
}

/**
 * Check whether this diagnostic is due for a run given its scan_frequency.
 *
 * Reads the per-diagnostic user frequency override first, falling back to the
 * class default.  Returns true when no prior run is recorded or when the
 * relevant time window has elapsed.
 *
 * Frequencies:
 *   'always'    — always due
 *   'on-change' — always due (cache-clearing on plugin/theme changes handles throttling)
 *   'daily'     — at most once every DAY_IN_SECONDS
 *   'weekly'    — at most once every WEEK_IN_SECONDS
 *   'monthly'   — at most once every 30 * DAY_IN_SECONDS
 *
 * @return bool True when the diagnostic should run; false when it ran recently enough.
 */
public static function is_due(): bool {
	$class         = get_called_class();
	$freq_overrides = get_option( 'thisismyurl_shadow_diagnostic_frequency_overrides', array() );
	$freq_overrides = is_array( $freq_overrides ) ? $freq_overrides : array();
	$freq           = isset( $freq_overrides[ $class ] ) && 'default' !== $freq_overrides[ $class ]
		? (string) $freq_overrides[ $class ]
		: static::get_scan_frequency();

	if ( 'always' === $freq || 'on-change' === $freq ) {
		return true;
	}

	$intervals = array(
		'daily'   => DAY_IN_SECONDS,
		'weekly'  => WEEK_IN_SECONDS,
		'monthly' => 30 * DAY_IN_SECONDS,
	);

	if ( ! isset( $intervals[ $freq ] ) ) {
		return true; // Unknown frequency → always due.
	}

	$last_run = (int) get_option( 'thisismyurl_shadow_last_run_' . static::get_run_key(), 0 );
	return ( $last_run + $intervals[ $freq ] ) <= time();
}

/**
 * Record the current timestamp as the last execution time for this diagnostic.
 *
 * Called automatically by execute() after a successful run so that is_due()
 * can throttle repeat runs within the same frequency window.
 *
 * @return void
 */
protected static function stamp_last_run(): void {
	update_option( 'thisismyurl_shadow_last_run_' . static::get_run_key(), time(), false );
}

/**
 * Execute the diagnostic through Christopher Ross Shadow's shared scan pipeline.
 *
 * Concrete diagnostics implement check(), but the plugin does not call that
 * method directly in normal operation. Instead it calls execute() so shared
 * behavior stays consistent across the codebase: enablement checks, frequency
 * throttling, before/after hooks, activity logging, and last-run stamping.
 *
 * @param bool $force When true the schedule gate is bypassed; the enabled gate
 *                    is always enforced regardless of this flag.
 * @return array|null Finding array if issues found, null if no issues OR skipped.
 */
public static function execute( bool $force = false ) {
$class = get_called_class();
$slug  = static::get_slug();

// Gate 1: Enabled check — always enforced.
if ( ! static::is_enabled() ) {
	do_action( 'thisismyurl_shadow_diagnostic_skipped_disabled', $class, $slug );
	return null;
}

// Gate 2: Schedule / frequency check — skipped for explicit user runs.
if ( ! $force && ! static::is_due() ) {
	do_action( 'thisismyurl_shadow_diagnostic_skipped_schedule', $class, $slug );
	return null;
}

/**
 * Fires before a diagnostic check is run.
 *
 * @param string $class Diagnostic class name.
 * @param string $slug  Diagnostic slug/identifier.
 */
do_action( 'thisismyurl_shadow_before_diagnostic_check', $class, $slug );

$finding = static::check();

// Log every diagnostic run to the activity log.
if ( class_exists( Activity_Logger::class ) ) {
$log_title = ! empty( static::$title ) ? static::$title : $slug;
if ( null !== $finding ) {
$log_details = sprintf(
/* translators: %s: diagnostic title */
__( 'Diagnostic found issue: %s', 'thisismyurl-shadow' ),
$log_title
);
} else {
$log_details = sprintf(
/* translators: %s: diagnostic title */
__( 'Diagnostic passed: %s', 'thisismyurl-shadow' ),
$log_title
);
}
Activity_Logger::log(
'diagnostic_run',
$log_details,
static::get_family(),
array(
'class'       => $class,
'slug'        => $slug,
'has_finding' => null !== $finding,
)
);
}

/**
 * Fires after a diagnostic check is run.
 *
 * @param string     $class   Diagnostic class name.
 * @param string     $slug    Diagnostic slug/identifier.
 * @param array|null $finding Finding result (null if no issues).
 */
// Record execution time so is_due() throttles future automated runs.
static::stamp_last_run();

do_action( 'thisismyurl_shadow_after_diagnostic_check', $class, $slug, $finding );

/**
 * Filter diagnostic check result.
 *
 * Allows modification of diagnostic findings before stored/displayed.
 *
 * @param array|null $finding Finding result (null if no issues).
 * @param string     $class   Diagnostic class name.
 * @param string     $slug    Diagnostic slug/identifier.
 */
return apply_filters( 'thisismyurl_shadow_diagnostic_result', $finding, $class, $slug );
}

/**
 * Get the diagnostic slug.
 *
 * @return string
 */
public static function get_slug(): string {
return static::$slug;
}

/**
 * Get the user-facing title, merging Diagnostic_Metadata overrides.
 *
 * @return string
 */
public static function get_title(): string {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( ! empty( $meta['title'] ) ) {
return (string) $meta['title'];
}
}

return static::$title;
}

/**
 * Get the user-facing description, merging Diagnostic_Metadata overrides.
 *
 * @return string
 */
public static function get_description(): string {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( ! empty( $meta['description'] ) ) {
return (string) $meta['description'];
}
}

return static::$description;
}

/**
 * Get the severity level of this diagnostic.
 *
 * Falls back to Diagnostic_Metadata registry when not overridden on the class.
 *
 * @return string 'critical' | 'high' | 'medium' | 'low'
 */
public static function get_severity(): string {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( ! empty( $meta['severity'] ) ) {
return (string) $meta['severity'];
}
}

return '' !== static::$severity ? static::$severity : 'medium';
}

/**
 * Get how often this diagnostic should run.
 *
 * Falls back to Diagnostic_Metadata registry when not overridden on the class.
 *
 * @return string 'always' | 'on-change' | 'daily' | 'weekly' | 'monthly'
 */
public static function get_scan_frequency(): string {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( ! empty( $meta['scan_frequency'] ) ) {
return (string) $meta['scan_frequency'];
}
}

return '' !== static::$scan_frequency ? static::$scan_frequency : 'daily';
}

/**
 * Get whether this diagnostic should only run during off-peak hours.
 *
 * Falls back to Diagnostic_Metadata registry when not overridden on the class.
 *
 * @return bool
 */
public static function get_off_hours(): bool {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( isset( $meta['off_hours'] ) ) {
return (bool) $meta['off_hours'];
}
}

return static::$off_hours;
}

/**
 * Get the estimated minutes to resolve this finding.
 *
 * Falls back to Diagnostic_Metadata registry when not overridden on the class.
 *
 * @return int
 */
public static function get_time_to_fix_minutes(): int {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( isset( $meta['time_to_fix_minutes'] ) ) {
return (int) $meta['time_to_fix_minutes'];
}
}

return static::$time_to_fix_minutes;
}

/**
 * Get the one-line business impact statement for this diagnostic.
 *
 * Falls back to Diagnostic_Metadata registry when not overridden on the class.
 *
 * @return string
 */
public static function get_impact(): string {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( ! empty( $meta['impact'] ) ) {
return (string) $meta['impact'];
}
}

return static::$impact;
}

/**
 * Check if this diagnostic is part of the core trusted set.
 *
 * Core diagnostics are universally applicable and high-confidence.
 *
 * @return bool
 */
public static function is_core(): bool {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( isset( $meta['is_core'] ) ) {
return (bool) $meta['is_core'];
}
}

return static::$is_core;
}

/**
 * Get the confidence level of this diagnostic.
 *
 * Falls back to Diagnostic_Metadata registry when not overridden on the class.
 *
 * @return string 'high' | 'standard' | 'low'
 */
public static function get_confidence(): string {
if ( class_exists( Diagnostic_Metadata::class ) ) {
$meta = Diagnostic_Metadata::get( static::$slug );
if ( ! empty( $meta['confidence'] ) ) {
return (string) $meta['confidence'];
}
}

return '' !== static::$confidence ? static::$confidence : 'standard';
}

/**
 * Check if the diagnostic is applicable.
 *
 * @return bool
 */
public static function is_applicable(): bool {
return true;
}

/**
 * Get available treatments for this diagnostic.
 *
 * @return array
 */
public static function get_available_treatments(): array {
return array();
}

/**
 * Get the family this diagnostic belongs to.
 *
 * @return string
 */
public static function get_family(): string {
return static::$family;
}

/**
 * Get the family label/display name.
 *
 * @return string
 */
public static function get_family_label(): string {
return static::$family_label;
}

/**
 * Check if this diagnostic belongs to a family.
 *
 * @return bool
 */
public static function has_family(): bool {
return ! empty( static::$family );
}

/**
 * Check if this diagnostic can be grouped/fixed with others in its family.
 *
 * @return bool
 */
public static function is_family_fixable(): bool {
return static::has_family();
}
}
