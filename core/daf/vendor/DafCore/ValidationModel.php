<?php
namespace DafCore;

trait ValidationModel
{
    function GetValidationErrorsArray() : array {
        return Validator::$errorMsgs;
    } 
    function GetValidationErrorsString(string $end_of_line = "\n") : string {
        return implode("\n", Validator::$errorMsgs);
    } 
    function Validate() : bool {
        return Validator::Validate($this);
    }
}