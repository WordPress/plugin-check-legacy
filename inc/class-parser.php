<?php

namespace WordPressdotorg\Plugin_Check;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PhpParser\Node;
use PhpParser\NodeFinder;
use WordPressdotorg\Plugin_Check\Checks\Check_Base;

abstract class Parser extends Check_Base {
	private $file = '';
	public $fileRelative = '';
	public $needsGetParents = false;
	public $needsGetSiblings = false;
	private $ready = false;
	public $nodeFinder;
	public $stmts;
	private $log = [];
	public $logMessagesTexts = [];
	public $logMessagesObjects = [];
	private $log_longer_location = [];
	private $log_already_shown_lines = [];
	public $prettyPrinter;

	// Known functions
	public $sanitizeFunctions = [
		'sanitize_email',
		'sanitize_file_name',
		'sanitize_hex_color',
		'sanitize_hex_color_no_hash',
		'sanitize_html_class',
		'sanitize_key',
		'sanitize_meta',
		'sanitize_mime_type',
		'sanitize_option',
		'sanitize_sql_orderby',
		'sanitize_term',
		'sanitize_term_field',
		'sanitize_text_field',
		'sanitize_textarea_field',
		'sanitize_title',
		'sanitize_title_for_query',
		'sanitize_title_with_dashes',
		'sanitize_user',
		'sanitize_url',
		'wp_kses',
		'wp_kses_post',
		'wc_clean',
		'wc_sanitize_order_id'
	];
	public $escapingFunctions = [
		'esc_html',
		'esc_html__',
		'esc_html_x',
		'esc_html_e',
		'esc_js',
		'esc_url',
		'esc_url_raw',
		'esc_xml',
		'esc_attr',
		'esc_attr__',
		'esc_attr_x',
		'esc_attr_e',
		'esc_textarea',
		'wp_kses',
		'wp_kses_post',
		'wp_kses_data',
		'ent2ncr',
		'tag_escape'
	];

	//TODO For known WP and WC options and hooks, ignore them in Prefix and maybe warn them in Calls.
	//TODO Hooks reference: https://github.com/wp-hooks/wordpress-core-hooks
	public $knownOptions = [
		'siteurl',
		'home',
		'blogname',
		'blogdescription',
		'users_can_register',
		'admin_email',
		'start_of_week',
		'use_balanceTags',
		'use_smilies',
		'require_name_email',
		'comments_notify',
		'posts_per_rss',
		'rss_use_excerpt',
		'mailserver_url',
		'mailserver_login',
		'mailserver_pass',
		'mailserver_port',
		'default_category',
		'default_comment_status',
		'default_ping_status',
		'default_pingback_flag',
		'posts_per_page',
		'date_format',
		'time_format',
		'links_updated_date_format',
		'comment_moderation',
		'moderation_notify',
		'permalink_structure',
		'rewrite_rules',
		'hack_file',
		'blog_charset',
		'moderation_keys',
		'active_plugins',
		'category_base',
		'ping_sites',
		'comment_max_links',
		'gmt_offset',
		'default_email_category',
		'recently_edited',
		'template',
		'stylesheet',
		'comment_registration',
		'html_type',
		'use_trackback',
		'default_role',
		'db_version',
		'uploads_use_yearmonth_folders',
		'upload_path',
		'blog_public',
		'default_link_category',
		'show_on_front',
		'tag_base',
		'show_avatars',
		'avatar_rating',
		'upload_url_path',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'thumbnail_crop',
		'medium_size_w',
		'medium_size_h',
		'avatar_default',
		'large_size_w',
		'large_size_h',
		'image_default_link_type',
		'image_default_size',
		'image_default_align',
		'close_comments_for_old_posts',
		'close_comments_days_old',
		'thread_comments',
		'thread_comments_depth',
		'page_comments',
		'comments_per_page',
		'default_comments_page',
		'comment_order',
		'sticky_posts',
		'widget_categories',
		'widget_text',
		'widget_rss',
		'uninstall_plugins',
		'timezone_string',
		'page_for_posts',
		'page_on_front',
		'default_post_format',
		'link_manager_enabled',
		'finished_splitting_shared_terms',
		'site_icon',
		'medium_large_size_w',
		'medium_large_size_h',
		'wp_page_for_privacy_policy',
		'show_comments_cookies_opt_in',
		'admin_email_lifespan',
		'disallowed_keys',
		'comment_previously_approved',
		'auto_plugin_theme_update_emails',
		'auto_update_core_dev',
		'auto_update_core_minor',
		'auto_update_core_major',
		'wp_force_deactivated_plugins',
		'initial_db_version',
		'wp_user_roles',
		'fresh_site',
		'user_count',
		'widget_block',
		'sidebars_widgets',
		'cron',
		'recovery_keys',
		'https_detection_errors',
		'can_compress_scripts',
		'recently_activated',
		'finished_updating_comment_type',
		'new_admin_email'
	];
	public $knownHooksActions = [];
	public $knownHooksFilters = [];

	public function load( $file ) {
		if ( file_exists( $file ) ) {
			$this->file         = $file;
			$this->fileRelative = str_replace( $this->path, '', $this->file );
			$this->parseFile( $this->file );
			$this->prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
			if ( $this->isReady() ) {
				$this->find();
			}
		} else {
			$this->logMessagesObjects[] = new \WordPressdotorg\Plugin_Check\Notice(
				'parser_read_file_error',
				sprintf( 'File %s can\'t be read by PHP', $file )
			);
		}

		return null;
	}

	abstract public function find();

	private function parseFile( $file ) {
		//Options

		// Activate ability to get parents. Performance will be degraded.
		// Get parents using $node->getAttribute('parent')
		if ( $this->needsGetParents ) {
			$traverser = new NodeTraverser;
			$traverser->addVisitor( new ParentConnectingVisitor );
		}

		if ( $this->needsGetSiblings ) {
			$traverser = new NodeTraverser;
			$traverser->addVisitor( new NodeConnectingVisitor );
		}

		//Parse file.
		$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );
		try {
			$code        = file_get_contents( $file );
			$this->stmts = $parser->parse( $code );
			if ( $this->needsGetParents || $this->needsGetSiblings ) {
				$this->stmts = $traverser->traverse( $this->stmts );
			}
		} catch ( \PhpParser\Error $error ) {
			echo $this->fileRelative . ": Parse error: {$error->getMessage()}\n";
			return;
		}
		$this->nodeFinder = new NodeFinder;
		$this->ready      = true;
	}

	public function parseCode($code){
		$stmts = '';
		if(!empty($code)) {
			$parser = ( new ParserFactory )->create( ParserFactory::PREFER_PHP7 );
			try {
				$stmts = $parser->parse( $code );
			} catch ( Error $error ) {
				echo "Parse error: {$error->getMessage()}\n";
				return;
			}
		}
		return $stmts;
	}

	public function isReady() {
		return $this->ready;
	}

	public function getArgs( $args ) {
		$argsArray = [];
		foreach ( $args as $arg ) {
			$argsArray[] = $this->prettyPrinter->prettyPrint( [ $arg ] );
		}

		return '( ' . implode( ', ', $argsArray ) . ' )';
	}


	public function logFunctionCall( $func_call, $argposition, $logid, $unique=false ) {
		$func_call->setAttribute( 'comments', null );
		$this->saveLog( $func_call->getStartLine(), $this->prettyPrinter->prettyPrint( [ $func_call ] ) . ';', $logid, $unique );
		$logkey = array_key_last( $this->log[ $logid ] );
		if ( 'PhpParser\Node\Name' === get_class( $func_call->name ) ) {
			$funcname = $func_call->name->__toString();
		} else {
			$funcname = get_class( $func_call->name );
		}
		$this->log[ $logid ][ $logkey ]['type'] = 'function_call-' . $funcname;
		if ( isset( $func_call->args[ $argposition ] ) ) {
			$arg = $func_call->args[ $argposition ];
			if ( isset( $arg->value ) && get_class( $arg->value ) === 'PhpParser\Node\Scalar\String_' ) {
				$this->log[ $logid ][ $logkey ]['name'] = $arg->value->value;
			}
		}
	}

	public function logNamespace( $namespace, $logid ) {
		$lineText = 'namespace ' . $namespace->name->toCodeString();
		$this->saveLog( $namespace->getStartLine(), $lineText, $logid );
		$logkey                                 = array_key_last( $this->log[ $logid ] );
		$this->log[ $logid ][ $logkey ]['type'] = 'namespace';
		$this->log[ $logid ][ $logkey ]['name'] = $namespace->name->__toString();
	}

	public function logAbstractionDeclaration( $abstraction, $logid ) {
		if ( ! empty( $abstraction ) ) {
			foreach ( $abstraction as $abstract ) {
				$type = 'unknown';
				switch ( $abstract->getType() ) {
					case 'Stmt_Class':
						$type = 'class';
						break;
					case 'Stmt_Function':
						$type = 'function';
						break;
					case 'Stmt_Interface':
						$type = 'interface';
						break;
					case 'Stmt_Trait':
						$type = 'trait';
						break;
				}
				$lineText = $type . " " . $abstract->name->toString();
				/*if(!empty($abstract->params) && $abstract->getType()=='Stmt_Function'){
					$lineText .= " ".$this->get_args($abstract->params);
				}*/
				$this->saveLog( $abstract->getStartLine(), $lineText, $logid );
				$logkey                                 = array_key_last( $this->log[ $logid ] );
				$this->log[ $logid ][ $logkey ]['type'] = 'abstraction';
				$this->log[ $logid ][ $logkey ]['name'] = $abstract->name->__toString();
			}
		}
	}


	/**
	 * Breaks down the elements inside an echo / arg and returns an array with each of its elements
	 *
	 * @param $expr
	 * @param $exprElements
	 *
	 * @return array|mixed
	 */
	public function unfoldConcatExpr( $expr, $exprElements = [] ) {
		if ( is_a( $expr, 'PhpParser\Node\Expr\BinaryOp\Concat' ) ) {
			$exprElements = array_merge( $this->unfoldConcatExpr( $expr->left, $exprElements ), $exprElements );
			if ( ! empty( $expr->right ) ) {
				$exprElements[] = $expr->right;
			}
		} else {
			$exprElements[] = $expr;
		}

		return $exprElements;
	}

	public function hasFunctionName( $funccall ) {
		if ( ! empty( $funccall->name ) ) {
			if ( in_array( get_class( $funccall->name ), [
				'PhpParser\Node\Name\FullyQualified',
				'PhpParser\Node\Name'
			] ) ) {
				return true;
			}
		}

		return false;
	}

	public function hasLog( $logid = 'default' ) {
		if ( ! empty( $this->log[ $logid ] ) ) {
			return true;
		}

		return false;
	}

	public function saveLog( $lineNumber, $text, $logid = 'default', $unique=false ) {
		$logLine = [
			'location'      => "",
			'text'          => $text,
			'textFormatted' => $text,
			'startLine'     => $lineNumber
		];
		if ( ! empty( $lineNumber ) ) {
			$logLine['location'] = $this->fileRelative . ":" . $lineNumber . " ";
		}
		if ( ! isset( $this->log_longer_location[ $logid ] ) ) {
			$this->log_longer_location[ $logid ] = 0;
		}
		if ( strlen( $logLine['location'] ) > $this->log_longer_location[ $logid ] ) {
			$this->log_longer_location[ $logid ] = strlen( $logLine['location'] );
		}
		if($unique) {
			$lineId = $this->getLogLineID($lineNumber);
			$this->log[ $logid ][ $lineId ] = $logLine;
		} else {
			$this->log[ $logid ][ ] = $logLine;
		}
	}

	public function clearLog() {
		$this->log = [];
	}

	public function saveLinesLog( $startLineNumber, $endLineNumber = '', $logid = 'default' ): int {
		$lineId = $this->getLogLineID($startLineNumber);
		$lineLenght = 0;
		if ( empty( $this->log_already_shown_lines[ $logid ] ) ) {
			$this->log_already_shown_lines[ $logid ] = [];
		}
		if ( ! isset( $this->log_already_shown_lines[ $logid ][ $lineId ] ) ) {
			$lines                                                       = $this->getLines( $startLineNumber, $endLineNumber );
			$linesString                                                 = implode( "", $lines );
			$lineLenght                                                  = strlen( $linesString );
			$this->log_already_shown_lines[ $logid ][ $lineId ] = [
				'lineLenght' => $lineLenght
			];
			$this->saveLog( $startLineNumber, $linesString, $logid );
		} else {
			$lineLenght = $this->log_already_shown_lines[ $logid ][ $lineId ]['lineLenght'];
		}

		return $lineLenght;
	}

	public function getLogLineID($lineNumber){
		$sanitized_filerelative = strtolower( $this->fileRelative );
		$sanitized_filerelative = preg_replace( '/[^a-z0-9_\-]/', '', $sanitized_filerelative );
		return $sanitized_filerelative.'_'.$lineNumber;
	}

	public function hasLogLineID($lineId, $logid){
		if(isset($this->log[ $logid ][ $lineId ])){
			return true;
		}
		return false;
	}

	public function saveLinesNodeDetailLog( $node, $logid = 'default' ) {
		$lineLenght = $this->saveLinesLog( $node->getStartLine(), $node->getEndLine(), $logid );
		if ( $lineLenght > 80 ) {
			$detail = $this->prettyPrinter->prettyPrint( [ $node ] );
			if ( strlen( $detail ) < $lineLenght / 2 ) {
				$logkey = array_key_last( $this->log[ $logid ] );
				if ( empty( $this->log[ $logid ][ $logkey ]['detail'] ) ) {
					$this->log[ $logid ][ $logkey ]['detail'] = [];
				}
				$this->log[ $logid ][ $logkey ]['detail'][] = $detail;
			}
		}
	}

	public function getLines( $startLineNumber, $endLineNumber = '' ) {
		$file  = new \SplFileObject( $this->file );
		$lines = [];

		if ( empty( $endLineNumber ) ) {
			$endLineNumber = $startLineNumber;
		}

		for ( $i = 1; $i <= $endLineNumber; $i ++ ) {
			if ( $i >= $startLineNumber ) {
				$lines[] = trim( $file->current(), " \t\0\x0B" );
			}
			if ( ! $file->eof() ) {
				$file->current();
				$file->next();
			} else {
				break;
			}
		}
		if ( ! empty( $lines ) ) {
			$lines[ array_key_last( $lines ) ] = str_replace( array(
				"\r",
				"\n"
			), '', $lines[ array_key_last( $lines ) ] );
		}

		return $lines;
	}

	private function loadLogMessagesVariable() {
		$this->logMessagesTexts = [
			'needs_sanitize'              => [
				'text' => __( 'Your code needs to be sanitized.', 'plugin_check' ),
				'type' => 'Error'
			],
			'sanitize_process_entire_var' => [
				'text' => __( 'Your code is processing the entire variable.', 'plugin_check' ),
				'type' => 'Error'
			],
			'needs_escape'                => [
				'text' => __( 'Your code needs to be escaped.', 'plugin_check' ),
				'type' => 'Error'
			],
		];
	}

	private function getLogText( $logid = 'default' ) {
		if ( empty( $this->logMessagesTexts ) ) {
			$this->loadLogMessagesVariable();
		}
		if ( isset( $this->logMessagesTexts[ $logid ]['text'] ) ) {
			return $this->logMessagesTexts[ $logid ]['text'];
		}

		return __( 'Error', 'plugin-check' );
	}

	private function getLogType( $logid = 'default' ) {
		if ( empty( $this->logMessagesTexts ) ) {
			$this->loadLogMessagesVariable();
		}
		if ( isset( $this->logMessagesTexts[ $logid ]['type'] ) ) {
			return $this->logMessagesTexts[ $logid ]['type'];
		}

		return 'Error';
	}

	public function getLog( $logid = 'default' ) {
		if ( ! empty( $this->log[ $logid ] ) ) {
			return $this->log[ $logid ];
		}

		return [];
	}

	public function setLog( $log, $logid = 'default' ) {
		$this->log[ $logid ] = $log;
	}

	public function addLog( $log, $logid = 'default' ) {
		if ( empty( $this->log[ $logid ] ) ) {
			$this->log[ $logid ] = [];
		}
		$this->log[ $logid ] = array_merge( $this->log[ $logid ], $log );
	}

	public function showLog( $logid = 'default' ) {
		if ( ! empty( $this->log[ $logid ] ) ) {
			$text = sprintf(
				'%s',
				'<strong>' . esc_html( $this->getLogText( $logid ) ) . '</strong>'
			);

			foreach ( $this->log[ $logid ] as $log ) {
				if(!empty($log['startLine'])){
					$text .= sprintf(
						'<br><br>%s %s',
						esc_html( $log['location'] ), '<code>' . esc_html( $log['text'] ) . '</code>'
					);
				} else {
					$text .= sprintf(
						'<br><br>%s',
						'<code>' . esc_html( $log['text'] ) . '</code>'
					);
				}
				if ( ! empty( $log['detail'] ) ) {
					foreach ( $log['detail'] as $key => $detail ) {
						$log['detail'][ $key ] = '<code>' . esc_html( $detail ) . '</code>';
					}
					$detail = implode( ', ', $log['detail'] );
					$text   .= sprintf(
						'<br>Check %s',
						$detail
					);
				}
			}
			$logType                    = '\WordPressdotorg\Plugin_Check\\' . $this->getLogType( $logid );
			$this->logMessagesObjects[] = new $logType(
				$logid,
				$text
			);
		}
	}
}
