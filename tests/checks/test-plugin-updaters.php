<?php

class Test_Plugin_Updaters extends PluginCheck_TestCase {
	public function test_update_uri() {
		$usage = '
		/*
		 * Plugin Name: Test Plugin
		 * Update URI: https://example.org/
		 */
		';

		$results = $this->run_against_string( $usage );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'plugin_updater_detected', 'needle' => 'Update URI header' ] );
	}
}
