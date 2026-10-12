<?php
require_once '../../includes/auth_check.php';
wajib_admin();

date_default_timezone_set('Asia/Jakarta');

$judul_halaman = 'Kelola Penyewaan';

// Pilihan status yang boleh dipakai sebagai filter
$boleh  = ['menunggu', 'dipinjam', 'dikembalikan', 'ditolak'];
$filter = $_GET['status'] ?? '';

$sql = "SELECT p.*, u.nama
        FROM penyewaan p
        JOIN users u ON p.id_user = u.id_user";

if (in_array($filter, $boleh, true)) {
    $sql .= " WHERE p.status = ?";
    $sql .= " ORDER BY p.id_sewa DESC";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, 's', $filter);
} else {
    $filter = '';
    // Yang menunggu ditaruh paling atas supaya cepat terlihat
    $sql .= " ORDER BY FIELD(p.status, 'menunggu', 'dipinjam', 'dikembalikan', 'ditolak'), p.id_sewa DESC";
    $stmt = mysqli_prepare($koneksi, $sql);
}

mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$hari_ini = date('Y-m-d');

require_once '../../includes/header.php';
?>

<div class="card">
    <div class="kepala-halaman">
        <h2>Kelola Penyewaan</h2>
        <a href="../index.php">&laquo; Dashboard</a>
    </div>

    <?php if (isset($_SESSION['pesan'])): ?>
        <p class="alert-sukses"><?= htmlspecialchars($_SESSION['pesan']) ?></p>
        <?php unset($_SESSION['pesan']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['pesan_error'])): ?>
        <p class="alert-error"><?= htmlspecialchars($_SESSION['pesan_error']) ?></p>
        <?php unset($_SESSION['pesan_error']); ?>
    <?php endif; ?>

    <!-- Saring berdasarkan status -->
    <form method="GET" class="filter-bar">
        <select name="status">
            <option value="">Semua status</option>
            <?php foreach ($boleh as $s): ?>
                <option value="<?= $s ?>" <?= $filter === $s ? 'selected' : '' ?>>
                    <?= ucfirst($s) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Saring</button>
        <a href="index.php">Reset</a>
    </form>

    <?php if (mysqli_num_rows($hasil) === 0): ?>
        <p>Tidak ada data penyewaan.</p>
    <?php else: ?>
        <table class="tabel">
            <tr>
                <th>No. Sewa</th>
                <th>Pelanggan</th>
                <th>Tanggal Sewa</th>
                <th>Rencana Kembali</th>
                <th>Total Biaya</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($hasil)):
                $terlambat = ($row['status'] === 'dipinjam' && $hari_ini > $row['tanggal_kembali_rencana']);
            ?>
            <tr>
                <td>#<?= $row['id_sewa'] ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= date('d-m-Y', strtotime($row['tanggal_sewa'])) ?></td>
                <td><?= date('d-m-Y', strtotime($row['tanggal_kembali_rencana'])) ?></td>
                <td>Rp <?= number_format($row['total_biaya'], 0, ',', '.') ?><br><small><?= ($row['status_pembayaran'] ?? 'belum_bayar') === 'lunas' ? 'Lunas' : 'Belum Bayar' ?></small></td>
                <td>
                    <span class="status status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span>
                    <?php if ($terlambat): ?>
                        <span class="badge-habis">Terlambat</span>
                    <?php endif; ?>
                </td>
                <td><a class="btn-kecil" href="detail.php?id=<?= $row['id_sewa'] ?>">Lihat</a></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>