<?php
/** @var DafCore\Component $this */
/** @var DafCore\Request $req */
$req = $this->Inject(DafCore\Request::class);

$align = $this->Parameter('Align') ?? "H";
if($align !== "V" && $align !== "H") $align = "H";

$param_name = $this->RequiredParameter('Parameter');
$param_default_value = $this->Parameter('ParameterDefaultValue') ?? "0";
$param = $req->GetQueryParams()[$param_name] ?? $param_default_value;
$param_name = $this->RequiredParameter('Parameter');
$param_default_value = $this->Parameter('ParameterDefaultValue') ?? "0";
$param = $req->GetQueryParams()[$param_name] ?? $param_default_value;

$this->SetAdditionalProps(['class' => ($align == "V" ? 'd-md-flex ':'')."align-items-start"])
?>

<div <?=$this->GetAdditionalProps()?>>
  <div class="nav nav-pills mb-3 <?=$align == "V" ? 'flex-column mb-md-0':'mb-3'?>  me-3" role="tablist" aria-orientation="vertical">
    <?php foreach($this->GetChildrenOfType("App\Views\Components\NavTabs\Tab") as $c){?>
        <a style="white-space: nowrap;" class="nav-link <?=($param == $c->Parameter('Value') ? 'active' : '')?>" href="<?=$req->GetUrlPath()."?$param_name=".$c->Parameter('Value') ?>"><?=$c->Parameter('Title')?></a>
    <?php }?>
  </div>
  <div class="tab-content">
    <?php foreach($this->GetChildrenOfType("App\Views\Components\NavTabs\Tab") as $c){?>
        <div class="tab-pane fade <?=($param == $c->Parameter('Value') ? 'show active' : '')?>">
            <?=$c->ChildContent ?>
        </div>
    <?php }?>
    
  </div>
</div>