<?php
/**
 * @daf-summary Sets the document <code><<span>title</span>></code> tag in the head outlet using the component child content.
 */
/** @var DafCore\IComponent $this */
/** @var DafCore\HeadOutlet $headOutlet */

$headOutlet = $this->Inject(DafCore\HeadOutlet::class);
$title = $this->RenderChildContent();
$headOutlet->AddContent('PageTitle',"<title>$title</title>");