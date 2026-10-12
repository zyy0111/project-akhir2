<?php
require_once '../../includes/auth_check.php';
wajib_admin();

date_default_timezone_set('Asia/Jakarta');

$id = (int) ($_GET['id'] ?? 0);

// Ambil penyewaan + nama pelanggan
$stmt = mysqli_prepare($koneksi,
    "SELECT p.*, u.nama, u.email
     FROM penyewaan p
     JOIN users u ON p.id_user = u.id_user
     WHERE p.id_sewa = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$sewa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$sewa) {
    $_SESSION['pesan_error'] = 'Data penyewaan tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Ambil alat yang disewa + stok saat ini
$stmt = mysqli_prepare($koneksi,
    "SELECT d.*, a.nama_alat, a.stok_tersedia
     FROM detail_penyewaan d
     JOIN alat a ON d.id_alat = a.id_alat
     WHERE d.id_sewa = ?
     ORDER BY a.nama_alat");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt);

// Data pengembalian (kalau sudah dikembalikan)
$stmt = mysqli_prepare($koneksi, "SELECT * FROM pengembalian WHERE id_sewa = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$kembali = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$terlambat       = ($sewa['status'] === 'dipinjam' && date('Y-m-d') > $sewa['tanggal_kembali_rencana']);
$ada_stok_kurang = false;

$judul_halaman = 'Detail Penyewaan';
require_once '../../includes/header.php';
?>

<div class="card">
    <div class="kepala-halaman">
        <h2>Penyewaan #<?= $sewa['id_sewa'] ?></h2>
        <a href="index.php">&laquo; Kembali</a>
    </div>

    <?php if (isset($_SESSION['pesan'])): ?>
        <p class="alert-sukses"><?= htmlspecialchars($_SESSION['pesan']) ?></p>
        <?php unset($_SESSION['pesan']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['pesan_error'])): ?>
        <p class="alert-error"><?= htmlspecialchars($_SESSION['pesan_error']) ?></p>
        <?php unset($_SESSION['pesan_error']); ?>
    <?php endif; ?>

    <p>
        <strong>Pelanggan:</strong> <?= htmlspecialchars($sewa['nama']) ?> (<?= htmlspecialchars($sewa['email']) ?>)<br>
        <strong>Status:</strong>
        <span class="status status-<?= $sewa['status'] ?>"><?= ucfirst($sewa['status']) ?></span>
        <?php if ($terlambat): ?>
            <span class="badge-habis">Terlambat</span>
        <?php endif; ?><br>
        <strong>Tanggal sewa:</strong> <?= date('d-m-Y', strtotime($sewa['tanggal_sewa'])) ?><br>
        <strong>Rencana kembali:</strong> <?= date('d-m-Y', strtotime($sewa['tanggal_kembali_rencana'])) ?><br>
        <strong>Durasi:</strong> <?= $sewa['durasi_hari'] ?> hari<br>
        <strong>Pembayaran:</strong>
        <?php $sudah_bayar = ($sewa['status_pembayaran'] ?? 'belum_bayar') === 'lunas'; ?>
        <?php if ($sudah_bayar): ?>
            <span class="badge-ok bayar-lunas"><?= logo_pembayaran($sewa['metode_pembayaran'] ?? '-') ?> Lunas (<?= htmlspecialchars($sewa['metode_pembayaran'] ?? '-') ?>)</span>
        <?php else: ?>
            <span class="badge-habis">Belum Bayar</span>
        <?php endif; ?>
    </p>

    <?php if ($kembali): ?>
        <p class="alert-sukses">
            <strong>Sudah dikembalikan</strong> pada <?= date('d-m-Y', strtotime($kembali['tanggal_kembali_aktual'])) ?><br>
            Kondisi: <?= htmlspecialchars(str_replace('_', ' ', $kembali['kondisi'])) ?><br>
            Denda: Rp <?= number_format($kembali['denda'], 0, ',', '.') ?><br>
            Catatan: <?= htmlspecialchars($kembali['catatan'] ?: '-') ?>
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Alat yang Disewa</h3>

    <table class="tabel">
        <tr>
            <th>Alat</th>
            <th>Harga / Hari</th>
            <th>Jumlah</th>
            <th>Subtotal</th>
            <?php if ($sewa['status'] === 'menunggu'): ?>
                <th>Stok Sekarang</th>
            <?php endif; ?>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($items)):
            $kurang = $row['jumlah'] > $row['stok_tersedia'];
            if ($kurang) { $ada_stok_kurang = true; }
        ?>
        <tr>
            <td><?= htmlspecialchars($row['nama_alat']) ?></td>
            <td>Rp <?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
            <td><?= $row['jumlah'] ?></td>
            <td>Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
            <?php if ($sewa['status'] === 'menunggu'): ?>
                <td>
                    <?php if ($kurang): ?>
                        <span class="badge-habis">Kurang (sisa <?= $row['stok_tersedia'] ?>)</span>
                    <?php else: ?>
                        <span class="badge-ok">Cukup (<?= $row['stok_tersedia'] ?>)</span>
                    <?php endif; ?>
                </td>
            <?php endif; ?>
        </tr>
        <?php endwhile; ?>

        <tr>
            <th colspan="3">Total Biaya</th>
            <th colspan="2">Rp <?= number_format($sewa['total_biaya'], 0, ',', '.') ?></th>
        </tr>
    </table>

    <!-- Tombol aksi sesuai status -->
    <?php if ($sewa['status'] === 'menunggu'): ?>

        <?php if (!$sudah_bayar): ?>
            <p class="alert-error">Pelanggan belum membayar, jadi pengajuan ini belum bisa disetujui.</p>
        <?php elseif ($ada_stok_kurang): ?>
            <p class="alert-error">Stok ada yang kurang, jadi pengajuan ini belum bisa disetujui.</p>
        <?php else: ?>
            <form method="POST" action="setujui.php" class="form-inline"
                  onsubmit="return confirm('Setujui dan serahkan alat? Stok akan berkurang.')">
                <input type="hidden" name="id_sewa" value="<?= $sewa['id_sewa'] ?>">
                <button type="submit" class="btn">Setujui &amp; Serahkan Alat</button>
            </form>
        <?php endif; ?>

        <form method="POST" action="tolak.php" class="form-inline"
              onsubmit="return confirm('Tolak pengajuan ini?')">
            <input type="hidden" name="id_sewa" value="<?= $sewa['id_sewa'] ?>">
            <button type="submit" class="btn btn-merah">Tolak</button>
        </form>

    <?php elseif ($sewa['status'] === 'dipinjam'): ?>

        <a class="btn" href="../pengembalian/proses.php?id=<?= $sewa['id_sewa'] ?>">Catat Pengembalian</a>

    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>