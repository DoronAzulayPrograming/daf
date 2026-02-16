namespace Daf;

internal static class CliHelp
{
    internal static void PrintGeneralHelp()
    {
        Console.WriteLine("daf - CLI for Daf projects");
        Console.WriteLine();
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf <command> [options]");
        Console.WriteLine();
        Console.WriteLine("Commands:");
        Console.WriteLine("  new            Create a new project");
        Console.WriteLine("  watch          Start PHP server + live reload");
        Console.WriteLine("  migrations     Manage database migrations");
        Console.WriteLine("  generate       Generate files (class/controller/etc)");
        Console.WriteLine("  download       Download a dependency");
        Console.WriteLine("  update         Update a dependency");
        Console.WriteLine("  kill-all       Kill running PHP server processes");
        Console.WriteLine("  --version      Print version");
        Console.WriteLine();
        Console.WriteLine("Examples:");
        Console.WriteLine("  daf new --help");
        Console.WriteLine("  daf watch --help");
        Console.WriteLine("  daf migrations --help");
        Console.WriteLine("  daf migrations add --help");
    }

    internal static void PrintNewHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf new [-t|--template <basic|empty>]");
        Console.WriteLine();
        Console.WriteLine("Examples:");
        Console.WriteLine("  daf new");
        Console.WriteLine("  daf new --template basic");
    }

    internal static void PrintWatchHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf watch [-p|--port <phpPort>] [-ws|--ws-port <wsPort>]");
        Console.WriteLine();
        Console.WriteLine("Examples:");
        Console.WriteLine("  daf watch");
        Console.WriteLine("  daf watch -p 3001 -ws 8182");
    }

    internal static void PrintKillAllHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf kill-all");
        Console.WriteLine();
        Console.WriteLine("Description:");
        Console.WriteLine("  Stops any running PHP server processes started by Daf.");
    }

    internal static void PrintGenerateHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf generate <type> <name> [options]");
        Console.WriteLine();
        Console.WriteLine("Types:");
        Console.WriteLine("  model | m");
        Console.WriteLine("  class | cl");
        Console.WriteLine("  controller | c");
        Console.WriteLine("  api-controller | ac");
        Console.WriteLine("  view-component | vc");
        Console.WriteLine("  class-component | cc");
        Console.WriteLine("  dbset | ds   -m|--model <ModelName>");
        Console.WriteLine();
        Console.WriteLine("Examples:");
        Console.WriteLine("  daf g c HomeController");
        Console.WriteLine("  daf g m User -v true");
        Console.WriteLine("  daf g ds UserRepo -m User");
    }

    internal static void PrintDownloadHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf download <package>");
        Console.WriteLine();
        Console.WriteLine("Packages:");
        Console.WriteLine("  core | db | firebase | globals");
        Console.WriteLine();
        Console.WriteLine("Example:");
        Console.WriteLine("  daf download core");
    }

    internal static void PrintUpdateHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf update <package>");
        Console.WriteLine();
        Console.WriteLine("Packages:");
        Console.WriteLine("  core | db | firebase | globals");
        Console.WriteLine();
        Console.WriteLine("Example:");
        Console.WriteLine("  daf update core");
    }

    internal static void PrintMigrationsHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf migrations <command> [options]");
        Console.WriteLine();
        Console.WriteLine("Commands:");
        Console.WriteLine("  add <name>       Generate a new migration");
        Console.WriteLine("  migrate          Run pending migrations");
        Console.WriteLine("  rollback         Roll back last migration");
        Console.WriteLine();
        Console.WriteLine("Examples:");
        Console.WriteLine("  daf migrations add CreateUsersTable");
        Console.WriteLine("  daf migrations migrate");
        Console.WriteLine("  daf migrations rollback");
    }

    internal static void PrintMigrationsAddHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf migrations add <name>");
        Console.WriteLine();
        Console.WriteLine("Example:");
        Console.WriteLine("  daf migrations add CreateUsersTable");
    }

    internal static void PrintMigrationsMigrateHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf migrations migrate");
    }

    internal static void PrintMigrationsRollbackHelp()
    {
        Console.WriteLine("Usage:");
        Console.WriteLine("  daf migrations rollback");
    }
}