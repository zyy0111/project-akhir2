<?php
require_once '../../includes/auth_check.php';
wajib_login();

date_default_timezone_set('Asia/Jakarta');

$id       = (int) ($_GET['id'] ?? 0);
$id_user  = (int) $_SESSION['id_user'];
$hari_ini = date('Y-m-d');

// Ambil penyewaan, hanya kalau milik saya
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM penyewaan WHERE id_sewa = ? AND id_user = ?"
);
mysqli_stmt_bind_param($stmt, 'ii', $id, $id_user);
mysqli_stmt_execute($stmt);
$sewa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$sewa) {
    $_SESSION['pesan_error'] = 'Data penyewaan tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Ambil alat yang disewa
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT d.*, a.nama_alat, a.gambar, a.harga_sewa_per_hari,
            k.nama_kategori
     FROM detail_penyewaan d
     JOIN alat a ON d.id_alat = a.id_alat
     JOIN kategori_alat k ON a.id_kategori = k.id_kategori
     WHERE d.id_sewa = ?
     ORDER BY a.nama_alat"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$hasil_items = mysqli_stmt_get_result($stmt);

// Tampung dulu supaya bisa dipakai 2x (tabel alat + invoice)
$daftar_alat = [];
while ($r = mysqli_fetch_assoc($hasil_items)) {
    $daftar_alat[] = $r;
}

// Data pengembalian (kalau sudah dikembalikan)
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM pengembalian WHERE id_sewa = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$kembali = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Tulisan status
$label_status = [
    'menunggu'     => 'Menunggu',
    'dipinjam'     => 'Dipinjam',
    'dikembalikan' => 'Selesai',
    'ditolak'      => 'Ditolak',
];

// Cek keterlambatan
$biaya_per_hari = (int) round(
    $sewa['total_biaya'] / max(1, $sewa['durasi_hari'])
);

$hari_terlambat = 0;

if (
    $sewa['status'] === 'dipinjam' &&
    $hari_ini > $sewa['tanggal_kembali_rencana']
) {
    $hari_terlambat = (
        new DateTime($sewa['tanggal_kembali_rencana'])
    )->diff(
        new DateTime($hari_ini)
    )->days;
}

$perkiraan_denda = $hari_terlambat * $biaya_per_hari;

$judul_halaman = 'Detail Penyewaan #' . $sewa['id_sewa'];

require_once '../../includes/header.php';
?>

<div class="card">

    <div class="kepala-halaman">
        <h2>Penyewaan #<?= $sewa['id_sewa'] ?></h2>
        <a href="index.php">&laquo; Kembali ke Riwayat</a>
    </div>

    <?php if (isset($_SESSION['pesan'])): ?>
        <p class="alert-sukses">
            <?= htmlspecialchars($_SESSION['pesan']) ?>
        </p>
        <?php unset($_SESSION['pesan']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['pesan_error'])): ?>
        <p class="alert-error">
            <?= htmlspecialchars($_SESSION['pesan_error']) ?>
        </p>
        <?php unset($_SESSION['pesan_error']); ?>
    <?php endif; ?>

    <p>
        <strong>Status:</strong>

        <span class="status status-<?= htmlspecialchars($sewa['status']) ?>">
            <?= htmlspecialchars(
                $label_status[$sewa['status']] ?? ucfirst($sewa['status'])
            ) ?>
        </span>

        <br>

        <strong>Tanggal sewa:</strong>
        <?= date('d-m-Y', strtotime($sewa['tanggal_sewa'])) ?>

        <br>

        <strong>Rencana kembali:</strong>
        <?= date('d-m-Y', strtotime($sewa['tanggal_kembali_rencana'])) ?>

        <br>

        <strong>Durasi:</strong>
        <?= $sewa['durasi_hari'] ?> hari

        <br>

        <strong>Pembayaran:</strong>
        <?php $sudah_bayar = ($sewa['status_pembayaran'] ?? 'belum_bayar') === 'lunas'; ?>
        <span class="status <?= $sudah_bayar ? 'status-dikembalikan' : 'status-ditolak' ?>">
            <?= $sudah_bayar ? 'Lunas' : 'Belum Bayar' ?>
        </span>
        <?php if ($sudah_bayar): ?>
            <br>
            <small class="bayar-lunas">
                <?= logo_pembayaran($sewa['metode_pembayaran'] ?? '-') ?>
                <?= htmlspecialchars($sewa['metode_pembayaran'] ?? '-') ?>
                <?php if (!empty($sewa['tanggal_bayar'])): ?>
                    &bull; <?= date('d-m-Y H:i', strtotime($sewa['tanggal_bayar'])) ?>
                <?php endif; ?>
            </small>
        <?php endif; ?>
    </p>

    <?php if (!$sudah_bayar && $sewa['status'] === 'menunggu'): ?>
        <p class="alert-error">
            Segera selesaikan pembayaran sebesar
            <strong><?= rupiah((int) $sewa['total_biaya']) ?></strong>
            supaya admin bisa menyetujui pengajuanmu.
        </p>
        <p>
            <a class="btn" href="bayar.php?id=<?= $sewa['id_sewa'] ?>">Bayar Sekarang</a>
        </p>
    <?php elseif ($sudah_bayar && $sewa['status'] === 'menunggu'): ?>
        <p class="alert-sukses">
            Pembayaran lunas. Pengajuanmu sedang menunggu persetujuan admin.
        </p>
    <?php endif; ?>

    <!-- Keterangan sesuai status -->

    <?php if ($sewa['status'] === 'dipinjam'): ?>

        <?php if ($hari_terlambat > 0): ?>

            <p class="alert-error">
                <strong>
                    Kamu terlambat <?= $hari_terlambat ?> hari.
                </strong>

                Segera kembalikan alat.
                Perkiraan denda sampai hari ini:

                <strong>
                    <?= rupiah($perkiraan_denda) ?>
                </strong>

                (<?= $hari_terlambat ?> hari
                &times;
                <?= rupiah($biaya_per_hari) ?>).

                Denda akhir dicatat admin saat alat dikembalikan.
            </p>

        <?php else: ?>

            <p class="alert-sukses">
                Alat sudah diserahkan.
                Harap kembalikan paling lambat

                <strong>
                    <?= date(
                        'd-m-Y',
                        strtotime($sewa['tanggal_kembali_rencana'])
                    ) ?>
                </strong>

                supaya tidak terkena denda keterlambatan.
            </p>

        <?php endif; ?>

    <?php elseif ($sewa['status'] === 'ditolak'): ?>

        <p class="alert-error">
            Maaf, pengajuan ini ditolak admin.
        </p>

    <?php elseif ($sewa['status'] === 'dikembalikan'): ?>

        <p class="alert-sukses">
            Alat sudah dikembalikan. Terima kasih!
        </p>

    <?php endif; ?>

</div>


<div class="card">

    <h3>Alat yang Disewa</h3>

    <table class="tabel">

        <thead>
            <tr>
                <th>Alat</th>
                <th>Harga / Hari</th>
                <th>Jumlah</th>
                <th>Subtotal</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($daftar_alat as $row): ?>

                <?php
                $harga_per_hari = (int) $row['harga_sewa_per_hari'];
                $jumlah = (int) $row['jumlah'];

                $subtotal = $harga_per_hari
                    * $jumlah
                    * (int) $sewa['durasi_hari'];
                ?>

                <tr>

                    <td>
                        <div class="item-nama">

                            <div class="thumb">
                                <?= tampil_gambar_alat(
                                    $row['gambar'],
                                    $row['nama_kategori'],
                                    $row['nama_alat']
                                ) ?>
                            </div>

                            <strong>
                                <?= htmlspecialchars($row['nama_alat']) ?>
                            </strong>

                        </div>
                    </td>

                    <td>
                        <?= rupiah($harga_per_hari) ?>
                    </td>

                    <td>
                        <?= $jumlah ?>
                    </td>

                    <td>
                        <?= rupiah($subtotal) ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        </tbody>

    </table>

    <div class="total-sewa">
        <strong>Total Biaya:</strong>
        <strong>
            <?= rupiah((int) $sewa['total_biaya']) ?>
        </strong>
    </div>

</div>

<?php if ($sudah_bayar):
    $no_invoice = 'INV-' . date('Ymd', strtotime($sewa['tanggal_bayar'] ?? $sewa['tanggal_sewa'])) . '-' . str_pad($sewa['id_sewa'], 5, '0', STR_PAD_LEFT);
    $nama_penyewa = htmlspecialchars($_SESSION['nama'] ?? 'Pelanggan');
?>
<div class="card invoice" id="invoice">
    <div class="invoice-kepala">
        <div>
            <h3>Invoice Pembayaran</h3>
            <p class="baris-info"><strong>No:</strong> <?= $no_invoice ?></p>
            <p class="baris-info"><strong>Penyewa:</strong> <?= $nama_penyewa ?></p>
            <p class="baris-info"><strong>Tanggal bayar:</strong>
                <?= !empty($sewa['tanggal_bayar']) ? date('d-m-Y H:i', strtotime($sewa['tanggal_bayar'])) : '-' ?>
            </p>
        </div>
        <div class="invoice-kanan">
            <?= logo_pembayaran($sewa['metode_pembayaran'] ?? '-') ?>
            <strong><?= htmlspecialchars($sewa['metode_pembayaran'] ?? '-') ?></strong>
            <?php if (!empty($sewa['detail_pembayaran'])): ?>
                <small><?= htmlspecialchars($sewa['detail_pembayaran']) ?></small>
            <?php endif; ?>
            <span class="status status-dikembalikan">LUNAS</span>
        </div>
    </div>

    <?php if (($sewa['metode_pembayaran'] ?? '') === 'QRIS'): ?>
        <?= gambar_dummy_bayar('QRIS') ?>
    <?php endif; ?>

    <table class="tabel">
        <thead>
            <tr><th>Alat</th><th>Jumlah</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
            <?php foreach ($daftar_alat as $row):
                $sub = (int) $row['harga_satuan'] * (int) $row['jumlah'] * (int) $sewa['durasi_hari'];
            ?>
            <tr>
                <td><?= htmlspecialchars($row['nama_alat']) ?></td>
                <td><?= (int) $row['jumlah'] ?></td>
                <td><?= rupiah($sub) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="total-sewa">
        <strong>Total Dibayar:</strong>
        <strong><?= rupiah((int) $sewa['total_biaya']) ?></strong>
    </div>
    <p class="catatan-info">Simpan invoice ini sebagai bukti pembayaran (simulasi, bukan transaksi asli).</p>
    <button type="button" class="btn btn-garis" onclick="window.print()">Cetak Invoice</button>
</div>
<?php endif; ?>


<?php if ($kembali): ?>

<div class="card">

    <h3>Informasi Pengembalian</h3>

    <p>
        <strong>Tanggal pengembalian:</strong>

        <?= !empty($kembali['tanggal_kembali'])
            ? date(
                'd-m-Y',
                strtotime($kembali['tanggal_kembali'])
            )
            : '-'
        ?>

        <br>

        <strong>Denda:</strong>

        <?= isset($kembali['denda'])
            ? rupiah((int) $kembali['denda'])
            : rupiah(0)
        ?>
    </p>

</div>

<?php endif; ?>


<?php require_once '../../includes/footer.php'; ?>