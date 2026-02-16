<?php
/**
 * @daf-summary Queues an inline <code><<span>script</span>></code> block to the scripts outlet, optionally by unique key.
 *
 * @daf-param Key string optional - unique identifier used to replace/deduplicate a queued script block
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\ScriptsOutlet $outlet  */
/** @var string $key  */
$outlet = $this->Inject(DafCore\ScriptsOutlet::class);
$key = $this->Parameter('Key');

if(is_null($key) || empty($key)) {
    $outlet->AddContent("<script type='text/javascript' ".$this->RenderAttributes().">".$this->RenderChildContent()."</script>");
}else {
    $outlet->AddContent($key, "<script type='text/javascript' ".$this->RenderAttributes().">".$this->RenderChildContent()."</script>");
}