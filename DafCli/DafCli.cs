using System.Diagnostics;
using System.IO.Compression;
using System.Net;
using System.Text.Json;
using System.Runtime.InteropServices;
using Daf.Scaffolding;

namespace Daf;

sealed class DafCli
{
    public static string ProjectName = "";
    private const string Version = "1.0.0";

    public DafCli(string projectName)
    {
        ProjectName = projectName;
    }

    private static readonly HttpClient PackageHttpClient = new(new HttpClientHandler
    {
        AutomaticDecompression = DecompressionMethods.All
    })
    {
        Timeout = TimeSpan.FromSeconds(120)
    };

    private readonly Dictionary<string, string> dependencyAliases = new()
    {
        {"core", "DafCore"},
        {"db", "DafDb"},
        {"firebase", "Firebase"},
        {"globals", "DafGlobals"}
    };

    private readonly Dictionary<string, string> dependencyUrls = new()
    {
        {"core", "https://daf.da-p.co.il/downloads/core/DafCore.zip"},
        {"db", "https://daf.da-p.co.il/downloads/db/DafDb.zip"},
        {"firebase", "https://daf.da-p.co.il/downloads/firebase/Firebase.zip"},
        {"globals", "https://daf.da-p.co.il/downloads/DafGlobals.zip"}
    };

    private readonly List<string> cliArgs = new();
    private CliSettings? cliSettings;

    public async Task RunAsync(string[] args)
    {
        if (args.Length < 1)
        {
            CliHelp.PrintGeneralHelp();
            return;
        }

        cliArgs.Clear();
        cliArgs.AddRange(args);

        string command = cliArgs.PopFirst();

        switch (command)
        {
            case "-h":
            case "--help":
            case "help":
                CliHelp.PrintGeneralHelp();
                return;
            case "-v":
            case "--version":
                Console.WriteLine(Version);
                return;
            case "kill-all":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintKillAllHelp();
                    return;
                }
                RunPKillPhp();
                return;
            case "new":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintNewHelp();
                    return;
                }
                await CreateNewProjectAsync();
                return;
            case "watch":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintWatchHelp();
                    return;
                }
                await WatchProjectAsync();
                return;
        }

        LoadProjFile();

        switch (command)
        {
            case "d":
            case "download":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintDownloadHelp();
                    return;
                }
                await DownloadLogicAsync();
                break;
            case "u":
            case "update":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintUpdateHelp();
                    return;
                }
                await UpdateLogicAsync();
                break;
            case "g":
            case "generate":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintGenerateHelp();
                    return;
                }
                GenerateLogic();
                break;
            case "migrations":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintMigrationsHelp();
                    return;
                }
                MigrationsLogic();
                break;
            default:
                Console.WriteLine("Invalid command.");
                break;
        }
    }

    private void MigrationsLogic()
    {
        if (cliArgs.Count < 1)
        {
            Console.WriteLine("Please provide migration command [add].");
            return;
        }

        string command = cliArgs.PopFirst();
        if (IsHelpFlag(command) || HasHelpFlag(cliArgs))
        {
            CliHelp.PrintMigrationsHelp();
            return;
        }

        var projectRoot = Directory.GetCurrentDirectory();
        switch (command)
        {
            case "add":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintMigrationsAddHelp();
                    return;
                }
                if (cliArgs.Count < 1)
                {
                    Console.WriteLine("Please provide migration name.");
                    return;
                }
                string migrationName = cliArgs.PopFirst();
                MigrationCommands.AddMigration(projectRoot, migrationName, ProjectName);
            break;
            case "migrate":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintMigrationsMigrateHelp();
                    return;
                }
                MigrationCommands.Migrate(projectRoot, ProjectName);
            break;
            case "rollback":
                if (HasHelpFlag(cliArgs))
                {
                    CliHelp.PrintMigrationsRollbackHelp();
                    return;
                }
                MigrationCommands.Rollback(projectRoot, ProjectName);
            break;
            default:
                Console.WriteLine("Invalid command.");
                break;
        }

    }

    private Task WatchProjectAsync()
    {
        string phpPortArg = cliArgs.PopFlagArg("-p", "--port") ?? "3000";
        string wsPortArg = cliArgs.PopFlagArg("-ws", "--ws-port") ?? "8181";

        int phpPortRequested = int.TryParse(phpPortArg, out var phpTmp) ? phpTmp : 3000;
        int wsPortRequested = int.TryParse(wsPortArg, out var wsTmp) ? wsTmp : 8181;

        int phpPort = PortHelper.GetAvailablePort(phpPortRequested);
        int wsPort = PortHelper.GetAvailablePort(wsPortRequested, exceptPort: phpPort);

        if (phpPort != phpPortRequested)
            Console.WriteLine($"[daf] Port {phpPortRequested} is in use, using {phpPort} for PHP server.");

        if (wsPort != wsPortRequested)
            Console.WriteLine($"[daf] Port {wsPortRequested} is in use, using {wsPort} for WebSocket server.");

        Dashboard.Print(ProjectName, phpPort, wsPort, Directory.GetCurrentDirectory());

        string openUrl = $"http://localhost:{phpPort}/";
        Watch("./", openUrl, phpPort, wsPort);
        return Task.CompletedTask;
    }

    private static void RunPKillPhp()
    {
        if (RuntimeInformation.IsOSPlatform(OSPlatform.Windows))
        {
            bool killedAny = false;
            try
            {
                foreach (var process in Process.GetProcessesByName("php"))
                {
                    try
                    {
                        process.Kill(true);
                        killedAny = true;
                    }
                    catch { }
                }
            }
            catch { }

            if (!killedAny)
            {
                try
                {
                    Process serverProcessWin = new();
                    serverProcessWin.StartInfo.FileName = "taskkill";
                    serverProcessWin.StartInfo.Arguments = "/IM php.exe /F";
                    serverProcessWin.StartInfo.UseShellExecute = false;
                    serverProcessWin.StartInfo.RedirectStandardOutput = true;
                    serverProcessWin.Start();
                }
                catch { }
            }
            return;
        }

        using Process serverProcess = new();
        serverProcess.StartInfo.FileName = "pkill";
        serverProcess.StartInfo.Arguments = "php";
        serverProcess.StartInfo.UseShellExecute = false;
        serverProcess.StartInfo.RedirectStandardOutput = true;
        serverProcess.Start();
    }

    private void GenerateLogic()
    {
        if (cliArgs.Count < 1)
        {
            Console.WriteLine("Please provide generate type or use --help flag for more information.");
            return;
        }

        string generateType = cliArgs.PopFirst();
        if (IsHelpFlag(generateType) || HasHelpFlag(cliArgs))
        {
            CliHelp.PrintGenerateHelp();
            return;
        }
        if (cliArgs.Count < 1)
        {
            Console.WriteLine("Please provide file name or use --help flag for more information.");
            return;
        }

        string fileName = cliArgs.PopFirst();

        switch (generateType)
        {
            case "cl":
            case "class":
                new Class(Path.Combine(ProjectName, fileName)).CreateFile();
                break;
            case "c":
            case "controller":
                new ControllerPhp(Path.Combine(ProjectName, cliSettings?.ControllersBaseFolder ?? string.Empty, fileName)).CreateFile();
                break;
            case "ac":
            case "api-controller":
                new ApiControllerPhp(Path.Combine(ProjectName, cliSettings?.ApiControllersBaseFolder ?? string.Empty, fileName)).CreateFile();
                break;
            case "m":
            case "model":
                new Model(Path.Combine(ProjectName, cliSettings?.ModelsBaseFolder ?? string.Empty, fileName)).CreateFile();
                break;
            case "vc":
            case "view-component":
                new ViewComponent(Path.Combine(ProjectName, "Views", fileName)).CreateFile();
                break;
            case "cc":
            case "class-component":
                new ClassComponent(Path.Combine(ProjectName, "Views", fileName)).CreateFile();
                break;
            case "ds":
            case "dbset":
                string model = cliArgs.PopFlagArg("-m", "--model") ?? string.Empty;
                if (string.IsNullOrEmpty(model))
                {
                    Console.WriteLine("Please provide model name or use --help flag for more information.");
                    return;
                }

                new DbSetPhp(
                    Path.Combine(ProjectName, cliSettings?.DbSetsBaseFolder ?? string.Empty, fileName),
                    ProjectName,
                    Path.Combine(ProjectName, cliSettings?.ModelsBaseFolder ?? string.Empty, model)
                ).CreateFile();
                break;
            default:
                Console.WriteLine("Invalid generate type use --help flag for more information.");
                break;
        }
    }

    private async Task CreateNewProjectAsync()
    {
        string projPath = Path.Combine(Directory.GetCurrentDirectory(), "cli.settings.json");
        if (File.Exists(projPath))
        {
            Console.WriteLine("Project already exists in this directory.");
            return;
        }

        const string downloadBaseUrl = "https://daf.da-p.co.il/downloads";
        string projectName = ProjectName;
        string template = cliArgs.PopFlagArg("-t", "--template") ?? "basic";

        CreateFolder(Path.Combine(Directory.GetCurrentDirectory(), projectName));
        string vendorPath = Path.Combine(Directory.GetCurrentDirectory(), "vendor");
        CreateFolder(vendorPath);

        if (template == "empty")
            CreateEmptyProject(projectName);
        else if (template == "basic")
            CreateBasicProject(projectName);

        bool autoloaderDownloaded = await DownloadAndExtractZipAsync(
            $"{downloadBaseUrl}/autoloader/autoloader.zip",
            vendorPath,
            "Autoloader"
        );

        bool coreDownloaded = await DownloadAndExtractZipAsync(
            $"{downloadBaseUrl}/core/DafCore.zip",
            vendorPath,
            "DafCore"
        );

        bool globalsDownloaded = await DownloadAndExtractZipAsync(
            $"{downloadBaseUrl}/globals/DafGlobals.zip",
            vendorPath,
            "DafGlobals"
        );

        if (!(autoloaderDownloaded && coreDownloaded && globalsDownloaded))
        {
            Console.WriteLine("One or more downloads failed.");
            return;
        }
    }

    private async Task DownloadLogicAsync()
    {
        if (cliArgs.Count < 1)
        {
            Console.WriteLine("Please provide package name or use --help flag for more information.");
            return;
        }

        string packageName = cliArgs.PopFirst();
        string packageFullName = GetDependencyFullName(packageName);
        if (string.IsNullOrEmpty(packageFullName))
        {
            Console.WriteLine("Package not found.");
            return;
        }

        if (cliSettings != null && cliSettings.Dependencies.ContainsKey(packageFullName))
        {
            Console.WriteLine("Package already exists in cli.settings.json file.");
            return;
        }

        string packageUrl = GetDependencyUrl(packageName);
        if (string.IsNullOrEmpty(packageUrl))
        {
            Console.WriteLine("Package url not found.");
            return;
        }

        bool success = await DownloadAndExtractZipAsync(
            packageUrl,
            Path.Combine(Directory.GetCurrentDirectory(), "vendor"),
            packageFullName
        );

        if (success)
        {
            if(cliSettings != null)
            {
                cliSettings.Dependencies.Add(packageFullName, "^");
                cliSettings.Save();
            }
        }
    }

    private async Task UpdateLogicAsync()
    {
        if (cliArgs.Count < 1)
        {
            Console.WriteLine("Please provide package name or use --help flag for more information.");
            return;
        }

        string packageName = cliArgs.PopFirst();
        string packageFullName = GetDependencyFullName(packageName);
        if (string.IsNullOrEmpty(packageFullName))
        {
            Console.WriteLine("Package not found.");
            return;
        }

        string packageUrl = GetDependencyUrl(packageName);
        if (string.IsNullOrEmpty(packageUrl))
        {
            Console.WriteLine("Package url not found.");
            return;
        }

        bool success = await DownloadAndExtractZipAsync(
            packageUrl,
            Path.Combine(Directory.GetCurrentDirectory(), "vendor"),
            packageFullName
        );

        if (success)
        {
            if(cliSettings != null)
            {
                cliSettings.Dependencies[packageFullName] = "^";
                cliSettings.Save();
            }
        }
    }

    
    private void LoadProjFile()
    {
        string fileName = "cli.settings.json";
        string path = Path.Combine(Directory.GetCurrentDirectory(), fileName);
        if (!File.Exists(path)) return;

        try
        {
            string projJson = File.ReadAllText(path);
            cliSettings = JsonSerializer.Deserialize<CliSettings>(projJson);
        }
        catch (Exception ex)
        {
            throw new Exception($"Error while reading {fileName} file.", ex);
        }
    }

    
    private static void Watch(string phpProjectPath, string openUrl, int port, int wsPort)
    {
        using var watcher = new PhpProjectWatcher(phpProjectPath, openUrl, port, wsPort);
        watcher.Start();
    }

    
    private static bool CreateFolder(string path)
    {
        if (Directory.Exists(path))
        {
            Console.WriteLine($"That path {path} exists already.");
            return false;
        }

        try
        {
            Directory.CreateDirectory(path);
            Console.WriteLine("The directory {1} was created successfully at {0}.", Directory.GetCreationTime(path), path);
            return true;
        }
        catch (Exception e)
        {
            Console.WriteLine("The process failed: {0}", e);
            return false;
        }
    }

    private void CreateEmptyProject(string projectName)
    {
        //new ApplicationEx(projectName).CreateFile();
        new IndexPhp(projectName).CreateFile();

        // new CliSettings
        // {
        //     ModelsBaseFolder = "",
        //     ControllersBaseFolder = "",
        //     DbSetsBaseFolder = "",
        //     ApiControllersBaseFolder = "",
        //     Dependencies = new Dictionary<string, string>
        //     {
        //         {"DafCore", "^"},
        //         {"DafGlobals", "^"}
        //     }
        // }.Save();
    }

    private void CreateBasicProject(string projectName)
    {
        new ApplicationEx(projectName).CreateFile();
        new IndexPhp(projectName, false).CreateFile();
        new _Host(projectName).CreateFile();
        new MainLayout(projectName).CreateFile();
        new HomePageCompoenent(projectName).CreateFile();
        new AppCss().CreateFile();

        // new CliSettings
        // {
        //     ModelsBaseFolder = "Models",
        //     ControllersBaseFolder = "Controllers",
        //     DbSetsBaseFolder = "Repositories",
        //     ApiControllersBaseFolder = "ApiControllers",
        //     Dependencies = new Dictionary<string, string>
        //     {
        //         {"DafCore", "^"},
        //         {"DafGlobals", "^"}
        //     }
        // }.Save();
    }


    private string GetDependencyFullName(string dependency)
    {
        return dependencyAliases.TryGetValue(dependency, out string? fullName)
            ? fullName
            : string.Empty;
    }

    private string GetDependencyUrl(string dependency)
    {
        return dependencyUrls.TryGetValue(dependency, out string? url)
            ? url
            : string.Empty;
    }


    private static async Task<bool> DownloadAndExtractZipAsync(string url, string extractPath, string packageName)
    {
        if (!Uri.TryCreate(url, UriKind.Absolute, out var uri))
        {
            Console.WriteLine($"Invalid url for {packageName}: {url}");
            return false;
        }

        if (!string.Equals(uri.Scheme, Uri.UriSchemeHttps, StringComparison.OrdinalIgnoreCase))
        {
            Console.WriteLine($"Refusing to download {packageName} because the url is not HTTPS: {url}");
            return false;
        }

        string tempZipPath = Path.Combine(Path.GetTempPath(), $"daf_pkg_{Guid.NewGuid():N}.zip");
        string tempExtractPath = Path.Combine(Path.GetTempPath(), $"daf_extract_{Guid.NewGuid():N}");

        try
        {
            Directory.CreateDirectory(tempExtractPath);

            using var response = await PackageHttpClient.GetAsync(uri, HttpCompletionOption.ResponseHeadersRead);
            response.EnsureSuccessStatusCode();

            await using (var fileStream = new FileStream(tempZipPath, FileMode.Create, FileAccess.Write, FileShare.None))
            {
                await response.Content.CopyToAsync(fileStream);
            }

            ZipFile.ExtractToDirectory(tempZipPath, tempExtractPath, true);
            CopyDirectory(tempExtractPath, extractPath, overwrite: true);

            Console.WriteLine($"The package {packageName} was downloaded successfully.");
            return true;
        }
        catch (Exception e)
        {
            Console.WriteLine($"The process failed while downloading {packageName}: {e.Message}");
            return false;
        }
        finally
        {
            SafeDeleteFile(tempZipPath);
            SafeDeleteDirectory(tempExtractPath);
        }
    }

    private static void CopyDirectory(string sourceDir, string destinationDir, bool overwrite)
    {
        Directory.CreateDirectory(destinationDir);

        foreach (string directory in Directory.GetDirectories(sourceDir, "*", SearchOption.AllDirectories))
        {
            string targetSubDir = Path.Combine(destinationDir, Path.GetRelativePath(sourceDir, directory));
            Directory.CreateDirectory(targetSubDir);
        }

        foreach (string file in Directory.GetFiles(sourceDir, "*", SearchOption.AllDirectories))
        {
            string relativePath = Path.GetRelativePath(sourceDir, file);
            string targetFile = Path.Combine(destinationDir, relativePath);
            Directory.CreateDirectory(Path.GetDirectoryName(targetFile) ?? destinationDir);
            File.Copy(file, targetFile, overwrite);
        }
    }

    private static void SafeDeleteFile(string path)
    {
        try
        {
            if (File.Exists(path))
            {
                File.Delete(path);
            }
        }
        catch
        {
        }
    }

    private static void SafeDeleteDirectory(string path)
    {
        try
        {
            if (Directory.Exists(path))
            {
                Directory.Delete(path, true);
            }
        }
        catch
        {
        }
    }

    private static bool HasHelpFlag(List<string> args)
    {
        foreach (var arg in args)
        {
            if (IsHelpFlag(arg))
                return true;
        }
        return false;
    }

    private static bool IsHelpFlag(string? arg)
    {
        return string.Equals(arg, "-h", StringComparison.OrdinalIgnoreCase)
            || string.Equals(arg, "--help", StringComparison.OrdinalIgnoreCase)
            || string.Equals(arg, "help", StringComparison.OrdinalIgnoreCase);
    }

}