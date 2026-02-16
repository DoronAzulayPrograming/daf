namespace Daf.Scaffolding;

class ApiControllerPhp : ProjectFile
{
    public ApiControllerPhp(string fileName)
    {
        string name = fileName.Split('/').Last();
        string fileNamespace = fileName.Replace($"/{name}", string.Empty);
        FilePath = Path.Combine($"{fileName}Controller.php".Split('/'));
        Namespace = fileNamespace.Replace('/', '\\');
        Content = $$"""
        <?php
        namespace {{Namespace}};
        use DafCore\Controllers\ApiController;
        use DafCore\Controllers\Attributes\Route;

        #[Route]
        class {{name}}Controller extends ApiController {
            
            public function __construct(){}
            
            
        }
        """;
    }
}
