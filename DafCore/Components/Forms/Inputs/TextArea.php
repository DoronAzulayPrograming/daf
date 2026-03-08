<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a `<textarea>` bound to a model field with validation feedback support.
 */
class TextArea extends InputBase
{
    // Explicit textarea value; falls back to bound model value.
    public mixed $Value = null;

    public function OnLoad(): void
    {
        parent::OnLoad();

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
