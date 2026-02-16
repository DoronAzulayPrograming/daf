namespace Daf.Scaffolding;

class DbSetPhp : ProjectFile
{
    public DbSetPhp(string fileName, string rootNamespace, string model)
    {
        string name = fileName.Split('/').Last();
        string fileNamespace = fileName.Replace($"/{name}", string.Empty);

        FilePath = Path.Combine($"{fileName}DbSet.php".Split('/'));
        Namespace = fileNamespace.Replace('/', '\\');

        string modelNamespace = $"{rootNamespace}\\{model.Replace('/', '\\')}";
        string modelShortName = model.Split('/').Last();

        Content = $$"""
        <?php
        namespace {{Namespace}};

        use DafDb\Query\DbSet;
        use DafDb\Attributes\Table;
        use {{modelNamespace}};

        #[Table(model: {{modelShortName}}::class)]
        class {{name}}DbSet extends DbSet
        {
            
        }
        """;
    }
}
