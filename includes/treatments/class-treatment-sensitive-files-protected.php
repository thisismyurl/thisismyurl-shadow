<?php
/**
 * Treatment: Block access to sensitive files via .htaccess
 *
 * Several files commonly found in WordPress documentroots leak credentials
 * or server information if served publicly: .env files, debug logs, SQL
 * dumps, phpinfo scripts, wp-config backup copies, and the .git config file.
 * This treatment:
 *
 *   1. Adds .htaccess rules in the document root that deny HTTP access to
 *      those file patterns (Apache). The block is idempotent and removable.
 *   2. Deletes any phpinfo.php or wp-config*.bak / wp-config*.old files it
 *      finds in ABSPATH — these have no legitimate purpose in production.
 *
 * The .htaccess approach is server-agnostic fallback protection; on nginx
 * servers the rules have no effect (Nginx does not read .htaccess) and a
 * separate server-level fix is recommended.
 *
 * Undo: removes the .htaccess marker block. Deleted files cannot be restored.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds .htaccess rules blocking access to known sensitive files.
 */
class Treatment_Sensitive_Files_Protected extends Treatment_Base {

	/** @var string */
	protected static $slug = 'sensitive-files-protected';

	/** @var string */
	private const MARKER = 'This Is My URL Shadow Sensitive Files';

	/** @return string */
	public static function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Write denial rules into the root .htaccess and delete known-dangerous files.
	 *
	 * @return array
	 */
	public static function apply(): array {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$htaccess = ABSPATH . '.htaccess';
		$messages = array();

		// --- .htaccess block ------------------------------------------------
		$rules = array(
			'# Block access to sensitive files — managed by Shadow by Christopher Ross',
			'<FilesMatch "^(\.env|\.env\..+|phpinfo\.php|debug\.log|error_log)$">',
			'  Order Deny,Allow',
			'  Deny from all',
			'  <IfModule mod_authz_core.c>',
			'    Require all denied',
			'  </IfModule>',
			'</FilesMatch>',
			'<FilesMatch "(wp-config\.(bak|old|backup|orig|save)|database\.sql|dump\.sql|backup\.sql)$">',
			'  Order Deny,Allow',
			'  Deny from all',
			'  <IfModule mod_authz_core.c>',
			'    Require all denied',
			'  </IfModule>',
			'</FilesMatch>',
			'# Block .git directory access',
			'<IfModule mod_rewrite.c>',
			'  RewriteEngine On',
			'  RewriteRule ^\.git(/.*)?$ - [F,L]',
			'</IfModule>',
		);

		$htaccess_written = insert_with_markers( $htaccess, self::MARKER, $rules );

		if ( $htaccess_written ) {
			$messages[] = __( 'Added .htaccess rules to block public access to .env, debug logs, SQL dumps, and .git files.', 'thisismyurl-shadow' );
		} else {
			$messages[] = __( 'Warning: could not write to root .htaccess. Rules were not applied — check file permissions.', 'thisismyurl-shadow' );
		}

		// --- Delete obviously dangerous files --------------------------------
		$deletable_patterns = array(
			ABSPATH . 'phpinfo.php',
			ABSPATH . 'wp-config.bak',
			ABSPATH . 'wp-config.old',
			ABSPATH . 'wp-config.backup',
			ABSPATH . 'wp-config.orig',
			ABSPATH . 'wp-config.save',
		);

		$deleted = array();

		foreach ( $deletable_patterns as $file ) {
			if ( file_exists( $file ) ) {
				if ( wp_delete_file( $file ) ) {
					$deleted[] = basename( $file );
				}
			}
		}

		if ( ! empty( $deleted ) ) {
			$messages[] = sprintf(
				/* translators: %s: comma-separated list of deleted filenames */
				__( 'Deleted sensitive files from the document root: %s', 'thisismyurl-shadow' ),
				implode( ', ', $deleted )
			);
		}

		return array(
			'success' => $htaccess_written,
			'message' => implode( ' ', $messages ),
			'details' => array(
				'htaccess_path' => $htaccess,
				'deleted_files' => $deleted,
			),
		);
	}

	/**
	 * Remove the Shadow by Christopher Ross sensitive-files block from root .htaccess.
	 *
	 * Deleted files are not restored — this only removes the .htaccess rules.
	 *
	 * @return array
	 */
	public static function undo(): array {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$htaccess = ABSPATH . '.htaccess';
		$result   = insert_with_markers( $htaccess, self::MARKER, array() );

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update .htaccess. Remove the This Is My URL Shadow Sensitive Files block manually.', 'thisismyurl-shadow' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Sensitive-files .htaccess block removed. Note: any deleted files (phpinfo.php, wp-config backups, etc.) were not restored.', 'thisismyurl-shadow' ),
		);
	}
}
