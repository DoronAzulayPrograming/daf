namespace Daf.Scaffolding;

class ControllerPhp : ProjectFile
{
    public ControllerPhp(string fileName)
    {
        string name = fileName.Split('/').Last();
        string fileNamespace = fileName.Replace($"/{name}", string.Empty);
        FilePath = Path.Combine($"{fileName}Controller.php".Split('/'));
        Namespace = fileNamespace.Replace('/', '\\');
        Content = $$"""
        <?php
        namespace {{Namespace}};
        use DafCore\Controllers\Controller;
        use DafCore\Controllers\Attributes\Route;

        #[Route]
        class {{name}}Controller extends Controller {
            
            public function __construct(){}
            
            
        }
        """;
    }
}
