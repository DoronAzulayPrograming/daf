namespace Daf.Scaffolding;

class Model : ProjectFile
{
    public Model(string fileName)
    {
        string name = fileName.Split('/').Last();
        string fileNamespace = fileName.Replace($"/{name}", string.Empty);
        FilePath = Path.Combine($"{fileName}.php".Split('/'));
        Namespace = fileNamespace.Replace('/', '\\');
        Content = $$"""
        <?php
        namespace {{Namespace}};
        use DafCore\AutoConstruct;
        
        class {{name}} extends AutoConstruct {
            
        }
        """;
    }
}
