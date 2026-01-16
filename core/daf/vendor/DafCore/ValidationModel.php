<?php
namespace DafCore;

trait ValidationModel
{
    function GetValidationErrorsArray() : array {
        return Validator::$errorMsgs;
    } 
    function GetValidationErrorsMsgsArray() : array {
        return array_column(Validator::$errorMsgs, 'msg');
    } 
    function GetValidationErrorsString(string $end_of_line = PHP_EOL) : string {
        $messages = array_column(Validator::$errorMsgs, 'msg');
        return implode($end_of_line, $messages) . PHP_EOL;
    } 
    function Validate($deep = false) : bool {
        return Validator::Validate($this, $deep);
    }
}