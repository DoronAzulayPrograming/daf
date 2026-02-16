using System.Diagnostics;
using Timer = System.Timers.Timer;

namespace Daf;

class PhpProjectWatcher : IDisposable
{
    private readonly int _wsServerPort;
    private readonly int _phpServerPort;
    private readonly string _phpProjectPath;
    private readonly string _openUrl;
    private FileSystemWatcher? _watcher;
    private readonly PhpServer _phpServer;
    private readonly LiveReloadServer _reloadServer;
    private readonly HashSet<string> _watchedExtensions = new(StringComparer.OrdinalIgnoreCase)
    {
        ".php",
        ".phar",
        ".html",
        ".css",
        ".js"
    };
    private readonly Timer _reloadDebounceTimer;
    private readonly object _reloadLock = new();
    private readonly CancellationTokenSource _shutdownCts = new();
    private bool _disposed;

    public PhpProjectWatcher(string phpProjectPath, string openUrl, int port, int wsPort)
    {
        _wsServerPort = wsPort;
        _phpServerPort = port;
        _phpProjectPath = phpProjectPath;
        _openUrl = openUrl;
        _phpServer = new PhpServer();
        _reloadServer = new LiveReloadServer(wsPort); // Start WebSocket server
        _reloadDebounceTimer = new Timer(200)
        {
            AutoReset = false
        };
        _reloadDebounceTimer.Elapsed += (_, _) => TriggerReload();
    }

    public void Start()
    {
        Console.CancelKeyPress += OnCancelKeyPress;
        AppDomain.CurrentDomain.ProcessExit += CurrentDomain_ProcessExit;

        _reloadServer.Start();
        StartWatching();

        if (!Console.IsInputRedirected)
        {
            _ = Task.Run(ListenForQuit);
        }

        _shutdownCts.Token.WaitHandle.WaitOne();

        Console.WriteLine("Shutting down...");
        Dispose();
    }

    private void ListenForQuit()
    {
        try
        {
            while (!_shutdownCts.IsCancellationRequested)
            {
                var key = Console.ReadKey(intercept: true);
                if (char.ToLowerInvariant(key.KeyChar) == 'q')
                {
                    RequestShutdown("Quit requested by user.");
                    return;
                }
            }
        }
        catch (InvalidOperationException)
        {
            // Input has been redirected; ignore key handling.
        }
    }

    private void OnCancelKeyPress(object? sender, ConsoleCancelEventArgs e)
    {
        e.Cancel = true; // don’t kill instantly
        RequestShutdown("Ctrl+C detected!");
    }

    void CurrentDomain_ProcessExit(object? sender, EventArgs e)
    {
        RequestShutdown("Process exiting...");
        Dispose();
    }

    public void StartWatching()
    {
        _phpServer.Start(_phpProjectPath, _phpServerPort, _wsServerPort);

        Thread.Sleep(220); // wait for the server to start

        // ✅ Open default system browser
        Process.Start(new ProcessStartInfo
        {
            FileName = _openUrl, // ex: "http://localhost:3000/"
            UseShellExecute = true
        });

        _watcher = new FileSystemWatcher(_phpProjectPath)
        {
            NotifyFilter = NotifyFilters.LastWrite | NotifyFilters.FileName | NotifyFilters.DirectoryName | NotifyFilters.Size,
            Filter = "*.*",
            IncludeSubdirectories = true,
            InternalBufferSize = 16 * 1024
        };

        _watcher.Changed += OnChanged;
        _watcher.Created += OnChanged;
        _watcher.Deleted += OnChanged;
        _watcher.Renamed += OnRenamed;
        _watcher.EnableRaisingEvents = true;
    }

    private bool IsInMigrationsFolder(string fullPath)
    {
        return fullPath
            .Split(Path.DirectorySeparatorChar, Path.AltDirectorySeparatorChar)
            .Any(p => p.Equals("Migrations", StringComparison.OrdinalIgnoreCase));
    }
    private bool IsInVendorStorageFolder(string fullPath)
    {
        return fullPath.Contains(Path.Combine("vendor", "storage"));
    }

    private void OnChanged(object source, FileSystemEventArgs e)
    {
        if (!ShouldHandle(e.FullPath) || IsInMigrationsFolder(e.FullPath) || IsInVendorStorageFolder(e.FullPath))
            return;
        else if(Path.GetFileName(e.FullPath) == "index.__daf_migration_build.php") return;

        Dashboard.Print(DafCli.ProjectName, _phpServerPort, _wsServerPort, Directory.GetCurrentDirectory());
        Console.WriteLine($"File: {e.FullPath} {e.ChangeType}");
        DebounceReload();
    }

    private void OnRenamed(object source, RenamedEventArgs e)
    {
        if ((!ShouldHandle(e.FullPath) && !ShouldHandle(e.OldFullPath)) || IsInMigrationsFolder(e.FullPath))
            return;

        Console.WriteLine($"File renamed: {e.OldFullPath} -> {e.FullPath}");
        Dashboard.Print(DafCli.ProjectName, _phpServerPort, _wsServerPort, Directory.GetCurrentDirectory());
        DebounceReload();
    }

    private bool ShouldHandle(string? path)
    {
        if (string.IsNullOrEmpty(path))
            return false;

        string extension = Path.GetExtension(path);
        return !string.IsNullOrEmpty(extension) && _watchedExtensions.Contains(extension);
    }

    private void DebounceReload()
    {
        lock (_reloadLock)
        {
            _reloadDebounceTimer.Stop();
            _reloadDebounceTimer.Start();
        }
    }

    private void TriggerReload()
    {
        if (_shutdownCts.IsCancellationRequested)
            return;

        _reloadServer?.NotifyReload();
    }

    private void RequestShutdown(string reason)
    {
        if (_shutdownCts.IsCancellationRequested)
            return;

        //Console.WriteLine(reason);
        _shutdownCts.Cancel();
    }

    public void Dispose()
    {
        if (_disposed)
            return;

        _disposed = true;

        _watcher?.Dispose();
        _reloadDebounceTimer?.Stop();
        _reloadDebounceTimer?.Dispose();
        _reloadServer?.Dispose();
        _phpServer?.Dispose();
        _shutdownCts.Cancel();
        _shutdownCts.Dispose();

        Console.CancelKeyPress -= OnCancelKeyPress;
        AppDomain.CurrentDomain.ProcessExit -= CurrentDomain_ProcessExit;

        //Console.WriteLine("PhpProjectWatcher disposed.");
    }

}

public class PhpServer : IDisposable
{
    private DateTime _lastPhpEngineErrorAt = DateTime.MinValue;
    private string? _lastPhpEngineErrorKey = null;

    private Process? _serverProcess;
    private string? _prependFilePath;

    public void Start(string phpProjectPath, int port, int wsPort)
    {
        phpProjectPath = Path.GetFullPath(phpProjectPath);

        string vendorPath = Path.Combine(phpProjectPath, "vendor", "storage");
        Directory.CreateDirectory(vendorPath);

        _prependFilePath = Path.Combine(vendorPath, "__daf_livereload_prepend.php");

        File.WriteAllText(_prependFilePath, $$"""
        <?php
        ob_start(function ($buffer) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (stripos($accept, 'text/html') === false) {
                return $buffer;
            }

            $script = <<<HTML
        <script>
        (function () {
            try {
                const socket = new WebSocket("ws://127.0.0.1:{{wsPort}}");
                socket.onmessage = (event) => {
                    if (event.data === "reload") {
                        window.location.reload();
                    }
                };
            } catch (e) {
                console.error("[Daf LiveReload] WebSocket error", e);
            }
        })();
        </script>
        HTML;

            if (stripos($buffer, '</body>') !== false) {
                return preg_replace('~</body>~i', $script . '</body>', $buffer, 1);
            }

            return $buffer . $script;
        });
        """);

        // Start PHP built-in server
        _serverProcess = new Process();

        string prependArg = $"-d auto_prepend_file=\"{_prependFilePath}\"";

        if (!PhpUtils.TryGetPhpExecutable(out var phpPath))
        {
            Console.Error.WriteLine("PHP was not found on PATH. Please install PHP and ensure it is available as 'php' in your terminal.");
            return;
        }

        _serverProcess.StartInfo.FileName = phpPath;
        _serverProcess.StartInfo.Arguments =
            $"{prependArg} -S localhost:{port} -t \"{phpProjectPath}\"";

        _serverProcess.StartInfo.UseShellExecute = false;
        _serverProcess.StartInfo.RedirectStandardOutput = true;
        _serverProcess.StartInfo.RedirectStandardError = true;

        // ✔ Attach event handlers to show PHP logs in your CLI
        _serverProcess.OutputDataReceived += (sender, e) =>
        {
            if (!string.IsNullOrEmpty(e.Data))
            {
                Console.ForegroundColor = ConsoleColor.DarkGray;
                Console.WriteLine($"[PHP] {e.Data}");
                Console.ResetColor();
            }
        };

        _serverProcess.ErrorDataReceived += (sender, e) =>
        {
            if (string.IsNullOrWhiteSpace(e.Data))
                return;

            var raw = e.Data.Trim();
            var line = StripPhpServerPrefix(raw);

            // Ignore noisy connection chatter
            if (line.Equals("Accepted", StringComparison.OrdinalIgnoreCase)) return;
            if (line.Equals("Closing", StringComparison.OrdinalIgnoreCase)) return;

            // 1) Detect PHP engine errors (parse/fatal/warning/notice)
            if (IsPhpEngineError(line, out var severity))
            {
                // remember for a short window, so we can suppress duplicated 500 access log
                _lastPhpEngineErrorAt = DateTime.UtcNow;
                _lastPhpEngineErrorKey = BuildErrorKey(ExtractCoreMessage(line));

                PrintPhp(severity, line);
                return;
            }

            // 2) Detect access log lines like: "[500]: GET / - message"
            if (TryParseAccessLog(line, out var status, out var method, out var path, out var extra))
            {
                // Ignore static assets (only for successful requests)
                if (status < 400 && IsStaticAsset(path)) return;

                // If we recently printed a PHP engine error, suppress the duplicated "[500]: GET / - same error"
                if (status >= 500 && !string.IsNullOrWhiteSpace(extra))
                {
                    var recent = (DateTime.UtcNow - _lastPhpEngineErrorAt) < TimeSpan.FromSeconds(2);
                    if (recent && _lastPhpEngineErrorKey != null && BuildErrorKey(extra) == _lastPhpEngineErrorKey)
                    {
                        // Keep a clean request line, but drop the repeated error text
                        PrintRequest(status, method, path, null);
                        return;
                    }
                }

                PrintRequest(status, method, path, extra);
                return;
            }

            // 3) Any other stderr line
            PrintPhp("log", line);
        };




        _serverProcess.Start();

        // Start reading streams
        _serverProcess.BeginOutputReadLine();
        _serverProcess.BeginErrorReadLine();
    }



    
    private static string StripPhpServerPrefix(string line)
    {
        // Input example:
        // [Wed Feb  4 08:41:13 2026] [::1]:63851 [200]: GET /
        // We want: [200]: GET /
        int idx = line.IndexOf("] [", StringComparison.Ordinal);
        if (idx < 0) return line;

        string rest = line[(idx + 3)..];           // ::1]:63851 [200]: GET /
        int space = rest.IndexOf(' ');
        if (space < 0) return rest;

        return rest[(space + 1)..];                // [200]: GET /
    }

    private static bool IsStaticAsset(string path)
    {
        if (string.IsNullOrEmpty(path)) return false;

        string p = path.ToLowerInvariant();
        return p.EndsWith(".css") || p.EndsWith(".js") || p.EndsWith(".png") ||
            p.EndsWith(".jpg") || p.EndsWith(".jpeg") || p.EndsWith(".gif") ||
            p.EndsWith(".svg") || p.EndsWith(".ico") || p.EndsWith(".woff") ||
            p.EndsWith(".woff2") || p.EndsWith(".ttf") || p.EndsWith(".map");
    }

    private static bool IsPhpEngineError(string line, out string severity)
    {
        severity = "error";

        if (line.Contains("PHP Parse error:", StringComparison.OrdinalIgnoreCase)) { severity = "parse"; return true; }
        if (line.Contains("PHP Fatal error:", StringComparison.OrdinalIgnoreCase)) { severity = "fatal"; return true; }
        if (line.Contains("PHP Warning:", StringComparison.OrdinalIgnoreCase))    { severity = "warning"; return true; }
        if (line.Contains("PHP Notice:", StringComparison.OrdinalIgnoreCase))     { severity = "notice"; return true; }
        if (line.Contains("PHP Deprecated:", StringComparison.OrdinalIgnoreCase)) { severity = "deprecated"; return true; }

        return false;
    }

    private static string BuildErrorKey(string text)
    {
        // Normalize to compare "syntax error..." parts reliably
        // Keep it simple: lowercase + remove paths (very common duplicates)
        var t = text.ToLowerInvariant();

        // remove long absolute paths to avoid mismatches
        // "/Users/xxx/..." -> "<path>"
        t = System.Text.RegularExpressions.Regex.Replace(t, @"/[^ ]+", "<path>");

        // collapse whitespace
        t = System.Text.RegularExpressions.Regex.Replace(t, @"\s+", " ").Trim();

        return t;
    }
    private static string ExtractCoreMessage(string text)
    {
        // Remove leading timestamp if exists: "[Wed Feb  4 09:50:06 2026] "
        if (text.StartsWith("[", StringComparison.Ordinal))
        {
            int close = text.IndexOf("] ", StringComparison.Ordinal);
            if (close > 0)
                text = text[(close + 2)..];
        }

        // Remove "PHP Parse error:", "PHP Fatal error:", etc.
        int colon = text.IndexOf(':');
        if (colon > 0 && text.Contains("PHP ", StringComparison.OrdinalIgnoreCase))
            text = text[(colon + 1)..];

        text = text.Trim();

        // Collapse spaces
        text = System.Text.RegularExpressions.Regex.Replace(text, @"\s+", " ").Trim();

        return text;
    }

    private static bool TryParseAccessLog(string line, out int status, out string method, out string path, out string? extra)
    {
        // Examples:
        // [200]: GET /
        // [404]: GET /Users
        // [500]: GET / - syntax error, unexpected ...
        status = 0;
        method = "";
        path = "";
        extra = null;

        if (!line.StartsWith("[", StringComparison.Ordinal)) return false;

        int close = line.IndexOf("]:", StringComparison.Ordinal);
        if (close < 0) return false;

        var statusStr = line.Substring(1, close - 1);
        if (!int.TryParse(statusStr, out status)) return false;

        var rest = line.Substring(close + 2).Trim(); // "GET / - msg"
        var parts = rest.Split(' ', 3, StringSplitOptions.RemoveEmptyEntries);
        if (parts.Length < 2) return false;

        method = parts[0];
        path = parts[1];

        if (parts.Length == 3)
        {
            var tail = parts[2].Trim();
            if (tail.StartsWith("-", StringComparison.Ordinal))
                tail = tail.TrimStart('-').Trim();
            if (!string.IsNullOrEmpty(tail))
                extra = tail;
        }

        return true;
    }

    private static void PrintRequest(int status, string method, string path, string? extra)
    {
        Console.ForegroundColor =
            status >= 500 ? ConsoleColor.Red :
            status >= 400 ? ConsoleColor.Yellow :
            ConsoleColor.DarkGray;

        if (string.IsNullOrWhiteSpace(extra))
            Console.WriteLine($"[PHP] [{status}] {method} {path}");
        else
            Console.WriteLine($"[PHP] [{status}] {method} {path} - {extra}");

        Console.ResetColor();
    }

    private static void PrintPhp(string severity, string line)
    {
        Console.ForegroundColor = severity switch
        {
            "warning" => ConsoleColor.Yellow,
            "notice" => ConsoleColor.DarkGray,
            "deprecated" => ConsoleColor.DarkGray,
            "parse" => ConsoleColor.Red,
            "fatal" => ConsoleColor.Red,
            _ => ConsoleColor.DarkGray
        };

        var tag = severity switch
        {
            "warning" => "[PHP-Warning]",
            "notice" => "[PHP-Notice]",
            "deprecated" => "[PHP-Deprecated]",
            "parse" => "[PHP-Parse]",
            "fatal" => "[PHP-Fatal]",
            _ => "[PHP]"
        };

        Console.WriteLine($"{tag} {line}");
        Console.ResetColor();
    }

    


    public void Stop()
    {
        try
        {
            if (_serverProcess != null && !_serverProcess.HasExited)
            {
                //Console.WriteLine("Stopping PHP server...");
                _serverProcess.Kill(true);
            }
        }
        catch { }
        finally
        {
            _serverProcess = null;
        }
    }

    private void DeletePrependFile()
    {
        if (!string.IsNullOrEmpty(_prependFilePath) && File.Exists(_prependFilePath))
        {
            try
            {
                File.Delete(_prependFilePath);
                //Console.WriteLine("Deleted __daf_livereload_prepend.php");
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Could not delete __daf_livereload_prepend.php: {ex.Message}");
            }
        }
    }

    public void Dispose()
    {
        Stop();
        DeletePrependFile();
        //Console.WriteLine("Php server disposed.");
    }
}
