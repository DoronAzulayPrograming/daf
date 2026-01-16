<?php
/** @var DafCore\IComponent $this */
/** @var DafCore\HeadOutlet $headOutlet */

$headOutlet = $this->Inject(DafCore\HeadOutlet::class);
$title = $this->RenderChildContent();
$headOutlet->AddContent('PageTitle',"<title>$title</title>");