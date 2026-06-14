<?php
/**
 * SSL Certificate Valid Diagnostic
 *
 * Verifies that the site SSL certificate is trusted, not expired, and not
 * expiring within the configured warning window.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_WP_Settings_Helper as WP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Ssl_Certificate_Valid Class
 *
 * Opens a TLS stream socket to the site hostname on port 443, parses the
 * peer certificate, and reports expired, soon-to-expire, or untrusted certs.
 *
 * @since 0.6095
 */
class Diagnostic_Ssl_Certificate_Valid extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'ssl-certificate-valid';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'SSL Certificate Valid';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Verifies that the site SSL certificate is trusted, not expired, and not expiring soon.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Days before expiry that trigger a warning finding.
	 *
	 * @var int
	 */
	const EXPIRY_WARNING_DAYS = 30;

	/**
	 * Whether this diagnostic can run in the current environment.
	 *
	 * Returns false when the openssl extension is unavailable or the site
	 * is not served over HTTPS.
	 *
	 * @return bool
	 */
	public static function is_applicable(): bool {
		return extension_loaded( 'openssl' ) && WP_Settings::is_site_url_https();
	}

	/**
	 * Run the diagnostic check.
	 *
	 * Opens a TLS stream socket to port 443, captures the peer certificate,
	 * and evaluates trust, expiry, and hostname match.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when the certificate has an issue, null when healthy.
	 */
	public static function check() {
		$host = wp_parse_url( get_option( 'siteurl' ), PHP_URL_HOST );
		if ( empty( $host ) ) {
			return null;
		}

		// Attempt a verified TLS handshake first to detect untrusted CAs.
		$context = stream_context_create( array(
			'ssl' => array(
				'capture_peer_cert'  => true,
				'verify_peer'        => true,
				'verify_peer_name'   => true,
				'peer_name'          => $host,
				'SNI_enabled'        => true,
			),
		) );

		$socket = @stream_socket_client(
			'ssl://' . $host . ':443',
			$errno,
			$errstr,
			10,
			STREAM_CLIENT_CONNECT,
			$context
		);

		// TLS handshake failed — untrusted CA or expired cert.
		if ( false === $socket ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: 1: host, 2: error message */
					__( 'The SSL/TLS certificate for %1$s could not be verified. The browser will display a security warning and visitors may be blocked from accessing your site. Error: %2$s', 'thisismyurl-shadow' ),
					$host,
					$errstr
				),
				'severity'     => 'critical',
				'threat_level' => 90,
				'details'      => array(
					'host'     => $host,
					'error'    => $errstr,
					'errno'    => $errno,
					'fix'      => __( 'Renew or replace the SSL certificate and ensure the full certificate chain is installed correctly.', 'thisismyurl-shadow' ),
				),
			);
		}

		// Grab the peer cert from the stream context for further inspection.
		$params = stream_context_get_params( $socket );
		fclose( $socket ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- $socket is a network stream from stream_socket_client(), not a filesystem handle; WP_Filesystem does not apply.

		$cert_resource = $params['options']['ssl']['peer_certificate'] ?? null;
		if ( empty( $cert_resource ) ) {
			return null; // Cannot parse — skip to avoid false positives.
		}

		$cert_info = openssl_x509_parse( $cert_resource );
		if ( ! is_array( $cert_info ) ) {
			return null;
		}

		$valid_to   = $cert_info['validTo_time_t'] ?? 0;
		$valid_from = $cert_info['validFrom_time_t'] ?? 0;
		$now        = time();

		// Certificate is already expired.
		if ( $valid_to > 0 && $now > $valid_to ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %s: expiry date */
					__( 'Your SSL certificate expired on %s. Browsers are blocking visitors with security warnings. Renew your certificate immediately.', 'thisismyurl-shadow' ),
					gmdate( 'Y-m-d', $valid_to )
				),
				'severity'     => 'critical',
				'threat_level' => 90,
				'details'      => array(
					'host'       => $host,
					'expired_at' => gmdate( 'Y-m-d', $valid_to ),
					'fix'        => __( 'Renew your SSL certificate with your hosting provider or certificate authority.', 'thisismyurl-shadow' ),
				),
			);
		}

		// Certificate expiring soon.
		$days_remaining = (int) floor( ( $valid_to - $now ) / DAY_IN_SECONDS );
		if ( $days_remaining <= self::EXPIRY_WARNING_DAYS ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: 1: days remaining, 2: expiry date */
					_n(
						'Your SSL certificate expires in %1$d day (on %2$s). Renew it now to avoid service interruption.',
						'Your SSL certificate expires in %1$d days (on %2$s). Renew it soon to avoid service interruption.',
						$days_remaining,
						'thisismyurl-shadow'
					),
					$days_remaining,
					gmdate( 'Y-m-d', $valid_to )
				),
				'severity'     => 'high',
				'threat_level' => 70,
				'details'      => array(
					'host'            => $host,
					'days_remaining'  => $days_remaining,
					'expires_at'      => gmdate( 'Y-m-d', $valid_to ),
					'fix'             => __( 'Log in to your hosting control panel or certificate authority and renew your SSL certificate before it expires.', 'thisismyurl-shadow' ),
				),
			);
		}

		// Hostname mismatch check.
		$cn   = $cert_info['subject']['CN'] ?? '';
		$sans = array();
		if ( ! empty( $cert_info['extensions']['subjectAltName'] ) ) {
			preg_match_all( '/DNS:([^\s,]+)/', $cert_info['extensions']['subjectAltName'], $san_matches );
			$sans = $san_matches[1] ?? array();
		}

		$hostname_valid = false;
		$all_names      = array_merge( array( $cn ), $sans );
		foreach ( $all_names as $name ) {
			if ( self::hostname_matches_cert_name( $host, $name ) ) {
				$hostname_valid = true;
				break;
			}
		}

		if ( ! $hostname_valid ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %s: site hostname */
					__( 'The SSL certificate does not list %s as a valid hostname. Browsers will show a certificate mismatch warning. You may need to reissue or replace the certificate to include this domain.', 'thisismyurl-shadow' ),
					$host
				),
				'severity'     => 'medium',
				'threat_level' => 55,
				'details'      => array(
					'host'       => $host,
					'cert_cn'    => $cn,
					'cert_sans'  => $sans,
					'fix'        => __( 'Reissue your SSL certificate to include this domain as a Subject Alternative Name (SAN).', 'thisismyurl-shadow' ),
				),
			);
		}

		return null;
	}

	/**
	 * Check whether a hostname matches a certificate name (supports wildcards).
	 *
	 * @param string $hostname  The actual hostname to test.
	 * @param string $cert_name The CN or SAN entry from the certificate.
	 * @return bool
	 */
	private static function hostname_matches_cert_name( string $hostname, string $cert_name ): bool {
		$hostname  = strtolower( $hostname );
		$cert_name = strtolower( $cert_name );

		if ( $hostname === $cert_name ) {
			return true;
		}

		// Wildcard: *.example.com should match sub.example.com but not deep.sub.example.com.
		if ( str_starts_with( $cert_name, '*.' ) ) {
			$wildcard_base = substr( $cert_name, 2 );
			$dot_pos       = strpos( $hostname, '.' );
			if ( false !== $dot_pos ) {
				return substr( $hostname, $dot_pos + 1 ) === $wildcard_base;
			}
		}

		return false;
	}
}
