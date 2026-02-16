<?php
/**
 * @daf-summary Renders all queued script tags from the scripts outlet.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\ScriptsOutlet $outlet  */
$outlet = $this->Inject(DafCore\ScriptsOutlet::class);
?>

<div data-daf-scripts>
    <?= $outlet->RenderOutlet(); ?>
</div>