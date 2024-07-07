<?php
/** @var DafCore\Component $this  */
/** @var DafCore\HeadOutlet $outlet  */
/** @var string $key  */
$outlet = $this->Inject(DafCore\HeadOutlet::class);
$key = $this->Parameter('Key');

if(is_null($key) || empty($key)) {
    $outlet->AddContent("<style>{$this->ChildContent}</style>");
}else {
    $outlet->AddContent($key, "<style>{$this->ChildContent}</style>");
}