<?php
/**
 * Loader class — registers all actions and filters for the plugin.
 *
 * @package CraftsmenitVideoPlatformForYouTube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and dispatches WordPress hooks for the plugin.
 */
class YTCP_Loader {

	/**
	 * Collection of registered actions.
	 *
	 * @var array
	 */
	private $actions = array();

	/**
	 * Collection of registered filters.
	 *
	 * @var array
	 */
	private $filters = array();

	/**
	 * Queues a new action to be registered with WordPress.
	 *
	 * @param string $hook          The hook name.
	 * @param object $component     The object instance.
	 * @param string $callback      The method name on the component.
	 * @param int    $priority      Hook priority.
	 * @param int    $accepted_args Number of arguments the callback accepts.
	 * @return void
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Queues a new filter to be registered with WordPress.
	 *
	 * @param string $hook          The hook name.
	 * @param object $component     The object instance.
	 * @param string $callback      The method name on the component.
	 * @param int    $priority      Hook priority.
	 * @param int    $accepted_args Number of arguments the callback accepts.
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Registers all queued actions and filters with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
}
