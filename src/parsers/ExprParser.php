<?php

namespace contour\parser\parsers;

use contour\parser\expressions\BooleanExpression;
use contour\parser\exceptions\ExpressionParseException;
use contour\parser\expressions\ElseExpression;
use contour\parser\expressions\iExpression;
use contour\parser\expressions\OperationExpression;
use contour\parser\expressions\ParamsExpression;
use contour\parser\expressions\RawValueExpression;
use contour\parser\expressions\ResultObject;
use contour\parser\expressions\TagExpression;
use contour\parser\expressions\ThenExpression;
use contour\parser\expressions\VariableDeclarationExpression;
use contour\parser\parsers\ParseStack;
use contour\parser\exceptions\StackEmptyException;

/**
 * Class ExprParser
 * @package bnjhope\php_parser\parsers
 */
class ExprParser
{

    /**
     * @var ParseStack
     * The stack used for parsing.
     */
    private $stack;

    public function __construct()
    {
        $this->stack = new ParseStack();
    }

    /**
     * @var array
     * Words at the start of lines that determine what type of command is being called.
     */
    private $linestarts = array("if", "then", "else", "let", "return", "params");

    /**
     * @var string
     * The expression to parse in string form.
     */
    private $expr;

    /**
     * @var array
     * Representation of the expression to be parsed as an array of characters.
     */
    private $exprArray;

    /**
     * @var integer
     * The current location of the parser in the expression.
     */
    private $parseIndex;

    private $boolExprs = ["=", ">", "<", "<=", ">="];

    private $arithmeticExprs = ["+", "-", "*", "/", "%", "&", "|"];
    /**
     * Parses a line of the function that is passed to the program.
     * @param $expr
     * The expression to be parsed
     * @return iExpression
     * Expression object which can be evaluated
     * @throws ExpressionParseException
     * If the expression is syntactically invalid, this exception is thrown.
     */
    function parse($expr)
    {
        //initialise the parser
        $this->setUpParser($expr);

        /**
         * @var object
         * The object that results from parsing the line.
         */
        $resultObject = null;

        /**
         * @var string
         * The first word in the line - determines the type of command that the line specifies.
         */
        $expressionType = $this->getType();

        //depending on the first word of the expression, parse the part after that first word.
        switch ($expressionType) {
            case $this->linestarts[0] :
                $resultObject = $this->parseIf();
                break;
            case $this->linestarts[1] :
                $resultObject = $this->parseThen();
                break;
            case $this->linestarts[2] :
                $resultObject = $this->parseElse();
                break;
            case $this->linestarts[3] :
                $resultObject = $this->parseVariable();
                break;
            case $this->linestarts[4] :
                $resultObject = $this->parseReturn();
                break;
            case $this->linestarts[5] :
                $resultObject = $this->parseParams();
                break;
            default :
                throw new ExpressionParseException("Parser failed to recognise command keyword - check first word");
        }

        return $resultObject;
    }

    /**
     * Gets the type of the current line to be parsed.
     * @return string
     * The type of the line that is being parsed.
     * @throws ExpressionParseException
     * If the line is syntactically incorrect, this exception is thrown.
     * @throws StackEmptyException
     * If there is an attempt to pop from the top of stack when it is empty then this is thrown.
     */
    function getType()
    {

        /**
         * The current char in the expression.
         */
        $currentChar = "";

        /**
         * Boolean to determine if a space
         */
        $spaceFound = false;

        /**
         * Result string to be returned
         */
        $result = "";

        /**
         * If there are no spaces in the string, then we need to terminate the while statement.
         */
        $limit = count($this->exprArray);

        //push everything upto the first space onto the stack
        while (!$spaceFound && $this->parseIndex < $limit) {
            $currentChar = $this->getNextChar();
            $this->stack->push($currentChar);
            $spaceFound = $currentChar == " " ? true : false;
        }

        //if the loop terminated because the expression came to the end of the line then throw this exception.
        if ($this->parseIndex >= $limit) {
            throw new ExpressionParseException("No keyword detected in expression - no spaces in line for parser to stop on.");
        }

        //get the space character off the stack
        $this->stack->pop();

        //get the key by popping every character off of the stack
        //and forming a string from them.
        while (!$this->stack->isEmpty()) {
            $result = $this->stack->pop() . $result;
        }

        return $result;
    }

    /**
     * Parses a boolean expression in the grammar.
     * @throws ExpressionParseException
     * If the statement is syntactically incorrect, then this exception is thrown.
     * @throws StackEmptyException
     * If the parser tries to pop off the top of the stack when it is empty then this exception is thrown.
     */
    function parseIf()
    {

        /**
         * The result to be returned from parsing the expression.
         */
        $result = new BooleanExpression();

        /**
         * Determines if the result expression is full or not.
         */
        $expressionComplete = false;

        //while there are still characters to be parsed
        //or the expression is not yet complete
        while ($this->hasNext() && !$expressionComplete) {

            //get the next char in the stream
            $currentChar = $this->getNextChar();

            //decide what to do next depending on what that character is
            switch ($currentChar) {

                //parses tags
                case "#" :

                    //make the expression to add the parsed reference tag
                    //if there are problems parsing, then throw that exception.
                    $exprToAdd = $this->parseTag();

                    //if the first expression is null then that means that the tag should take the place
                    //of the first expression
                    if ($result->getFirstExpr() == null) {
                        $result->setFirstExpr($exprToAdd);

                        //if the operator is null but the first expression is full then that means that the tag is
                        //in the place of the operator of the expression, which is illegal.
                    } else if ($result->getOperator() == null) {
                        throw new ExpressionParseException("Expression contains tag at operator space.");

                        //if the second expression is null and everything else is full then that means that the reference tag
                        //if for the second expression
                    } else if ($result->getSecondExpr() == null) {
                        $result->setSecondExpr($exprToAdd);
                        $expressionComplete = true;
                    }
                    break;
                //parse the sub expression recursively if its an open bracket
                case "(" :
                    //add open bracket to the stack
                    $this->stack->push($currentChar);

                    if ($result->getFirstExpr() == null) {

                        //try to set the first expression of the result to this subexpression
                        $result->setFirstExpr($this->parseIf());
                    } elseif ($result->getSecondExpr() == null) {

                        //try to set the second expression of the result to this subexpression
                        $result->setSecondExpr($this->parseIf());

                        //expression now full - exit the parsing
                        $expressionComplete = true;
                    } else
                        //throw exception if there are problems
                        throw new ExpressionParseException("Error on open bracket");

                    //if the open bracket is the last character on the stack then pop it off
                    if ($this->stack->top() == "(")
                        $this->stack->pop();

                    //otherwise throw a parse exception
                    else
                        throw new ExpressionParseException("Error on brackets");
                    break;

                case " " :

                    //set the expression to add back to empty so it can be filled
                    $exprToAdd = "";

                    //while the top of the stack is not an open bracket and while the stack is not empty
                    while (!$this->stack->isEmpty() && $this->stack->top() != "(") {

                        //pop off the top of the stack into the expression to add string
                        $exprToAdd = $this->stack->pop() . $exprToAdd;

                    }

                    //if a bracketed statement has just been resolved then a property has already been filled
                    //and so does not need any further action.
                    if ($this->previousTokenIsCloseBracket()) break;

                    //if the first expression is empty then use this result to fill it
                    if ($result->getFirstExpr() == null)
                        $result->setFirstExpr(new RawValueExpression($exprToAdd));

                    //if the first expression is not empty but the operator is then fill it
                    elseif ($result->getOperator() == null)
                        $result->setOperator(new OperationExpression($exprToAdd));

                    //if the first expression and operator are not empty but the second expression is then fill it
                    elseif ($result->getSecondExpr() == null) {
                        $result->setSecondExpr(new RawValueExpression($exprToAdd));
                        $expressionComplete = true;
                    } else
                        throw new ExpressionParseException("Illegal space character found after " . $exprToAdd);
                    break;

                case ")" :
                    //set the expression to add back to empty so it can be filled
                    $exprToAdd = "";

                    //while the top of the stack is not an open bracket and while the stack is not empty
                    while ($this->stack->top() != "(" && !$this->stack->isEmpty()) {
                        $exprToAdd = $this->stack->pop() . $exprToAdd;
                    }

                    if ($this->stack->isEmpty())
                        throw new ExpressionParseException("Closed bracket found, no matching open bracket found before " . $exprToAdd . ".");

                    if ($result->getFirstExpr() != null && $result->getOperator() != null && $result->getSecondExpr() == null) {
                        $result->setSecondExpr(new RawValueExpression($exprToAdd));
                        $expressionComplete = true;
                    } else if ($result->getFirstExpr() == null) {
                        $result->setFirstExpr(new RawValueExpression($exprToAdd));
                        $expressionComplete = true;
                    } else
                        throw new ExpressionParseException("Illegal close bracket - closes before expression is finished.");
                    break;

                //if its not a special character, just add it to the stack
                default :
                    $this->stack->push($currentChar);
                    break;
            }
        }

        //if the loop is done and there are still things left to parse
        //then either empty the stack or empty it to the first open bracket
        //so that it can be added to the expression
        $exprToAdd = "";
        while (!$this->stack->isEmpty() && $this->stack->top() != "(") {
            $exprToAdd = $this->stack->pop() . $exprToAdd;
        }

        //if the parser reaches the end of the line and the operator and first expression are full and the second one is empty
        //then fill the second expression with the contents of the stack up to the first opening bracket or until the stack is empty
        if ($result->getFirstExpr() != null && $result->getOperator() != null && $result->getSecondExpr() == null) {
            $result->setSecondExpr(new RawValueExpression($exprToAdd));
            //if all the terms are full then just return the result
        } elseif ($result->getFirstExpr() != null && $result->getSecondExpr() != null && $result->getOperator() != null) {
            //do nothing - just makes sure that this correct case does not throw exceptions

            //if only the first expression is filled and nothing else
        } elseif ($result->getFirstExpr() != null && $result->getSecondExpr() == null && $result->getOperator() == null) {
            //do nothing - just makes sure that this correct case does not throw exceptions

            //if there is a null term then throw a parse exception
        } else {
            throw new ExpressionParseException("Some values missing in expression : " . $result->getFirstExpr() . " " . $result->getOperator() . " " . $result->getSecondExpr());
        }

        return $result;
    }


    /**
     * Parses a then statement and returns a result object from it.
     * @return iExpression
     * A parsed expression following the then statement.
     * @throws ExpressionParseException
     * If the expression is syntactically incorrect then this exception is thrown.
     * @throws StackEmptyException
     * If the stack is empty when the parser tries to pop from it then this exception is thrown.
     */
    function parseThen()
    {
        /**
         * Gets the statement to do after the then
         */
        $nextInstruction = $this->getType();

        /**
         * The object to be returned
         */
        $resultObject = new ThenExpression();

        switch ($nextInstruction) {

            //if it is another if statement
            case $this->linestarts[0] :
                $resultObject->setSubExpression($this->parseIf());
                break;

            //if it is a variable declaration
            case $this->linestarts[3] :
                $resultObject->setSubExpression($this->parseVariable());
                break;

            //if it is a return statement
            case $this->linestarts[4] :
                $resultObject->setSubExpression($this->parseReturn());
                break;
            default :
                throw new ExpressionParseException("Invalid instruction following the THEN statement");
        }

        return $resultObject;
    }

    function parseElse()
    {
        /**
         * Gets the statement to do after the then
         */
        $nextInstruction = $this->getType();

        /**
         * The object to be returned
         */
        $resultObject = new ElseExpression();

        switch ($nextInstruction) {
            //if it is another if statement
            case $this->linestarts[0] :
                $resultObject->setSubExpression($this->parseIf());
                break;

            //if it is a variable declaration
            case $this->linestarts[3] :
                $resultObject->setSubExpression($this->parseVariable());
                break;

            //if it is a return statement
            case $this->linestarts[4] :
                $resultObject->setSubExpression($this->parseReturn());
                break;
            default :
                throw new ExpressionParseException("Invalid instruction following the THEN statement");
        }

        return $resultObject;
    }

    /**
     * Parses a return statement.
     * @return ResultObject
     * The result object parsed from this method.
     * @throws ExpressionParseException
     * This is thrown when there is a syntax error of some description.
     */
    function parseReturn()
    {

        /**
         * The string to be returned.
         * @var string
         */
        $result = new ResultObject();

        /**
         * The raw result to return.
         */
        $resString = "";

        if (!$this->hasNext()) {
            throw new ExpressionParseException("Not given anything to return.");
        }

        //put the rest of the line as the result container
        while ($this->hasNext()) {
            $resString .= $this->getNextChar();
        }

        //make the result value the string from the stack.
        $result->setResult(new RawValueExpression($resString));

        return $result;
    }

    /**
     * Parses the line as a variable
     * @return VariableDeclarationExpression
     * The resulting key value pair of the variable declaration.
     * @throws ExpressionParseException
     * If the expression is syntactically incorrect.
     * @throws StackEmptyException
     * If the parser stack is empty when the parser tries to pop from it.
     */
    function parseVariable()
    {

        /**
         * Result to be returned from the function.
         */
        $result = new VariableDeclarationExpression();

        /**
         * The expression that will be added to the parse tree
         */
        $exprToAdd = "";

        //while there is still a character in the line
        while ($this->hasNext()) {

            //get the next character in the line
            $currentChar = $this->getNextChar();

            switch ($currentChar) {

                case " " :
                    //set the expression to add back to empty again
                    $exprToAdd = "";

                    //empty the stack contents into expression to add
                    while (!$this->stack->isEmpty()) {
                        $exprToAdd = $this->stack->pop() . $exprToAdd;
                    }

                    //if there is no variable identifier in the variable declaration expression
                    //then the expression to add must be that identifier
                    if ($result->getIdentifier() == null)
                        $result->setIdentifier(new RawValueExpression($exprToAdd));

                    //if it is the equals symbol then ignore it
                    elseif ($exprToAdd == "=") {
                    }

                    //if it has been determined that the identifier is not empty
                    //and that the expression to add is not the equals sign
                    //then it must be the value of the variable
                    elseif ($result->getValue() == null) {
                        $result->setValue(new RawValueExpression($exprToAdd));

                        //the set is complete - break from the parsing
                        break;
                    } //if there are parsing issues, throw an exception
                    else throw new ExpressionParseException("Error when declaring variable");

                    break;

                //if its a tag store the result as a tag
                case "#" :
                    //make the expression to add the parsed reference tag
                    //if there are problems parsing, then throw that exception.
                    $exprToAdd = $this->parseTag();

                    //if the first expression is null then that means that the tag should take the place
                    //of the first expression
                    if ($result->getIdentifier() == null) {
                        throw new ExpressionParseException("Cannot declare variable as tag");

                        //if the operator is null but the first expression is full then that means that the tag is
                        //in the place of the operator of the expression, which is illegal.
                    } else if ($result->getValue() == null) {
                        $result->setValue($exprToAdd);

                        //any other problems parsing then throw an exception
                    } else {
                        throw new ExpressionParseException("Error parsing variable declaration expression.");
                    }

                    break;

                //if it isn't a space character or a tag, push it onto the stack
                default :
                    $this->stack->push($currentChar);
            }
        }

        //if there is an identifier but no value then set the value as the leftover string
        if ($result->getIdentifier() != null && $result->getValue() == null) {

            //if the loop is done and there are still things left to parse
            //then either empty the stack or empty it to the first open bracket
            //so that it can be added to the expression
            $exprToAdd = "";
            while (!$this->stack->isEmpty() && $this->stack->top() != "(") {
                $exprToAdd = $this->stack->pop() . $exprToAdd;
            }

            $result->setValue(new RawValueExpression($exprToAdd));

            //if all values are filled then do nothing
        } else if ($result->getIdentifier() != null && $result->getValue() != null) {

            //any other exceptions, throw this exception
        } else {
            throw new ExpressionParseException("Invalid variable declaration.");
        }


        //return the resulting structure
        return $result;
    }

    function parseTag()
    {

        /**
         * The tag expression to be returned.
         */
        $result = new TagExpression();

        /**
         * Determines whether in the parsing a close bracket has been found or not.
         */
        $closeBraceFound = false;

        /**
         * An array of the tags that form the datablock
         */
        $tags = array();

        /**
         * The name of the tag being parsed - everything upto the close brace.
         */
        $tagName = "";

        //if the first character is not an open brace then throw a parse exception
        if ($this->getNextChar() != "(") {
            throw new ExpressionParseException("Hashtag not followed by open bracket character.");
        }

        //get the tag name - the string upto the last brace - but break if it reaches the end of the line.
        while (!$closeBraceFound && $this->hasNext()) {
            $currentChar = $this->getNextChar();
            switch ($currentChar) {
                case "," :
                    array_push($tags, trim($tagName));
                    $tagName = "";
                    break;

                case ")" :
                    array_push($tags, trim($tagName));
                    $closeBraceFound = true;
                    break;

                default :
                    $tagName .= $currentChar;
            }
        }

        //if the loop broke unexpectedly then throw a parse exception.
        if (!$closeBraceFound) {
            throw new ExpressionParseException("No close brace found ");
        }

        //set the tag name in the result structure to the tag name parsed
        $result->setTags($tags);

        return $result;
    }

    /**
     * Parses the parameters statement for the function to use.
     * @return ParamsExpression
     * @throws ExpressionParseException
     */
    function parseParams(){

        /**
         * An array of the parameters to be used in the function.
         */
        $params = array();

        /**
         * The current character that the parser index is pointing to in the instruction.
         */
        $currentChar = "";

        /**
         * Determines whether the parser has found a close brace yet or not.
         * @var bool
         */
        $closeBraceFound = false;

        /**
         * The name of the current paramter being read in by the parser.
         */
        $paramName = "";

        if($this->getNextChar() != "(")
            throw new ExpressionParseException("No open brace found for parameter expression");

        while(!$closeBraceFound && $this->hasNext()) {
            $currentChar = $this->getNextChar();
            switch($currentChar) {
                case "," :
                    array_push($params, trim($paramName));
                    $paramName = "";
                    break;
                case ")" :
                    array_push($params, trim($paramName));
                    $closeBraceFound = true;
                    break;
                default :
                    $paramName .= $currentChar;
            }
        }

        if(!$closeBraceFound)
            throw new ExpressionParseException("Failed to find close brace on paramaters declaration.");

        return new ParamsExpression($params);
    }

    /**
     * Determines if the previous token in the string is a closed bracket/brace or not to see if a space should be ignored
     * when parsing if statements with bracketed statements.
     * @return bool
     */
    function previousTokenIsCloseBracket()
    {

        /**
         * The list of characters that represent closing statements, which gets the parser to skip
         * to the next statement.
         */
        $closingChars = array(")", "}");

        /**
         * The previous token in the parser.
         */
        $previousTokenChar = $this->exprArray[$this->parseIndex - 2];

        /**
         * Returns true if the previous token is any of the closing bracket style characters.
         */
        return in_array($previousTokenChar, $closingChars);
    }

    /**
     * @return string
     * Gets the next character in the expression.
     */
    function getNextChar()
    {
        $charToReturn = $this->exprArray[$this->parseIndex];
        $this->parseIndex++;
        return $charToReturn;
    }

    /**
     * @return bool
     * Checks if there is still more characters in the expression to be parsed.
     */
    function hasNext()
    {
        return $this->parseIndex < (count($this->exprArray));
    }

    function getExpressionTree() {
        /**
         * The array of expressions in order of postfix to be evaluated.
         */
        $resultArray = $this->shunt();


    }

    /**
     * Determines the tree of boolean operations from a line of operations.
     */
    function shunt() {

        /**
         * The stack that will be used to hold the different values during the process of the algorithm.
         */
        $workingStack = [];

        /**
         * The resulting stack that contains the expressions in correct order.
         */
        $output = [];

        /**
         * The token object that is being checked in the shunting process.
         */
        $token = null;

        /**
         * Determines if a close bracket term has been found.
         */
        $closeBracketFound = false;

        /**
         * Determines if the inner loop which determines the order of operators in the output
         * needs to end or not depending on the precedences of the operators.
         */
        $innerLoopEnd = false;

        /**
         * While there are still tokens in the stream...
         */
        while($this->hasNext() && !$closeBracketFound) {

            /**
             * Get the next token.
             */
            $token = $this->getNextToken();

            /**
             * If the token is a close bracket then exit the loop
             */
            if ($token == ")")
                $closeBracketFound = true;

            /**
             * If it is a raw value of some description then push it onto the stack to be returned.
             */
            else if (($token instanceof RawValueExpression) || ($token instanceof TagExpression))
                array_push($output, $token);

            /**
             * If it is an operator then check the order of the stack.
             */
            else if ($token instanceof OperationExpression) {

                /**
                 * While the working stack is not empty...
                 */
                while(!empty($workingStack) && !$innerLoopEnd) {

                    /**
                     * Get the operator held at the top of the working stack.
                     * @var OperationExpression
                     */
                    $top = array_pop($workingStack);

                    if($this->precedence($top->getOperation()) >= $this->precedence($token->getOperation()))
                        array_push($output, $top);
                    else {
                        array_push($workingStack, $top);
                        $innerLoopEnd = true;
                    }
                }

                array_push($workingStack, $token);
                $innerLoopEnd = false;
            }

            else if ($token == "(")
                $output = array_merge($output, $this->shunt());
        }

        /**
         * while the working stack is still not empty, push all remaining operators onto the end of the result.
         */
        while(!empty($workingStack))
            array_push($output, array_pop($workingStack));

        return $output;
}

    /**
     * Determines the precedence of the operation passed to the function.
     * @param $op
     * The operation to determine the precedence for.
     * @return int
     * The precedence of the given operation, where a larger number constitutes a higher precedence.
     * @throws ExpressionParseException
     * If it is an unrecognised operation then this expression is thrown.
     */
    function precedence($op) {

        /**
         * Switch case depending on the operation.
         */
        switch ($op) {
            /**
             * Multiply
             */
            case '*' :
            /**
             * Divide
             */
            case '/' :
            /**
             * Modulo
             */
            case '%' :
                return 7;

            /**
             * Addition
             */
            case '+' :
            /**
             * Subtraction
             */
            case '-' :
                return 6;

            /**
             * Boolean Equals
             */
            case '=' :
            /**
             * Less than
             */
            case '<' :
            /**
             * Greater than
             */
            case '>' :
            /**
             * Greater than or equal to
             */
            case '>=' :
            /**
             * Less than or equal to
             */
            case '<=' :
                return 4;

            /**
             * Boolean And
             */
            case '&' :
                return 3;
            /**
             * Boolean Or
             */
            case '|' :
                return 2;

            /**
             * If the operation doesn't fit any of the above then throw an exception.
             */
            default:
                throw new ExpressionParseException("Invalid Operation : " . $op);
        }

    }

    /**
     * Gets the next whole token for the shunting process.
     * @return OperationExpression|RawValueExpression|TagExpression|null|string
     */
    function getNextToken() {
        /**
         * The next expression token to be returned.
         */
        $exprToReturn = null;

        /**
         * The next character in the stream.
         */
        $currentChar = $this->getNextChar();

        /**
         * Ignores any whitespace left at the beginning of the line
         */
        while($currentChar == " " && $this->hasNext())
            $currentChar = $this->getNextChar();

        switch(true) {
            case $currentChar == "#" :
                $exprToReturn = $this->parseTag();
                break;
            case $currentChar == "\"" :
                $exprToReturn = new RawValueExpression("\"" . $this->getRestOfString());
                break;
            case in_array($currentChar, $this->boolExprs) || in_array($currentChar, $this->arithmeticExprs) :
                /**
                 * Check to see if the next character in the string is a part of the operation.
                 */
                $extraChar = $this->getNextChar();

                /**
                 * If the operation involves multiple characters then
                 * append the extra character to the operator string representation.
                 */
                if($extraChar == "=")
                    $currentChar .= "=";
                else
                    $this->moveBackPointer();

                $exprToReturn = new OperationExpression($currentChar);
                break;
            case $currentChar == "(":
                $exprToReturn = "(";
                break;
            case $currentChar == ")" :
                $exprToReturn = ")";
                break;
            default :
                $exprToReturn = new RawValueExpression($currentChar . $this->getRestOfRawValue());
                break;
        }

        return $exprToReturn;
    }

    /**
     * Moves back the parse pointer.
     */
    function moveBackPointer() {
        $this->parseIndex--;
    }

    /**
     * Gets the remainder of a raw value.
     */
    function getRestOfRawValue(){
        /**
         * The current character in the parser stream.
         * @var string
         */
        $currentChar = "";

        /**
         * The result string to be returned.
         * @var string
         */
        $result = "";

        /**
         * Determines whether a non alphanumeric character has been found yet.
         */
        $nonAlphaNumericCharacterFound = false;

        /**
         * Add characters to the result string upto and including the next occurrence of a non alphanumeric character.
         */
        while($this->hasNext() && !$nonAlphaNumericCharacterFound) {
            $currentChar = $this->getNextChar();
            $nonAlphaNumericCharacterFound = !ctype_alnum($currentChar);
            if($nonAlphaNumericCharacterFound)
                $this->moveBackPointer();
            else
                $result .= $currentChar;
        }

        return $result;
    }

    /**
     * Gets rest of string when getting a string value as a token in the parser.
     */
    function getRestOfString() {
        /**
         * The current character in the parser stream.
         * @var string
         */
        $currentChar = "";

        /**
         * The result string to be returned.
         * @var string
         */
        $result = "";

        /**
         * Add characters to the result string upto and including the next occurrence of quotation marks.
         */
        while($this->hasNext() && $currentChar != "\"") {
            $currentChar = $this->getNextChar();
            $result .= $currentChar;
        }

        /**
         * If the parser made it to the end of the line without completing the string parse then throw a parse exception.
         */
        if(!$this->hasNext() && mb_substr($result, -1) != "\"")
            throw new ExpressionParseException("String expression did not contain terminating speech marks.");

        return $result;
    }

    /**
     * Initialises the parser with the given expression by setting the expression properties
     * and restarting the parse index
     * @param $exprToSetUp
     */
    function setUpParser($exprToSetUp) {
        //set the objects expression property to the expression passed to the
        $this->expr = $exprToSetUp;

        //Set the objects expression array to the exploded expression string.
        $this->exprArray = str_split($this->expr);

        //Set the parse index of the object back to the start.
        $this->parseIndex = 0;
    }

    /**
     * A function wrapper that tests solely the shunt function without needing to do an entire parsing process.
     * @param $expression
     * @return array
     */
    function testShunt($expression) {
        $this->setUpParser($expression);
        $result = $this->shunt();
        return $result;
    }
}

