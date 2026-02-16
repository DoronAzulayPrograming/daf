namespace Daf.Scaffolding;

class Class : ProjectFile
{
    public Class(string fileName)
    {
        string name = fileName.Split('/').Last();
        string fileNamespace = fileName.Replace($"/{name}", string.Empty);
        FilePath = Path.Combine($"{fileName}.php".Split('/'));
        Namespace = fileNamespace.Replace('/', '\\');
        Content = $$"""
        <?php
        namespace {{Namespace}};

        class {{name}} {
            public function __construct(){}

        }
        """;
    }
}
