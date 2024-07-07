<?php
/** 
 * @var DafCore\Component $this 
 * @var DafCore\Request $req 
 * @var bool $match 
 * */

$req = $this->Inject(DafCore\Request::class);
$match = $this->Parameter('Match', 'bool') ?? true;

if($match){
    if($this->AdditionalParameters['href'] === $req->GetUrlPath()){
        $this->SetAdditionalProps(['class' => 'active'], 'end');
    }
}
?>


<a <?=$this->GetAdditionalProps()?>><?=$this->ChildContent ?></a>