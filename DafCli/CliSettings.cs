using System.Text.Json;

namespace Daf;

class CliSettings
{
    public string? ModelsBaseFolder { get; set; }
    public string? ControllersBaseFolder { get; set; }
    public string? DbSetsBaseFolder { get; set; }
    public string? ApiControllersBaseFolder { get; set; }
    
    public Dictionary<string, string> Dependencies { get; set; } = new();

    public void Save()
    {
        string json = JsonSerializer.Serialize(this, new JsonSerializerOptions
        {
            WriteIndented = true
        });

        string filePath = Path.Combine(Directory.GetCurrentDirectory(), "cli.settings.json");
        File.WriteAllText(filePath, json);
        Console.WriteLine("cli.settings.json file was updated at {0}.", File.GetLastWriteTime(filePath));
    }
}
