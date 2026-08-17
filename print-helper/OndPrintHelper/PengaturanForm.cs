using System.Drawing.Printing;

namespace OndPrintHelper;

/// <summary>
/// Satu jendela ini dipakai untuk dua situasi sekaligus: pemasangan
/// pertama kali (diklik dua kali dari Explorer) maupun mengganti printer
/// belakangan (dijalankan ulang kapan saja). Isinya sama persis, hanya beda
/// pesan pembuka — mengganti printer bukan proses berbeda dari memasangnya.
/// </summary>
internal sealed class PengaturanForm : Form
{
    private readonly ComboBox cboPrinter = new() { DropDownStyle = ComboBoxStyle.DropDownList, Width = 320 };

    private readonly Label lblInfo = new() { AutoSize = false, Width = 340, Height = 60 };

    public PengaturanForm()
    {
        Text = "OND Print Helper - Pengaturan";
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = false;
        StartPosition = FormStartPosition.CenterScreen;
        ClientSize = new Size(380, 220);
        Font = new Font("Segoe UI", 9F);

        var sudahTerdaftar = Pendaftaran.SudahTerdaftar();

        lblInfo.Text = sudahTerdaftar
            ? "Pilih printer tujuan untuk tombol \"Cetak Langsung ke Printer\" di web OND System, lalu klik Simpan."
            : "OND Print Helper belum terdaftar di komputer ini. Pilih printer tujuan, lalu klik Simpan untuk mendaftarkannya sekaligus.";
        lblInfo.Location = new Point(20, 15);

        var lblPrinter = new Label { Text = "Printer:", AutoSize = true, Location = new Point(20, 85) };
        cboPrinter.Location = new Point(20, 105);

        try
        {
            foreach (string nama in PrinterSettings.InstalledPrinters)
            {
                cboPrinter.Items.Add(nama);
            }
        }
        catch (Exception ex)
        {
            // Paling sering karena layanan Print Spooler Windows mati/macet
            // — enumerasi printer lewat cara ini bergantung penuh padanya.
            MessageBox.Show(
                this,
                "Tidak bisa membaca daftar printer dari Windows.\n\n"
                    + $"Rincian: {ex.Message}\n\n"
                    + "Kemungkinan besar layanan \"Print Spooler\" di Windows sedang berhenti — "
                    + "buka services.msc, cari \"Print Spooler\", pastikan statusnya Running, lalu jalankan ulang OND Print Helper.",
                "OND Print Helper",
                MessageBoxButtons.OK,
                MessageBoxIcon.Warning);
        }

        var tersimpan = PengaturanPrinter.Ambil();

        if (tersimpan is not null && cboPrinter.Items.Contains(tersimpan))
        {
            cboPrinter.SelectedItem = tersimpan;
        }
        else if (cboPrinter.Items.Count > 0)
        {
            cboPrinter.SelectedIndex = 0;
        }

        if (cboPrinter.Items.Count == 0)
        {
            cboPrinter.Items.Add("(Tidak ada printer terpasang di Windows)");
            cboPrinter.SelectedIndex = 0;
            cboPrinter.Enabled = false;
        }

        var btnSimpan = new Button { Text = "Simpan", Location = new Point(20, 145), Width = 100, DialogResult = DialogResult.OK };
        btnSimpan.Click += BtnSimpan_Click;

        var btnBatal = new Button { Text = "Batal", Location = new Point(128, 145), Width = 100, DialogResult = DialogResult.Cancel };

        var btnLepas = new Button { Text = "Lepas Pendaftaran", Location = new Point(236, 145), Width = 124 };
        btnLepas.Click += BtnLepas_Click;

        AcceptButton = btnSimpan;
        CancelButton = btnBatal;

        Controls.AddRange([lblInfo, lblPrinter, cboPrinter, btnSimpan, btnBatal, btnLepas]);
    }

    private void BtnSimpan_Click(object? sender, EventArgs e)
    {
        if (cboPrinter.SelectedItem is not string namaPrinter || !cboPrinter.Enabled)
        {
            MessageBox.Show(this, "Tidak ada printer yang bisa dipilih. Pasang printernya dulu di Windows, baru jalankan ulang OND Print Helper.", "OND Print Helper", MessageBoxButtons.OK, MessageBoxIcon.Warning);
            DialogResult = DialogResult.None;

            return;
        }

        try
        {
            var jalurExe = Environment.ProcessPath ?? Application.ExecutablePath;
            Pendaftaran.Daftarkan(jalurExe);
            PengaturanPrinter.Simpan(namaPrinter);

            MessageBox.Show(
                this,
                $"Tersimpan. Tombol \"Cetak Langsung ke Printer\" di web OND System sekarang akan mencetak ke:\n\n{namaPrinter}",
                "OND Print Helper",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);
        }
        catch (Exception ex)
        {
            MessageBox.Show(this, $"Gagal menyimpan: {ex.Message}", "OND Print Helper - Gagal", MessageBoxButtons.OK, MessageBoxIcon.Error);
            DialogResult = DialogResult.None;
        }
    }

    private void BtnLepas_Click(object? sender, EventArgs e)
    {
        var jawaban = MessageBox.Show(
            this,
            "Melepas pendaftaran akan menonaktifkan tombol \"Cetak Langsung ke Printer\" di web OND System dari komputer ini (pilihan printer yang tersimpan juga akan dihapus). Lanjutkan?",
            "OND Print Helper",
            MessageBoxButtons.YesNo,
            MessageBoxIcon.Question);

        if (jawaban != DialogResult.Yes)
        {
            return;
        }

        Pendaftaran.Lepas();
        PengaturanPrinter.Hapus();

        MessageBox.Show(this, "OND Print Helper sudah dilepas dari komputer ini.", "OND Print Helper", MessageBoxButtons.OK, MessageBoxIcon.Information);
        DialogResult = DialogResult.Cancel;
        Close();
    }
}
