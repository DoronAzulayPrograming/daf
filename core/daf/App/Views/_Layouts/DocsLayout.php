<?php
/** @var DafCore\IComponent $this */
$this->Use("App\Views\_Layouts\NavBar");
$this->Use("App\Views\_Layouts\DocsNavBar");
?>

<HeadContent>
    <style>
        html,body{
            height: 100%;
        }
        .docs-page{
            display: grid;
            grid-template-areas:
            "top top"
            "sidebar  content";
            grid-template-rows: auto 1fr;
            grid-template-columns: auto 1fr;
            height: 100%;
        }
        .sidebar{
            grid-area: sidebar;
            padding: 1rem 2rem;
            height: 100%;
            overflow-y: auto;
        }
        .content{
            grid-area: content;
        }
        .top{
            grid-area: top;
        }
    </style>
</HeadContent>


<div class="docs-page">
    <div class="top">
        <NavBar />
    </div>
    <div class="d-none d-md-block sidebar">
         <DocsNavBar />
    </div>
    <div class="container-md pt-3 content">
        <h1 class="niceText display-5 fw-bold">Docs</h1>
        <Body />
    </div>
</div>