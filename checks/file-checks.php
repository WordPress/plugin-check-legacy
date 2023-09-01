<?php
namespace WordPressdotorg\Plugin_Check\Checks;
use WordPressdotorg\Plugin_Check\{Error, Guideline_Violation, Message, Notice, Warning};

class File_Checks extends Check_Base {
	function check_compressed_files() {
		$types = [ 'zip', 'gz', 'tgz', 'rar', 'tar', '7z' ];

		$files = array_filter( $this->files, function( $file ) use ( $types ) {
			return in_array( pathinfo( $file, PATHINFO_EXTENSION ), $types, true );
		} );

		if ( $files ) {
			return new Error(
				'compressed_files',
				sprintf(
					'Compressed files are not permitted. Found: %s',
					implode( ', ', array_map( function( $file ) {
						return '<code>' . esc_html( basename( $file ) ) . '</code>';
					}, $files ) )
				)
			);
		}
	}

	function check_phar() {
		if ( $matches = preg_grep( '!\.phar$!i', $this->files ) ) {
			return new Error(
				'phar_detected',
				sprintf(
					'Phar files are not permitted.. Detected: %s',
					basename( array_shift( $matches ) )
				)
			);
		}
	}

	function check_application() {
		$application_files = [
			'.a',
			'.bin',
			'.bpk',
			'.deploy',
			'.dist',
			'.distz',
			'.dmg',
			'.dms',
			'.DS_Store',
			'.dump',
			'.elc',
			'.exe',
			'.iso',
			'.lha',
			'.lrf',
			'.lzh',
			'.o',
			'.obj',
			'.phar',
			'.pkg',
			'.sh',
			'.so'
		];

		$files = array_filter( $this->files, function( $file ) use ( $application_files ) {
			$extension = sprintf( '.%s', pathinfo( $file, PATHINFO_EXTENSION ) );
			return in_array( $extension, $application_files, true );
		} );

		if ( $files ) {
			$notice_or_error = ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || 'production' !== wp_get_environment_type() ) ? Notice::class : Error::class;

			return new $notice_or_error(
				'application_detected',
				sprintf(
					__( 'Application files are not permitted. Found: %s', 'wporg-plugins' ),
					implode( ', ', array_unique( array_map( function( $file ) {
						return '<code>' . esc_html( $file ) . '</code>';
					}, $files ) ) )
				)
			);
		}
	}

	function check_vcs() {
		$directories = [ '.git', '.svn', '.hg', '.bzr' ];

		$files = array_filter( $this->files, function( $file ) use ( $directories ) {
			return in_array( basename( dirname( $file ) ), $directories, true );
		} );

		if ( $files ) {
			$notice_or_error = ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || 'production' !== wp_get_environment_type() ) ? Notice::class : Error::class;

			return new $notice_or_error(
				'vcs_present',
				sprintf(
					'Version control checkouts should not be present. Found: %s',
					implode( ', ', array_unique( array_map( function( $file ) {
						return '<code>' . esc_html( basename( dirname( $file ) ) ) . '</code>';
					}, $files ) ) )
				)
			);
		}
	}

	function check_warn_hidden_files() {
		$dotfiles      = [];
		$ignore_within = [ 'vendor/', 'node_modules/' ];
		array_walk( $this->files, function( $file ) use( &$dotfiles, $ignore_within ) {
			if ( str_starts_with( basename( $file ), '.' ) ) {
				foreach ( $ignore_within as $ignore ) {
					if ( str_starts_with( $file, $ignore ) ) {
						return;
					}
				}

				$dotfiles[] = $file;
			}
		} );

		if ( $dotfiles ) {
			return new Warning(
				'hidden_files',
				sprintf(
					'Hidden files and directories are not permitted. Found: %s',
					implode( ', ', array_map( function( $file ) {
						return '<code>' . esc_html( $file ) . '</code>';
					}, $dotfiles ) )
				)
			);
		}
	}
}