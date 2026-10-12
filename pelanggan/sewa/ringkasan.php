<?php
require_once '../../includes/auth_check.php';
wajib_login();

date_default_timezone_set('Asia/Jakarta');

$id      = (int) ($_GET['id'] ?? 0);
$id_user = (int) $_SESSION['id_user'];

// Ambil rencana, hanya kalau milik saya
$stmt = mysqli_prepare($koneksi,
    "SELECT * FROM rencana_camping WHERE id_rencana = ? AND id_user = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id, $id_user);
mysqli_stmt_execute($stmt);
$rencana = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$rencana) {
    $_SESSION['pesan_error'] = 'Rencana tidak ditemukan.';
    header('Location: ../rencana/index.php');
    exit;
}

// Data pengguna (untuk bagian Informasi Penyewa)
$stmt = mysqli_prepare($koneksi, "SELECT nama, email FROM users WHERE id_user = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_user);
mysqli_stmt_execute($stmt);
$pengguna = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Apakah rencana ini sudah diajukan dan masih aktif?
$stmt = mysqli_prepare($koneksi,
    "SELECT id_sewa FROM penyewaan
     WHERE id_rencana = ? AND status IN ('menunggu', 'dipinjam')");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$sewa_aktif = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Ambil alat di rencana
$stmt = mysqli_prepare($koneksi,
    "SELECT ri.jumlah,
            a.nama_alat, a.gambar, a.harga_sewa_per_hari, a.stok_tersedia,
            k.nama_kategori
     FROM rencana_item ri
     JOIN alat a ON ri.id_alat = a.id_alat
     JOIN kategori_alat k ON a.id_kategori = k.id_kategori
     WHERE ri.id_rencana = ?
     ORDER BY a.nama_alat");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$daftar_item = [];
while ($row = mysqli_fetch_assoc($hasil)) {
    $daftar_item[] = $row;
}

// Hitung tanggal kembali dan total biaya (rumusnya sama dengan proses pengajuan)
$durasi      = (int) $rencana['durasi_hari'];
$tgl_kembali = date('Y-m-d', strtotime($rencana['tanggal_mulai'] . ' +' . $durasi . ' days'));
$total       = 0;

foreach ($daftar_item as $it) {
    $total += $it['harga_sewa_per_hari'] * $it['jumlah'] * $durasi;
}

// Kumpulkan semua masalah yang menghalangi pengajuan
$masalah = [];

if ($sewa_aktif) {
    $masalah[] = 'Rencana ini sudah diajukan dan masih diproses.';
}
if ($rencana['tanggal_mulai'] < date('Y-m-d')) {
    $masalah[] = 'Tanggal mulai rencana sudah lewat. Buat rencana baru dengan tanggal yang benar.';
}
if (count($daftar_item) === 0) {
    $masalah[] = 'Rencana masih kosong. Tambahkan alat dulu.';
}
foreach ($daftar_item as $it) {
    if ($it['jumlah'] > $it['stok_tersedia']) {
        $masalah[] = 'Stok ' . $it['nama_alat'] . ' tidak cukup (sisa ' . $it['stok_tersedia'] . ').';
    }
}

$judul_halaman = 'Ringkasan Ajukan Sewa';
require_once '../../includes/header.php';
?>

<div class="katalog-kepala">
    <h2>Ringkasan Pengajuan Sewa</h2>
    <p class="petunjuk">Periksa kembali data di bawah, lalu ajukan sewa.</p>
</div>

<?php if (isset($_SESSION['pesan_error'])): ?>
    <p class="alert-error"><?= htmlspecialchars($_SESSION['pesan_error']) ?></p>
    <?php unset($_SESSION['pesan_error']); ?>
<?php endif; ?>

<div class="ringkasan-layout">

    <!-- ===== Kiri: informasi penyewa dan jadwal ===== -->
    <div class="card">
        <h3>Informasi Penyewa</h3>
        <p class="baris-info"><strong>Nama:</strong> <?= htmlspecialchars($pengguna['nama']) ?></p>
        <p class="baris-info"><strong>Email:</strong> <?= htmlspecialchars($pengguna['email']) ?></p>

        <h3>Jadwal Sewa</h3>
        <p class="baris-info"><strong>Rencana:</strong> <?= htmlspecialchars($rencana['nama_rencana']) ?></p>
        <p class="baris-info"><strong>Lokasi:</strong> <?= htmlspecialchars($rencana['lokasi'] ?: '-') ?></p>
        <p class="baris-info"><strong>Tanggal sewa:</strong> <?= date('d-m-Y', strtotime($rencana['tanggal_mulai'])) ?></p>
        <p class="baris-info"><strong>Rencana kembali:</strong> <?= date('d-m-Y', strtotime($tgl_kembali)) ?></p>
        <p class="baris-info"><strong>Durasi:</strong> <?= $durasi ?> hari</p>
        <p class="baris-info"><strong>Jumlah orang:</strong> <?= $rencana['jumlah_orang'] ?></p>
    </div>

    <!-- ===== Kanan: ringkasan pesanan ===== -->
    <div class="card">
        <h3>Ringkasan Pesanan</h3>

        <?php foreach ($daftar_item as $row):
            $subtotal = $row['harga_sewa_per_hari'] * $row['jumlah'] * $durasi;
        ?>
            <div class="item-ringkas">
                <div class="thumb">
                    <?= tampil_gambar_alat($row['gambar'], $row['nama_kategori'], $row['nama_alat']) ?>
                </div>
                <div class="isi">
                    <strong><?= htmlspecialchars($row['nama_alat']) ?></strong><br>
                    <small><?= $row['jumlah'] ?> &times; <?= rupiah($row['harga_sewa_per_hari']) ?> &times; <?= $durasi ?> hari</small>
                </div>
                <div class="harga-ringkas"><?= rupiah($subtotal) ?></div>
            </div>
        <?php endforeach; ?>

        <div class="kotak-total" style="margin-top:16px;">
            <div class="baris total">
                <span>Total</span>
                <span><?= rupiah($total) ?></span>
            </div>
        </div>

        <p class="catatan-info">
            Setelah klik <strong>Ajukan Sewa</strong>, kamu akan diarahkan ke
            <strong>halaman pembayaran</strong> (simulasi). Admin baru bisa
            menyetujui setelah pembayaran lunas.
        </p>

        <?php if (count($masalah) > 0): ?>

            <?php foreach ($masalah as $pesan): ?>
                <p class="alert-error"><?= htmlspecialchars($pesan) ?></p>
            <?php endforeach; ?>

            <?php if ($sewa_aktif): ?>
                <a class="btn" href="detail.php?id=<?= $sewa_aktif['id_sewa'] ?>">Lihat Status Penyewaan</a>
            <?php endif; ?>
            <a class="btn btn-garis" href="../rencana/detail.php?id=<?= $id ?>">&laquo; Kembali ke Rencana</a>

        <?php else: ?>

            <form method="POST" action="ajukan.php">
                <input type="hidden" name="id_rencana" value="<?= $id ?>">
                <button type="submit" class="btn" style="width:100%;">Ajukan Sewa</button>
            </form>
            <a class="btn btn-garis" style="width:100%; text-align:center;"
               href="../rencana/detail.php?id=<?= $id ?>">&laquo; Kembali ke Rencana</a>

        <?php endif; ?>
    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>