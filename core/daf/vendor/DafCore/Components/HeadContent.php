<?php
/** @var DafCore\IComponent $this  */
/** @var DafCore\HeadOutlet $outlet  */
$outlet = $this->Inject(DafCore\HeadOutlet::class);

$outlet->AddContent($this->RenderChildContent());
foreach ($this->GetChildren() as $c) {
    $outlet->AddContent($c->Render());
}