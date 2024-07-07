<?php
/** @var DafCore\Component $this  */
/** @var DafCore\ScriptsOutlet $outlet  */
/** @var string $key  */
$outlet = $this->Inject(DafCore\ScriptsOutlet::class);
$key = $this->Parameter('Key');

if(is_null($key) || empty($key)) {
    $outlet->AddContent("<script>{$this->ChildContent}</script>");
}else {
    $outlet->AddContent($key, "<script>{$this->ChildContent}</script>");
}