<?php
/**
 * @daf-summary Pushes rendered child content into the shared head outlet.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\HeadOutlet $outlet  */
$outlet = $this->Inject(DafCore\HeadOutlet::class);

$outlet->AddContent($this->RenderChildContent());
foreach ($this->GetChildren() as $c) {
    $outlet->AddContent($c->Render());
}