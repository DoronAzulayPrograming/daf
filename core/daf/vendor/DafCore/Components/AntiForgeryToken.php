<?php
/** @var DafCore\Component $this */
/** @var DafCore\AntiForgery $antiForgery */

$antiForgery = $this->Inject(DafCore\AntiForgery::class);
$token = $antiForgery->GetToken();
if(!empty($token)){ ?>
    <input type="hidden" name="csrft" value="<?=$token?>">
<?php }?>