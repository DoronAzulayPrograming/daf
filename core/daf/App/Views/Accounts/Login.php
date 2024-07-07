<?php
/** @var DafCore\Component $this */
/** @var DafCore\Request $req */
/** @var DafCore\Response $res */

$req = $this->Inject(\DafCore\Request::class);
$res = $this->Inject(\DafCore\Response::class);

$error = $req->GetQueryParams()['error']?? "";
if(!empty($error)) {
    $res->Status(DafCore\Response::HTTP_BAD_REQUEST);
}
?>

<PageTitle>DAF - Login</PageTitle>

<?php if(!empty($error)) { ?>
    <div class="alert alert-danger">
        <?php echo $error;?>
    </div>
<?php }?>

<!-- Simple HTML form for login /Accounts/Login -->
<form method="POST">
    <AntiForgeryToken />
    Username: <input type="text" name="Username"><br>
    Password: <input type="password" name="Password"><br>
    <input type="submit" value="Login">
</form>
