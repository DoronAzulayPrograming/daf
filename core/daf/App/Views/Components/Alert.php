<?php
/** @var DafCore\Component $this  */
/** @var string $msg  */
$msg = $this->RequiredParameter("Msg");
?>

<div <?=$this->GetAdditionalProps()?>>
    <p class="alert alert-danger"><?=$msg ?? "some alert msg" ?></p>
</div>