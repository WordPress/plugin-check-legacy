<?php

class Test_Functions extends PluginCheck_TestCase {
	public function test_str_rot13() {
		$usage = 'echo str_rot13( "WordPress" );';

		$results = $this->run_against_string( $usage );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'Generic.PHP.ForbiddenFunctions.Found', 'needle' => 'function str_rot13() is forbidden' ] );
	}

	public function test_base64_encode() {
		$usage = 'echo base64_encode( "WordPress" );';

		$results = $this->run_against_string( $usage );

		$this->assertHasErrorType( $results, [ 'type' => 'warning', 'code' => 'Generic.PHP.ForbiddenFunctions.Found', 'needle' => 'function base64_encode() is forbidden' ] );
	}

	public function test_base64_decode() {
		$usage = 'echo base64_decode( "V29yZFByZXNz" );';

		$results = $this->run_against_string( $usage );

		$this->assertHasErrorType( $results, [ 'type' => 'warning', 'code' => 'Generic.PHP.ForbiddenFunctions.Found', 'needle' => 'function base64_decode() is forbidden' ] );
	}

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
