using System.Runtime.InteropServices;

namespace OndPrintHelper;

/// <summary>
/// Mengirim byte apa adanya ke printer lewat Windows spooler, tanpa lewat
/// GDI/rasterisasi sama sekali — mode "RAW" bawaan Windows. Ini pola resmi
/// dari Microsoft (dulu KB Q322091), dipakai luas untuk printer dot-matrix
/// yang menerima perintah ESC/P langsung.
/// </summary>
internal static class RawPrinter
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    private struct DOCINFOW
    {
        public string pDocName;
        public string? pOutputFile;
        public string pDataType;
    }

    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true, ExactSpelling = true)]
    private static extern bool OpenPrinterW(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);

    [DllImport("winspool.drv", SetLastError = true, ExactSpelling = true)]
    private static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true, ExactSpelling = true)]
    private static extern bool StartDocPrinterW(IntPtr hPrinter, int level, ref DOCINFOW pDocInfo);

    [DllImport("winspool.drv", SetLastError = true, ExactSpelling = true)]
    private static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true, ExactSpelling = true)]
    private static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true, ExactSpelling = true)]
    private static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.drv", SetLastError = true, ExactSpelling = true)]
    private static extern bool WritePrinter(IntPtr hPrinter, byte[] pBytes, int dwCount, out int dwWritten);

    /// <summary>Melempar exception dengan pesan jelas kalau gagal di titik mana pun.</summary>
    public static void KirimMentah(string namaPrinter, byte[] data, string namaDokumen)
    {
        if (!OpenPrinterW(namaPrinter, out var hPrinter, IntPtr.Zero))
        {
            throw new InvalidOperationException(
                $"Tidak bisa membuka printer \"{namaPrinter}\". Pastikan nama printer persis sama dengan yang terdaftar di Windows (Settings > Printers & scanners). Kode error: {Marshal.GetLastWin32Error()}");
        }

        try
        {
            var docInfo = new DOCINFOW
            {
                pDocName = namaDokumen,
                pOutputFile = null,
                pDataType = "RAW",
            };

            if (!StartDocPrinterW(hPrinter, 1, ref docInfo))
            {
                throw new InvalidOperationException($"Gagal memulai dokumen cetak. Kode error: {Marshal.GetLastWin32Error()}");
            }

            try
            {
                if (!StartPagePrinter(hPrinter))
                {
                    throw new InvalidOperationException($"Gagal memulai halaman cetak. Kode error: {Marshal.GetLastWin32Error()}");
                }

                try
                {
                    if (!WritePrinter(hPrinter, data, data.Length, out var ditulis) || ditulis != data.Length)
                    {
                        throw new InvalidOperationException($"Data tidak terkirim penuh ke printer ({ditulis}/{data.Length} byte). Kode error: {Marshal.GetLastWin32Error()}");
                    }
                }
                finally
                {
                    EndPagePrinter(hPrinter);
                }
            }
            finally
            {
                EndDocPrinter(hPrinter);
            }
        }
        finally
        {
            ClosePrinter(hPrinter);
        }
    }
}
