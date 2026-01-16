<?php
/** 
 * @var DafCore\IComponent $this 
 * @var DafCore\Request $req 
 * @var bool $match 
 * */

$req = $this->Inject(DafCore\Request::class);
$match = $this->Parameter('Match', 'bool') ?? true;
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