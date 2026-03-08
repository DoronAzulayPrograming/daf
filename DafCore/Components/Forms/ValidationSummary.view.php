<?php
/**
 * @daf-summary Renders a validation summary from form context errors and supports client-side summary updates.
 */
/** @var DafCore\IComponent $this  */
$ctx = $this->Inject(\DafCore\Forms\FormContext::class);
$cur = $ctx->Current();
$clientSideValidation = $cur['clientSideValidation'];

$errors = $cur['errors']['__global__'] ?? [];
// also include all field errors if you want:
foreach (($cur['errors'] ?? []) as $k => $arr) {
    if ($k === '__global__') continue;
    foreach ($arr as $m) $errors[] = $m;
}

if ($this->GetAttribute("class") === null) $this->SetAttributes(['class'=>'alert alert-danger']);
$errosLen = count($errors);

if ($errosLen > 0 && $clientSideValidation) {
    ?>
    <div data-daf-validation-summary <?=$this->RenderAttributes()?>>
        <ul class="mb-0">
            <?php foreach ($errors as $e) { ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php } ?>
        </ul>
    </div>
    <?php
}else if($errosLen > 0){
    ?>
    <div <?=$this->RenderAttributes()?>>
        <ul class="mb-0">
            <?php foreach ($errors as $e) { ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php } ?>
        </ul>
    </div>
    <?php
} else if($clientSideValidation ){
    $this->AddAttributesToEnd(['class' => 'd-none'])
    ?>
    <div data-daf-validation-summary <?=$this->RenderAttributes()?>>
        <ul class="mb-0">
            
        </ul>
    </div>
    <?php
}
