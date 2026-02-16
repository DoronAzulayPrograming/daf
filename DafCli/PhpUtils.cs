using System.Runtime.InteropServices;

namespace Daf;

internal static class PhpUtils
{
    internal static bool TryGetPhpExecutable(out string phpPath)
    {
        phpPath = string.Empty;
        string? pathEnv = Environment.GetEnvironmentVariable("PATH");
        if (string.IsNullOrWhiteSpace(pathEnv))
            return false;

        string fileName = RuntimeInformation.IsOSPlatform(OSPlatform.Windows) ? "php.exe" : "php";

        foreach (string dir in pathEnv.Split(Path.PathSeparator))
        {
            if (string.IsNullOrWhiteSpace(dir))
                continue;

            string candidate = Path.Combine(dir.Trim(), fileName);
            if (File.Exists(candidate))
            {
                phpPath = candidate;
                return true;
            }
        }

        return false;
    }
}
