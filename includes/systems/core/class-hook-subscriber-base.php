<?php
/**
 * Hook Subscriber Base Class
 *
 * Abstract base class that enables convention-based hook registration.
 * Eliminates the need for manual add_action() and add_filter() calls.
 *
 * Usage:
 * 1. Extend this class
 * 2. Implement get_hooks() method
 * 3. Call YourClass::subscribe() once
 *
 * Philosophy:
 * - Commandment #7: Ridiculously Good (self-documenting hook subscriptions)
 * - Commandment #12: Expandable (easy for developers to extend)
 * - DRY: Zero repetitive add_action() calls
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Core
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hook_Subscriber_Base Class
 *
 * Base class for all hook subscribers using convention-based registration.
 *
 * @since 0.6095
 */
abstract class Hook_Subscriber_Base {

	/**
	 * Get hook subscriptions.
	 *
	 * Return an array of hook subscriptions in the format:
	 * [
	 *     'hook_name' => 'method_name',
	 *     'hook_name' => ['method_name', priority],
	 *     'hook_name' => ['method_name', priority, accepted_args],
	 * ]
	 *
	 * @since 0.6095
	 * @return array Hook subscriptions.
	 */
	abstract protected static function get_hooks(): array;

	/**
	 * Subscribe to all hooks defined in get_hooks().
	 *
	 * Automatically registers all actions and filters based on hook name patterns:
	 * - If hook name contains 'filter', uses add_filter()
	 * - Otherwise uses add_action()
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function subscribe(): void {
		$hooks = static::get_hooks();

		foreach ( $hooks as $hook => $config ) {
			$configs = ( is_array( $config ) && isset( $config[0] ) && is_array( $config[0] ) )
				? $config
				: array( $config );

			foreach ( $configs as $entry ) {
				if ( is_string( $entry ) ) {
					$method   = $entry;
					$priority = 10;
					$args     = 1;
				} elseif ( is_array( $entry ) ) {
					$method   = $entry[0] ?? '';
					$priority = $entry[1] ?? 10;
					$args     = $entry[2] ?? 1;
				} else {
					continue;
				}

				if ( ! is_string( $method ) || '' === $method || ! method_exists( static::class, $method ) ) {
					continue;
				}

				// Determine if this is a filter or action based on hook name
				$is_filter = strpos( $hook, 'filter_' ) === 0 || strpos( $hook, '_filter' ) !== false;

				if ( $is_filter ) {
					add_filter( $hook, array( static::class, $method ), $priority, $args );
				} else {
					add_action( $hook, array( static::class, $method ), $priority, $args );
				}
			}
		}
	}

	/**
	 * Convenience method for filters.
	 *
	 * Sugar syntax for registering filters specifically.
	 *
	 * @since 0.6095
	 * @return array Filter subscriptions.
	 */
	protected static function get_filters(): array {
		return array();
	}

	/**
	 * Convenience method for actions.
	 *
	 * Sugar syntax for registering actions specifically.
	 *
	 * @since 0.6095
	 * @return array Action subscriptions.
	 */
	protected static function get_actions(): array {
		return array();
	}
}
