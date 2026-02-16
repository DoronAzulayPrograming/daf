namespace Daf.Scaffolding;

class IndexPhp : ProjectFile
{
    public IndexPhp(string fileNamespace, bool clean = true)
    {
        FilePath = "index.php";
        Namespace = fileNamespace.Replace('/', '\\');

        if (clean)
        {
            Content = $"""
            <?php
            namespace {Namespace};
            require_once 'vendor/autoloader.php';
            use DafCore\Application;

            $app = new Application();

            $app->Router->Get("/" , fn() => "<h1>Hello World!</h1>");

            $app->Run();
            """;
        }
        else
        {
            Content = $"""
            <?php
            namespace {Namespace};
            require_once 'vendor/autoloader.php';

            $app = new ApplicationEx();

            $app->Router->Get("/" , "Pages/Home");

            $app->Run();
            """;
        }
    }
}
