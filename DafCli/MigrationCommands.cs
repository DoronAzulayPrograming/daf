using System.Diagnostics;
using System.Text.RegularExpressions;

namespace Daf;

public static class MigrationCommands
{
    public static void Rollback(string projectRoot, string appFolder)
    {
        // 1. Find index.php at project root
        var indexPath = Path.Combine(projectRoot, "index.php");
        if (!File.Exists(indexPath))
        {
            Console.Error.WriteLine("index.php not found in project root.");
            return;
        }

        // 2. Read and transform content
        var original = File.ReadAllText(indexPath);
        var tempContent = InjectBuildOnlyAndRollbackCall(original, appFolder);

        var tempPath = Path.Combine(projectRoot, "index.__daf_migration_build.php");
        File.WriteAllText(tempPath, tempContent);

        try
        {
            // 3. Run PHP on the temp file
            RunPhp(projectRoot, $"index.__daf_migration_build.php");

            // 4. Done. The PHP side already created migration files.
        }
        finally
        {
            // 5. Clean up temp file
            if (File.Exists(tempPath))
                File.Delete(tempPath);
        }
        
    }
    public static void Migrate(string projectRoot, string appFolder)
    {
        // 1. Find index.php at project root
        var indexPath = Path.Combine(projectRoot, "index.php");
        if (!File.Exists(indexPath))
        {
            Console.Error.WriteLine("index.php not found in project root.");
            return;
        }

        // 2. Read and transform content
        var original = File.ReadAllText(indexPath);
        var tempContent = InjectBuildOnlyAndMigrateCall(original, appFolder);

        var tempPath = Path.Combine(projectRoot, "index.__daf_migration_build.php");
        File.WriteAllText(tempPath, tempContent);

        try
        {
            // 3. Run PHP on the temp file
            RunPhp(projectRoot, $"index.__daf_migration_build.php");

            // 4. Done. The PHP side already created migration files.
        }
        finally
        {
            // 5. Clean up temp file
            if (File.Exists(tempPath))
                File.Delete(tempPath);
        }
        
    }
    public static void AddMigration(string projectRoot, string migrationName, string appFolder)
    {
        // 1. Find index.php at project root
        var indexPath = Path.Combine(projectRoot, "index.php");
        if (!File.Exists(indexPath))
        {
            Console.Error.WriteLine("index.php not found in project root.");
            return;
        }

        // 2. Read and transform content
        var original = File.ReadAllText(indexPath);
        var tempContent = InjectBuildOnlyAndMigrationCall(original, migrationName, appFolder);

        var tempPath = Path.Combine(projectRoot, "index.__daf_migration_build.php");
        File.WriteAllText(tempPath, tempContent);

        try
        {
            // 3. Run PHP on the temp file
            RunPhp(projectRoot, $"index.__daf_migration_build.php");

            // 4. Done. The PHP side already created migration files.
        }
        finally
        {
            // 5. Clean up temp file
            if (File.Exists(tempPath))
                File.Delete(tempPath);
        }
    }

    private static string InsertBuilOnlyToCode(string original)
    {
        // 1. Insert Application::$BuildOnly = true; after the last "use" block
        var insertMarkerIndex = FindInsertPositionAfterAutoloader(original);

        var buildOnlyLine = "\n\n\\DafCore\\Application::$BuildOnly = true;\n\n";
        
        string withBuildOnly = original.Insert(insertMarkerIndex, buildOnlyLine);
        
        return withBuildOnly;
    }
    private static string InjectBuildOnlyAndRollbackCall(string original, string appFolder)
    {
        // 1. Insert Application::$BuildOnly = true; after the last "use" block
        string withBuildOnly = InsertBuilOnlyToCode(original);

        // 2. Append migration call at the end
        var migrateCall = @"

$appContext = \DafCore\ServicesProvidor::$DI->GetOne(AppContext::class);
$migrations = new \DafDb\Migrations\Migrations();
$migrations->Rollback($appContext,'"+appFolder+@"');
";

        return withBuildOnly.TrimEnd() + "\n" + migrateCall + "\n";
    }
    private static string InjectBuildOnlyAndMigrateCall(string original, string appFolder)
    {
        // 1. Insert Application::$BuildOnly = true; after the last "use" block
        string withBuildOnly = InsertBuilOnlyToCode(original);

        // 2. Append migration call at the end
        var migrateCall = @"

$appContext = \DafCore\ServicesProvidor::$DI->GetOne(AppContext::class);
$migrations = new \DafDb\Migrations\Migrations();
$migrations->Migrate($appContext,'"+appFolder+@"');
";

        return withBuildOnly.TrimEnd() + "\n" + migrateCall + "\n";
    }

    private static string InjectBuildOnlyAndMigrationCall(string original, string migrationName, string appFolder)
    {
        // 1. Insert Application::$BuildOnly = true; after the last "use" block
        string withBuildOnly = InsertBuilOnlyToCode(original);

        // 2. Append migration call at the end
        var migrationCall = @"

$appContext = \DafCore\ServicesProvidor::$DI->GetOne(AppContext::class);
$migrations = new \DafDb\Migrations\Migrations();
$migrations->Generate($appContext, '" + migrationName + "','"+appFolder+@"');
";

        return withBuildOnly.TrimEnd() + "\n" + migrationCall + "\n";
    }


    /// <summary>
    /// Finds position (index in string) just after
    /// require_once "./vendor/autoloader.php";
    /// </summary>
    private static int FindInsertPositionAfterAutoloader(string content)
    {
        // Matches:
        // require_once 'vendor/autoloader.php';
        // require_once "vendor/autoloader.php";
        // require_once './vendor/autoloader.php';
        // require_once "./vendor/autoloader.php";
        var pattern = @"require_once\s*['""](?:\./)?vendor/autoloader\.php['""]\s*;";

        var match = Regex.Match(content, pattern, RegexOptions.CultureInvariant);
        if (!match.Success) return -1;

        return match.Index + match.Length;
    }



    /// <summary>
    /// Finds position (index in string) just after the last "use" statement block at top of file.
    /// </summary>
    private static int FindInsertPositionAfterUseBlock(string content)
    {
        int lastPos = -1;
        int searchStart = 0;
        while (true)
        {
            int idx = content.IndexOf("use ", searchStart, StringComparison.Ordinal);
            if (idx == -1)
                break;

            // ensure it's not part of a comment, but we keep it simple here.
            int endOfLine = content.IndexOf('\n', idx);
            if (endOfLine == -1)
                endOfLine = content.Length;

            lastPos = endOfLine + 1;
            searchStart = endOfLine + 1;
        }

        return lastPos;
    }

    private static void RunPhp(string workingDir, string scriptFileName)
    {
        if (!PhpUtils.TryGetPhpExecutable(out var phpPath))
        {
            Console.Error.WriteLine("PHP was not found on PATH. Please install PHP and ensure it is available as 'php' in your terminal.");
            return;
        }

        var psi = new ProcessStartInfo
        {
            FileName = phpPath,
            Arguments = scriptFileName,
            WorkingDirectory = workingDir,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
            UseShellExecute = false,
            CreateNoWindow = true
        };

        using var process = Process.Start(psi);
        if (process == null)
            throw new Exception("Failed to start PHP process.");

        process.OutputDataReceived += (_, e) =>
        {
            if (e.Data != null)
                Console.WriteLine(e.Data);
        };
        process.ErrorDataReceived += (_, e) =>
        {
            if (e.Data != null)
                Console.Error.WriteLine(e.Data);
        };

        process.BeginOutputReadLine();
        process.BeginErrorReadLine();

        process.WaitForExit();

        if (process.ExitCode != 0)
            throw new Exception($"PHP exited with code {process.ExitCode}.");
    }

}
