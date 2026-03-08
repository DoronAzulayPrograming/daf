<?php
/**
 * @daf-summary Pushes rendered child content into the shared head outlet.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\Views\HeadOutlet $outlet  */
$outlet = $this->Inject(DafCore\Views\HeadOutlet::class);

$outlet->AddContent($this->RenderChildContent());
foreach ($this->GetChildren() as $c) {
    $outlet->AddContent($c->Render());
}