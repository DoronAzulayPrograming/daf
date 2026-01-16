<h1>ViewManager Class</h1>
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
    <a href="/Docs/IViewManager" class="php-class">IViewManager</a>
</p>


<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetLayout</span>() :&nbsp;<span class="tag-name">string</span>
        </code>    
        <br>
        <br>
        Return the current layout.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">SetLayout</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$layout</span>) :&nbsp;<span class="tag-name">self</span>
        </code>    
        <br>
        <br>
        Set the current layout.
        <br>
        <code>layout</code> must be under the root <code >Views/_Layouts</code> folder.</code>
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">OnRender</span>(<span class="tag-name">callable</span>&nbsp;<span class="attr-name">$callback</span>) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Add a callback function to trigger before render.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">RenderView</span>(<span class="tag-name">string</span>&nbsp;<span class="attr-name">$view</span>, <span class="tag-name">array</span>&nbsp;<span class="attr-name">$params</span>&nbsp;=&nbsp;[]) :&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        <code>view</code> - is a view/component path to render.
        <br><code>params</code> - is array of parameters to pass to view/component.
    </div>
</div>