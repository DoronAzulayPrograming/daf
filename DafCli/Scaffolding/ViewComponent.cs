namespace Daf.Scaffolding;

class ViewComponent : ProjectFile
{
    public ViewComponent(string fileName)
    {
        string name = fileName.Split('/').Last();
        string fileNamespace = fileName.Replace($"/{name}", string.Empty);
        FilePath = Path.Combine($"{fileName}.php".Split('/'));
        Namespace = fileNamespace.Replace('/', '\\');
        Content = $"""
        <?php
        /** @var DafCore\IComponent $this */
        ?>

        <h1>Hello From {name} ViewComponent!</h1>
        """;
    }
}
