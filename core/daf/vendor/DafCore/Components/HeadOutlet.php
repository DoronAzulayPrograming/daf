<?php
/** @var DafCore\Component $this  */
/** @var DafCore\HeadOutlet $outlet */ 
$outlet = $this->Inject(DafCore\HeadOutlet::class);

$outlet->RenderOutlet();