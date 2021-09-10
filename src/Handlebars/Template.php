<?php
/**
 * Handlebars base template
 * contain some utility method to get context and helpers
 *
 * @category  Xamin
 * @package   Handlebars
 * @author    fzerorubigd <fzerorubigd@gmail.com>
 * @author    Behrooz Shabani <everplays@gmail.com>
 * @author    Mardix <https://github.com/mardix>
 * @copyright 2012 (c) ParsPooyesh Co
 * @copyright 2013 (c) Behrooz Shabani
 * @copyright 2013 (c) Mardix
 * @license   MIT
 * @link      http://voodoophp.org/docs/handlebars
 */

namespace Handlebars;

use InvalidArgumentException;
use RuntimeException;

class Template
{
    /**
     * @var Handlebars
     */
    protected $handlebars;


    protected $tree = [];

    protected $source = '';

    /**
     * @var array Run stack
     */
    private $stack = [];
    private $_stack = [];

    /**
     * Handlebars template constructor
     *
     * @param Handlebars $engine handlebar engine
     * @param array      $tree   Parsed tree
     * @param string     $source Handlebars source
     */
    public function __construct(Handlebars $engine, $tree, $source)
    {
        $this->handlebars = $engine;
        $this->tree = $tree;
        $this->source = $source;
        array_push($this->stack, [0, $this->getTree(), false]);

    }

    /**
     * Get current tree
     *
     * @return array
     */
    public function getTree()
    {
        return $this->tree;
    }

    /**
     * Get current source
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Get current engine associated with this object
     *
     * @return Handlebars
     */
    public function getEngine()
    {
        return $this->handlebars;
    }

    /**
     * set stop token for render and discard method
     *
     * @param string $token token to set as stop token or false to remove
     *
     * @return void
     */

    public function setStopToken($token)
    {
        $this->_stack = $this->stack;
        $topStack = array_pop($this->stack);
        $topStack[2] = $token;
        array_push($this->stack, $topStack);
    }

    /**
     * get current stop token
     *
     * @return string|bool
     */

    public function getStopToken()
    {
        return end($this->stack)[2];
    }

    /**
     * Render top tree
     *
     * @param mixed $context current context
     *
     * @throws \RuntimeException
     * @return string
     */
    public function render($context)
    {
        if (!$context instanceof Context) {
            $context = new Context($context, [
                'enableDataVariables' => $this->handlebars->isDataVariablesEnabled(),
            ]);
        }
        $topTree = end($this->stack); // never pop a value from stack
        list($index, $tree, $stop) = $topTree;

        // Whitespace control
        $mustStripWhitespaceBefore = false;

        $buffer = '';
        while (array_key_exists($index, $tree)) {
            $current = $tree[$index];

            //if the section is exactly like waitFor
            if (is_string($stop)
                && $current[Tokenizer::TYPE] == Tokenizer::T_ESCAPED
                && $this->getNameForNode($current) === $stop
            ) {
                break;
            }
            switch ($current[Tokenizer::TYPE]) {
            case Tokenizer::T_SECTION :
                $newStack = isset($current[Tokenizer::NODES])
                    ? $current[Tokenizer::NODES] : [];
                array_push($this->stack, [0, $newStack, false]);
                $newValue = $this->section($context, $current);

                if ($this->handlebars->isWhitespaceControlEnabled()) {
                    // Apply whitespace control to the content of a section
                    if ($mustStripWhitespaceBefore || $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_BEFORE_CONTENT)) {
                        $newValue = $this->stripLeadingWhitespace($newValue);
                    }
                    if ($this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_AFTER_CONTENT)) {
                        $newValue = $this->stripTrailingWhitespace($newValue);
                    }

                    // Apply whitespace control to the text before the section
                    if ($this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_BEFORE)) {
                        $buffer = $this->stripTrailingWhitespace($buffer);
                    }

                    // Make sure the next nodes to strip before
                    $mustStripWhitespaceBefore = $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_AFTER);
                }

                $buffer .= $newValue;
                array_pop($this->stack);
                break;
            case Tokenizer::T_INVERTED :
                $newStack = isset($current[Tokenizer::NODES]) ?
                    $current[Tokenizer::NODES] : [];
                array_push($this->stack, [0, $newStack, false]);
                $newValue = $this->inverted($context, $current);
                $buffer .= $newValue;
                array_pop($this->stack);
                break;
            case Tokenizer::T_COMMENT :
                $buffer .= '';
                break;
            case Tokenizer::T_PARTIAL:
            case Tokenizer::T_PARTIAL_2:
                $newValue = $this->partial($context, $current);
                if ($this->handlebars->isWhitespaceControlEnabled()) {
                    if ($mustStripWhitespaceBefore || $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_BEFORE)) {
                        $buffer = $this->stripTrailingWhitespace($buffer);
                        $newValue = $this->stripLeadingWhitespace($newValue);
                    }
                    if ($this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_AFTER_CONTENT)) {
                        $newValue = $this->stripTrailingWhitespace($newValue);
                    }

                    // Make sure the next nodes to strip before
                    $mustStripWhitespaceBefore = $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_AFTER);
                }
                $buffer .= $newValue;
                break;
            case Tokenizer::T_UNESCAPED:
            case Tokenizer::T_UNESCAPED_2:
                $newValue = $this->variables($context, $current, false);
                if ($this->handlebars->isWhitespaceControlEnabled()) {
                    if ($mustStripWhitespaceBefore || $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_BEFORE)) {
                        $buffer = $this->stripTrailingWhitespace($buffer);
                        $newValue = $this->stripLeadingWhitespace($newValue);
                    }
                    if ($this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_AFTER_CONTENT)) {
                        $newValue = $this->stripTrailingWhitespace($newValue);
                    }

                    // Make sure the next nodes to strip before
                    $mustStripWhitespaceBefore = $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_AFTER);
                }
                $buffer .= $newValue;
                break;
            case Tokenizer::T_ESCAPED:
                $newValue = $this->variables($context, $current, true);
                if ($this->handlebars->isWhitespaceControlEnabled()) {
                    if ($mustStripWhitespaceBefore || $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_BEFORE)) {
                        $buffer = $this->stripTrailingWhitespace($buffer);
                    }

                    // Make sure the next nodes to strip before
                    $mustStripWhitespaceBefore = $this->isArrayValueTrue($current, Tokenizer::STRIP_WHITESPACE_AFTER);
                }
                $buffer .= $newValue;
                break;
            case Tokenizer::T_TEXT:
                $newValue = $current[Tokenizer::VALUE];

                // If the tag before this text has whitespace control to remove whitespace after, then make sure that
                // this text doesn't have start with any whitespace.
                if ($this->handlebars->isWhitespaceControlEnabled()) {
                    if ($mustStripWhitespaceBefore) {
                        $newValue = $this->stripLeadingWhitespace($newValue);
                    }

                    // If the new value only contained spaces (which were stripped), then we need to continue stripping
                    // before the next node.
                    $mustStripWhitespaceBefore = strlen($newValue) === 0;
                }

                $buffer .= $newValue;
                break;
            default:
                throw new RuntimeException(
                    'Invalid node type : ' . json_encode($current)
                );
            }

            $index++;
        }
        if ($stop) {
            //Ok break here, the helper should be aware of this.
            $newStack = array_pop($this->stack);
            $newStack[0] = $index;
            $newStack[2] = false; //No stop token from now on
            array_push($this->stack, $newStack);
        }

        return $buffer;
    }

    /**
     * Discard top tree
     *
     * @param mixed $context current context
     *
     * @return string
     */
    public function discard()
    {
        $topTree = end($this->stack); //This method never pop a value from stack
        list($index, $tree, $stop) = $topTree;
        while (array_key_exists($index, $tree)) {
            $current = $tree[$index];
            $index++;
            //if the section is exactly like waitFor
            if (is_string($stop)
                && $current[Tokenizer::TYPE] == Tokenizer::T_ESCAPED
                && $this->getNameForNode($current) === $stop
            ) {
                break;
            }
        }
        if ($stop) {
            //Ok break here, the helper should be aware of this.
            $newStack = array_pop($this->stack);
            $newStack[0] = $index;
            $newStack[2] = false;
            array_push($this->stack, $newStack);
        }

        return '';
    }

    /**
     * Process section nodes
     *
     * @param Context $context current context
     * @param array   $current section node data
     *
     * @throws \RuntimeException
     * @return string the result
     */
    private function section(Context $context, $current)
    {
        $helpers = $this->handlebars->getHelpers();
        $sectionName = $this->getNameForNode($current);
        if ($helpers->has($sectionName)) {
            if (isset($current[Tokenizer::END])) {
                $source = substr(
                    $this->getSource(),
                    $current[Tokenizer::INDEX],
                    $current[Tokenizer::END] - $current[Tokenizer::INDEX]
                );
            } else {
                $source = '';
            }
            $params = [
                $this, //First argument is this template
                $context, //Second is current context
                $current[Tokenizer::ARGS], //Arguments
                $source
            ];

            $return = call_user_func_array($helpers->$sectionName, $params);
            if ($return instanceof String) {
                return $this->handlebars->loadString($return)->render($context);
            } else {
                return $return;
            }
        } elseif (trim($current[Tokenizer::ARGS]) == '') {
            // fallback to mustache style each/with/for just if there is
            // no argument at all.
            try {
                $sectionVar = $context->get($sectionName, true);
            } catch (InvalidArgumentException $e) {
                throw new RuntimeException(
                    $sectionName . ' is not registered as a helper'
                );
            }
            $buffer = '';
            if (is_array($sectionVar) || $sectionVar instanceof \Traversable) {
                foreach ($sectionVar as $index => $d) {
                    $context->pushIndex($index);
                    $context->push($d);
                    $buffer .= $this->render($context);
                    $context->pop();
                    $context->popIndex();
                }
            } elseif (is_object($sectionVar)) {
                //Act like with
                $context->push($sectionVar);
                $buffer = $this->render($context);
                $context->pop();
            } elseif ($sectionVar) {
                $buffer = $this->render($context);
            }

            return $buffer;
        } else {
            throw new RuntimeException(
                $sectionName . ' is not registered as a helper'
            );
        }
    }

    /**
     * Process inverted section
     *
     * @param Context $context current context
     * @param array   $current section node data
     *
     * @return string the result
     */
    private function inverted(Context $context, $current)
    {
        $sectionName = $this->getNameForNode($current);
        $data = $context->get($sectionName);
        if (!$data) {
            return $this->render($context);
        } else {
            //No need to discard here, since it has no else
            return '';
        }
    }

    /**
     * Process partial section
     *
     * @param Context $context current context
     * @param array   $current section node data
     *
     * @return string the result
     */
    private function partial(Context $context, $current)
    {
        $partial = $this->handlebars->loadPartial($this->getNameForNode($current));

        if ($this->handlebars->isWhitespaceControlEnabled()) {
            if ($current[Tokenizer::ARGS]) {
                $context = $context->get($current[Tokenizer::ARGS]);
            }
        } else {
            if ($current[Tokenizer::ORIGINAL_ARGS]) {
                $context = $context->get($current[Tokenizer::ORIGINAL_ARGS]);
            } else if ($current[Tokenizer::ARGS]) {
                $context = $context->get($current[Tokenizer::ARGS]);
            }
        }

        return $partial->render($context);
    }

    /**
     * Process partial section
     *
     * @param Context $context current context
     * @param array   $current section node data
     * @param boolean $escaped escape result or not
     *
     * @return string the result
     */
    private function variables(Context $context, $current, $escaped)
    {
        $name = $this->getNameForNode($current);
        $value = $context->get($name);

        // If @data variables are enabled, use the more complex algorithm for handling the the variables otherwise
        // use the previous version.
        if ($this->handlebars->isDataVariablesEnabled()) {
            if (substr(trim($name), 0, 1) == '@') {
                $variable = $context->getDataVariable($name);
                if (is_bool($variable)) {
                    return $variable ? 'true' : 'false';
                }
                return $variable;
            }
        } else {
            // If @data variables are not enabled, then revert back to legacy behavior
            if ($name == '@index') {
                return $context->lastIndex();
            }
            if ($name == '@key') {
                return $context->lastKey();
            }
        }

        if ($escaped) {
            $args = $this->handlebars->getEscapeArgs();
            array_unshift($args, $value);
            $value = call_user_func_array(
                $this->handlebars->getEscape(),
                array_values($args)
            );
        }

        return $value;
    }

    /**
     * Gets the name of the template node. If whitespace control is enabled, the name will be the parsed name without
     * the control characters otherwise the name with the whitespace control characters are returned to keep
     * compatibility with templates before whitespace control was added.
     *
     * For example, given the template "~foo~", if whitespace control is enabled, the name should be "foo" otherwise
     * the name will be "~foo~".
     * @param array $current
     * @return false|string False when the name does not exist in the node or the name of the node given the rules above.
     */
    private function getNameForNode($current)
    {
        if ($this->handlebars->isWhitespaceControlEnabled() && array_key_exists(Tokenizer::NAME, $current)) {
            // We want to return the name of the node when whitespace control is enabled because name has already
            // been parsed by the tokenizer to remove the whitespace control character (~). If the name doesn't exist,
            // then fall to the previous logic.
            return $current[Tokenizer::NAME];
        }

        // Original name is the name before parsing. This is returned when whitespace control has not been enabled to
        // keep backwards compatibility with functionality before whitespace control was added.
        if (array_key_exists(Tokenizer::ORIGINAL_NAME, $current)) {
            return $current[Tokenizer::ORIGINAL_NAME];
        } else if (array_key_exists(Tokenizer::NAME, $current)) {
            return $current[Tokenizer::NAME];
        } else {
            return false;
        }
    }

    /**
     * @param array $array
     * @param string|int $key
     * @return bool
     */
    private function isArrayValueTrue($array, $key)
    {
        // This is the same as null coalescing operator (??) added in PHP7 but that can't be used since we need to
        // support <7. When this library is updated to PHP7 this function can be replaced.
        return isset($array[$key]) && $array[$key] === true;
    }

    /**
     * Removes the whitespace characters from the end of the given text string and returns the resulting string. See
     * {@see isWhitespaceCharacter} to see which characters are removed.
     * @param string $text
     * @return string The string without the trailing whitespace.
     */
    private function stripTrailingWhitespace($text)
    {
        $length = strlen($text);
        while ($length > 0) {
            if ($this->isWhitespaceCharacter($text, $length - 1)) {
                $length--;
            } else {
                // we encountered a non-whitespace character so stop
                break;
            }
        }
        if ($length !== strlen($text)) {
            $text = substr($text, 0, $length);
        }
        return $text;
    }

    /**
     * Removes the whitespace characters from the start of the given text string and returns the resulting string. See
     * {@see isWhitespaceCharacter} to see which characters are removed.
     * @param string $text
     * @return string The string without the leading whitespace.
     */
    private function stripLeadingWhitespace($text)
    {
        $offset = 0;
        while ($offset < strlen($text)) {
            if ($this->isWhitespaceCharacter($text, $offset)) {
                $offset++;
            } else {
                // we encountered a non-whitespace character so stop
                break;
            }
        }
        if ($offset !== 0) {
            $text = substr($text, $offset);
        }
        return $text;
    }

    private function isWhitespaceCharacter($text, $index)
    {
        // Matches the same characters as the Javascript Handlebars implementation.
        return $text[$index] === ' ' ||     // space
            $text[$index] === "\n" ||       // newline
            $text[$index] === "\f" ||       // line feed
            $text[$index] === "\r" ||       // carriage return
            $text[$index] === "\t" ||       // horizontal tab
            $text[$index] === "\v" ||       // vertical tab
            $text[$index] === "\u{00a0}" || // nbsp
            $text[$index] === "\u{1680}" || // ogham space mark
            $text[$index] === "\u{2000}" || // en quad
            $text[$index] === "\u{2001}" || // em quad
            $text[$index] === "\u{2002}" || // en space
            $text[$index] === "\u{2003}" || // em space
            $text[$index] === "\u{2004}" || // three-per-em space
            $text[$index] === "\u{2005}" || // four-per-em space
            $text[$index] === "\u{2006}" || // six-per-em space
            $text[$index] === "\u{2007}" || // figure space
            $text[$index] === "\u{2008}" || // punctuation space
            $text[$index] === "\u{2009}" || // thin space
            $text[$index] === "\u{200a}" || // hair space
            $text[$index] === "\u{2028}" || // line separator
            $text[$index] === "\u{2029}" || // paragraph separator
            $text[$index] === "\u{202f}" || // narrow nbsp
            $text[$index] === "\u{205f}" || // medium mathematical space
            $text[$index] === "\u{3000}" || // ideographic space
            $text[$index] === "\u{feff}";   // zero-width no-break space
    }

    public function __clone()
    {
        return $this;
    }
}
