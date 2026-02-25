<?php
/**
 * @daf-summary Renders all queued head tags from the head outlet.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\Views\HeadOutlet $outlet */ 
$outlet = $this->Inject(DafCore\Views\HeadOutlet::class);

$outlet->RenderOutlet();