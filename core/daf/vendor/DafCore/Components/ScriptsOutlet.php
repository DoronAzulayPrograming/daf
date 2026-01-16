<?php
/** @var DafCore\IComponent $this  */
/** @var DafCore\ScriptsOutlet $outlet  */
$outlet = $this->Inject(DafCore\ScriptsOutlet::class);
?>

<div data-daf-scripts>
    <?php $outlet->RenderOutlet(); ?>
</div>