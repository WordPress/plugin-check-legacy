<?php

class Test_The_Tests extends PluginCheck_TestCase {
	public function test_str_rot13() {
		$usage = 'echo str_rot13( "WordPress" );';

		$results = $this->run_against_string( $usage );

		$this->assertHasErrorType( $results, [ 'type' => 'error', 'code' => 'Generic.PHP.ForbiddenFunctions.Found', 'needle' => 'function str_rot13() is forbidden' ] );
	}

}
