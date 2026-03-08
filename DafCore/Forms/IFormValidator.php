<?php
namespace DafCore\Forms;

interface IFormValidator
{
    /** Validates the model and stores errors into IFormFeedback (flash-backed). */
    public function Validate(object $model): bool;

    /** Same as Validate(), but also clears previous form feedback first (recommended per POST). */
    public function ValidateFresh(object $model): bool;

    /** Adds one global error (nice for login errors etc.). */
    public function Error(string $msg): void;

    /** Adds a field error. */
    public function Field(string $field, string $msg): void;

    /** Shortcut: returns true when there are any errors currently stored. */
    public function HasErrors(): bool;

    /** Clears current stored form errors. */
    public function Clear(): void;
}
