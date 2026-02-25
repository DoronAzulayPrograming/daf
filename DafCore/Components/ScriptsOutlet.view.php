<?php
/**
 * @daf-summary Renders all queued script tags from the scripts outlet.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\Views\ScriptsOutlet $outlet  */
$outlet = $this->Inject(DafCore\Views\ScriptsOutlet::class);
?>

<div data-daf-scripts>
    <?= $outlet->RenderOutlet(); ?>
</div>