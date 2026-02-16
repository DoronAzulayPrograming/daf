namespace Daf.Scaffolding;

abstract class ProjectFile
{
    public string FilePath { get; protected set; } = string.Empty;
    public string Namespace { get; protected set; } = string.Empty;
    public string Content { get; protected set; } = string.Empty;

    public void CreateFile()
    {
        var fullPath = Path.Combine(Directory.GetCurrentDirectory(), FilePath);

        // Pretty path (relative to project root)
        var prettyPath = Path.GetRelativePath(
            Directory.GetCurrentDirectory(), 
            fullPath
        );

        // Optional: ensure it starts with "/ProjectName"
        prettyPath = "/" + prettyPath.Replace("\\", "/");

        if (File.Exists(fullPath))
        {
            Console.WriteLine($"The file {prettyPath} already exists.");
            return;
        }

        string? directory = Path.GetDirectoryName(fullPath);
        if (!string.IsNullOrEmpty(directory) && !Directory.Exists(directory))
        {
            Directory.CreateDirectory(directory);
            var prettyDirectoryPath =  Path.GetRelativePath(
                Directory.GetCurrentDirectory(), 
                directory
            );
            
            prettyDirectoryPath = "/" + prettyDirectoryPath.Replace("\\", "/");

            Console.WriteLine("The directory {1} was created successfully at {0}.", Directory.GetCreationTime(directory), prettyDirectoryPath);
        }

        try
        {
            File.WriteAllText(fullPath, Content);
            Console.WriteLine("The file {1} was created successfully at {0}.", File.GetCreationTime(fullPath), prettyPath);
        }
        catch (Exception e)
        {
            Console.WriteLine("The process failed: {0}", e.ToString());
        }
    }
}
