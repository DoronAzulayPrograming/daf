<h1>AntiForgery Class</h1>
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
        <code>new AntiForgery(Session $session, Request $request);</code>
    </div>
</div>

<ul class="mt-2">
    <li>
    <code><span class="php-class">Session</span>&nbsp;$session</code> - class for handle current sessions.
    </li>
    <li>
        <code><span class="php-class">Request</span>&nbsp;$request</code> - class for handle current Request.
    </li>
</ul>


<br>
<h3>Methods</h3>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">RegisterToken</span>()&nbsp;:&nbsp;<span class="tag-name">void</span>
        </code>    
        <br>
        <br>
        Register AntiForgery token to the current session.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">GetToken</span>()&nbsp;:&nbsp;<span class="tag-name">string</span>
        </code>    
        <br>
        <br>
        Get the AntiForgery token from the current session.
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <code class="text-white">
            <span class="tag-name">function</span>&nbsp;<span class="php-function-name">ValidateToken</span>()&nbsp;:&nbsp;<span class="tag-name">bool</span>
        </code>    
        <br>
        <br>
        Validate the AntiForgery token of the current session with the request token.
    </div>
</div>