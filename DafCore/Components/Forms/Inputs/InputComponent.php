<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a generic <code><<span>input</span>></code> element with model binding and validation support.
 *
 * @daf-param For string required - model field path used for binding and validation
 * @daf-param Type string optional default=text - HTML input type (text, email, number, password, file, etc.)
 * @daf-param Value mixed optional - explicit value override; falls back to bound model value
 * @daf-param Feedback bool optional default=true - renders invalid feedback container/message
 * @daf-param InvalidClass bool optional default=true - appends <code>is-invalid</code> class when field has errors
 */
class InputComponent extends InputBaseComponent
{
    public string $Type = 'text';
    public mixed $Value = null;

    public function OnLoad(): void
    {
        parent::OnLoad();

        $this->Type  = (string)($this->Parameter("Type") ?? "text");
        $this->Value = $this->Parameter("Value");

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
