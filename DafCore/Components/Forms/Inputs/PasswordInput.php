<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a password `<input type='password'>` and never re-populates its value.
 */
class PasswordInput extends Input
{
    public function OnLoad(): void
    {
        $this->_->Parameters['Type'] = 'password';
        parent::OnLoad();
        $this->Value = null; // never re-populate
    }
}
