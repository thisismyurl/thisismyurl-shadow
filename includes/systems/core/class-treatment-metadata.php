<?php
/**
 * Treatment Metadata Registry
 *
 * Single source of truth for the maturity, risk level, category, and
 * reversibility of every treatment Shadow by Christopher Ross ships.
 *
 * Maturity values
 * ---------------
 *  'shipped'  — apply() makes real automated changes; undo() reverts them.
 *  'guidance' — apply() returns manual step-by-step instructions (success=false);
 *               no automated change is made. Undo is irrelevant.
 *
 * Risk level values (normalised from the per-treatment get_risk_level())
 * -----------------------------------------------------------------------
 *  'safe'     — Minimal disruption risk. Was 'safe' or 'low' in source.
 *  'moderate' — Requires care; generally non-destructive. Was 'moderate'/'medium'.
 *  'high'     — Significant disruption risk (file writes, session invalidation, etc).
 *  'guidance' — No automated action; risk lives in the manual steps advised.
 *
 * Category values
 * ---------------
 *  'security' | 'performance' | 'database' | 'content' | 'configuration' | 'maintenance'
 *
 * Operators may override any single field via the 'thisismyurl_shadow_treatment_metadata'
 * filter using deep-merge semantics — only the supplied fields are changed.
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.7055
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides curated metadata for all shipped Shadow by Christopher Ross treatments.
 */
final class Treatment_Metadata {

	/** @var array<string, array{maturity:string, risk_level:string, category:string, reversible:bool}>|null */
	private static $cache = null;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Get metadata for a single treatment slug.
	 *
	 * @param string $slug Treatment slug (e.g. 'auth-keys-and-salts-set').
	 * @return array{maturity:string, risk_level:string, category:string, reversible:bool}|null
	 *         Null when slug is unknown.
	 */
	public static function get( string $slug ): ?array {
		$slug = sanitize_key( $slug );
		$all  = self::build_cache();

		return $all[ $slug ] ?? null;
	}

	/**
	 * Get metadata for all registered treatments.
	 *
	 * @return array<string, array{maturity:string, risk_level:string, category:string, reversible:bool}>
	 */
	public static function get_all(): array {
		return self::build_cache();
	}

	/**
	 * Get a concise count summary suitable for AJAX and governance context payloads.
	 *
	 * @return array{
	 *   total: int,
	 *   shipped: int,
	 *   guidance: int,
	 *   reversible: int,
	 *   by_risk: array{safe:int,moderate:int,high:int,guidance:int},
	 *   by_category: array{security:int,performance:int,database:int,content:int,configuration:int,maintenance:int}
	 * }
	 */
	public static function get_counts(): array {
		$all = self::build_cache();

		$by_risk = array(
			'safe'     => 0,
			'moderate' => 0,
			'high'     => 0,
			'guidance' => 0,
		);

		$by_category = array(
			'security'      => 0,
			'performance'   => 0,
			'database'      => 0,
			'content'       => 0,
			'configuration' => 0,
			'maintenance'   => 0,
		);

		$shipped   = 0;
		$guidance  = 0;
		$reversible = 0;

		foreach ( $all as $meta ) {
			if ( 'shipped' === $meta['maturity'] ) {
				++$shipped;
			} else {
				++$guidance;
			}

			if ( $meta['reversible'] ) {
				++$reversible;
			}

			if ( isset( $by_risk[ $meta['risk_level'] ] ) ) {
				++$by_risk[ $meta['risk_level'] ];
			}

			if ( isset( $by_category[ $meta['category'] ] ) ) {
				++$by_category[ $meta['category'] ];
			}
		}

		return array(
			'total'       => count( $all ),
			'shipped'     => $shipped,
			'guidance'    => $guidance,
			'reversible'  => $reversible,
			'by_risk'     => $by_risk,
			'by_category' => $by_category,
		);
	}

	/**
	 * Flush the internal cache.
	 *
	 * Causes the next call to rebuild metadata from the registry definition
	 * and re-apply all filters. Useful after test setup or plugin reload.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	// -------------------------------------------------------------------------
	// Cache builder
	// -------------------------------------------------------------------------

	/**
	 * Build and return the cached metadata registry.
	 *
	 * The raw registry is built once, then passed through the
	 * 'thisismyurl_shadow_treatment_metadata' filter which accepts deep-merge overrides:
	 * operators need only supply the fields they wish to change.
	 *
	 * @return array<string, array{maturity:string, risk_level:string, category:string, reversible:bool}>
	 */
	private static function build_cache(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$registry = self::raw_registry();

		/**
		 * Filter treatment metadata entries.
		 *
		 * Each override is deep-merged so callers only need to provide the
		 * fields they want to change:
		 *
		 *   add_filter( 'thisismyurl_shadow_treatment_metadata', function( $overrides ) {
		 *       $overrides['auth-keys-and-salts-set']['risk_level'] = 'moderate';
		 *       return $overrides;
		 *   } );
		 *
		 * @since 0.7055
		 * @param array<string, array> $overrides Sparse override map. Default [].
		 */
		$overrides = apply_filters( 'thisismyurl_shadow_treatment_metadata', array() );

		if ( is_array( $overrides ) ) {
			foreach ( $overrides as $slug => $override ) {
				$slug = sanitize_key( (string) $slug );
				if ( '' === $slug || ! is_array( $override ) ) {
					continue;
				}

				$base            = $registry[ $slug ] ?? self::default_entry();
				$registry[$slug] = array_merge( $base, $override );
			}
		}

		self::$cache = self::validate_registry( $registry );

		return self::$cache;
	}

	/**
	 * Return a default entry for unknown/unconfigured treatments.
	 *
	 * @return array{maturity:string, risk_level:string, category:string, reversible:bool}
	 */
	private static function default_entry(): array {
		return array(
			'maturity'   => 'shipped',
			'risk_level' => 'safe',
			'category'   => 'configuration',
			'reversible' => true,
		);
	}

	/**
	 * Validate registry entries, removing or replacing invalid values.
	 *
	 * @param array<string, array> $registry Raw registry (post-filter).
	 * @return array<string, array{maturity:string, risk_level:string, category:string, reversible:bool}>
	 */
	private static function validate_registry( array $registry ): array {
		$valid_maturity   = array( 'shipped', 'guidance' );
		$valid_risk       = array( 'safe', 'moderate', 'high', 'guidance' );
		$valid_categories = array( 'security', 'performance', 'database', 'content', 'configuration', 'maintenance' );
		$out              = array();

		foreach ( $registry as $slug => $entry ) {
			if ( ! is_string( $slug ) || '' === $slug ) {
				continue;
			}

			$out[ $slug ] = array(
				'maturity'   => in_array( $entry['maturity'] ?? '', $valid_maturity, true ) ? $entry['maturity'] : 'shipped',
				'risk_level' => in_array( $entry['risk_level'] ?? '', $valid_risk, true ) ? $entry['risk_level'] : 'safe',
				'category'   => in_array( $entry['category'] ?? '', $valid_categories, true ) ? $entry['category'] : 'configuration',
				'reversible' => isset( $entry['reversible'] ) ? (bool) $entry['reversible'] : true,
			);
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Raw registry — the curated per-treatment metadata
	//
	// Maturity key:   'shipped' = automated apply+undo | 'guidance' = manual steps
	// Risk key:       'safe' | 'moderate' | 'high' | 'guidance'
	// Reversible key: true when undo() restores the prior state automatically
	// -------------------------------------------------------------------------

	/**
	 * Return the raw, unfiltered treatment metadata registry.
	 *
	 * @return array<string, array{maturity:string, risk_level:string, category:string, reversible:bool}>
	 */
	private static function raw_registry(): array {
		return array(

			// -----------------------------------------------------------------
			// SECURITY (22 treatments)
			// -----------------------------------------------------------------

			'admin-session-expiration-hardened' => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'security',
				'reversible' => true,
			),
			'auth-keys-and-salts-set'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'database-prefix-intentional'       => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'security',
				'reversible' => false,
			),
			'db-charset-collation-correct'      => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'directory-listing-disabled'        => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'file-mods-policy-defined'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'file-editor-disabled'             => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'file-permissions'                  => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'security',
				'reversible' => true,
			),
			'force-ssl-admin'                   => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'plugin-auto-updates'              => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'security',
				'reversible' => true,
			),
			'form-rate-limiting-active'         => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'security',
				'reversible' => true,
			),
			'https-enabled'                     => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'security',
				'reversible' => false,
			),
			'login-throttling-active'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'security',
				'reversible' => true,
			),
			'login-url-hardening'               => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'security',
				'reversible' => true,
			),
			'registration-setting-intentional' => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'security',
				'reversible' => true,
			),
			'mixed-content-eliminated'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'query-debug-logging-disabled-production' => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'fatal-error-handler-enabled'      => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'security-headers-present'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'security',
				'reversible' => true,
			),
			'sensitive-files-protected'         => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'readme-html-protected'            => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'ssl-certificate-valid'             => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'security',
				'reversible' => false,
			),
			'uploads-php-execution-blocked'     => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'wp-config-location'                => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'security',
				'reversible' => false,
			),
			'wp-config-permissions-hardened'    => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'security',
				'reversible' => true,
			),
			'wp-debug-display-off'              => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),
			'wp-debug-log-private'              => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'security',
				'reversible' => true,
			),

			// -----------------------------------------------------------------
			// PERFORMANCE (18 treatments)
			// -----------------------------------------------------------------

			'adjacent-posts-links'              => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'autosave-interval-optimized'       => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'block-library-css'                 => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'browser-caching-headers'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'compression-enabled'               => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'concatenate-scripts-disabled'      => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'performance',
				'reversible' => true,
			),
			'dashicons-frontend'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'embed-assets'                      => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'emoji-assets'                      => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'emoji-in-admin'                    => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'heartbeat-usage'                   => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'http2-or-http3-enabled'            => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'performance',
				'reversible' => false,
			),
			'image-lazy-loading'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'jpeg-quality'                      => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'large-image-threshold'             => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'noncritical-js-deferred'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'performance',
				'reversible' => true,
			),
			'opcache-enabled'                   => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'performance',
				'reversible' => false,
			),
			'post-revision-limit-set'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'performance',
				'reversible' => true,
			),
			'script-debug-production'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'performance',
				'reversible' => true,
			),
			'trash-auto-empty-configured'       => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'performance',
				'reversible' => true,
			),
			'error-logging'                     => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'configuration',
				'reversible' => true,
			),

			// -----------------------------------------------------------------
			// DATABASE (13 treatments)
			// -----------------------------------------------------------------

			'database-indexes-missing'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => true,
			),
			'database-version-supported'        => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'database',
				'reversible' => false,
			),
			'expired-transients-cleared'        => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => false,
			),
			'innodb-storage-engine-used'        => array(
				'maturity'   => 'shipped',
				'risk_level' => 'high',
				'category'   => 'database',
				'reversible' => true,
			),
			'orphaned-comments'                 => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => false,
			),
			'orphaned-post-meta'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => false,
			),
			'orphaned-term-relationships'       => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => false,
			),
			'php-memory-limit-optimized'        => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => true,
			),
			'site-charset-utf8'                 => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => true,
			),
			'transients-cleanup'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => false,
			),
			'php-version'                       => array(
				'maturity'   => 'guidance',
				'risk_level' => 'guidance',
				'category'   => 'database',
				'reversible' => false,
			),
			'auto-draft-accumulation'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => false,
			),
			'orphaned-user-meta'               => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'database',
				'reversible' => false,
			),

			// -----------------------------------------------------------------
			// CONTENT (18 treatments)
			// -----------------------------------------------------------------

			'default-category-renamed'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'default-comment-removed'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'content',
				'reversible' => true,
			),
			'default-image-size'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'default-page-removed'              => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'content',
				'reversible' => true,
			),
			'default-page-slug-updated'         => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'default-post-removed'              => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'content',
				'reversible' => true,
			),
			'default-post-slug-updated'         => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'image-link-default'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'homepage-page-published'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'content',
				'reversible' => true,
			),
			'media-attachment-pages'            => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'posts-page-published'             => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'content',
				'reversible' => true,
			),
			'search-page-indexing'             => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'rss-feed-summary'                  => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'rss-version-leak'                  => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),
			'uncategorized-usage'               => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'content',
				'reversible' => true,
			),

			// -----------------------------------------------------------------
			// CONFIGURATION (16 treatments)
			// -----------------------------------------------------------------

			'cron-overlap-protection-enabled'   => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'default-role-subscriber'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'default-user-role'                 => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'auto-update-policy'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'maintenance-mode-off'             => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'maintenance',
				'reversible' => true,
			),
			'media-year-month-folders-enabled' => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'oembed-discovery-links'            => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'permalink-structure-meaningful'    => array(
				'maturity'   => 'shipped',
				'risk_level' => 'moderate',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'posts-per-page-optimized'          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'pingback-head-link'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'search-engine-visibility-intentional' => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'rest-api-head-link'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'rsd-link'                          => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'rss-head-links'                    => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'shortlink-head-tag'                => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'update-services-intentional'       => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'wlwmanifest-link'                  => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),
			'wp-generator-tag'                  => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'configuration',
				'reversible' => true,
			),

			// -----------------------------------------------------------------
			// MAINTENANCE (8 treatments)
			// -----------------------------------------------------------------

			'comment-link-limit-set'            => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'maintenance',
				'reversible' => true,
			),
			'comment-moderation-enabled'        => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'maintenance',
				'reversible' => true,
			),
			'comments-auto-close-old-posts'     => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'maintenance',
				'reversible' => true,
			),
			'discussion-defaults'               => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'maintenance',
				'reversible' => true,
			),
			'dashboard-rss-widget-active'       => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'maintenance',
				'reversible' => true,
			),
			'jetpack-stats-admin-bar'           => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'maintenance',
				'reversible' => true,
			),
			'pingbacks-trackbacks'              => array(
				'maturity'   => 'shipped',
				'risk_level' => 'safe',
				'category'   => 'maintenance',
				'reversible' => true,
			),
		);
	}
}
