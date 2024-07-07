<?php
DafCore\ViewManager::$layout = "DocsLayout";
?>

<h1>Application Class</h1>
<br>
<h3>Definition</h3>

<p>
    <span>Namespace:</span>
    <span>DafCore</span>
    <br>
    <span>Phar:</span>
    <span>DafCore.phar</span>
    <br>
    <span>Using:</span>
    <a class="php-class" href="/Docs/RouterMapMethods">RouterMapMethods</a>
    <br>
</p>

<h3>Constructor</h3>
<div class="card">
    <div class="card-body">
        <code>new Application(string $baseFolder, bool $isRelease);</code>
    </div>
</div>

<ul class="mt-2">
    <li>
    <code>$baseFolder</code> - {App Name}, base folder of application.
    </li>
    <li>
        <code>$isRelease</code> - {bool}, set to true for release mode.
        <br> on release mode <code>true</code> the app will load from phar files.
    </li>
</ul>

<br>
<h3>Properties</h3>
<div class="card">
    <div class="card-body">
        <code><span class="tag-name">static</span>&nbsp;<span class="tag-name">string</span>&nbsp;$BaseFolder</code> - Hold the app base folder.
        <br>
        <code><a class="php-class" href="/Docs/Router">Router</a>&nbsp;$Router</code> - Hold the app router class.
        <br>
        <code><a href="/Docs/IServicesProvidor" class="php-class">IServicesProvidor</a>&nbsp;$Services</code> - Hold the app IServicesProvidor class.
    </div>
</div>

<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">Run</span>()&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Start the pipline.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">ShowTimePerformance</span>()&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Display in the end of the content the seconds take to compleate current response.
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">AddGlobalMiddleware</span>(<span class="attr-name">$callback</span>)&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Gets <code>callback</code> function to add to the pipline.
        <br>
        in the <code>callback</code> function Prameters <b>section</b> you can get any dependency that avaible in the DI Container right now.
        <a href="/Docs/ServicesProvidor">global dependency list</a>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">AddAntiForgeryToken</span>()&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Add the <a href="/Docs/AntiForgery"><code style="pointer-events: none;" class="php-class">AntiForgery</code></a> <b>:</b><code class="tag-name">class</code> to the DI Container.
        <br>
        Register AntiForgery token to the sesson before app Render.
        <a href="/Docs/ServicesProvidor">global dependency list</a>
    </div>
</div>

