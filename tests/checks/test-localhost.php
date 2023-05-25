<?php

/**
 * @group Checks
 * @group LocalHost
 */
class Test_Localhost extends PluginCheck_TestCase {
	/**
	 * @dataProvider data_localhost
	 */
	public function test_for_localhost( $file_content, $expected_needle ) {
		$results = $this->run_against_string( $file_content );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'localhost_code_detected', 'needle' => $expected_needle ] );
	}

	public function data_localhost() {
		return [
			'localhost in HTML tag' => [
				'<a href="http://localhost/wp-content">',
				'http://localhost'
			],
			'localhost in wp_remote_get()' => [
				'wp_remote_get( "http://localhost/wp-content" );',
				'http://localhost'
			]
		];
	}
}
