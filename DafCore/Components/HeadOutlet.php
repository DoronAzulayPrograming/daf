<?php
/**
 * @daf-summary Renders all queued head tags from the head outlet.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\HeadOutlet $outlet */ 
$outlet = $this->Inject(DafCore\HeadOutlet::class);

$outlet->RenderOutlet();