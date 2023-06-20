<?php

namespace WordPressdotorg\Plugin_Check\Checks;

if (! defined('ABSPATH')) {
	exit;
}

use WordPressdotorg\Plugin_Check\Parser;
use const WordPressdotorg\Plugin_Check\PLUGIN_DIR;
use const WordPressdotorg\Plugin_Check\HAS_VENDOR;

include_once PLUGIN_DIR . '/inc/class-parser.php';

use PhpParser\Node;

class Escape extends Parser {

	public $noEscapingNeeded = [
		'intval',
		'absint',
		'number_format',
		'number_format_i18n',
		'get_the_ID',
		'count',
		'strtotime',
		'mktime',
		'rand',
		'mt_rand',
		'post_class',
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
		'esc_html__',
		'esc_html_e',
		'esc_html_x',
		'esc_attr__',
		'esc_attr_e',
		'esc_attr_x',
		'ent2ncr',
		'tag_escape'
	];

	private $mightBeWrong = false;

	public function load( $file ) {
		parent::load( $file );
	}

	public function check_escape()
	{
		if (!HAS_VENDOR) {
			return new \WordPressdotorg\Plugin_Check\Notice(
				'escape_not_tested',
				'Escape have not been tested, as the vendor directory is missing. Perhaps you need to run <code>`composer install`</code>.'
			);
		}

		if (!empty($this->files)) {
			$php_files = preg_grep('#\.php$#', $this->files);
			if (! empty($php_files)) {
				foreach ($php_files as $file) {
					$this->clearLog();
					$this->load($this->path . $file);
				}

				return $this->logMessagesObjects;
			}
		}
		return false;
	}

	function find() {
		$echos = $this->nodeFinder->findInstanceOf( $this->stmts, Node\Stmt\Echo_::class );
		if ( ! empty( $echos ) ) {
			foreach ( $echos as $echo ) {
				$echo->setAttribute( 'comments', null );
				$this->process_echo( $echo );
			}
		}
		$this->showLog('needs_escape');
	}

	function process_echo( $echo ) {
		if ( ! empty( $echo->exprs ) ) {
			$this->mightBeWrong = false;
			$exprs              = $echo->exprs;
			foreach ( $exprs as $expr ) {
				$exprElements = $this->unfoldEchoExpr( $expr ); //Array of all the elements that are contained in the echo.
				if ( ! empty( $exprElements ) ) {
					foreach ( $exprElements as $element ) {
						if ( ! $this->isThisValidForEscaping( $element ) ) {
							$this->saveLinesNodeDetailLog( $echo, 'needs_escape' );
							return;
						}
					}
				}
			}
			if ( $this->mightBeWrong ) {
				$this->saveLinesLog( $echo->getStartLine(), $echo->getEndLine(), 'escape_mightBeWrong' );
			}
		}
	}

	function isThisValidForEscaping( $node ) {
		$class = get_class( $node );
		//var_dump($class);

		switch ( $class ):
			// String
			case 'PhpParser\Node\Scalar\String_':
				return true;
				break;

			// Functions
			case 'PhpParser\Node\Expr\FuncCall':
				if ( in_array( $node->name->toCodeString(), $this->escapingFunctions ) ) {
					$this->mightBeWrong = true;

					return true;
				} else if ( in_array( $node->name->toCodeString(), $this->noEscapingNeeded ) ) {
					$this->mightBeWrong = true;

					return true;
				}
				break;

			// Special operators
			case 'PhpParser\Node\Expr\BinaryOp\Coalesce':
				if ( ! empty( $node->left ) && ! empty( $node->right ) ) {
					return $this->isThisValidForEscaping( $node->left ) && $this->isThisValidForEscaping( $node->right );
				}
				break;

			case 'PhpParser\Node\Expr\Ternary':
				if ( ! empty( $node->if ) && ! empty( $node->else ) ) {
					return $this->isThisValidForEscaping( $node->if ) && $this->isThisValidForEscaping( $node->else );
				}
				break;

			// Operators
			case 'PhpParser\Node\Expr\BinaryOp\Identical':
			case 'PhpParser\Node\Expr\BinaryOp\NotIdentical':
			case 'PhpParser\Node\Expr\BinaryOp\Equal':
			case 'PhpParser\Node\Expr\BinaryOp\NotEqual':
			case 'PhpParser\Node\Expr\BinaryOp\Greater':
			case 'PhpParser\Node\Expr\BinaryOp\GreaterOrEqual':
			case 'PhpParser\Node\Expr\BinaryOp\Smaller':
			case 'PhpParser\Node\Expr\BinaryOp\SmallerOrEqual':
			case 'PhpParser\Node\Expr\BinaryOp\BooleanAnd':
			case 'PhpParser\Node\Expr\BinaryOp\BooleanOr':
			case 'PhpParser\Node\Expr\BooleanNot':
				return true;
				break;

			// Casting
			case 'PhpParser\Node\Expr\Cast\Int_':
			case 'PhpParser\Node\Expr\Cast\Bool_':
			case 'PhpParser\Node\Expr\Cast\Double':
				return true;
				break;

		endswitch;

		return false;
	}

}
