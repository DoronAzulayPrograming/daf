namespace Daf;

class Program
{
    static async Task Main(string[] args)
    {   
        DirectoryInfo directoryInfo = new DirectoryInfo(Directory.GetCurrentDirectory());
        string projectName = directoryInfo.Name;
        
        await new DafCli(projectName).RunAsync(args);
    }

}

