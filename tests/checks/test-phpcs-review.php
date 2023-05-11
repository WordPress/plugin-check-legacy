<?php

class Test_PHPCS_Review extends PluginCheck_TestCase {

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

	/**
	 * @dataProvider data_forbidden_function_warnings
	 */
	public function test_forbidden_function_warnings( $function, $triggering_php ) {
		$results = $this->run_against_string( $triggering_php );

		$this->assertHasErrorType(
			$results,
			[
				'type' => 'warning',
				'code' => 'Generic.PHP.ForbiddenFunctions.Found',
				'needle' => "function {$function} is forbidden"
			]
		);
	}

	public function data_forbidden_function_warnings() {
		return [
			[ 'error_reporting()',    'error_reporting( E_ALL );' ],
			[ 'move_uploaded_file()', 'move_uploaded_file( $a, $b );' ],
			[ 'wp_create_user()',     'wp_create_user( "admin", "admin" );' ],
			[ 'hex2bin()',            'echo hex2bin( "313031" );' ],
			[ 'base64_encode()',      'echo base64_encode( "WordPress" );' ],
			[ 'base64_decode()',      'echo base64_decode( "V29yZFByZXNz" );' ],
			[ 'shell_exec()',         'echo shell_exec( "cat /etc/passwd" );' ],
			[ 'exec()',               'exec( "cat /etc/passwd" );' ],
		];
	}

}
