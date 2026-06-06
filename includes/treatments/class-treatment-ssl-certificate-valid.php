<?php
/**
 * Treatment: SSL Certificate Valid
 *
 * Provides guidance for renewing or replacing an invalid, expired, or
 * mis-issued SSL certificate. Certificate management must be performed at
 * the hosting or DNS level; a WordPress plugin cannot issue or renew TLS certs.
 *
 * Risk level: n/a (guidance only)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns step-by-step certificate renewal guidance.
 */
class Treatment_Ssl_Certificate_Valid extends Treatment_Base {

	/** @var string */
	protected static $slug = 'ssl-certificate-valid';

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	public static function get_finding_id(): string {
		return self::$slug;
	}

	public static function get_risk_level(): string {
		return 'none';
	}

	/**
	 * Return SSL certificate renewal guidance.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		$now    = gmdate( 'Y-m-d' );
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: 1: current date, 2: domain */
				__(
					"SSL certificate issues must be resolved at the hosting or server level.

COMMON CAUSES:
  • Certificate expired — must be renewed.
  • Certificate issued for a different domain (CN mismatch).
  • Self-signed certificate (not trusted by browsers).
  • Certificate chain is incomplete (missing intermediate CA).

OPTION 1 — cPanel / Shared Hosting:
  1. Log in to your control panel and find 'SSL/TLS' or 'AutoSSL'.
  2. Click 'Renew' or 'Re-issue' next to %2\$s.
  3. Or use Let's Encrypt: look for a 'Let's Encrypt' icon and issue a new certificate.
  4. Provisioning takes 1–5 minutes; then verify at https://%2\$s.

OPTION 2 — Certbot (Apache/Nginx on VPS):
  sudo certbot renew --force-renewal
  sudo service apache2 restart   # or nginx

OPTION 3 — Cloudflare:
  1. In Cloudflare dashboard, go to SSL/TLS → Edge Certificates.
  2. Cloudflare auto-renews edge certificates. Check that 'Always Use HTTPS' is on.
  3. Verify the origin certificate (between Cloudflare and your server) is valid.

VERIFICATION (run on server):
  openssl s_client -connect %2\$s:443 -servername %2\$s 2>&1 | openssl x509 -noout -dates

Today's date: %1\$s. Re-run the Christopher Ross Shadow scan after renewing the certificate.",
					'thisismyurl-shadow'
				),
				$now,
				$domain
			),
		];
	}

	/**
	 * No state to undo (guidance only).
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function undo(): array {
		return [
			'success' => true,
			'message' => __( 'This is a guidance-only treatment — no changes were made by Christopher Ross Shadow.', 'thisismyurl-shadow' ),
		];
	}
}
