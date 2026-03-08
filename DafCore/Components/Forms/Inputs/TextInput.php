<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a text `<input>` bound to a model field with validation feedback support.
 */
class TextInput extends InputBase
{
    // Explicit input value; falls back to bound model value.
    public mixed $Value = null;

    public function OnLoad(): void
    {
        parent::OnLoad();

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
