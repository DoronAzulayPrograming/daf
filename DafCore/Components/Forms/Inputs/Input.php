<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a generic `<input>` element with model binding and validation support.
 */
class Input extends InputBase
{
    // HTML input type (text, email, number, password, file, etc.).
    public string $Type = 'text';

    // Explicit value override; falls back to bound model value.
    public mixed $Value = null;

    public function OnLoad(): void
    {
        parent::OnLoad();

        // Don’t set value attribute for file inputs (browser blocks it)
        if ($this->Type !== 'file') {
            if ($this->Value === null) {
                $v = $this->getModelValue();
                if ($v !== null) $this->Value = $v;
            }
        }
    }

    public function Render(): string
    {
        $type = htmlspecialchars($this->Type, ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($this->htmlName, ENT_QUOTES, 'UTF-8');

        $valueAttr = "";
        if ($this->Type !== 'file') {
            $stringVal = $this->valueToString($this->Value);

            // date/datetime-local want specific formats (optional but recommended)
            if ($stringVal !== null) {
                $valueAttr = "value='" . htmlspecialchars($stringVal, ENT_QUOTES, 'UTF-8') . "'";
            }
        }

        return "<input type='{$type}' name='{$name}' {$valueAttr} {$this->renderClientValidationAttrs()} {$this->RenderAttributes()} />"
            . $this->renderInvalidFeedback();
    }
}
