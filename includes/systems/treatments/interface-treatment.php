<?php
/**
 * Treatment Interface
 *
 * Defines the contract for treatment implementations. All treatments must implement
 * these methods to be recognized and executed by the Shadow by Christopher Ross system.
 *
 * **Architecture:**
 * Treatment system uses interface-based architecture:
 * - Treatment_Interface: Defines what methods treatments MUST implement
 * - Treatment_Base: Provides common implementation, extends interface
 * - Each treatment class: Implements interface via extending Treatment_Base
 *
 * **Why Interfaces Matter Here:**
 * - Ensures all treatments have consistent behavior
 * - Enables Shadow by Christopher Ross framework to treat all treatments uniformly
 * - Type safety: code that expects Treatment_Interface gets consistent behavior
 * - Future-proofing: new feature additions can be added to interface
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Interface for treatment/fix implementations
 *
 * Every treatment class must implement this interface to be recognized by the
 * Shadow by Christopher Ross framework. Use Treatment_Base instead of implementing directly.
 *
 * **Contract Requirements:**
 * - `get_finding_id()` - Returns diagnostic ID this treatment fixes
 * - `can_apply()` - Checks user permissions and environment compatibility
 * - `apply()` - Implements the actual fix (persistent change)
 * - `execute()` - Wrapper that calls apply() with hooks and logging
 *
 * **Implementation Example:**
 * ```php
 * class Treatment_My_Fix extends Treatment_Base {
 *     // Interface methods (inherited from Treatment_Base):
 *     public static function get_finding_id() { return 'my-check'; }
 *     public static function can_apply() {
 *         return parent::can_apply();
 *     }
 *     public static function apply() {
 *         // Your fix logic here
 *         return [ 'success' => true ];
 *     }
 * }
 * ```
 *
 * @since 0.6095
 */
interface Treatment_Interface {
	/**
	 * Get the finding ID this treatment addresses
	 *
	 * @return string
	 */
	public static function get_finding_id();

	/**
	 * Check if this treatment can be applied
	 *
	 * @return bool True if treatment can run.
	 */
	public static function can_apply();

	/**
	 * Apply the treatment/fix
	 *
	 * @return array Result with 'success' bool and 'message' string.
	 */
	public static function apply();

	/**
	 * Undo the treatment (if possible)
	 *
	 * @return array Result with 'success' bool and 'message' string.
	 */
	public static function undo();
}
