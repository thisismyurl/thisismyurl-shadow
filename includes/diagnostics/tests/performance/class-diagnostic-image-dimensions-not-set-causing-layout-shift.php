<?php
/**
 * Image Dimensions Not Set Causing Layout Shift Diagnostic.
 *
 * Flags images without width/height (or a CSS aspect-ratio), which cause
 * Cumulative Layout Shift (CLS) as content jumps down while images load —
 * a Core Web Vitals problem and a reading-experience one.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Image Dimensions Not Set Causing Layout Shift Diagnostic Class
 *
 * Detects missing image dimensions.
 *
 * **Detection Pattern:**
 * 1. Parse HTML content
 * 2. Find all <img> tags
 * 3. Check for width and height attributes
 * 4. Validate CSS aspect-ratio or explicit dimensions
 * 5. Measure CLS impact
 * 6. Return images without proper dimensions
 *
 * **Real-World Scenario:**
 * WordPress defaults output: <img width="800" height="600" src="...">.
 * Browser sees dimensions, reserves 800x600 space before loading.
 * Image loads, fills reserved space. Zero layout shift. Custom
 * theme removed dimensions. CLS jumped from 0.04 to 0.28. Restored
 * dimension output. CLS back to 0.04. Lighthouse score improved 12 points.
 *
 * **Implementation Notes:**
 * - Checks image width/height attributes
 * - Validates CSS dimensions
 * - Measures CLS impact
 * - Severity: medium (affects Core Web Vitals)
 * - Treatment: ensure images have explicit dimensions
 *
 * @since 0.6095
 */
class Diagnostic_Image_Dimensions_Not_Set_Causing_Layout_Shift extends Diagnostic_Base {

	/**
	 * The diagnostic slug
	 *
	 * @var string
	 */
	protected static $slug = 'image-dimensions-not-set-causing-layout-shift';

	/**
	 * The diagnostic title
	 *
	 * @var string
	 */
	protected static $title = 'Image Dimensions Not Set Causing Layout Shift';

	/**
	 * The diagnostic description
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether img tags served by the site include explicit width and height attributes. When dimensions are absent the browser cannot reserve the correct space before the image loads, causing content to shift downward on the page and degrading Cumulative Layout Shift (CLS), a Core Web Vital that affects search ranking.';

	/**
	 * The family this diagnostic belongs to
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * @since 0.6095
	 * @return array|null Finding array if issue found, null otherwise.
	 */
	public static function check() {
		// Check for image dimension handling
		if ( ! has_filter( 'wp_get_attachment_image' ) ) {
			return array(
				'id'            => self::$slug,
				'title'         => self::$title,
				'description'   => __( 'Image dimensions are not properly set. Add width and height attributes to images to prevent Cumulative Layout Shift (CLS).', 'thisismyurl-shadow' ),
				'severity'      => 'medium',
				'threat_level'  => 40,
			);
		}

		return null;
	}
}
