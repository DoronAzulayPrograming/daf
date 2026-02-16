namespace Daf.Scaffolding;

class MainLayout : ProjectFile
{
    public MainLayout(string projectName)
    {
        FilePath = Path.Combine(projectName, "Views", "_Layouts", "MainLayout.php");
        Content = $"""
        <main>
            <?= $this->RenderChildContent() ?>
        </main>
        """;
    }
}
