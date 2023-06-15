<?php
namespace WordPressdotorg\Plugin_Check\Checks;
use WordPressdotorg\Plugin_Check\{Error, Guideline_Violation, Message, Notice, Warning};

class Code_Constants extends Check_Base {

	function check_allow_unfiltered_uploads() {
		if (
			$this->scan_matching_files_for_needle( 'ALLOW_UNFILTERED_UPLOADS', '\.php$' )
		) {
			return new Error(
				'allow_unfiltered_uploads_detected',
				__( 'ALLOW_UNFILTERED_UPLOADS is not permitted.', 'wporg-plugins' )
			);
		}
	}

}
