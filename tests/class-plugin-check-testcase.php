<?php
use function WordPressdotorg\Plugin_Check\{ run_all_checks };

class PluginCheck_TestCase extends WP_UnitTestCase {
	public function run_against_string( $string ) {
		$tempname = wp_tempnam( 'plugin-check' );
		unlink( $tempname );
		mkdir( $tempname );

		if ( ! str_starts_with( $string,  '<' . '?php' ) ) {
			$string = "<?php {$string}";
		}

		file_put_contents( "$tempname/plugin.php", $string );

		$results = run_all_checks( $tempname );

		unlink( "$tempname/plugin.php" );
		rmdir( $tempname );

		return $results;
	}

	public function assertHasErrorType( $results, $search = [] ) {
		$type   = $search['type'] ?? false;
		$code   = $search['code'] ?? false;
		$needle = $search['needle'] ?? false;

		$this->assertIsArray( $results );

		if ( $type ) {
			$results = wp_list_filter( $results, [ 'error_class' => $type ] );
		}

		$this->assertNotEmpty( $results );

		$codes = wp_list_pluck( $results, 'errors' );
		$codes = call_user_func_array( 'array_merge', $codes );

		if ( $code ) {
			$this->assertArrayHasKey( $code, $codes );
			$codes = $codes[ $code ];
		} else {
			$codes = call_user_func_array( 'array_merge', $codes );
		}

		if ( $needle ) {
			foreach ( $codes as $text ) {
				if ( false !== stripos( $text, $needle ) ) {
					break;
				}
			}
			$this->assertStringContainsString( $needle, $text );
		}
	}
}
