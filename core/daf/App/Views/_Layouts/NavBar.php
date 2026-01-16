<?php
/** @var DafCore\IComponent $this */
/** @var DafCore\IViewManager $vm */
$vm = $this->Inject(\DafCore\IViewManager::class);

?>
<HeadContent>
    <style>
        .navbar-brand{
            color:rgba(246,246,246,.6);
        }
        .navbar-brand.active{
            color:white;
        }
    </style>
</HeadContent>

<nav class="navbar navbar-expand-md bg-body-tertiary">
  <div class="container-lg">
    <?php if($vm->GetLayout() === "DocsLayout") {?>
        <button class="d-md-none btn btn-primary">+</button>
    <?php }?>
    <NavLink class="navbar-brand agbalumo-regular" href="/">
        DAF
    </NavLink>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-lg-0">
        <li class="nav-item">
            <NavLink class="nav-link" href="/GetStarted">GetStarted</NavLink>
        </li>
        <li class="nav-item">
            <NavLink class="nav-link" href="/Docs">Docs</NavLink>
        </li>
        <li class="nav-item">
            <NavLink class="nav-link" href="/Users">Users</NavLink>
        </li>
      </ul>
      <div class="d-flex">
        <ul class="navbar-nav mb-lg-0">
            <AuthorizedView> 
                <Authorized>
                    <li class="nav-item">
                        <form class="d-inline" action="/Accounts/Logout" method="POST">
                            <input class="nav-link" type="submit" value="Logout" />
                        </form>
                    </li>
                </Authorized>
                <NotAuthorized>
                    <li class="nav-item">
                        <NavLink class="nav-link" href="/Accounts/Login">Login</NavLink>
                    </li>
                </NotAuthorized>
            </AuthorizedView>
        </ul>
      </div>
  </div>
</nav>