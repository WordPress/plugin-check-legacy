<?php

/**
 * @group Checks
 * @group CodeObfuscation
 */
class Test_Localhost extends PluginCheck_TestCase {
	/**
	 * @dataProvider data_localhost
	 */
	public function test_localhost( $file_structure ) {
		$results = $this->run_against_virtual_files( $file_structure );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'localhost_code_detected', 'needle' => 'http://localhost' ] );
		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'localhost_code_detected', 'needle' => 'https://localhost' ] );
	}

	public function data_localhost() {
		return [
			[
				[
					'plugin.php' => 'http://localhost',
				]
			],
			[
				[
					'plugin.php' => 'https://localhost',
				]
			]
		];
	}
}
