<PageTitle>DAF - Home</PageTitle>

<Alert Msg="Hello" />

<section class="pt-2">
    <div class="d-grid justify-content-center">
        <div>
            <h1 class="agbalumo-regular d-inline">
                DAF
            </h1>
            <p class="d-inline h4" style="font-weight: 300;">
                Your new, lightweight, php framework.
            </p>
        </div>
        <div class="text-center mt-4">
            <a href="/GetStarted" type="button" class="btn btn-lg btn-primary">Get Started</a>
        </div>
    </div>
</section>

<section class="py-5 py-md-0">
    <div class="row justify-content-center align-items-center">
        <div class="col-5 col-md-4 col-lg-3">
            <img src="/public/daf-logos/daf-logo-colord.png" style="width:100%;"/>
        </div>
        <div class="col-md-8 col-lg-7 col-xl-6 col-xxl-5">
            <p class="h4" style="font-weight: 300; line-height: 1.5;">
            Easy to <b style="font-weight: 600; text-decoration: underline;">Build & Deploy</b> Websites & APIs.<br> 
            Build fast ui with <b style="font-weight: 600; text-decoration: underline;">Components</b> and use <b style="font-weight: 600; text-decoration: underline;">LINQ</b> for query database.
            </p>
        </div>
    </div>
</section>


<section class="py-5">


<NavTabs Parameter="tab">
    <Tab Title="Component" Value="0">
        <div class="row gap-3 gap-lg-0">
            <div class="col-12 col-lg-7 col-xl-7">
                <CardCode class="h-100">
                    <span class="comment-text"><span class="comment-text"><</span>!-- &nbsp;Views/Home.php --<span class="comment-text">></span></span>
                    <br>
                    <span class="braket"><</span><span class="tag-name">PageTitle</span><span class="braket">></span>
                    <span>Home Page</span>
                    <span class="braket"><</span><span class="braket">/</span><span class="tag-name">PageTitle</span><span class="braket">></span>
                    <br>
                    <br>
                    <span class="braket"><</span><span class="tag-name">h1</span><span class="braket">></span>
                    <span>Hello World.</span>
                    <span class="braket"><</span><span class="braket">/</span><span class="tag-name">h1</span><span class="braket">></span>
                    <br>
                    <span class="braket"><</span><span class="tag-name">NiceMsg</span>
                    <span class="attr-name">Text</span>=<span class="string-text">"Hello From DAF Component :-)."</span>
                    <span class="braket">/></span>
                </CardCode>
            </div>
            <div class="col-12 col-lg-5 col-xl-5">
            <div>
                <img src="/public/daf-logos/component-example.png" style="width:100%;" />
            </div>
            </div>
        </div>
    </Tab>
    <Tab Title="LINQ" Value="1">
        <div class="row gap-3 gap-xxl-0">
            <div class="col-12 col-xxl-6">
                <CardCode class="h-100">
                    <span class="braket"><</span><span class="tag-name">?php</span>
                    <br>
                    <span class="attr-name">$result</span> =
                    <span class="attr-name">$products</span> 
                    <br>
                    -><span class="php-function-name">Where</span>(<span class="tag-name">fn</span>(<span class="attr-name">$x</span>)
                    => <span class="php-function-name">str_contains</span>(<span class="attr-name">$x->Category</span>, <span class="string-text">"smart"</span>))
                    <br>
                    -><span class="php-function-name">Where</span>(<span class="tag-name">fn</span>(<span class="attr-name">$x</span>)
                    => 
                    <span class="attr-name">$x->Price</span>
                    > <span class="php-number">500</span> &&
                    <span class="attr-name">$x->Price</span>
                    < <span class="php-number">1800</span>)
                    <br>
                    -><span class="php-function-name">OrderBy</span>(<span class="tag-name">fn</span>(<span class="attr-name">$x</span>)
                    => 
                    <span class="attr-name">$x->Price</span>)
                    <br>
                    -><span class="php-function-name">Skip</span>(<span class="php-number">1</span>)-><span class="php-function-name">Take</span>(<span class="php-number">2</span>)
                    <br>
                    -><span class="php-function-name">Map</span>(<span class="tag-name">fn</span>(<span class="attr-name">$x</span>)
                    => 
                    <span class="string-text">"</span><span class="attr-name">$x->Name</span><span class="string-text">,&nbsp;[&nbsp;</span><span class="attr-name">$x->Price</span><span class="string-text">&nbsp;]"</span>);
                    <br>
                    <span class="tag-name">?</span><span class="braket">></span>
                                
                    <br>
                    <br>
                    <span class="braket"><</span><span class="tag-name">ul</span><span class="braket">></span>
                    <br>&nbsp; &nbsp; 
                    <span class="braket"><</span><span class="tag-name">?php</span>
                                
                    <span class="php-for">foreach</span>(<span class="attr-name">$result</span>
                    <span class="tag-name">as</span>
                    <span class="attr-name">$p</span>) {
                    <span class="tag-name">?</span><span class="braket">></span>
                    <br> &nbsp; &nbsp; &nbsp; &nbsp; 
                    <span class="braket"><</span><span class="tag-name">li</span><span class="braket">></span>
                    <span class="braket"><</span><span class="tag-name">?=</span>
                    <span class="attr-name">$p</span>
                    <span class="tag-name">?</span><span class="braket">></span>

                    <span class="braket"><</span><span class="braket">/</span><span class="tag-name">li</span><span class="braket">></span>
                                
                    <br> &nbsp; &nbsp;
                    <span class="braket"><</span><span class="tag-name">?php</span> }
                    <span class="tag-name">?</span><span class="braket">></span>
                    <br>
                    <span class="braket"><</span><span class="braket">/</span><span class="tag-name">ul</span><span class="braket">></span>
                </CardCode>
            </div>
            <div class="col-12 col-xxl-6">
            <div class="mb-3">
                <img src="/public/daf-logos/sqlite-products-table.png" style="width:100%;" />
            </div>
            <div>
                <img src="/public/daf-logos/linq-example.png" style="width:100%;" />
            </div>
            </div>
        </div>
    </Tab>
</NavTabs>

</section>
