<?php
/** @var DafCore\Component $this  */
/** @var DafCore\ScriptsOutlet $outlet  */
$outlet = $this->Inject(DafCore\ScriptsOutlet::class);
?>

<div data-daf-scripts>
    <?php $outlet->RenderOutlet(); ?>
</div>