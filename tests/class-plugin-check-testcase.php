<?php
use function WordPressdotorg\Plugin_Check\{ run_all_checks };

class PluginCheck_TestCase extends WP_UnitTestCase {
	public function run_against_string( $string, $args = [] ) {
		return $this->run_against_virtual_files(
			[
				'plugin.php' => $string
			],
			$args
		);
	}

	public function run_against_virtual_files( $files, $args = [] ) {
		$tempname = wp_tempnam( 'plugin-check' );
		unlink( $tempname );
		mkdir( $tempname );

		foreach ( $files as $filename => $string ) {
			$full_filename = "{$tempname}/{$filename}";

			if ( str_ends_with( $filename, '.php' ) && ! str_starts_with( $string,  '<' . '?php' ) ) {
				$string = "<?php {$string}";
			}

			file_put_contents( $full_filename, $string );
		}

		$args[ 'path' ] = $tempname;

		$results = run_all_checks( $args );

		// Cleanup
		foreach ( $files as $filename => $string ) {
			unlink( "{$tempname}/{$filename}" );
		}
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
