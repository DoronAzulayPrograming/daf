<?php
/**
 * @daf-summary Renders an anchor and applies the `active` class when its href matches the current route.
 *
 * @daf-param Match bool optional default=true - exact href match against the current path
 * @daf-param StartWith bool optional default=false - prefix match against the current path
 * */
 /** @var DafCore\IComponent $this */

 /** @var DafCore\Request $req */
$req = $this->Inject(DafCore\Request::class);

 /** @var bool $match */
$match = $this->Parameter('Match', 'bool') ?? true;

 /** @var bool $startWith */
$startWith = $this->Parameter('StartWith', 'bool') ?? false;

if($match){
    if($this->GetAttribute('href') === $req->GetUrlPath()){
        $this->AddAttributesToEnd(['class' => 'active']);
    }
}
if($startWith){
    $href = $this->GetAttribute('href');
    if($href !== null && str_starts_with($req->GetUrlPath(), $href)){
        $this->AddAttributesToEnd(['class' => 'active']);
    }
}
?>


<a <?=$this->RenderAttributes()?>><?=$this->RenderChildContent() ?></a>