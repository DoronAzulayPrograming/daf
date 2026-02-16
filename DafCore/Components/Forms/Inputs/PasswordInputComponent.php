<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a password input (<code>type=password</code>) and never re-populates its value.
 *
 * @daf-param For string required - model field path used for binding and validation
 * @daf-param Feedback bool optional default=true - renders invalid feedback container/message
 * @daf-param InvalidClass bool optional default=true - appends <code>is-invalid</code> class when field has errors
 */
class PasswordInputComponent extends InputComponent
{
    public function OnLoad(): void
    {
        $this->_->Parameters['Type'] = 'password';
        parent::OnLoad();
        $this->Value = null; // never re-populate
    }
}
