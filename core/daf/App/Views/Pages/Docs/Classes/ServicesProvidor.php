<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>ServicesProvidor Class</h1>
<br>
<h3>Definition</h3>

<p>
    <span>Namespace:</span>
    <span>DafCore</span>
    <br>
    <span>Phar:</span>
    <span>DafCore.phar</span>
    <br>
    <span>implements:</span>
    <a href="/Docs/IServicesProvidor" class="php-class">IServicesProvidor</a>
</p>

<h3>Constructor</h3>
<div class="card">
    <div class="card-body">
        <code>new ServicesProvidor(DIContainer $dIContainer);</code>
    </div>
</div>

<ul class="mt-2">
    <li>
    <code><a class="php-class" href="/Docs/DIContainer">DIContainer</a>&nbsp;$dIContainer</code> - class for handle DI.
    </li>
</ul>

<br>
<h3>Properties</h3>
<div class="card">
    <div class="card-body">
        <code>
            <span class="tag-name">static</span>&nbsp;<span class="php-class">DIContainer</span>&nbsp;<span class="attr-name">$dIContainer</span></span>
        </code>
        <br>
        <br>

        <p>use for using this class from <code class="php-class">Component</code> <code class="php-function-name">Inject</code> method</p>
    </div>
</div>

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
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetServicesForCallback</span>(<span class="tag-name">string</span>&nbsp;|&nbsp;<span class="tag-name">callable</span>&nbsp;<span class="attr-name">$classOrCallable</span>, <span class="tag-name">callable</span>&nbsp;<span class="attr-name">$onNotFound</span>&nbsp;=&nbsp;<span class="tag-name">null</span>) :&nbsp;<span class="tag-name">array</span>
        </code>    
        <br>
        <br>
        Get services array from DI Container by giving class or function.
        <br>
        if <code>classOrCallable</code> is a string the retun array will be full with the services from the DI Container by the Constructor parameters.
        <br>
        if <code>classOrCallable</code> is a Callable the retun array will be full with the services from the DI Container by the Function parameters.
    </div>
</div>