<?php
/**
 * @daf-summary Emits a hidden CSRF token input when a token exists in the anti-forgery service.
 */
/** @var DafCore\IComponent $this */
/** @var DafCore\AntiForgery $antiForgery */

$antiForgery = $this->Inject(DafCore\AntiForgery::class);
$token = $antiForgery->GetToken();
if(!empty($token)){ ?>
    <input type="hidden" name="csrft" value="<?=$token?>">
<?php }?>