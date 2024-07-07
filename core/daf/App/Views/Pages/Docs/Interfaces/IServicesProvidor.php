<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>IServicesProvidor Interface</h1>
<br>
<h3>Definition</h3>

<p>
    <span>Namespace:</span>
    <span>DafCore</span>
    <br>
    <span>Phar:</span>
    <span>DafCore.phar</span>
</p>

<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">AddSingleton</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$serviceName</span>, <span class="php-class">Closure</span>&nbsp;<span class="attr-name">$callback</span> = <span class="tag-name">null</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Register service the current DI Container.
        <br>
        <p>
            <code>callback</code> is a optional parameter.
            <br>
            if <code>callback</code> is not null the return value will be used as the service.
            <br>
            if <code>callback</code> is null the service will be created with the new instance of serviceName as value
        </p>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">BindInterface</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$interface</span>, <span class="tag-name">string</span>&nbsp;<span class="attr-name">$concrete</span>)
            :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Bind interface to same concrete service that Register in DI Container.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetOne</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$serviceName</span>)&nbsp;:&nbsp;<span class="tag-name">mixed</span>
        </code>    
        <br>
        <br>
        Get service by key in the DI Container.
    </div>
</div>