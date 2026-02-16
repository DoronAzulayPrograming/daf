namespace Daf;

static class CliArgsExtensions
{
    public static string PopFirst(this List<string> args)
    {
        if (args.Count == 0)
            return string.Empty;

        string value = args[0];
        args.RemoveAt(0);
        return value;
    }

    public static string? PopFlagArg(this List<string> args, params string[] aliases)
    {
        if (aliases == null || aliases.Length == 0)
            return null;

        foreach (string alias in aliases)
        {
            int index = args.FindIndex(a => a == alias);
            if (index >= 0)
            {
                args.RemoveAt(index);
                if (index < args.Count)
                {
                    string value = args[index];
                    args.RemoveAt(index);
                    return value;
                }

                return null;
            }
        }

        return null;
    }
}
