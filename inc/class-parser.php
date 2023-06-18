<?php

namespace WordPressdotorg\Plugin_Check;

if (! defined('ABSPATH')) {
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

abstract class Parser extends Check_Base
{
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

    public function load($file)
    {
	    $this->log = [];
        if (file_exists($file)) {
	        $this->file = $file;
            $this->fileRelative = str_replace($this->path, '', $this->file);
            $this->parseFile($this->file);
            $this->prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
            if ($this->isReady()) {
                $this->find();
            }
        } else {
            $this->logMessagesObjects[] = new \WordPressdotorg\Plugin_Check\Notice(
                'parser_read_file_error',
                sprintf('File %s can\'t be read by PHP', $file)
            );
        }
        return null;
    }

    abstract public function find();

    private function parseFile($file)
    {
        //Options

        // Activate ability to get parents. Performance will be degraded.
        // Get parents using $node->getAttribute('parent')
        if ($this->needsGetParents) {
            $traverser = new NodeTraverser;
            $traverser->addVisitor(new ParentConnectingVisitor);
        }

        if ($this->needsGetSiblings) {
            $traverser = new NodeTraverser;
            $traverser->addVisitor(new NodeConnectingVisitor);
        }

        //Parse file.
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        try {
            $code = file_get_contents($file);
            $this->stmts = $parser->parse($code);
            if ($this->needsGetParents || $this->needsGetSiblings) {
                $this->stmts = $traverser->traverse($this->stmts);
            }
        } catch (\PhpParser\Error $error) {
            echo $this->fileRelative . ": Parse error: {$error->getMessage()}\n";
            return;
        }
        $this->nodeFinder = new NodeFinder;
        $this->ready = true;
    }

    public function isReady()
    {
        return $this->ready;
    }

    public function getArgs($args)
    {
        $argsArray = [];
        foreach ($args as $arg) {
            $argsArray[] = $this->prettyPrinter->prettyPrint([$arg]);
        }
        return '( '.implode(', ', $argsArray).' )';
    }

    public function logFunctionCall($func_call)
    {
        $func_call->setAttribute('comments', null);
        $this->saveLog($func_call->getStartLine(), $this->prettyPrinter->prettyPrint([$func_call]) . ';');
    }

    public function logNamespace($namespace)
    {
        $lineText = 'namespace '.$namespace->name->toCodeString();
        $this->saveLog($namespace->getStartLine(), $lineText);
    }

    public function logAbstractionDeclaration($abstraction)
    {
        if (!empty($abstraction)) {
            foreach ($abstraction as $abstract) {
                $type = 'unknown';
                switch ($abstract->getType()) {
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
                $lineText = $type." ".$abstract->name->toString();
                /*if(!empty($abstract->params) && $abstract->getType()=='Stmt_Function'){
                    $lineText .= " ".$this->get_args($abstract->params);
                }*/
                $this->saveLog($abstract->getStartLine(), $lineText);
            }
        }
    }

	/**
	 * Breaks down the elements inside an echo and returns an array with each of its elements
	 * @param $expr
	 * @param $exprElements
	 *
	 * @return array|mixed
	 */
    public function unfoldEchoExpr($expr, $exprElements = [])
    {
        if (is_a($expr, 'PhpParser\Node\Expr\BinaryOp\Concat')) {
            $exprElements = array_merge($this->unfold_echo_expr($expr->left, $exprElements), $exprElements);
            if (!empty($expr->right)) {
                $exprElements[] = $expr->right;
            }
        } else {
            $exprElements[] = $expr;
        }
        return $exprElements;
    }

    public function hasLog($logid = 'default')
    {
        if (!empty($this->log[$logid])) {
            return true;
        }
        return false;
    }

    public function saveLog($lineNumber, $text, $logid = 'default')
    {
        $logLine = [
            'location' => $this->fileRelative.":".$lineNumber." ",
            'text' => $text,
            'textFormatted' => $text,
            'startLine' => $lineNumber
        ];
        if (!isset($this->log_longer_location[$logid])) {
            $this->log_longer_location[$logid]=0;
        }
        if (strlen($logLine['location']) > $this->log_longer_location[$logid]) {
            $this->log_longer_location[$logid]=strlen($logLine['location']);
        }
        $this->log[$logid][] = $logLine;
    }

    public function saveLinesLog($startLineNumber, $endLineNumber = '', $logid = 'default'): int
    {
        $lineLenght = 0;
        if (empty($this->log_already_shown_lines[$logid])) {
            $this->log_already_shown_lines[$logid]=[];
        }
        if (!isset($this->log_already_shown_lines[$logid][$startLineNumber])) {
            $lines = $this->getLines($startLineNumber, $endLineNumber);
            $linesString = implode("", $lines);
            $lineLenght = strlen($linesString);
            $this->log_already_shown_lines[$logid][$startLineNumber]=[
                'lineLenght' => $lineLenght
            ];
            $this->saveLog($startLineNumber, $linesString, $logid);
        } else {
            $lineLenght = $this->log_already_shown_lines[$logid][$startLineNumber]['lineLenght'];
        }
        return $lineLenght;
    }

    public function saveLinesNodeDetailLog($node, $logid = 'default')
    {
        $startLine = $node->getStartLine();

        $lineLenght = $this->saveLinesLog($startLine, $node->getEndLine(), $logid);

        $detail = $this->prettyPrinter->prettyPrint([ $node ]);
        if (strlen($detail) + 20 < $lineLenght) {
            foreach ($this->log[ $logid ] as $key => $log) {
                if ($log['startLine'] === $startLine) {
                    if (empty($this->log[ $logid ][ $key ]['detail'])) {
                        $this->log[ $logid ][ $key ]['detail'] = [];
                    }
                    $this->log[ $logid ][ $key ]['detail'][] = $detail;
                    break;
                }
            }
        }
    }

    public function getLines($startLineNumber, $endLineNumber = '')
    {
        $file = new \SplFileObject($this->file);
        $lines = [];

        if (empty($endLineNumber)) {
            $endLineNumber=$startLineNumber;
        }

        for ($i=1; $i<=$endLineNumber; $i++) {
            if ($i>=$startLineNumber) {
                $lines[] = trim($file->current(), " \t\0\x0B");
            }
            if (!$file->eof()) {
                $file->current();
                $file->next();
            } else {
                break;
            }
        }
        if (!empty($lines)) {
            $lines[array_key_last($lines)] = str_replace(array("\r", "\n"), '', $lines[array_key_last($lines)]);
        }
        return $lines;
    }

    private function loadLogMessagesVariable()
    {
        $this->logMessagesTexts = [
            'needs_sanitize' => [
                'text' => __('Your code needs to be sanitized.', 'plugin_check'),
                'type' => 'Error'
            ],
            'sanitize_process_entire_var' => [
                'text' => __('Your code is processing the entire variable.', 'plugin_check'),
                'type' => 'Error'
            ],
        ];
    }

    private function getLogText($logid = 'default')
    {
        if (empty($this->logMessagesTexts)) {
            $this->loadLogMessagesVariable();
        }
        if (isset($this->logMessagesTexts[$logid]['text'])) {
            return $this->logMessagesTexts[$logid]['text'];
        }
        return __('Error', 'plugin-check');
    }

    private function getLogType($logid = 'default')
    {
        if (empty($this->logMessagesTexts)) {
            $this->loadLogMessagesVariable();
        }
        if (isset($this->logMessagesTexts[$logid]['type'])) {
            return $this->logMessagesTexts[$logid]['type'];
        }
        return 'Error';
    }

    public function showLog($logid = 'default')
    {
        if (!empty($this->log[$logid])) {
            $text = sprintf(
                '%s File %s',
                "<strong>{$this->getLogText($logid)}</strong>",
                $this->fileRelative
            );

            foreach ($this->log[$logid] as $log) {
                $text .= sprintf(
                    '<br><br>Line %d: %s',
                    $log['startLine'],
                    "<code>{$log['text']}</code>"
                );
                if (!empty($log['detail'])) {
                    foreach ($log['detail'] as $key => $detail) {
                        $log['detail'][$key] = '<code>'.$detail.'</code>';
                    }
                    $detail = implode(', ', $log['detail']);
                    $text .= sprintf(
                        '<br>Check %s',
                        $detail
                    );
                }
            }
            $logType = '\WordPressdotorg\Plugin_Check\\'.$this->getLogType($logid);
            $this->logMessagesObjects[] = new $logType(
                'needs_sanitize',
                $text
            );
        }
    }
}
