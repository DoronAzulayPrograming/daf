<?php
namespace DafCore\Forms;

final class FormValidator implements IFormValidator
{
    public function __construct(private IFormFeedback $feedback) {}

    public function Validate(object $model): bool
    {
        if (!\DafCore\Validator::Validate($model)) {
            $this->feedback->AddMany(\DafCore\Validator::GetErrors());
            return false;
        }
        return true;
    }

    public function ValidateFresh(object $model): bool
    {
        $this->feedback->Clear();
        return $this->Validate($model);
    }

    public function Error(string $msg): void
    {
        $this->feedback->AddGlobal($msg);
    }

    public function Field(string $field, string $msg): void
    {
        $this->feedback->AddField($field, $msg);
    }

    public function HasErrors(): bool
    {
        return $this->feedback->HasErrors();
    }

    public function Clear(): void
    {
        $this->feedback->Clear();
    }
}
