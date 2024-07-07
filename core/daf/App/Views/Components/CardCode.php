<?php
/** @var DafCore\Component $this */

$this->SetAdditionalProps(['class' => 'card mb-3']);
$lang = $this->Parameter("Lang") ?? "PHP";
$copyBtn = $this->Parameter("Copy") ?? true;

?>
<div <?=$this->GetAdditionalProps()?>>
    <div class="card-header pe-2 d-flex justify-content-between align-items-center">
        <span><?=$lang?></span>
        <?php if($copyBtn){?>
            <button onclick="CopyCardCode()" class="btn btn-sm btn-secondary btn-copy">Copy</button>
        <?php }?>
    </div>
    <div class="card-body">
        <p style="font-family: Menlo, Monaco, 'Courier New', monospace;">
            <?=$this->ChildContent?>
        </p>
    </div>
</div>

<Script Key="CardCode">
    function CopyCardCode() {
        event.preventDefault();
        const text = event.target.parentElement.nextElementSibling.innerText;
        var regularText = text.replace(/\u00A0/g, ' '); // Replace non-breaking spaces with regular spaces
        navigator.clipboard.writeText(regularText);
        alert('Copied!');
    }
</Script>