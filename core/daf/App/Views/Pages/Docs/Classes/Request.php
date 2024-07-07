<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>Request Class</h1>
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
    <a href="/Docs/IRequest" class="php-class">IRequest</a>
</p>

<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetUrlPath</span>()&nbsp;:&nbsp;<span class="tag-name">string</span>
        </code>  
        <br>
        <br>
        Get the url without queryParams.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetQueryParams</span>()&nbsp;:&nbsp;<span class="tag-name">array</span>
        </code>  
        <br>
        <br>
        Get the query parameters.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetRouteParams</span>()&nbsp;:&nbsp;<span class="tag-name">array</span>
        </code>  
        <br>
        <br>
        Get the route parameters.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">SetRouteParams</span>()&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Set the route parameters. ( <a href="/Docs/Router" class="php-class">Router</a><b>:</b><code class="tag-name">class</code> set the route parameters ).
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetMethod</span>()&nbsp;:&nbsp;<span class="tag-name">string</span>
        </code>  
        <br>
        <br>
        Get the request method name.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetBody</span>()&nbsp;:&nbsp;<span class="php-class">RequestBody</span>
        </code>  
        <br>
        <br>
        Get the parameters for [ POST, PUT, DELETE ] methods as <a href="/Docs/RequestBody" class="php-class">RequestBody</a> that contine the parameters as props.
        <br>
        <a href="/Docs/RequestBody" class="php-class">RequestBody</a><b>:</b><code class="tag-name">class</code> extends <code class="php-class">stdClass</code><b></b>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetBodyArray</span>()&nbsp;:&nbsp;<span class="tag-name">array</span>
        </code>  
        <br>
        <br>
        Get the parameters for [ POST, PUT, DELETE ] methods as array.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetHeaders</span>()&nbsp;:&nbsp;<span class="tag-name">array</span>
        </code>  
        <br>
        <br>
        Get the request headers as array.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetCookies</span>()&nbsp;:&nbsp;<span class="tag-name">array</span>
        </code>  
        <br>
        <br>
        Get the request cookies as array.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">TryGetCookie</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$name</span>, <span class="tag-name">&</span><span class="attr-name">$value</span>)&nbsp;:&nbsp;<span class="tag-name">bool</span>
        </code>  
        <br>
        <br>
        Get the request cookie by key.
        <br>
        if cookie found return true and set the <code>$value</code> to the cookie value else return false.
    </div>
</div>
