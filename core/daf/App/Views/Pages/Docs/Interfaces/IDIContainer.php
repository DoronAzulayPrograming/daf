<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>DIContainer Interface</h1>
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
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">AddSingleton</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$serviceName</span>, <span class="php-class">Closure</span>&nbsp;<span class="attr-name">$callback</span> = <span class="tag-name">null</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Register service the current DI Container.
        <br>
        <code>callback</code> return value will be used as the service.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">BindInterface</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$interface</span>, <span class="tag-name">string</span>&nbsp;<span class="attr-name">$concrete</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Bind interface to same concrete service that Register in DI Container.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Exists</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$serviceName</span>)&nbsp;:&nbsp;<span class="tag-name">bool</span>
        </code>    
        <br>
        <br>
        Return true if service is in the DI Container by key.
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
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetAll</span>(<span class="tag-name">array</span>&nbsp;<span class="attr-name">$keys</span>)&nbsp;:&nbsp;<span class="tag-name">array</span>
        </code>    
        <br>
        <br>
        Get All service by keys in the DI Container.
    </div>
</div>