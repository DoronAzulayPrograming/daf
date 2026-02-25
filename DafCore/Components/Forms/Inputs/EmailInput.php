<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders an email `<input type='email'>` with model binding and validation feedback support.
 */
class EmailInput extends Input
{
    public function OnLoad(): void
    {
        $this->_->Parameters['Type'] = 'email';
        parent::OnLoad();
    }
}
