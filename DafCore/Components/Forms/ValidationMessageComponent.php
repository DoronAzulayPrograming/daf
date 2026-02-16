<?php
namespace DafCore\Components\Forms;

use DafCore\Component;
use DafCore\Forms\FormContext;

/**
 * @daf-summary Renders validation feedback for a specific field, with optional all-messages list output.
 *
 * @daf-param For string required - field path (for example <code>Email</code> or <code>Address.City</code>)
 * @daf-param Tag string optional default=div - HTML tag used to wrap the message output
 * @daf-param All bool optional default=false - when true, renders all field messages instead of first only
 */
final class ValidationMessageComponent extends Component
{
    public bool $All;
    public string $Tag;
    public string $For;

    public function OnLoad(): void {
        $this->All = $this->Parameter("All") ?? false;
        $this->Tag = $this->Parameter("Tag") ?? "div";
        $this->For = $this->RequiredParameter("For");
    }

    public function Render(): string
    {
        /** @var FormContext $ctx */
        $ctx = $this->Inject(FormContext::class);
        $cur = $ctx->Current();
        $clientSideValidation = $cur['clientSideValidation'];

        if (!$cur) return "";

        $errors = $cur['errors'][$this->For] ?? [];

        $hasInvalidClass = true;
        if ($this->GetAttribute("class") === null) {
            $hasInvalidClass = false;
            $this->SetAttributes(['class'=>'invalid-feedback']);
        }

        $clientValidationStr = "";
        if ($clientSideValidation) {
            $clientValidationStr = "data-daf-validation-message=\"{$this->For}\"";
            $ulContainer = $this->All ? "<ul class='mb-0'></ul>" : "";
            if(!$errors) return "<{$this->Tag} $clientValidationStr {$this->RenderAttributes()}>$ulContainer</{$this->Tag}>";
            if (!$hasInvalidClass) $this->SetAttributes(['class'=>'invalid-feedback d-block']);
        }

        if (!$errors) return "";

        $msgs = $this->All ? $errors : [ $errors[0] ];

        $out = "<{$this->Tag} $clientValidationStr {$this->RenderAttributes()}>";
        if (count($msgs) === 1) {
            $out .= htmlspecialchars((string)$msgs[0], ENT_QUOTES, 'UTF-8');
        } else {
            $out .= "<ul class=\"mb-0\">";
            foreach ($msgs as $m) {
                $out .= "<li>" . htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') . "</li>";
            }
            $out .= "</ul>";
        }
        $out .= "</{$this->Tag}>";

        return $out;
    }
}
