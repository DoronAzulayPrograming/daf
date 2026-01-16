<?php 
/** @var DafCore\IComponent $this */
$errors = $this->Parameter('Errors') ?? [];

if($this->GetAttribute("class") === null){
    $this->SetAttributes(['class' => 'alert alert-danger']);
}

/** @var DafCore\Session $session */
$session = $this->Inject(DafCore\Session::class);
if($session->TryGetFlushValueFromJson("Errors", $flush_errors, true)){ ?>
    <div <?=$this->RenderAttributes()?>>
        <ul>
            <?php foreach ($flush_errors as $e) { ?>
                <li><?= $e['msg'] ?></li>
            <?php } ?>
            <?php foreach ($errors as $e) { ?>
                <li><?= $e['msg'] ?></li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>