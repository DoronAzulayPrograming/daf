<?php
/** @var DafCore\Component $this  */
/** @var DafCore\HeadOutlet $outlet  */
$outlet = $this->Inject(DafCore\HeadOutlet::class);

$outlet->AddContent($this->ChildContent);
foreach ($this->Children as $c) {
    $outlet->AddContent($c->Render());
}