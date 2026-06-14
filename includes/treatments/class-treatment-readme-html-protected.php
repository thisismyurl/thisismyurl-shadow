<?php
/**
 * Treatment: Block public access to readme.html
 *
 * Adds an Apache `.htaccess` rule denying direct access to `/readme.html`.
 * That prevents version disclosure through the stock WordPress readme file
 * while keeping the change reversible.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.7056
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;

require_once __DIR__ . '/trait-file-write-helpers.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Treatment_Readme_Html_Protected extends Treatment_Base {

	use File_Write_Helpers;

	/** @var string */
	protected static $slug = 'readme-html-protected';

	private const MARKER_SLUG = 'readme-html-protected';
	private const HTACCESS_BLOCK = '<Files "readme.html">
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</Files>';

	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	public static function get_risk_level(): string {
		return 'high';
	}

	public static function apply(): array {
		return self::write_htaccess_block( self::get_target_file(), self::MARKER_SLUG, self::HTACCESS_BLOCK );
	}

	public static function undo(): array {
		return self::remove_htaccess_block( self::get_target_file(), self::MARKER_SLUG );
	}

	public static function get_target_file(): string {
		return ABSPATH . '.htaccess';
	}

	public static function get_file_label(): string {
		return '.htaccess';
	}

	public static function get_proposed_change_summary(): string {
		return __( 'Block direct public access to readme.html via .htaccess', 'thisismyurl-shadow' );
	}

	public static function get_proposed_snippet(): string {
		return "# thisismyurl_shadow_MARKER_START: " . self::MARKER_SLUG . "\n" . self::HTACCESS_BLOCK . "\n# thisismyurl_shadow_MARKER_END: " . self::MARKER_SLUG;
	}

	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", array(
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Remove the Shadow by Christopher Ross block that wraps the readme.html deny rule.",
			"Save the file and reload your site.",
		) );
	}
}

Treatment_Readme_Html_Protected::boot();