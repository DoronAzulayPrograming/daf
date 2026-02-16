<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a text input bound to a model field with validation feedback support.
 *
 * @daf-param For string required - model field path used for binding and validation
 * @daf-param Value mixed optional - explicit input value; falls back to bound model value
 * @daf-param Feedback bool optional default=true - renders invalid feedback container/message
 * @daf-param InvalidClass bool optional default=true - appends <code>is-invalid</code> class when field has errors
 */
class TextInputComponent extends InputBaseComponent
{
    public string $Invalid_feedback;
    public mixed $Value;

    public function OnLoad(): void
    {
        parent::OnLoad();

        $this->Value = $this->Parameter("Value") ?? null;
        
        // Bind value from model unless Value explicitly passed
        if ($this->Value === null) {
            $v = $this->getModelValue();
            if ($v !== null) $this->Value = $v;
        }
    }
   
    public function Render(): string
    {
        $htmlName = htmlspecialchars($this->htmlName, ENT_QUOTES, 'UTF-8');

        $stringVal = $this->valueToString($this->Value);

        $valueAttr = "";
        if($stringVal !== null){
            $valueAttr = "value='" . htmlspecialchars($stringVal, ENT_QUOTES, 'UTF-8') . "'";
        }
        
        return "<input type='text' name='$htmlName' $valueAttr {$this->renderClientValidationAttrs()} {$this->RenderAttributes()} />
        {$this->renderInvalidFeedback()}";
    }

}
