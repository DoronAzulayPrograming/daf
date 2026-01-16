<?php
/** @var DafCore\IComponent $this  */
/** @var DafCore\HeadOutlet $outlet  */
/** @var string $key  */
$outlet = $this->Inject(DafCore\HeadOutlet::class);
$key = $this->Parameter('Key');

if(is_null($key) || empty($key)) {
    $outlet->AddContent("<style>".$this->RenderChildContent()."</style>");
}else {
    $outlet->AddContent($key, "<style>".$this->RenderChildContent()."</style>");
}