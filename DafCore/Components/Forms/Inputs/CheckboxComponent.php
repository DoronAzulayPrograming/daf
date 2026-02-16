<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a checkbox input with model binding and validation feedback support.
 *
 * @daf-param For string required - model field path used for binding and validation
 * @daf-param Value string optional default=1 - submitted value when checkbox is checked
 * @daf-param Checked bool optional - explicit checked state; otherwise inferred from bound model value
 * @daf-param Feedback bool optional default=true - renders invalid feedback container/message
 * @daf-param InvalidClass bool optional default=true - appends <code>is-invalid</code> class when field has errors
 */
class CheckboxComponent extends InputBaseComponent
{
    public string $Value;  
    public bool $Checked;

    public function OnLoad(): void
    {
        parent::OnLoad();

        $this->Value = $this->Parameter("Value") ?? "1";

        $checked = $this->Parameter("Checked");
        if ($checked !== null) {
            $this->Checked = (bool)$checked;
        } else {
            $v = $this->getModelValue();
            $this->Checked = (is_bool($v) ? $v : ((string)$v === (string)$this->Value));
        }
    }

    public function Render(): string
    {
        $name = htmlspecialchars($this->htmlName, ENT_QUOTES, 'UTF-8');
        $val  = htmlspecialchars((string)$this->Value, ENT_QUOTES, 'UTF-8');

        $checkedAttr = $this->Checked ? "checked" : "";

        return "<input type='checkbox' name='{$name}' value='{$val}' {$checkedAttr} {$this->renderClientValidationAttrs()} {$this->RenderAttributes()} />"
            . $this->renderInvalidFeedback();
    }
}
