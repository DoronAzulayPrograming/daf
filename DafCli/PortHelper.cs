using System.Net;
using System.Net.Sockets;

namespace Daf;

static class PortHelper
{
    public static bool IsPortFree(int port)
    {
        TcpListener? v4 = null;
        TcpListener? v6 = null;

        try
        {
            // Try IPv4
            v4 = new TcpListener(IPAddress.Loopback, port);
            v4.Start();

            // Try IPv6 (loopback)
            v6 = new TcpListener(IPAddress.IPv6Loopback, port);
            v6.Server.DualMode = true; // allow dual mode where supported
            v6.Start();

            return true; // if both succeed, we consider it free
        }
        catch
        {
            return false;
        }
        finally
        {
            try { v4?.Stop(); } catch { }
            try { v6?.Stop(); } catch { }
        }
    }

    public static int GetAvailablePort(int preferredPort, int? exceptPort = null)
    {
        if (preferredPort == exceptPort)
            preferredPort++;

        if (preferredPort > 0 && preferredPort <= 65535 && IsPortFree(preferredPort))
            return preferredPort;

        // Fallback: ask OS for a free port
        using var listener = new TcpListener(IPAddress.Loopback, 0);
        listener.Start();
        int port = ((IPEndPoint)listener.LocalEndpoint).Port;
        listener.Stop();

        if (port == exceptPort)
            return GetAvailablePort(preferredPort + 1, exceptPort);

        return port;
    }
}