<?php
/**
 * @daf-summary Pushes rendered child content into the shared scripts outlet.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\Views\ScriptsOutlet $outlet  */
$outlet = $this->Inject(DafCore\Views\ScriptsOutlet::class);

$outlet->AddContent($this->RenderChildContent());
foreach ($this->GetChildren() as $c) {
    $outlet->AddContent($c->Render());
}
