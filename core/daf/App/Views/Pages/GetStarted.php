<PageTitle>DAF - GetStarted</PageTitle>

<Style>
    .btn-uniqe{
        background-color: #936de6;
    }
    .btn-uniqe:hover{
        background-color: #6a6df1;
    }
</Style>

<h1 class="niceText display-5 fw-bold">Build your first web app with <span class="agbalumo-regular">DAF</span>.</h1>

<NavTabs Parameter="tab" Align="V" class="pt-3 pt-md-5 mb-5">
    <Tab Title="Intro" Value="0">
        <div class="ms-lg-3">
            <h2>Intro</h2>
            <h4>Purpose</h4>
            <p>Build your first web app with <span class="agbalumo-regular">DAF</span>.</p>
            <h4>Prerequisites</h4>
            <p>php v8.0 <-> v8.2 and set as environment variable.</p>
            <h4>Time to Complete</h4>
            <p>15-20 minutes + download/installation time.</p>
            <h4>Scenario</h4>
            <p>Create, use, and modify a 
                <ul>
                    <li>NiceMsg Component</li>
                    <li>Navbar Component</li>
                    <li>About Page/Component</li>
                    <li>Global Using</li>
                </ul>
            </p>
            <a href="/GetStarted?tab=1" class="btn btn-lg  btn-uniqe">Let's get started</a>
        </div>
    </Tab>
    <Tab Title="Download & Install" Value="1">
        <div class="ms-lg-3">
            <h2>Download & Install</h2>
            <p>To start building <span class="agbalumo-regular">DAF</span> apps, download daf <b>SDK</b> zip.</p>

            <div class="btn-group mb-2">
                <a href="https://daf.somaybe.co.il/plus/downloads/daf-osx-arm64.zip" class="btn btn-lg btn-primary" style="line-height: 1;">
                    <span class="d-block">Mac</span>
                    <small><small>Apple silicon</small></small>
                </a>
                <a href="https://daf.somaybe.co.il/plus/downloads/daf-win-x64.zip" class="btn btn-lg btn-primary px-4" style="line-height: 1;">
                    <span class="d-block">Win</span>
                    <small><small>(64<small>bit</small>)</small></small>
                </a>
            </div>

            <p>After downloading, extract the zip file and add the daf app to the environment variables.</p>

            <h4>Check everything installed correctly</h4>
            <p>Once you've installed, open a new terminal and run the following command:</p>
            <CardCode Lang="Terminal">
                daf --version
            </CardCode>
            <p>If the installation succeeded, you should see version 1.0.0 or higher outputted:</p>
            <CardCode Lang="Terminal" Copy="false">
                1.0.0
            </CardCode>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=2" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Create your app" Value="2">
        <div class="ms-lg-3">
            <h2>Create your app</h2>
            <ol>
                <li>Open a new folder where you like.</li>
                <li>Open terminal in your folder, run the following command to create your app:</li>
            </ol>
            <CardCode Lang="Terminal">
                daf new HelloWorldApp
            </CardCode>
            <p>This command creates your new <span class="agbalumo-regular">DAF</span> Web App project and places it inside your current location.</p>
            <div class="alert alert-info">
                <i class="bi bi-info-circle text-warning me-2"></i>
                Take note of the <b>-t</b> flag: it defines the app template.
                <br>options: [ empty , basic ], default is basic
                <br>
                <br>
                creates a new app with:
                <ul>
                    <li><b>empty</b> - an empty structure.</li>
                    <li><b>basic</b> - base structure for create MVC and API apps.</li>
                </ul>
                </ul>
            </div>

            <br>
            <p>Take a quick look at the contents of the directory.</p>
            <div class="row">
                <div class="col-6">
                    <h4>Mac</h4>
                    <CardCode Lang="Terminal">
                        ls
                    </CardCode>
                </div>
                <div class="col-6">
                    <h4>Win</h4>
                    <CardCode Lang="Terminal">
                        dir
                    </CardCode>
                </div>
            </div>
            <p>Several files were created in the directory, to give you a simple <span class="agbalumo-regular">DAF</span> app that is ready to run.</p>
            <ul>
                <li class="mb-2"><b>index.php</b>  is the entry point for the app that starts the server and where you configure the app services and middleware.</li>
                <li class="mb-2">{App Name} is <b>HelloWorldApp</b> it will store the necessary files for the project
                    <ul>
                        <li><b>ApplicationEx</b> is the main app class that extends from \DafCore\Application.</li>
                        <li>The <b>Views</b> directory contains some example web page and basic components for the app.</li>
                    </ul>
                 </li>
                <li class="mb-2"><b>vendor</b> directory: contains all the dependencies for your project.</li>
                <li class="mb-2"><b>prog.json</b> defines the app project and its dependencies.</li>
            </ul>
            
            <div class="alert alert-info">
                <i class="bi bi-info-circle text-warning me-2"></i>
                Take note of the <b>{App Name}</b> directory path as you will use it later in the tutorial.  
            </div>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=3" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Run your app" Value="3">
        <div class="ms-lg-3">
            <h2>Run your app</h2>
            <p>In your terminal, run the following command:</p>
            <CardCode Lang="Terminal">
                daf watch
            </CardCode>
            <p>The daf watch command will start the app, and then update the app whenever you make code changes. You can stop the app at any time by selecting Ctrl+C.</p>
            <p>Wait for the app to display that it's listening on http://localhost:<port number> and for the browser to launch at that address.</p>
            <p>Once you get to the following page, you have successfully run your first <span class="agbalumo-regular">DAF</span> app!</p>
            <div class="mb-3">
                <img src="/public/daf-logos/daf-new-project-screenshot.png" alt="daf hello world screenshot" style="width:100%;" />
            </div>
            <p>The displayed page is defined by the index.php file located inside the root directory. This is what its contents look like:</p>
            <CardCode>
                <span class="braket"><</span><span class="tag-name">?php</span>
                <br>
                <span class="tag-name">namespace</span>&nbsp;<span class="php-class">HelloWorldApp</span>;
                <br>
                <span class="php-for">require_once</span>&nbsp;<span class="string-text">'vendor/autoloader.php'</span>;
                <br>
                <br>
                <span class="tag-name">use</span>&nbsp;DafCore\<span class="php-class">IViewManager</span>;
                <br>
                <br>
                <span class="attr-name">$app</span>&nbsp;=&nbsp;<span class="tag-name">new</span>&nbsp;<span class="php-class">ApplicationEx</span>();
                <br>
                <br>
                <span class="attr-name">$app</span>-><span class="attr-name">Router</span>-><span class="php-function-name">Get</span>(<span class="string-text">"/"</span>,&nbsp;<span class="tag-name">fn</span>(<span class="php-class">IViewManager</span>&nbsp;<span class="attr-name">$vm</span>)&nbsp;=>&nbsp;<span class="attr-name">$vm</span>-><span class="php-function-name">RenderView</span>(<span class="string-text">"Pages/Home"</span>));
                <br>
                <br>
                <span class="attr-name">$app</span>-><span class="php-function-name">Run</span>();
            </CardCode>
            <p>It already contains the code that sets the homepage and displays the text Hello world! With Daf.</p>
            <br>
            <p>The displayed page is defined by this line in the index.php file. <br>
                for request with GET method with path &nbsp;/&nbsp;, it will render the <code>{App Name}/Views/Pages/Home.php</code> Component. 
            </p>
            <CardCode>
            <span class="attr-name">$app</span>-><span class="attr-name">Router</span>-><span class="php-function-name">Get</span>(<span class="string-text">"/"</span>,&nbsp;<span class="tag-name">fn</span>(<span class="php-class">IViewManager</span>&nbsp;<span class="attr-name">$vm</span>)&nbsp;=>&nbsp;<span class="attr-name">$vm</span>-><span class="php-function-name">RenderView</span>(<span class="string-text">"Pages/Home"</span>));
            </CardCode>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=4" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Create NiceMsg" Value="4">
        <div class="ms-lg-3">
            <h2>Create NiceMsg Component</h2>
            <p>In your terminal, run the following command:</p>
            <CardCode Lang="Terminal">
                daf g vc Views/Components/NiceMsg
            </CardCode>
            <p>The command will create a new view component file located in <code style="white-space: nowrap;">{App Name}/Views/Components/NiceMsg.php</code>.</p>
            <div class="alert alert-info">
                <i class="bi bi-info-circle text-warning me-2"></i>
                <span class="text-danger">Take note all the views/component need to be under the views root folder.</span><br>
                Take note of the <b>g / generate</b> command: it will generate file base template at provided location.
                <br><code class="p-1 m-4">daf g {template} {path}</code>
                <br>options: [ cl / class, m / model , c / controller , ac / api-controller, vc / view-component  ], <span class="text-danger">Not default value</span>
                </ul>
            </div>
            <p>Here is the file content that generated:</p>
            <CardCode Lang="PHP" Copy="false">
                <span class="tag-name"><</span><span class="tag-name">?php</span>
                <br>
                <span class="comment-text">/**&nbsp;</span><span class="tag-name">@var</span>&nbsp;<span class="comment-text">DafCore\</span><span class="php-class">Component</span>&nbsp;<span class="attr-name">$this</span>&nbsp;<span class="comment-text">*/</span>
                <br>
                <span class="tag-name">?></span>
                <br>
                <br>
                <span class="braket"><</span><span class="tag-name">h1</span><span class="braket">></span>Hello From NiceMsg ViewComponent!<span class="braket"><</span><span class="braket">/</span><span class="tag-name">h1</span><span class="braket">></span>
            </CardCode>
            <p class="text-warning">The top 3 lines are just for Component class <b>intellisense</b>.</p>
            <p>Now edit the file content to:</p>
            <CardCode Lang="PHP">
                <span class="tag-name"><</span><span class="tag-name">?php</span>
                <br>
                <span class="comment-text">/**&nbsp;</span><span class="tag-name">@var</span>&nbsp;<span class="comment-text">DafCore\</span><span class="php-class">Component</span>&nbsp;<span class="attr-name">$this</span>&nbsp;<span class="comment-text">*/</span>
                <br>
                <span class="attr-name">$msg</span>&nbsp;=&nbsp;<span class="tag-name">$this</span>-><span class="php-function-name">RequiredParameter</span>(<span class="string-text">"Text"</span>);
                <br>
                <span class="tag-name">?></span>
                <br>
                <br>
                <span class="braket"><</span><span class="tag-name">div</span>&nbsp;<span class="attr-name">class</span>=<span class="string-text">"alert alert-success"</span><span class="braket">></span>
                <br>&nbsp;&nbsp;
                <span class="tag-name"><</span><span class="tag-name">?=</span><span class="attr-name">$msg</span><span class="tag-name">?</span><span class="tag-name">></span>
                <br>
                <span class="braket"><</span><span class="braket">/</span><span class="tag-name">div</span><span class="braket">></span>
            </CardCode>
            <p>
                the <b> <code>RequiredParameter("Text")</code> </b> function are make the component to require the "Text" Parameter.
            </p>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=5" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Add NiceMsg to home" Value="5">
            <p>Update Home Component file located in <code style="white-space: nowrap;">{App Name}/Views/Pages/Home.php</code> to the following code.</p>
            <CardCode Lang="PHP">
                <span class="tag-name"><</span><span class="tag-name">?php</span>
                <br>
                <span class="comment-text">/**&nbsp;</span><span class="tag-name">@var</span>&nbsp;<span class="comment-text">DafCore\</span><span class="php-class">Component</span>&nbsp;<span class="attr-name">$this</span>&nbsp;<span class="comment-text">*/</span>
                <br>
                <span class="tag-name">$this</span>-><span class="php-function-name">Use</span>(<span class="string-text">"HelloWorld\Views\Components\NiceMsg"</span>);
                <br>
                <span class="tag-name">?></span>
                <br>
                <br>
                <span class="braket"><</span><span class="tag-name">h1</span><span class="braket">></span>Hello From About ViewComponent!<span class="braket"><</span><span class="braket">/</span><span class="tag-name">h1</span><span class="braket">></span>
                <br>
                <br>
                <span class="braket"><</span><span class="tag-name">NiceMsg</span>&nbsp;<span class="attr-name">Text</span>=<span class="string-text">"Hello From DAF Component :-)."</span>&nbsp;<span class="braket">/</span><span class="braket">></span>
            </CardCode>
            <p>
                the <b> <code>Use</code> </b> function get component full file path to include in the page.
            </p>
            <p class="text-danger">Make sure the file is saved.!!!</p>
            <p>This the content you need to see.</p>
            <div>
                <img src="/public/daf-logos/daf-new-project-add-nicemsg-screenshot.png" alt="NiceMsg in component screenshot" style="width:100%;" />
            </div>
            <br>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=6" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
    </Tab>
    <Tab Title="Create About" Value="6">
        <div class="ms-lg-3">
            <h2>Create About Component</h2>
            <p>In your terminal, run the following command:</p>
            <CardCode Lang="Terminal">
                daf g vc Views/Pages/About
            </CardCode>
            <p>The command will create a new view component file located in <code style="white-space: nowrap;">{App Name}/Views/Pages/About.php</code>.</p>
            <div class="alert alert-info">
                <i class="bi bi-info-circle text-warning me-2"></i>
                Take note of the <b>g / generate</b> command: it will generate file base template at provided location.
                <br><code class="p-1 m-4">daf g {template} {path}</code>
                <br>options: [ cl / class, m / model , c / controller , ac / api-controller, vc / view-component  ], <span class="text-danger">Not default value</span>
                </ul>
            </div>
            <p>Here is the file content that generated:</p>
            <CardCode Lang="PHP" Copy="false">
                <span class="tag-name"><</span><span class="tag-name">?php</span>
                <br>
                <span class="comment-text">/**&nbsp;</span><span class="tag-name">@var</span>&nbsp;<span class="comment-text">DafCore\</span><span class="php-class">Component</span>&nbsp;<span class="attr-name">$this</span>&nbsp;<span class="comment-text">*/</span>
                <br>
                <span class="tag-name">?></span>
                <br>
                <br>
                <span class="braket"><</span><span class="tag-name">h1</span><span class="braket">></span>Hello From About ViewComponent!<span class="braket"><</span><span class="braket">/</span><span class="tag-name">h1</span><span class="braket">></span>
            </CardCode>
            <p class="text-warning">The top 3 lines are just for Component class <b>intellisense</b>.</p>
            <p>Remove the top 3 lines we don't need them yet.</p>
            <p>Now the file content need to look like this:</p>
            <CardCode Lang="PHP" Copy="false">
                <span class="braket"><</span><span class="tag-name">h1</span><span class="braket">></span>Hello From About ViewComponent!<span class="braket"><</span><span class="braket">/</span><span class="tag-name">h1</span><span class="braket">></span>
            </CardCode>
            <p class="text-danger">Make sure the file is saved.!!!</p>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=7" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Add Route" Value="7">
        <div class="ms-lg-3">
            <h2>Add Route</h2>
            <p>Open the main <code>index.php</code> file and add the following code before <code>$app->Run();</code> line.</p>
            <CardCode Lang="PHP">
                <span class="attr-name">$app</span>-><span class="attr-name">Router</span>-><span class="php-function-name">Get</span>(<span class="string-text">"/About"</span>,&nbsp;<span class="tag-name">fn</span>(<span class="php-class">IViewManager</span>&nbsp;<span class="attr-name">$vm</span>)&nbsp;=>&nbsp;<span class="attr-name">$vm</span>-><span class="php-function-name">RenderView</span>(<span class="string-text">"Pages/About"</span>));
            </CardCode>
            <p class="text-danger">Make sure the file is saved.!!!</p>
            <p>Now manually navigate to <code>/About</code> to see the About page.</p>
            <div>
                <img src="/public/daf-logos/daf-new-project-about-page-screenshot.png" alt="About page screenshot" style="width:100%;"/>
            </div>
            <br>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=8" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Create Navbar" Value="8">
        <div class="ms-lg-3">
            <h2>Create ,Add Navbar Component</h2>
            <p>In your terminal, run the following command:</p>
            <CardCode Lang="Terminal">
                daf g vc Views/_Layouts/NavBar
            </CardCode>
            <p>The command will create a new view component file located in <code style="white-space: nowrap;">{App Name}/Views/_Layouts/NavBar.php</code>.</p>
            <p>Edit the file content that generated to this new content:</p>
            <CardCode Lang="PHP">
                <span class="braket"><</span><span class="tag-name">nav</span><span class="braket">></span>
                <br> &nbsp;&nbsp;
                <span class="braket"><</span><span class="tag-name">a</span>&nbsp;<span class="attr-name">href</span>=<span class="string-text">"/"</span><span class="braket">></span>Home<span class="braket"><</span><span class="braket">/</span><span class="tag-name">a</span><span class="braket">></span>
                <br> &nbsp;&nbsp;
                <span class="braket"><</span><span class="tag-name">a</span>&nbsp;<span class="attr-name">href</span>=<span class="string-text">"/About"</span><span class="braket">></span>About<span class="braket"><</span><span class="braket">/</span><span class="tag-name">a</span><span class="braket">></span>
                <br>
                <span class="braket"><</span><span class="braket">/</span><span class="tag-name">nav</span><span class="braket">></span>
            </CardCode>
            <p class="text-danger">Make sure the file is saved.!!!</p>
            <p>Update MainLayout Component file located in <code style="white-space: nowrap;">{App Name}/Views/_Layouts/MainLayout.php</code> to the following code.</p>
            <CardCode Lang="PHP">
                <span class="tag-name"><</span><span class="tag-name">?php</span>
                <br>
                <span class="comment-text">/**&nbsp;</span><span class="tag-name">@var</span>&nbsp;<span class="comment-text">DafCore\</span><span class="php-class">Component</span>&nbsp;<span class="attr-name">$this</span>&nbsp;<span class="comment-text">*/</span>
                <br>
                <span class="tag-name">$this</span>-><span class="php-function-name">Use</span>(<span class="string-text">"HelloWorld\Views\_Layouts\Navbar"</span>);
                <br>
                <span class="tag-name">?></span>
                <br>
                <br>
                <span class="braket"><</span><span class="tag-name">div</span>&nbsp;<span class="attr-name">class</span>=<span class="string-text">"container-md pt-3"</span><span class="braket">></span>
                <br>&nbsp;&nbsp;
                <span class="braket"><</span><span class="tag-name">Navbar</span>&nbsp;<span class="braket">/</span><span class="braket">></span>
                <br>&nbsp;&nbsp;
                <span class="tag-name"><</span><span class="tag-name">?=</span><span class="attr-name">$Body</span><span class="tag-name">?</span><span class="tag-name">></span>
                <br>
                <span class="braket"><</span><span class="braket">/</span><span class="tag-name">div</span><span class="braket">></span>
            </CardCode>
            <p class="text-danger">Make sure the file is saved.!!!</p>
            <p>This the content you need to see.</p>
            <div>
                <img src="/public/daf-logos/daf-new-project-add-navbar-screenshot.png" alt="Navbar screenshot" style="width:100%;"/>
            </div>
            <br>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=9" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Global Using" Value="9">
        <div class="ms-lg-5">
            <h2>Global Using</h2>
            <p>One file to add usage for certain components that need to be available in all your view components.</p>
            <p>In your terminal, run the following command:</p>
            <CardCode Lang="Terminal">
                daf g vc Views/_GlobalUsing
            </CardCode>
            <p>The command will create a new view component file located in <code style="white-space: nowrap;">{App Name}/Views/_GlobalUsing.php</code>.</p>
            <p>Update <code>_GlobalUsing</code> Component content to the following code.</p>
            <CardCode Lang="PHP">
                <span class="tag-name"><</span><span class="tag-name">?php</span>
                <br>
                <span class="comment-text">/**&nbsp;</span><span class="tag-name">@var</span>&nbsp;<span class="comment-text">DafCore\</span><span class="php-class">Component</span>&nbsp;<span class="attr-name">$this</span>&nbsp;<span class="comment-text">*/</span>
                <br>
                <span class="tag-name">$this</span>-><span class="php-function-name">Use</span>([
                <br>&nbsp;
                <span class="string-text">"HelloWorld\Views\Components\*"</span>,
                <br>&nbsp;
                <span class="string-text">"HelloWorld\Views\_Layouts\Navbar"</span>,
                <br>]);
            </CardCode>
            <p>The <code>*</code> mark is for include all the components in the folder.</p>
            <p>Update <code>MainLayout</code> Component content to the following code.</p>
            <CardCode Lang="PHP">
                <span class="braket"><</span><span class="tag-name">div</span>&nbsp;<span class="attr-name">class</span>=<span class="string-text">"container-md pt-3"</span><span class="braket">></span>
                <br>&nbsp;&nbsp;
                <span class="braket"><</span><span class="tag-name">Navbar</span>&nbsp;<span class="braket">/</span><span class="braket">></span>
                <br>&nbsp;&nbsp;
                <span class="tag-name"><</span><span class="tag-name">?=</span><span class="attr-name">$Body</span><span class="tag-name">?</span><span class="tag-name">></span>
                <br>
                <span class="braket"><</span><span class="braket">/</span><span class="tag-name">div</span><span class="braket">></span>
            </CardCode>
            <p>Update <code>Home</code> Component content to the following code.</p>
            <CardCode Lang="PHP">
                <span class="braket"><</span><span class="tag-name">h1</span><span class="braket">></span>Hello From About ViewComponent!<span class="braket"><</span><span class="braket">/</span><span class="tag-name">h1</span><span class="braket">></span>
                <br>
                <br>
                <span class="braket"><</span><span class="tag-name">NiceMsg</span>&nbsp;<span class="attr-name">Text</span>=<span class="string-text">"Hello From DAF Component :-)."</span>&nbsp;<span class="braket">/</span><span class="braket">></span>
            </CardCode>
            <br>
            <p>If everything looks good, select the Continue button below to go to the next step.</p>
            <a href="/GetStarted?tab=10" class="btn btn-lg btn-uniqe me-2">Continue</a>
            <a href="" class="btn btn-lg btn-outline-warning">I ran into issue</a>
        </div>
    </Tab>
    <Tab Title="Summary" Value="10">
        <div class="ms-lg-5">
            <h2>Summary</h2>
            <p>Congratulations! You have successfully created your first <span class="agbalumo-regular">DAF</span> app.</p>
            <p>In this tutorial, you have learned how to:</p>
            <ul>
                <li>Install <span class="agbalumo-regular">DAF</span></li>
                <li>Create a new <span class="agbalumo-regular">DAF</span> app</li>
                <li>Create, Add Components</li> 
                <li>Create, Add Minimal Routes</li> 
                <li>Hot Reload - <code>daf watch</code></li> 
                <li>Run the app</li>
            </ul>
        </div>
    </Tab>
</NavTabs>

<Script>
    setTimeout(()=>{
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    },230)
</Script>