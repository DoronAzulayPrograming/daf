<?php
/** @var DafCore\Component $this */
/** @var DafCore\HeadOutlet $headOutlet */

$headOutlet = $this->Inject(DafCore\HeadOutlet::class);
$title = $this->ChildContent;
$headOutlet->AddContent('PageTitle',"<title>$title</title>");