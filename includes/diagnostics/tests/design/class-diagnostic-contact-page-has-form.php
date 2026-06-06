<?php
/**
 * Contact Page Has a Form Diagnostic
 *
 * Checks that the site's contact page contains shortcode or block markup
 * consistent with a web form plugin. A contact page with no form is a missed
 * conversion opportunity.
 *
 * @package    Christopher Ross Shadow
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
 * Diagnostic_Contact_Page_Has_Form Class
 *
 * Finds the published page most likely to be a contact page by title slug,
 * then inspects its post_content for known form plugin shortcodes and block
 * names. Returns a low-severity finding when no form markup is detected.
 *
 * @since 0.6095
 */
class Diagnostic_Contact_Page_Has_Form extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'contact-page-has-form';

	/**
	 * @var string
	 */
	protected static $title = 'Contact Page Has a Form';

	/**
	 * @var string
	 */
	protected static $description = 'Checks that the site\'s contact page contains an actual web form. A contact page with no form is a missed conversion opportunity.';

	/**
	 * @var string
	 */
	protected static $family = 'design';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Searches published pages whose post_name or post_title contains 'contact'.
	 * If none is found, returns null (no contact page to evaluate). If a contact
	 * page is found, checks its post_content for known form plugin shortcodes and
	 * Gutenberg block names. Returns a low-severity finding when no form markup
	 * is detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when contact page has no form, null when healthy or not applicable.
	 */
	public static function check() {
		// Find the most likely contact page.
		$contact_pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			's'              => 'contact',
			'fields'         => 'all',
		) );

		if ( empty( $contact_pages ) ) {
			return null; // No contact page found — skip.
		}

		// Prefer exact slug/title matches.
		$contact_page = null;
		foreach ( $contact_pages as $page ) {
			if ( in_array( $page->post_name, array( 'contact', 'contact-us', 'contactus', 'get-in-touch' ), true ) ) {
				$contact_page = $page;
				break;
			}
		}
		if ( null === $contact_page ) {
			$contact_page = $contact_pages[0];
		}

		$content = $contact_page->post_content;

		// Patterns from popular form plugins (shortcodes and block names).
		$form_patterns = array(
			'[contact-form-7',
			'[gravityforms',
			'[wpforms',
			'[formidable',
			'[ninja_forms',
			'[caldera_form',
			'[wpcf7',
			'[ws_form',
			'[fluentform',
			'wp:contact-form-7',
			'wp:wpforms',
			'wp:gravityforms',
			'wp:gf',
			'wp:fluent-forms',
			'<form',
			'class="wpcf7',
			'class="wpforms',
		);

		foreach ( $form_patterns as $pattern ) {
			if ( str_contains( $content, $pattern ) ) {
				return null; // Form markup detected — healthy.
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: page title */
				__( 'The "%s" page does not appear to contain a contact form. Visitors who want to get in touch need a clear, frictionless way to do so. Add a form using a plugin such as Contact Form 7, WPForms, or Gravity Forms.', 'thisismyurl-shadow' ),
				$contact_page->post_title
			),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'page_id'    => $contact_page->ID,
				'page_title' => $contact_page->post_title,
				'page_slug'  => $contact_page->post_name,
			),
		);
	}
}
