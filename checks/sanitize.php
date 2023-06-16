<?php
namespace WordPressdotorg\Plugin_Check\Checks;

use WordPressdotorg\Plugin_Check\Notice;
use WordPressdotorg\Plugin_Check\parser;
use const WordPressdotorg\Plugin_Check\{ PLUGIN_DIR, HAS_VENDOR };
use PhpParser\Error;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PhpParser\{Node, NodeFinder};

include_once PLUGIN_DIR . '/inc/class-parser.php';

class Sanitize extends parser {

	public $needSanitizeVars = [
		'_POST',
		'_REQUEST',
		'_GET',
		'_FILES',
		'_COOKIE',
		'_SERVER',
		'_SESSION'
	];

	//TODO ALPHA: Please recheck with the team.
	public $commonIntermediateFunctions = [
		'wp_unslash',
		'trim'
	];

	public $noSanitizingNeeded = [
		'wp_verify_nonce',
		'intval',
		'absint',
		'strpos',
		'in_array',
		'array_key_exists'
	];

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
		'wp_kses_post'
	];

	function check_sanitize(){
		$this->needsGetParents=true;

		if ( ! HAS_VENDOR ) {
			return new Notice(
				'sanitize_not_tested',
				'Sanitize have not been tested, as the vendor directory is missing. Perhaps you need to run <code>`composer install`</code>.'
			);
		}

		if(!empty($this->files)){
			$php_files = preg_grep( '#\.php$#', $this->files );
			if(!empty($php_files)){
				foreach ($php_files as $file){
					$this->load( $this->path . $file );
				}
				return $this->logErrors;
			}
		}

		return false;
	}

	function find() {
		// $_POST, $_REQUEST, etc check.
		$vars = $this->nodeFinder->findInstanceOf( $this->stmts, \PhpParser\Node\Expr\Variable::class );
		if(!empty($vars)){
			foreach ($vars as $var){
				if(!empty($var->name) && in_array($var->name, $this->needSanitizeVars)) {
					$var->setAttribute( 'comments', null );
					$this->process_var( $var );
				}
			}
		}

		// php://input check
		$vars = $this->nodeFinder->findInstanceOf( $this->stmts, \PhpParser\Node\Expr\FuncCall::class );
		if(!empty($vars)){
			foreach ($vars as $var){
				if(!empty($var->name) && in_array($var->name, ['file_get_contents'])) {
					if(isset($var->args[0])){
						if('PhpParser\Node\Scalar\String_' === get_class($var->args[0]->value)){
							if('php://input' === $var->args[0]->value->value){
								$var->setAttribute( 'comments', null );
								$this->process_var( $var );
							}
						}
					}
				}
			}
		}
		$this->show_log(__('Your code needs to be sanitized.', 'plugin_check'));
		$this->show_log(__('Your code is processing the entire variable.', 'plugin_check'), 'process_entire');
	}

	function process_var($var, $parent=false){
		if(!empty($var->getAttribute('parent'))){
			if('PhpParser\Node\Expr\ArrayDimFetch' === get_class($var->getAttribute('parent'))){
				$this->process_var($var->getAttribute('parent'), true);
			} else {
				if(!$parent){
					// Processing the entire $_VAR
					$this->save_lines_node_detail_log($var->getAttribute('parent'), 'process_entire');
				}
				$this->process_var_wrapper($var->getAttribute('parent'));
			}
		} else {
			if(!$parent) {
				// Processing the entire $_VAR
				$this->save_lines_node_detail_log($var, 'process_entire');
			} else {
				// No sanitizing
				$this->save_lines_node_detail_log($var);
			}
		}
	}

	function process_var_wrapper($wrapper, $parent=0){
		$valid = false;
		if(!empty($wrapper)){
			if('PhpParser\Node\Arg' == get_class($wrapper) && !empty($wrapper->getAttribute("parent"))){
				$wrapper = $wrapper->getAttribute("parent");
			}
			$valid = $this->isThisValidForSanitization($wrapper);
		}
		if(!$valid){
			// No sanitizing
			$this->save_lines_node_detail_log($wrapper);
		}
		//var_dump(get_class($wrapper));
		return $valid;
	}

	function isThisValidForSanitization($node){
		$class = get_class($node);
		//var_dump($class);

		switch ($class):
			// Functions
			case 'PhpParser\Node\Expr\FuncCall':
				if(!empty($node->name->toCodeString())){
					if(in_array($node->name->toCodeString(), $this->sanitizeFunctions)){
						return true;
					} else if (in_array($node->name->toCodeString(), $this->noSanitizingNeeded)){
						return true;
					} else if (in_array($node->name->toCodeString(), $this->commonIntermediateFunctions)){
						return $this->process_var_wrapper($node->getAttribute("parent"));
					} else if ('array_map' === $node->name->toCodeString()){
						if(isset($node->args[0]->value)){
							if('PhpParser\Node\Scalar\String_' === get_class($node->args[0]->value)){
								if(isset($node->args[0]->value->value)){
									if(in_array($node->args[0]->value->value, $this->sanitizeFunctions)){
										return true;
									}
								}
							}
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
				return $this->process_var_wrapper($node->getAttribute("parent"));
				break;

			// Casting
			case 'PhpParser\Node\Expr\Cast\Int_':
			case 'PhpParser\Node\Expr\Cast\Bool_':
				return true;
				break;

		endswitch;

		return false;
	}
}
