<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>Response Class</h1>
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
    <a href="/Docs/IResponse" class="php-class">IResponse</a>
</p>

<h3>Constructor</h3>
<div class="card">
    <div class="card-body">
        <code>new Response($statusCode = 200, $reasonPhrase = null);</code>
    </div>
</div>

<ul class="mt-2">
    <li>
    <code><span class="tag-name">int</span>&nbsp;$statusCode</code> - http status code, default 200 (ok).
    </li>
    <li>
        <code><span class="tag-name">string</span>&nbsp;$reasonPhrase</code> - status code reason msg.
    </li>
</ul>

<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Reset</span>() :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Reset the response obj to clean new response.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Status</span>(<span class="tag-name">int</span>&nbsp;<span class="attr-name">$statusCode</span>, <span class="tag-name">string</span>&nbsp;<span class="attr-name">$reasonPhrase</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status header to the response.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Send</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$text</span>, <span class="tag-name">array</span>&nbsp;<span class="attr-name">$headers</span>&nbsp;=&nbsp;<span class="tag-name">null</span>) :&nbsp;<span class="tag-name">self</span>
        </code>    
        <br>
        <br>
        Write message and status headers ( if not empty ) to the response.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Json</span>(<span class="tag-name">mixed</span>&nbsp;<span class="attr-name">$data</span>, <span class="tag-name">array</span>&nbsp;<span class="attr-name">$headers</span>&nbsp;=&nbsp;<span class="tag-name">null</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write JSON message and status headers ( if not empty ) to the response.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Redirect</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$location</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Redirect the request to new location.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">RedirectBack</span>() :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Redirect to the last location in the request.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Ok</span>(<span class="tag-name">mixed</span>&nbsp;<span class="attr-name">$obj</span>, <span class="tag-name">array</span>&nbsp;<span class="attr-name">$headers</span>&nbsp;=&nbsp;<span class="tag-name">null</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 200 ( Ok ) and message to the response.
        <br>
        if <code>obj</code> is string it will trigger Send with the headers.
        <br>else it will trigger Json with the headers.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Created</span>(<span class="tag-name">mixed</span>&nbsp;<span class="attr-name">$obj</span>, <span class="tag-name">array</span>&nbsp;<span class="attr-name">$headers</span>&nbsp;=&nbsp;<span class="tag-name">null</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 201 ( Created ) and message to the response.
        <br>
        if <code>obj</code> is string it will trigger Send with the headers.
        <br>else it will trigger Json with the headers.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">InternalError</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$msg</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 500 ( InternalError ) and message to the response.
        <br>
        if <code>msg</code> not empty it will trigger Send with msg.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">NoContent</span>() :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 204 ( NoContent ) and message to the response.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">BadRequest</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$msg</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 400 ( BadRequest ) and message to the response.
        <br>
        if <code>msg</code> not empty it will trigger Send with msg.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">NotFound</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$msg</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 404 ( NotFound ) and message to the response.
        <br>
        if <code>msg</code> not empty it will trigger Send with msg.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Forbidden</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$msg</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 403 ( Forbidden ) and message to the response.
        <br>
        if <code>msg</code> not empty it will trigger Send with msg.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Unauthorized</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$msg</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Write status code 401 ( Unauthorized ) and message to the response.
        <br>
        if <code>msg</code> not empty it will trigger Send with msg.
    </div>
</div>
