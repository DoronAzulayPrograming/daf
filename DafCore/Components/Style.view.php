<?php
/**
 * @daf-summary Queues an inline '<style>' block into the head outlet, optionally by unique key.
 *
 * @daf-param Key string optional - unique identifier used to replace/deduplicate a queued style block
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\Views\HeadOutlet $outlet  */
/** @var string $key  */
$outlet = $this->Inject(DafCore\Views\HeadOutlet::class);
$key = $this->Parameter('Key');

if(is_null($key) || empty($key)) {
    $outlet->AddContent("<style>".$this->RenderChildContent()."</style>");
}else {
    $outlet->AddContent($key, "<style>".$this->RenderChildContent()."</style>");
}