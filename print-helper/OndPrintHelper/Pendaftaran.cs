using Microsoft.Win32;

namespace OndPrintHelper;

/// <summary>Pendaftaran "ondprint://" sebagai protokol Windows, per akun pengguna (HKEY_CURRENT_USER — tidak perlu admin/UAC).</summary>
internal static class Pendaftaran
{
    private const string SkemaProtokol = "ondprint";

    public static bool SudahTerdaftar()
    {
        using var kunci = Registry.CurrentUser.OpenSubKey($@"Software\Classes\{SkemaProtokol}\shell\open\command");

        return kunci is not null;
    }

    public static void Daftarkan(string jalurExe)
    {
        using var kunciProtokol = Registry.CurrentUser.CreateSubKey($@"Software\Classes\{SkemaProtokol}");
        kunciProtokol.SetValue(string.Empty, "URL:OND Print Protocol");
        kunciProtokol.SetValue("URL Protocol", string.Empty);

        using var kunciIkon = kunciProtokol.CreateSubKey("DefaultIcon");
        kunciIkon.SetValue(string.Empty, $"\"{jalurExe}\",0");

        using var kunciPerintah = kunciProtokol.CreateSubKey(@"shell\open\command");
        kunciPerintah.SetValue(string.Empty, $"\"{jalurExe}\" \"%1\"");
    }

    public static void Lepas()
    {
        Registry.CurrentUser.DeleteSubKeyTree($@"Software\Classes\{SkemaProtokol}", throwOnMissingSubKey: false);
    }
}
