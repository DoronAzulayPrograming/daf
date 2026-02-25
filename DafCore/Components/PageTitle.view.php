<?php
/**
 * @daf-summary Sets the document `<title>` tag in the head outlet using the component child content.
 */
/** @var DafCore\IComponent $this  */
/** @var DafCore\Views\HeadOutlet $headOutlet */

$headOutlet = $this->Inject(DafCore\Views\HeadOutlet::class);
$title = $this->RenderChildContent();
$headOutlet->AddContent('PageTitle',"<title>$title</title>");