namespace Daf.Scaffolding;

class _Host : ProjectFile
{
    public _Host(string projectName)
    {
        FilePath = Path.Combine(projectName, "Views", "_Layouts", "_Host.php");
        Content = """
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link href="/public/app.css" rel="stylesheet" />
            
            <HeadOutlet />
        </head>
        <body>
            
            <?= $this->RenderChildContent() ?>
            
            <DafJs />
            <ScriptsOutlet />
        </body>
        </html>
        """;
    }
}
