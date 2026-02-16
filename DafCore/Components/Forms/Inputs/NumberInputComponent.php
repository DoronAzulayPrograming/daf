<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a number input (<code>type=number</code>) with model binding and validation feedback support.
 *
 * @daf-param For string required - model field path used for binding and validation
 * @daf-param Value mixed optional - explicit input value; falls back to bound model value
 * @daf-param Feedback bool optional default=true - renders invalid feedback container/message
 * @daf-param InvalidClass bool optional default=true - appends <code>is-invalid</code> class when field has errors
 */
class NumberInputComponent extends InputComponent
{
    public function OnLoad(): void
    {
        $this->_->Parameters['Type'] = 'number';
        parent::OnLoad();
    }
}
