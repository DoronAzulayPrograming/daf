namespace Daf;

public static class Dashboard
{
    private const int MinInnerWidth = 50;

    public static void Print(string projectName, int phpPort, int wsPort, string rootPath)
    {
        string phpUrl = $"http://localhost:{phpPort}";
        string wsUrl  = $"ws://127.0.0.1:{wsPort}";

        if (Console.IsOutputRedirected)
        {
            PrintPlain(projectName, rootPath, phpUrl, wsUrl);
            return;
        }

        Console.Clear();
        PrintDecorated(projectName, rootPath, phpUrl, wsUrl);
    }

    private static void PrintPlain(string projectName, string rootPath, string phpUrl, string wsUrl)
    {
        Console.WriteLine("daf watch");
        Console.WriteLine($"Project: {projectName}");
        Console.WriteLine($"Root:    {rootPath}");
        Console.WriteLine($"PHP Server:    {phpUrl}");
        Console.WriteLine($"LiveReload WS: {wsUrl}");
        Console.WriteLine("Press 'q' to quit. Ctrl+C also works.");
        Console.WriteLine();
    }

    private static void PrintDecorated(string projectName, string rootPath, string phpUrl, string wsUrl)
    {
        string title = "daf watch";
        string projectLine = $"Project: {projectName}";
        string rootLine = $"Root:    {rootPath}";
        string phpLine = $"PHP Server:     {phpUrl}";
        string wsLine = $"LiveReload WS:  {wsUrl}";
        string hintLine = "Press 'q' to quit. Ctrl+C also works.";

        int innerWidth = Math.Max(MinInnerWidth, new[]
        {
            title.Length,
            projectLine.Length,
            rootLine.Length,
            phpLine.Length,
            wsLine.Length,
            hintLine.Length
        }.Max());

        string line = new string('─', innerWidth);

        Console.ForegroundColor = ConsoleColor.Cyan;
        Console.WriteLine($"┌{line}┐");
        Console.WriteLine(FormatLine(Center(title, innerWidth), innerWidth));
        Console.WriteLine($"├{line}┤");

        Console.ResetColor();
        Console.ForegroundColor = ConsoleColor.Yellow;
        Console.WriteLine(FormatLine(projectLine, innerWidth));
        Console.WriteLine(FormatLine(rootLine, innerWidth));
        Console.WriteLine(FormatLine(string.Empty, innerWidth));

        Console.ForegroundColor = ConsoleColor.Green;
        Console.WriteLine(FormatLine(phpLine, innerWidth));
        Console.ForegroundColor = ConsoleColor.Magenta;
        Console.WriteLine(FormatLine(wsLine, innerWidth));

        Console.ResetColor();
        Console.WriteLine($"├{line}┤");

        Console.ForegroundColor = ConsoleColor.DarkGray;
        Console.WriteLine(FormatLine(hintLine, innerWidth));

        Console.ResetColor();
        Console.WriteLine($"└{line}┘");
        Console.WriteLine();
    }

    private static string FormatLine(string text, int innerWidth)
    {
        if (text.Length > innerWidth)
            text = text.Substring(0, innerWidth);

        return $"│{text.PadRight(innerWidth)}│";
    }

    private static string Center(string text, int innerWidth)
    {
        if (text.Length >= innerWidth)
            return text;

        int left = (innerWidth - text.Length) / 2;
        return text.PadLeft(left + text.Length).PadRight(innerWidth);
    }
}
