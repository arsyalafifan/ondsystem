namespace OndPrintHelper;

/// <summary>
/// Jaring pengaman terakhir: kalau ada apa pun yang gagal sebelum jendela
/// sempat muncul (mis. exception di dalam constructor form, sebelum
/// Application.Run bahkan mulai memompa pesan Windows), proses akan mati
/// diam-diam tanpa ini — persis seperti gejala "diklik tapi tidak muncul
/// apa-apa". Dicatat ke berkas juga, bukan cuma MessageBox, supaya bisa
/// ditelusuri lagi belakangan tanpa perlu mengulang kegagalannya.
/// </summary>
internal static class Log
{
    private static string BerkasLog => Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "OndPrintHelper",
        "error.log");

    public static void Fatal(Exception ex, string konteks)
    {
        try
        {
            var direktori = Path.GetDirectoryName(BerkasLog);

            if (direktori is not null)
            {
                Directory.CreateDirectory(direktori);
            }

            File.AppendAllText(
                BerkasLog,
                $"{DateTime.Now:yyyy-MM-dd HH:mm:ss} [{konteks}] {ex}{Environment.NewLine}{Environment.NewLine}");
        }
        catch
        {
            // Kalau menulis log saja sudah gagal (mis. tidak ada izin tulis),
            // tetap lanjut menampilkan MessageBox — itu yang paling penting.
        }

        MessageBox.Show(
            $"OND Print Helper mengalami kesalahan tak terduga:\n\n{ex.Message}\n\n"
                + $"Rincian lengkap tersimpan di:\n{BerkasLog}",
            "OND Print Helper - Kesalahan",
            MessageBoxButtons.OK,
            MessageBoxIcon.Error);
    }
}
