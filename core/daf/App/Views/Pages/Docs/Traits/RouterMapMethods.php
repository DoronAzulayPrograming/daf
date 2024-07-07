<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>RouterMapMethods trait</h1>
<br>
<h3>Definition</h3>

<p>
    <span>Namespace:</span>
    <span>DafCore</span>
    <br>
    <span>Phar:</span>
    <span>DafCore.phar</span>
    <p>
        this trait is add to the parnet class shortcuts to the router map functions.
        <br>
        the function just passing the parameters to the router functions.
        <br>
        if you want more info about the router map functions see the 
        <a class="php-class" href="/Docs/Router">Router</a>
        <b>:</b>
        <code class="tag-name">class</code>
    </p>
</p>



<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Get</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>,&nbsp; ...<span class="attr-name">$callbacks</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Pass the parameters to the <code class="php-class">Router</code>   
        <br>
        Add a GET route to the route list.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Post</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>,&nbsp; ...<span class="attr-name">$callbacks</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Pass the parameters to the <code class="php-class">Router</code>-><span class="php-function-name">Get</span>
        <br>
        Add a POST route to the route list.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Post</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>,&nbsp; ...<span class="attr-name">$callbacks</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Pass the parameters to the <code class="php-class">Router</code>-><span class="php-function-name">Post</span>
        <br>
        Add a POST route to the route list.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Put</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>,&nbsp; ...<span class="attr-name">$callbacks</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Pass the parameters to the <code class="php-class">Router</code>-><span class="php-function-name">Put</span>
        <br>
        Add a PUT route to the route list.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Delete</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>,&nbsp; ...<span class="attr-name">$callbacks</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Pass the parameters to the <code class="php-class">Router</code>-><span class="php-function-name">Delete</span>
        <br>
        Add a Delete route to the route list.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">SetRouteBasePath</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$path</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>  
        <br>
        <br>
        Pass the parameters to the <code class="php-class">Router</code>-><span class="php-function-name">SetBasePath</span>
        <br>
        Set the prefix for all the next routes.
    </div>
</div>