<?php
/** @var DafCore\IComponent $this  */
/** @var string $msg  */
$msg = $this->RequiredParameter("Msg");
?>

<div <?=$this->RenderAttributes()?>>
    <p class="alert alert-danger"><?=$msg ?? "some alert msg" ?></p>
</div>