<?php
/** @var DafCore\Component $this */
$this->Use("App\Views\_Layouts\NavBar");
?>

<div>
    <NavBar />
</div>

<div class="container-md pt-3">
    <?= $Body ?>
</div>