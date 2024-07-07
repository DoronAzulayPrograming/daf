<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>Router Class</h1>
<br>
<h3>Definition</h3>

<p>
    <span>Namespace:</span>
    <span>DafCore</span>
    <br>
    <span>Phar:</span>
    <span>DafCore.phar</span>
</p>

<h3>Constructor</h3>
<div class="card">
    <div class="card-body">
        <code>new Router(ServicesProvidor $sp, ApplicationContext $context, ViewManager $viewManager);</code>
    </div>
</div>

<ul class="mt-2">
    <li>
        <code>
            <a class="php-class" href="/Docs/ServicesProvidor">ServicesProvidor</a>&nbsp;$sp
        </code>
    </li>
    <li>
        <code>
            <a class="php-class" href="/Docs/ApplicationContext">ApplicationContext</a>&nbsp;$context
        </code>
    </li>
    <li>
        <code>
            <a class="php-class" href="/Docs/ViewManager">ViewManager</a>&nbsp;$viewManager
        </code>
    </li>
</ul>

<br>


<p>
    for the following 
    <br><code>$path</code> is a spesific path 
    <br>
    example:
    <br> <code class="string-text">"/Users"</code>
    <br> <code class="string-text">"/Accounts/Login"</code>
    <br>
    <br> the <code>$path</code> variable can hold parameters
    <br>
    example:
    <br> <code class="string-text">"/Users/{id}"</code>
</p>
<p>
    for the <code>$callbacks</code> variable
    you can ask every service that available in the current DI Container as function Parameter with type.
    <br>
    or you can ask every route param by its name and type.
    <br>
    example:
    <br> <code class="string-text">"/Users/{id}"</code>
    <br> <code><span class="tag-name">function</span>(<span class="php-class">Request</span>&nbsp;<span class="attr-name">$req</span>,<span class="tag-name">int</span>&nbsp;<span class="attr-name">$id</span>)</code>{}
</p>


<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Resolve</span>()&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Trigger the pipline (start process the requst).
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">SetBasePath</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>) :&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Set the prefix for all the next routes.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">AddMiddleware</span>(<span class="tag-name">callable</span>&nbsp;<span class="attr-name">$callback</span>) :&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Add the <code>callback</code> as a global middleware.
        <br>global middleware will be executed before the route middleware.
    </div>
</div>
<br>
<p>
    the array of <code>...$callbacks</code>
    <br>
    can containe middlewares and the last elemet is the endpoit.
</p>
<br>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">AddController</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$controllerClass</span>) :&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Add all the routes in a controll and attributes.
        <br> <b>routes</b> difined with <code>[ HttpGet, HttpPost, HttpPut, HttpDelete ]</code>
        <br> <b>attributes</b> difined as middlewares.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Get</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>, ...<span class="attr-name">$callbacks</span>) :&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Add a GET route to the pipline.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Post</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>, ...<span class="attr-name">$callbacks</span>) :&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Add a POST route to the pipline.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Post</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>, ...<span class="attr-name">$callbacks</span>) :&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Add a POST route to the pipline.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Put</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>,&nbsp; ...<span class="attr-name">$callbacks</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Add a PUT route to the pipline.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Delete</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>,&nbsp; ...<span class="attr-name">$callbacks</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Add a Delete route to the pipline.
    </div>
</div>