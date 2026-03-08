<?php
namespace DafCore\Components\Forms\Inputs;

use DafCore\Component;
use DafCore\Forms\FormContext;

/**
 * @daf-summary Base class for form inputs: resolves model-bound field paths, validation metadata, and invalid feedback rendering.
 */
abstract class InputBase extends Component
{
    // Model field path used for binding and validation (dot notation).
    public string $For;

    // Renders invalid feedback container/message
    public bool $Feedback = true;

    // Appends `is-invalid` class when field has errors
    public bool $InvalidClass = true;
    
    protected string $htmlName;
    protected FormContext $formContext;

    public function OnLoad(): void {

        $this->formContext = $this->Inject(FormContext::class);
        $this->htmlName = $this->buildHtmlName();
        
        $this->applyBootstrapInvalidIfNeeded();
    }


    protected function valueToString(mixed $val): ?string
    {
        if ($val === null || $val === "") return null;
        if (is_scalar($val)) return (string)$val;
        if (is_object($val) && method_exists($val, '__toString')) return (string)$val;
        return null;
    }

    protected function getModelValue(): mixed
    {
        $cur = $this->model();
        if (!$cur) return null;

        $parts = $this->pathParts();
        if (!$parts) return null;

        $last = count($parts) - 1;

        foreach ($parts as $i => $name) {
            if (!is_object($cur)) return null;

            $getter = 'get' . ucfirst($name);

            try {
                if (method_exists($cur, $getter)) {
                    $val = $cur->$getter();
                } elseif (property_exists($cur, $name)) {
                    $val = $cur->$name;
                } else {
                    return null;
                }
            } catch (\Throwable) {
                return null;
            }

            if ($i === $last) return $val;
            $cur = $val;
        }

        return null;
    }

    protected function invalidClassEnabled(): bool { return $this->InvalidClass; }

    protected function feedbackEnabled(): bool { return $this->Feedback; }

    protected function clientSideValidationEnabled(): bool
    {
        $cur = $this->currentForm();
        $clientSideValidation = $cur['clientSideValidation'];
        return $clientSideValidation;
    }


    protected function applyBootstrapInvalidIfNeeded(): void
    {
        if (!$this->invalidClassEnabled()) return;
        if (!$this->firstError()) return;

        $cls = $this->GetAttribute("class") ?? "";
        if (!str_contains($cls, "is-invalid")) {
            $this->SetAttributes(['class' => trim($cls . " is-invalid")]);
        }
    }

    protected function renderInvalidFeedback(): string
    {
        if (!$this->feedbackEnabled()) return "";

        $err = $this->firstError();
        $clientValidationStr = "";

        if ($this->clientSideValidationEnabled()) {
            $clientValidationStr = "data-daf-validation-message=\"{$this->htmlName}\"";
            if(!$err) return "<div $clientValidationStr class=\"invalid-feedback\"></div>";
        }

        if(!$err) return "";

        return "<div $clientValidationStr class=\"invalid-feedback\">" . htmlspecialchars($err) . "</div>";
    }

    protected function renderClientValidationAttrs(): string
    {
        $info = $this->fieldRules();
        if (!$info) return "";

        $json = htmlspecialchars(json_encode($info), ENT_QUOTES, 'UTF-8');

        $invalidClassEnabled = $this->invalidClassEnabled() ? 'data-daf-invalid' : '';

        return " $invalidClassEnabled data-daf-validation-roles='$json' ";
    }


    private function currentForm(): ?array { return $this->formContext->Current(); }
    private function model(): ?object
    {
        $cur = $this->currentForm();
        return $cur['model'] ?? null;
    }
    private function buildHtmlName(): string
    {
        $parts = $this->pathParts();
        $name = array_shift($parts);

        foreach ($parts as $p) {
            $name .= "[" . $p . "]";
        }
        return $name;
    }
    private function pathParts(): array
    {
        return array_values(array_filter(explode('.', $this->For), fn($x) => $x !== ""));
    }
    private function fieldRules(): ?array
    {
        $rules = $this->currentFormRules();
        $path = $this->For;
        return $rules[$path] ?? null;
    }
    private function currentFormRules(){
        $cur = $this->currentForm();
        if (!$cur) return [];

        return $cur['rules'];
    }
    private function firstError(): ?string
    {
        $errs = $this->errorMessages();
        return $errs[0] ?? null;
    }
    private function errorMessages(): array
    {
        $cur = $this->currentForm();
        if (!$cur) return [];

        $errors = $cur['errors'] ?? [];
        $rel = $this->For;

        return $errors[$rel] ?? [];
    }
}
