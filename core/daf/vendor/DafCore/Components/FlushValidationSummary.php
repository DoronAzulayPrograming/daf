<?php 
/** @var DafCore\IComponent $this */

/** @var string $flush_key */
if($this->GetAttribute("class") === null){
    $this->SetAttributes(['class' => 'alert alert-danger']);
}

/** @var DafCore\Session $session */
$session = $this->Inject(DafCore\Session::class);
if($session->TryGetFlushErrors( $flush_errors)){ ?>
    <div <?=$this->RenderAttributes()?>>
        <ul class="m-0">
            <?php foreach ($flush_errors as $flush_error) { ?>
                <li><?= $flush_error ?></li>
            <?php } ?>
        </ul>
    </div>
<?php } ?>