<?php

/**
 * Created by PhpStorm.
 * User: Owner
 * Date: 12/07/2016
 * Time: 09:16
 */
class RawValueExpression implements iExpression
{
    /**
     * @var string
     * The raw value stored in this expression.
     */
    private $value;

    function __construct($value) {
        $this->$value = value;
    }

    public function __toString()
    {
       return $this->value;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Determines the type of the raw value and returns it.
     */
    public function evaluate()
    {

    }

}