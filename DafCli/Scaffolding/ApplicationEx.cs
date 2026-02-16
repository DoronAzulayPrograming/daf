namespace Daf.Scaffolding;

class ApplicationEx : ProjectFile
{
    public ApplicationEx(string projectName)
    {
        const string name = "ApplicationEx";
        FilePath = Path.Combine(projectName, $"{name}.php");
        Namespace = projectName;
        Content = $$"""
        <?php
        namespace {{Namespace}};

        class {{name}} extends \DafCore\Application {
            // Add external functions here its clean
        }
        """;
    }
}
