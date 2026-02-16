namespace Daf.Scaffolding;

class HomePageCompoenent : ProjectFile
{
    public HomePageCompoenent(string projectName)
    {
        FilePath = Path.Combine(projectName, "Views", "Pages", "Home.php");
        Content = $"""
        <h1>Hello World!</h1>
        """;
    }
}
