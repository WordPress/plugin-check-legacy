<?php

/**
 * @group Checks
 * @group Trademarks
 */
class Test_Trademark_Checks extends PluginCheck_TestCase {
	public function test_plugin_headers() {
		$results = $this->run_against_string('<?php
			// Plugin Name: Example WordPress Plugin
		' );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'trademarked_term', 'needle' => 'wordpress' ] );
	}

	public function test_readme() {
		$results = $this->run_against_virtual_files( [
			'readme.txt' => '=== Example WordPress ==='
		] );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'trademarked_term', 'needle' => 'wordpress' ] );
	}

	public function test_plugin_headers_for_use_exception() {
		$results = $this->run_against_string('<?php
			// Plugin Name: WooCommerce Example String
		' );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'trademarked_term', 'needle' => 'woocommerce' ] );

		$results = $this->run_against_string('<?php
			// Plugin Name: Example String for WooCommere
		' );

		$this->assertNotHasErrorType( $results, [ 'type' => 'error', 'code' => 'trademarked_term' ] );
	}
}
