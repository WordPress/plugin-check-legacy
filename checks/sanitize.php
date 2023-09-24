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

class Sanitize extends Parser
{

	public $needSanitizeVars = [
		'_POST',
		'_REQUEST',
		'_GET',
		'_FILES',
		'_COOKIE',
		'_SERVER',
		'_SESSION'
	];

	public $filterInputNeedSanitizeConstants = [
		'INPUT_GET', 'INPUT_POST', 'INPUT_COOKIE', 'INPUT_SERVER', 'INPUT_ENV'
	];

	public $commonIntermediateFunctions = [
		'wp_unslash',
		'trim',
		'array_key_first',
		'basename',
		'strtolower'
	];

	public $noSanitizingNeeded = [
		'intval',
		'abs',
		'absint',
		'strpos',
		'in_array',
		'array_key_exists',
		'key_exists',
		'is_array',
		'is_numeric',
		'strlen',
		'count',
		'stripos',
		'is_plugin_inactive',
		'wp_handle_upload',
		'wp_parse_id_list'
	];


	// TODO check if additional filters might be valid. https://www.php.net/manual/en/filter.filters.sanitize.php
	public $phpValidFilters = [
		'FILTER_SANITIZE_NUMBER_INT', 'FILTER_SANITIZE_NUMBER_FLOAT', 'FILTER_SANITIZE_STRING'
	];

	public function load($file)
	{
		$this->needsGetParents = true;
		parent::load($file);
	}

	public function check_sanitize()
	{
		if (!HAS_VENDOR) {
			return new \WordPressdotorg\Plugin_Check\Notice(
				'sanitize_not_tested',
				'Sanitize have not been tested, as the vendor directory is missing. Perhaps you need to run <code>`composer install`</code>.'
			);
		}

		if (!empty($this->files)) {
			$php_files = preg_grep('#\.php$#', $this->files);
			if (! empty($php_files)) {
				foreach ($php_files as $file) {
					$this->load($this->path . $file);
				}
				$this->showLog('needs_sanitize');
				$this->showLog('sanitize_process_entire_var');
				$this->showLog('needs_sanitize_confusion_escape');

				return $this->logMessagesObjects;
			}
		}

		return false;
	}

	public function find()
	{
		// $_POST, $_REQUEST, etc check.
		$vars = $this->nodeFinder->findInstanceOf($this->stmts, Node\Expr\Variable::class);
		if (! empty($vars)) {
			foreach ($vars as $var) {
				if($this->isNeedSanitizeVar($var)){
					$var->setAttribute('comments', null);
					$this->processVar($var);
				}
			}
		}

		// php://input check
		$vars = $this->nodeFinder->findInstanceOf($this->stmts, Node\Expr\FuncCall::class);
		if (!empty($vars)) {
			foreach ($vars as $var) {
				if($this->isNeedSanitizeVar($var)){
					$var->setAttribute('comments', null);
					$this->processVar($var);
				}
			}
		}
	}

	function isNeedSanitizeVar($var){
		$class = get_class($var);
		if('PhpParser\Node\Expr\ArrayDimFetch'===$class){
			return $this->isNeedSanitizeVar($var->var);
		}
		if(in_array($class, ['PhpParser\Node\Expr\Variable'])) {
			if ( ! empty( $var->name ) && in_array( $var->name, $this->needSanitizeVars ) ) {
				return true;
			}
		} else if ( 'PhpParser\Node\Expr\FuncCall' === $class ) {
			if (!empty($var->name) && in_array($var->name, ['file_get_contents'])) {
				if (isset($var->args[0])) {
					if ('PhpParser\Node\Scalar\String_' === get_class($var->args[0]->value)) {
						if ('php://input' === $var->args[0]->value->value) {
							return true;
						}
					}
				}
			}
			if (!empty($var->name) && in_array($var->name, ['filter_input'])) {
				if (isset($var->args[0])) {
					if ('PhpParser\Node\Expr\ConstFetch' === get_class($var->args[0]->value)) {
						if (in_array($var->args[0]->value->name->__toString(), $this->filterInputNeedSanitizeConstants)) {
							$var->setAttribute('comments', null);
							$this->processVarWrapper($var);
							return false;
						}
					}
				}
			}
		}
	}

	private function processVar($var, $parent = false)
	{
		if (!empty($var->getAttribute('parent'))) {
			if ('PhpParser\Node\Expr\ArrayDimFetch' === get_class($var->getAttribute('parent'))) {
				$this->processVar($var->getAttribute('parent'), true);
			} else {
				if('PhpParser\Node\Expr\Assign' === get_class($var->getAttribute('parent'))) {
					if ( $var !== $var->getAttribute( 'parent' )->expr ) {
						return; // This is the saved variable in the assign.
					}
				}
				if (!$parent) {
					// Processing the entire $_VAR
					if (!$this->isThisValidForEntireVar($var->getAttribute('parent'))) {
						$this->saveLinesNodeDetailLog($var->getAttribute('parent'), 'sanitize_process_entire_var');
					}
				}
				$this->processVarWrapper($var->getAttribute('parent'));
			}
		} else {
			if (!$parent) {
				// Processing the entire $_VAR
				$this->saveLinesNodeDetailLog($var, 'sanitize_process_entire_var');
			} else {
				// No sanitizing
				$this->saveLinesNodeDetailLog($var, 'needs_sanitize');
			}
		}
	}

	private function processVarWrapper($wrapper, $parent = 0)
	{
		$valid = false;
		if (!empty($wrapper)) {
			if ('PhpParser\Node\Arg' == get_class($wrapper) && !empty($wrapper->getAttribute("parent"))) {
				$wrapper = $wrapper->getAttribute("parent");
			}
			$valid = $this->isThisValidForSanitization($wrapper);
		}
		if (!$valid) {
			// No sanitizing
			$this->saveLinesNodeDetailLog($wrapper, 'needs_sanitize');
		}
		//var_dump(get_class($wrapper));
		return $valid;
	}

	private function isThisValidForSanitization($node)
	{
		$class = get_class($node);
		//var_dump($class);

		switch ($class) :
			// Functions
			case 'PhpParser\Node\Expr\FuncCall':
				if ($this->hasFunctionName($node)) {
					if ( ! empty( $node->name->toString() ) ) {
						if ( in_array( $node->name->toString(), $this->sanitizeFunctions ) ) {
							return true;
						} elseif ( in_array( $node->name->toString(), $this->noSanitizingNeeded ) ) {
							return true;
						} elseif ( in_array( $node->name->toString(), $this->commonIntermediateFunctions ) ) {
							return $this->processVarWrapper( $node->getAttribute( "parent" ) );
						} elseif ( 'array_map' === $node->name->toString() ) {
							if ( isset( $node->args[0]->value ) ) {
								if ( 'PhpParser\Node\Scalar\String_' === get_class( $node->args[0]->value ) ) {
									if ( isset( $node->args[0]->value->value ) ) {
										if ( in_array( $node->args[0]->value->value, $this->sanitizeFunctions ) || in_array( $node->args[0]->value->value, $this->noSanitizingNeeded ) ) {
											return true;
										}
									}
								}
							}
						} elseif ( in_array($node->name->toString(), ['array_walk', 'array_walk_recursive', 'map_deep']) ) {
							if ( isset( $node->args[1]->value ) ) {
								if ( 'PhpParser\Node\Scalar\String_' === get_class( $node->args[1]->value ) ) {
									if ( isset( $node->args[1]->value->value ) ) {
										if ( in_array( $node->args[1]->value->value, $this->sanitizeFunctions ) || in_array( $node->args[1]->value->value, $this->noSanitizingNeeded ) ) {
											return true;
										}
									}
								}
							}
						} elseif ('filter_var' === $node->name->toString() ){
							if ( isset( $node->args[1]->value ) ) {
								if( 'PhpParser\Node\Expr\ConstFetch' === get_class($node->args[1]->value)) {
									if(in_array($node->args[1]->value->name->__toString(), $this->phpValidFilters)){
										return true;
									}
								}
							}
						} elseif ('filter_input' === $node->name->toString() ){
							if ( isset( $node->args[2]->value ) ) {
								if( 'PhpParser\Node\Expr\ConstFetch' === get_class($node->args[2]->value)) {
									if(in_array($node->args[2]->value->name->__toString(), $this->phpValidFilters)){
										return true;
									}
								}
							}
						}

						// Confusing escaping with sanitizing
						if(in_array( $node->name->toString(), $this->escapingFunctions )){
							$this->saveLinesNodeDetailLog($node, 'needs_sanitize_confusion_escape');
						}
					}
				}
				break;

			// isset / empty
			case 'PhpParser\Node\Expr\Isset_':
			case 'PhpParser\Node\Expr\Empty_':
				return true;
				break;

			// Conditionals
			case 'PhpParser\Node\Stmt\If_':
				return true;
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

			// Operations
			case 'PhpParser\Node\Expr\BinaryOp\Plus':
			case 'PhpParser\Node\Expr\BinaryOp\Minus':
			case 'PhpParser\Node\Expr\BinaryOp\Mul':
			case 'PhpParser\Node\Expr\BinaryOp\Div':
			case 'PhpParser\Node\Expr\BinaryOp\Mod':
			case 'PhpParser\Node\Expr\BinaryOp\Coalesce':
			//case 'PhpParser\Node\Expr\Ternary':
				return $this->processVarWrapper($node->getAttribute("parent"));
				break;

			// Casting to itself or error supress (weird development somewhere)
			case 'PhpParser\Node\Expr\Cast\Array_':
			case 'PhpParser\Node\Expr\ErrorSuppress':
				return $this->processVarWrapper($node->getAttribute("parent"));
				break;

			// Assign - Only the expression part.
			case 'PhpParser\Node\Expr\Assign':
				return false;
				break;

			// Casting
			case 'PhpParser\Node\Expr\Cast\Int_':
			case 'PhpParser\Node\Expr\Cast\Bool_':
				return true;
				break;

			// Unset
			case 'PhpParser\Node\Stmt\Unset_':
				return true;
				break;

			// Switch
			case 'PhpParser\Node\Stmt\Switch_':
				return true;
				break;

		endswitch;

		return false;
	}

	private function isThisValidForEntireVar($node)
	{
		$class = get_class($node);
		//var_dump($class);

		switch ($class) :
			// isset / empty
			case 'PhpParser\Node\Expr\Isset_':
			case 'PhpParser\Node\Expr\Empty_':
				return true;
				break;

			// Conditionals
			case 'PhpParser\Node\Stmt\If_':
				return true;
				break;

			// Operators
			case 'PhpParser\Node\Expr\BinaryOp\BooleanAnd':
			case 'PhpParser\Node\Expr\BinaryOp\BooleanOr':
			case 'PhpParser\Node\Expr\BooleanNot':
				return true;
				break;
		endswitch;

		return false;
	}
}
