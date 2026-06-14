<?php
/**
 * Autoloaded Options Total Size Diagnostic
 *
 * Checks that the total byte size of autoloaded wp_options rows does not
 * exceed a safe threshold. Excessive autoload data delays every page load
 * because WordPress fetches it all in a single query on boot.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Diagnostic_Wp_Options_Autoload_Size Class
 *
 * @since 0.6095
 */
class Diagnostic_Wp_Options_Autoload_Size extends Diagnostic_Base {

    /** @var string */
    protected static $slug = 'wp-options-autoload-size';

    /** @var string */
    protected static $title = 'Autoloaded Options Total Size Under Limit';

    /** @var string */
    protected static $description = 'Checks that the total byte size of all autoloaded wp_options rows is under 800 KB. Large autoload payloads slow every page load.';

    /** @var string */
    protected static $family = 'database';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

    /**
     * Run the diagnostic check.
     *
     * Queries wp_options to sum the byte length of all autoloaded option values.
     * Returns null when the total is under 800 KB. Returns a medium finding for
     * 800 KB–2 MB and a high finding above 2 MB, including the top offenders.
     *
     * @since  0.6095
     * @return array|null Finding array when autoload payload is too large, null when healthy.
     */
    public static function check() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $total_bytes = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        $threshold_warn = 800 * 1024;   // 800 KB.
        $threshold_high = 2 * 1024 * 1024; // 2 MB.

        if ( $total_bytes < $threshold_warn ) {
            return null;
        }

        $severity     = $total_bytes >= $threshold_high ? 'high' : 'medium';
        $threat_level = $total_bytes >= $threshold_high ? 60 : 40;
        $size_label   = round( $total_bytes / 1024, 1 ) . ' KB';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $top_options = $wpdb->get_results(
            "SELECT option_name, LENGTH(option_value) AS bytes
             FROM {$wpdb->options}
             WHERE autoload = 'yes'
             ORDER BY bytes DESC
             LIMIT 10"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery

        $top_list = array_map( static function ( $row ) {
            return array(
                'option_name' => $row->option_name,
                'bytes'       => (int) $row->bytes,
            );
        }, $top_options ?: array() );

        return array(
            'id'           => self::$slug,
            'title'        => self::$title,
            'description'  => sprintf(
                /* translators: %s: total autoload size with unit */
                __( 'The total size of autoloaded WordPress options is %s. WordPress loads all autoloaded options on every page request in a single query. A large autoload payload increases memory usage and time-to-first-byte on every page view.', 'thisismyurl-shadow' ),
                $size_label
            ),
            'severity'     => $severity,
            'threat_level' => $threat_level,
            'details'      => array(
                'total_bytes'   => $total_bytes,
                'total_size_kb' => round( $total_bytes / 1024, 1 ),
                'top_options'   => $top_list,
                'explanation_sections' => array(
                    'summary' => sprintf(
                        /* translators: %s: autoload payload size label */
                        __( 'Shadow by Christopher Ross measured your autoload payload at %s. Autoloaded options are loaded on every uncached request before most plugins run, so oversized payloads slow down every page view, not just one specific feature. Large serialized settings from old plugins are a common source of this growth.', 'thisismyurl-shadow' ),
                        $size_label
                    ),
                    'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross executed a byte-length aggregation query across wp_options rows where autoload is set to yes, then compared the total against practical warning and high-risk thresholds. It also sampled the top ten largest autoloaded options so you can identify which records contribute the most to startup overhead.', 'thisismyurl-shadow' ),
                    'why_it_matters' => __( 'Autoload bloat raises memory usage and slows Time To First Byte because WordPress has to fetch and deserialize more data before rendering begins. This can reduce throughput under traffic spikes and make admin actions feel sluggish, especially on smaller hosting plans with constrained PHP workers.', 'thisismyurl-shadow' ),
                    'how_to_fix_it' => __( 'Review the listed largest options first. Remove settings from plugins you no longer use, move large infrequently-used options to autoload=no where safe, and verify object cache behavior after changes. Apply updates incrementally, then run this check again to confirm the payload drops below the warning threshold without breaking plugin behavior.', 'thisismyurl-shadow' ),
                ),
            ),
        );
    }
}
