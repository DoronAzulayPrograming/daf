<?php
namespace DafCore\Components\Forms\Inputs;

/**
 * @daf-summary Renders a checkbox `<input type='checkbox'>` with model binding and validation feedback support.
 */
class Checkbox extends InputBase
{
    // Submitted value when checkbox is checked.
    public string $Value = "1"; 
    
    // Explicit checked state; otherwise inferred from bound model value.
    public ?bool $Checked = null;

    public function OnLoad(): void
    {
        parent::OnLoad();

        if ($this->Checked === null)  {
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
