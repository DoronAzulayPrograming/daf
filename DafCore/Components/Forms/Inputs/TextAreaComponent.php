<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a <code><<span>textarea</span>></code> bound to a model field with validation feedback support.
 *
 * @daf-param For string required - model field path used for binding and validation
 * @daf-param Value mixed optional - explicit textarea value; falls back to bound model value
 * @daf-param Feedback bool optional default=true - renders invalid feedback container/message
 * @daf-param InvalidClass bool optional default=true - appends <code>is-invalid</code> class when field has errors
 */
class TextAreaComponent extends InputBaseComponent
{
    public mixed $Value = null;

    public function OnLoad(): void
    {
        parent::OnLoad();
        $this->Value = $this->Parameter("Value");

        if ($this->Value === null) {
            $v = $this->getModelValue();
            if ($v !== null) $this->Value = $v;
        }
    }

    public function Render(): string
    {
        $name = htmlspecialchars($this->htmlName, ENT_QUOTES, 'UTF-8');
        $text = $this->valueToString($this->Value);
        $safe = $text === null ? "" : htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return "<textarea name='{$name}' {$this->renderClientValidationAttrs()} {$this->RenderAttributes()}>{$safe}</textarea>"
            . $this->renderInvalidFeedback();
    }
}
