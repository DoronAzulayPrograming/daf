<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a number `<input type='number'>` with model binding and validation feedback support.
 */
class NumberInput extends Input
{
    public function OnLoad(): void
    {
        $this->_->Parameters['Type'] = 'number';
        parent::OnLoad();
    }
}
