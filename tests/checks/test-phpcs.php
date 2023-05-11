<?php

class Test_PHPCS extends PluginCheck_TestCase {

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

}
