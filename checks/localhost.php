<?php
namespace WordPressdotorg\Plugin_Check\Checks;
use WordPressdotorg\Plugin_Check\{Error, Guideline_Violation, Message, Notice, Warning};

class Localhost extends Check_Base {

	function check_localhost() {
		if (
			$this->scan_matching_files_for_needle( 'http://localhost', '\.php$' ) ||
			$this->scan_matching_files_for_needle( 'https://localhost', '\.php$' ) ||
			$this->scan_matching_files_for_needle( 'https://127.0.0.1', '\.php$' ) ||
				$this->scan_matching_files_for_needle( 'http://127.0.0.1', '\.php$' )
		) {
			return new Error(
				'localhost_code_detected',
				__( 'Do not use Localhost in your code. Detected: localhost', 'plugin-check' )
			);
		}
	}

}
