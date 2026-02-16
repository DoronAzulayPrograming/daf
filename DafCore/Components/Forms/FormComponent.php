<?php
namespace DafCore\Components\Forms;

use DafCore\Component;
use DafCore\AutoConstruct;
use DafCore\Forms\FormContext;
use DafCore\Forms\IFormFeedback;

/**
 * @daf-summary Renders a form bound to a model and wires server/client validation context for nested inputs.
 *
 * @daf-param Model mixed required - model instance used for binding values and validation metadata
 * @daf-param ClientSideValidation bool optional default=false - enables client-side validation and JS submit interception
 */
class FormComponent extends Component
{
    public mixed $Model;
    public mixed $ClientSideValidation;
    private string|null $id = null;

    private FormContext $formContext;
    private IFormFeedback $feedback;

    public function OnLoad(): void {
        $this->Model  = $this->RequiredParameter("Model");
        $this->ClientSideValidation = $this->Parameter("ClientSideValidation") ?? false;

        if($this->ClientSideValidation){
            $this->id = $this->GetAttribute("id");
            if(!$this->id) throw new \Exception("id attribute is required for client side validation.");
        }

        $this->feedback = $this->Inject(IFormFeedback::class);
        $this->formContext = $this->Inject(FormContext::class);
    }

    function buildErrors(): array {
        return $this->feedback->ErrorsByField();
    }

    function buildRules(object $model, string $prefix = ""): array {
        $meta = AutoConstruct::metaFor($model);
        $props = $meta['props'] ?? [];
        $out = [];
        foreach ($props as $prop => $m) {
            $path = $prefix ? $prefix . "." . $prop : $prop;

            $validators = $m['validators'] ?? [];
            if (!empty($validators)) {
                $out[$path] = [
                    'display' => $m['display'] ?? $prop,
                    'rules' => $validators,
                ];
            }

            $type = $m['type'] ?? null;
            if ($type && is_subclass_of($type, AutoConstruct::class)) {
                try {
                    $child = $m['p']->isInitialized($model) ? $m['p']->getValue($model) : new $type();
                    if ($child) {
                        $out += $this->buildRules($child, $path);
                    }
                } catch (\Throwable) {}
            }
        }

        return $out;
    }

    public function Render(): string
    {
        $errorsByField = $this->buildErrors();
        $rulesByField = $this->ClientSideValidation ? $this->buildRules($this->Model) : [];

        $this->formContext->Push($this->Model, $errorsByField, $rulesByField, $this->ClientSideValidation);

        $dafIgnore = $this->ClientSideValidation ? "data-daf-ignore" : "";

        $script = "";
        if($this->ClientSideValidation){
            $script = "<Script Key=\"DafFormComponent\">
                function validateOnSubmit(e){
                    e.preventDefault();
                    const res = window.DafForms.validateForm(e.target);
                    if (res.ok) {
                        window.Daf.submitForm(e.target);
                    }
                }
                function initValidateOnSubmit(id){
                    const form = document.getElementById(id)
                    form.removeEventListener('submit', validateOnSubmit);
                    form.addEventListener('submit', validateOnSubmit);
                }
            </Script>
            <Script>
                initValidateOnSubmit('{$this->id}');
            </Script>";
        }

        try {
            return "<form $dafIgnore {$this->RenderAttributes()}> {$this->RenderChildContent()} </form>$script";
        } finally {
            $this->formContext->Pop();
        }
    }
}
