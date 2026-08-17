namespace OndPrintHelper;

/// <summary>
/// Jembatan antara tombol "Cetak Langsung" di web OND System dan printer
/// dot-matrix fisik. Diregistrasi sebagai penangan protokol khusus
/// "ondprint://" di Windows — begitu link itu diklik dari browser, Windows
/// menjalankan .exe ini dengan link tadi sebagai argumen. .exe ini lalu
/// mengambil isi ESC/P dari server dan mengirimkannya mentah-mentah ke
/// printer, tanpa lewat rasterisasi GDI sama sekali.
///
/// Dijalankan tanpa argumen (mis. diklik dua kali langsung dari Explorer),
/// jendela pengaturan (PengaturanForm) muncul untuk memilih printer dan
/// sekaligus mendaftarkan diri — dipakai baik untuk pemasangan pertama kali
/// maupun mengganti printer belakangan, tidak ada bedanya.
/// </summary>
internal static class Program
{
    private const string SkemaProtokol = "ondprint";

    [STAThread]
    private static void Main(string[] args)
    {
        // Jaring pengaman global — tanpa ini, exception yang lolos dari
        // pesan Windows (mis. dari event handler tombol) membuat proses
        // mati diam-diam tanpa pesan apa pun ke pengguna.
        Application.ThreadException += (_, e) => Log.Fatal(e.Exception, "Application.ThreadException");
        AppDomain.CurrentDomain.UnhandledException += (_, e) =>
        {
            if (e.ExceptionObject is Exception ex)
            {
                Log.Fatal(ex, "AppDomain.UnhandledException");
            }
        };
        Application.SetUnhandledExceptionMode(UnhandledExceptionMode.CatchException);

        Application.EnableVisualStyles();

        try
        {
            Jalankan(args);
        }
        catch (Exception ex)
        {
            // Menangkap exception yang terjadi SEBELUM Application.Run mulai
            // memompa pesan Windows — misalnya dari constructor form itu
            // sendiri. Application.ThreadException di atas tidak menjangkau
            // titik ini karena message loop-nya belum berjalan.
            Log.Fatal(ex, "Main");
        }
    }

    private static void Jalankan(string[] args)
    {
        if (args.Length == 1 && args[0].Equals("--uninstall", StringComparison.OrdinalIgnoreCase))
        {
            Pendaftaran.Lepas();
            PengaturanPrinter.Hapus();
            MessageBox.Show("OND Print Helper sudah dilepas dari Windows.", "OND Print Helper", MessageBoxButtons.OK, MessageBoxIcon.Information);

            return;
        }

        if (args.Length == 1 && args[0].StartsWith($"{SkemaProtokol}://", StringComparison.OrdinalIgnoreCase))
        {
            ProsesCetak(args[0]);

            return;
        }

        // Tanpa argumen yang dikenali — biasanya berkasnya diklik dua kali
        // langsung, entah untuk memasang pertama kali atau mengganti printer.
        Application.Run(new PengaturanForm());
    }

    private static void ProsesCetak(string uriMentah)
    {
        try
        {
            var namaPrinter = PengaturanPrinter.Ambil();

            // Printer belum pernah dipilih di komputer ini — minta pilih dulu
            // sebelum lanjut, alih-alih gagal dengan pesan yang membingungkan.
            if (string.IsNullOrWhiteSpace(namaPrinter))
            {
                MessageBox.Show(
                    "Printer tujuan belum dipilih di komputer ini. Pilih printernya dulu di jendela berikutnya.",
                    "OND Print Helper",
                    MessageBoxButtons.OK,
                    MessageBoxIcon.Information);

                Application.Run(new PengaturanForm());
                namaPrinter = PengaturanPrinter.Ambil();

                if (string.IsNullOrWhiteSpace(namaPrinter))
                {
                    return;
                }
            }

            var uri = new Uri(uriMentah);
            var parameter = UraiQuery(uri.Query);

            if (!parameter.TryGetValue("url", out var urlSumber) || string.IsNullOrWhiteSpace(urlSumber))
            {
                throw new InvalidOperationException("Link cetak tidak menyertakan parameter \"url\".");
            }

            if (!Uri.TryCreate(urlSumber, UriKind.Absolute, out var uriSumber)
                || (uriSumber.Scheme != Uri.UriSchemeHttp && uriSumber.Scheme != Uri.UriSchemeHttps))
            {
                throw new InvalidOperationException("Parameter \"url\" bukan alamat http/https yang sah.");
            }

            using var http = new HttpClient { Timeout = TimeSpan.FromSeconds(20) };
            // HttpClient tidak mengirim User-Agent sama sekali secara bawaan
            // — beberapa server/WAF (mis. Cloudflare, ModSecurity) langsung
            // menolak permintaan tanpa header ini dengan 403, di luar
            // urusan tanda tangan link sama sekali.
            http.DefaultRequestHeaders.UserAgent.ParseAdd("OndPrintHelper/1.0");

            using var respons = http.GetAsync(uriSumber).GetAwaiter().GetResult();

            if (respons.StatusCode == System.Net.HttpStatusCode.Forbidden)
            {
                throw new InvalidOperationException(
                    "Server menolak permintaan (403 Forbidden) — kemungkinan besar link cetak ini sudah kedaluwarsa "
                        + "(berlaku 5 menit sejak halaman nota dibuka). Kembali ke web, buka ulang halaman nota, lalu "
                        + "klik \"Cetak Langsung ke Printer\" lagi tanpa jeda lama.");
            }

            respons.EnsureSuccessStatusCode();

            var data = respons.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult();

            if (data.Length == 0)
            {
                throw new InvalidOperationException("Server mengembalikan berkas kosong. Klik ulang tombol Cetak dari web.");
            }

            RawPrinter.KirimMentah(namaPrinter, data, "Nota OND System");
        }
        catch (Exception ex)
        {
            Log.Fatal(ex, "ProsesCetak");
        }
    }

    private static Dictionary<string, string> UraiQuery(string query)
    {
        var hasil = new Dictionary<string, string>(StringComparer.OrdinalIgnoreCase);

        foreach (var bagian in query.TrimStart('?').Split('&', StringSplitOptions.RemoveEmptyEntries))
        {
            var pos = bagian.IndexOf('=');

            if (pos < 0)
            {
                continue;
            }

            var kunci = Uri.UnescapeDataString(bagian[..pos]);
            var nilai = Uri.UnescapeDataString(bagian[(pos + 1)..]);
            hasil[kunci] = nilai;
        }

        return hasil;
    }
}
