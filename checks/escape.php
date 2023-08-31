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
		'selected',
		'checked'
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
					$this->load($this->path . $file);
				}

				$this->showLog('needs_escape');

				return $this->logMessagesObjects;
			}
		}
		return false;
	}

	function find() {
		// echo
		$echos = $this->nodeFinder->findInstanceOf( $this->stmts, Node\Stmt\Echo_::class );
		if ( ! empty( $echos ) ) {
			foreach ( $echos as $echo ) {
				$echo->setAttribute( 'comments', null );
				$this->process_echo_or_print_or_function( $echo );
			}
		}

		// print
		$prints = $this->nodeFinder->findInstanceOf( $this->stmts, PhpParser\Node\Expr\Print_::class );
		if ( ! empty( $prints ) ) {
			foreach ( $prints as $print ) {
				$print->setAttribute( 'comments', null );
				$this->process_echo_or_print_or_function( $print );
			}
		}

		// Look for printf function
		$funcCalls = $this->nodeFinder->findInstanceOf( $this->stmts, Node\Expr\FuncCall::class );
		if ( ! empty( $funcCalls ) ) {
			foreach ( $funcCalls as $funccall ) {
				if ($this->hasFunctionName($funccall) && in_array($funccall->name->toString(), ['printf'])){
					$funccall->setAttribute( 'comments', null );
					$this->process_echo_or_print_or_function( $funccall );
				}
			}
		}
	}

	function process_echo_or_print_or_function( $node ) {
		if ( ! empty( $node->exprs ) || ! empty( $node->expr ) || !empty($node->args) ) {
			$this->mightBeWrong = false;
			$isEcho = false;
			$isFunction = false;
			if(!empty($node->exprs)){
				$isEcho = true;
				$exprs              = $node->exprs;
			} else if(!empty($node->expr)){
				$isEcho = true;
				$exprs              = [$node->expr];
			} else if (!empty($node->args)){
				$isFunction = true;
				$exprs              = $node->args;
			}
			foreach ( $exprs as $expr ) {
				$exprElements = $this->unfoldConcatExpr( $expr ); //Array of all the elements that are contained in the echo.
				if ( ! empty( $exprElements ) ) {
					foreach ( $exprElements as $element ) {
						if ( ! $this->isThisValidForEscaping( $element ) ) {
							if($isEcho) {
								$this->saveLinesNodeDetailLog( $node, 'needs_escape' );
							}
							if ($isFunction){
								$this->saveLinesNodeDetailLog( $element, 'needs_escape' );
							}
							return;
						}
					}
				}
			}
			if ( $this->mightBeWrong ) {
				$this->saveLinesLog( $node->getStartLine(), $node->getEndLine(), 'escape_mightBeWrong' );
			}
		}
	}

	function isThisValidForEscaping( $node ) {
		$class = get_class( $node );
		//var_dump($class);

		switch ( $class ):
			// Arg: Read what's inside
			case 'PhpParser\Node\Arg':
				if(!empty($node->value)){
					return $this->isThisValidForEscaping($node->value);
				}
				return true;
				break;

			// String and Number
			case 'PhpParser\Node\Scalar\String_':
			case 'PhpParser\Node\Scalar\LNumber':
				return true;
				break;

			// Functions
			case 'PhpParser\Node\Expr\FuncCall':
				if ($this->hasFunctionName($node)) {
					if ( in_array( $node->name->toString(), $this->escapingFunctions ) ) {
						$this->mightBeWrong = true;
						return true;
					} else if ( in_array( $node->name->toString(), $this->noEscapingNeeded ) ) {
						$this->mightBeWrong = false;
						return true;
					} else if ( in_array( $node->name->toString(), ['sprintf'] ) ) {
						$this->process_echo_or_print_or_function($node);
						$this->mightBeWrong = false;
						return true;
					}

					// TODO Confusing sanitizing with escaping
					if(in_array( $node->name->toString(), $this->sanitizeFunctions )){
						$this->saveLinesNodeDetailLog($node, 'needs_escape_confusion_sanitize');
					}

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
