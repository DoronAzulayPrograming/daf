namespace Daf;

using Fleck;

public class LiveReloadServer : IDisposable
{
    int port;
    private WebSocketServer server;
    private List<IWebSocketConnection> clients = new();

    public LiveReloadServer(int port)
    {
        this.port = port;
        FleckLog.Level = LogLevel.Warn; // Reduce console output
        server = new WebSocketServer($"ws://127.0.0.1:{port}");
    }

    public void Start()
    {
        server.Start(socket =>
        {
            socket.OnOpen = () =>
            {
                clients.Add(socket);
                Console.WriteLine("Browser connected for live reload.");
            };
            socket.OnClose = () => clients.Remove(socket);
        });

        //Console.WriteLine($"LiveReload WebSocket server started on ws://127.0.0.1:{port}");
    }

    public void NotifyReload()
    {
        foreach (var socket in clients)
        {
            socket.Send("reload");
        }
    }

    public void Dispose()
    {
        foreach (var client in clients.ToList())
        {
            client.Close();
        }
        clients.Clear();

        server.Dispose(); // Stops the Fleck WebSocket server
        //Console.WriteLine("LiveReload server disposed.");
    }

}
