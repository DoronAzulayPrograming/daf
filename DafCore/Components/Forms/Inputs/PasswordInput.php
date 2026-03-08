<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a password `<input type='password'>` and never re-populates its value.
 */
class PasswordInput extends Input
{
    public string $Type = 'password';
    public function OnLoad(): void
    {
        parent::OnLoad();
        $this->Value = null; // never re-populate
    }
}
