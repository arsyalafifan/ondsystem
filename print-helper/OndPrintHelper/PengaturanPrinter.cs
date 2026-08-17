using Microsoft.Win32;

namespace OndPrintHelper;

/// <summary>
/// Nama printer target disimpan lokal di komputer ini (registry), BUKAN
/// dikirim dari server. Sengaja begitu — nama printer yang terdaftar di
/// Windows bisa beda-beda per komputer (mis. "EPSON LX-310 ESC/P" vs
/// "EPSON LX-310 ESC/P (Copy 1)"), dan bisa berubah kapan saja tanpa
/// sepengetahuan server. Pilihan pengguna di sini selalu menang.
/// </summary>
internal static class PengaturanPrinter
{
    private const string Kunci = @"Software\OndPrintHelper";

    private const string NilaiNamaPrinter = "PrinterName";

    public static string? Ambil()
    {
        using var kunci = Registry.CurrentUser.OpenSubKey(Kunci);

        return kunci?.GetValue(NilaiNamaPrinter) as string;
    }

    public static void Simpan(string namaPrinter)
    {
        using var kunci = Registry.CurrentUser.CreateSubKey(Kunci);
        kunci.SetValue(NilaiNamaPrinter, namaPrinter);
    }

    public static void Hapus()
    {
        Registry.CurrentUser.DeleteSubKeyTree(Kunci, throwOnMissingSubKey: false);
    }
}
