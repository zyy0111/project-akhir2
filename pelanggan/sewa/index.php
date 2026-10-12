<?php
require_once '../../includes/auth_check.php';
wajib_login();

date_default_timezone_set('Asia/Jakarta');

$judul_halaman = 'Sewa Saya';
$id_user  = (int) $_SESSION['id_user'];
$hari_ini = date('Y-m-d');

// ---------- Daftar tab dan status yang termasuk di dalamnya ----------
$daftar_tab = [
    'semua'       => ['label' => 'Semua',       'status' => []],
    'berlangsung' => ['label' => 'Berlangsung', 'status' => ['menunggu', 'dipinjam']],
    'selesai'     => ['label' => 'Selesai',     'status' => ['dikembalikan']],
    'dibatalkan'  => ['label' => 'Dibatalkan',  'status' => ['ditolak']],
];

// Tulisan status yang tampil di label
$label_status = [
    'menunggu'     => 'Menunggu',
    'dipinjam'     => 'Dipinjam',
    'dikembalikan' => 'Selesai',
    'ditolak'      => 'Ditolak',
];

// Tab yang dipilih (kalau tidak valid, pakai "semua")
$tab = $_GET['tab'] ?? 'semua';
if (!is_string($tab) || !isset($daftar_tab[$tab])) {
    $tab = 'semua';
}

// ---------- Hitung jumlah penyewaan per status (untuk angka di tab) ----------
$jumlah_status = ['menunggu' => 0, 'dipinjam' => 0, 'dikembalikan' => 0, 'ditolak' => 0];

$stmt = mysqli_prepare($koneksi,
    "SELECT status, COUNT(*) AS total FROM penyewaan WHERE id_user = ? GROUP BY status");
mysqli_stmt_bind_param($stmt, 'i', $id_user);
mysqli_stmt_execute($stmt);
$hasil_hitung = mysqli_stmt_get_result($stmt);

while ($r = mysqli_fetch_assoc($hasil_hitung)) {
    $jumlah_status[$r['status']] = (int) $r['total'];
}

// Jumlah untuk setiap tab
foreach ($daftar_tab as $kode => $info) {
    if (count($info['status']) === 0) {
        $daftar_tab[$kode]['jumlah'] = array_sum($jumlah_status);
    } else {
        $jml = 0;
        foreach ($info['status'] as $s) {
            $jml += $jumlah_status[$s];
        }
        $daftar_tab[$kode]['jumlah'] = $jml;
    }
}

// ---------- Ambil penyewaan sesuai tab ----------
$sql = "SELECT p.*, pg.denda, pg.tanggal_kembali_aktual
        FROM penyewaan p
        LEFT JOIN pengembalian pg ON pg.id_sewa = p.id_sewa
        WHERE p.id_user = ?";

$status_tab = $daftar_tab[$tab]['status'];
if (count($status_tab) > 0) {
    // Isi daftar status ini kita tulis sendiri di atas (bukan dari pengguna), jadi aman
    $sql .= " AND p.status IN ('" . implode("','", $status_tab) . "')";
}
$sql .= " ORDER BY p.id_sewa DESC";

$stmt = mysqli_prepare($koneksi, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id_user);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$riwayat   = [];
$daftar_id = [];
while ($row = mysqli_fetch_assoc($hasil)) {
    $riwayat[]   = $row;
    $daftar_id[] = (int) $row['id_sewa'];
}

// ---------- Ambil alat (dengan foto) untuk semua riwayat sekaligus ----------
$alat_per_sewa = [];

if (count($daftar_id) > 0) {
    $id_in = implode(',', $daftar_id);   // angka bulat dari database, aman

    $q_alat = mysqli_query($koneksi,
        "SELECT d.id_sewa, d.jumlah, a.nama_alat, a.gambar, k.nama_kategori
         FROM detail_penyewaan d
         JOIN alat a ON d.id_alat = a.id_alat
         JOIN kategori_alat k ON a.id_kategori = k.id_kategori
         WHERE d.id_sewa IN ($id_in)
         ORDER BY a.nama_alat");

    while ($a = mysqli_fetch_assoc($q_alat)) {
        $alat_per_sewa[$a['id_sewa']][] = $a;
    }
}

require_once '../../includes/header.php';
?>

<div class="katalog-kepala">
    <h2>Riwayat Penyewaan</h2>
    <p class="petunjuk">Pantau semua pengajuan sewa alat campingmu di sini.</p>
</div>

<?php if (isset($_SESSION['pesan'])): ?>
    <p class="alert-sukses"><?= htmlspecialchars($_SESSION['pesan']) ?></p>
    <?php unset($_SESSION['pesan']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['pesan_error'])): ?>
    <p class="alert-error"><?= htmlspecialchars($_SESSION['pesan_error']) ?></p>
    <?php unset($_SESSION['pesan_error']); ?>
<?php endif; ?>

<div class="card">

    <!-- ===== Tab ===== -->
    <div class="tab-bar">
        <?php foreach ($daftar_tab as $kode => $info): ?>
            <a class="tab <?= $tab === $kode ? 'aktif' : '' ?>" href="?tab=<?= $kode ?>">
                <?= $info['label'] ?>
                <span class="jumlah"><?= $info['jumlah'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ===== Daftar riwayat ===== -->
    <?php if (count($riwayat) === 0): ?>

        <?php if ($tab === 'semua'): ?>
            <p>Belum ada penyewaan. Buat rencana, tambahkan alat, lalu klik <strong>Ajukan Sewa</strong>.</p>
            <a class="btn" href="<?= BASE_URL ?>/pelanggan/alat.php">Buka Katalog</a>
        <?php else: ?>
            <p>Tidak ada penyewaan di tab ini.</p>
        <?php endif; ?>

    <?php else: ?>

        <?php foreach ($riwayat as $row):
            $items     = $alat_per_sewa[$row['id_sewa']] ?? [];
            $terlambat = ($row['status'] === 'dipinjam' && $hari_ini > $row['tanggal_kembali_rencana']);

            // Nama alat: tampilkan 2 pertama, sisanya diringkas
            $nama_alat = [];
            foreach ($items as $it) {
                $nama_alat[] = $it['nama_alat'];
            }
            $teks_nama = implode(' + ', array_slice($nama_alat, 0, 2));
            if (count($nama_alat) > 2) {
                $teks_nama .= ' + ' . (count($nama_alat) - 2) . ' lainnya';
            }
        ?>
            <div class="riwayat-item">

                <!-- Foto (maksimal 3) -->
                <div class="foto-baris">
                    <?php foreach (array_slice($items, 0, 3) as $it): ?>
                        <div class="thumb">
                            <?= tampil_gambar_alat($it['gambar'], $it['nama_kategori'], $it['nama_alat']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Keterangan -->
                <div class="riwayat-isi">
                    <strong><?= htmlspecialchars($teks_nama) ?></strong><br>
                    <small>
                        #<?= $row['id_sewa'] ?> &bull;
                        <?= date('d-m-Y', strtotime($row['tanggal_sewa'])) ?>
                        sampai <?= date('d-m-Y', strtotime($row['tanggal_kembali_rencana'])) ?>
                        (<?= $row['durasi_hari'] ?> hari)
                    </small><br>

                    Total: <strong><?= rupiah($row['total_biaya']) ?></strong>
                    <?php $lunas = ($row['status_pembayaran'] ?? 'belum_bayar') === 'lunas'; ?>
                    <br><small>Pembayaran:
                        <strong style="color:<?= $lunas ? 'green' : 'red' ?>"><?= $lunas ? 'Lunas' : 'Belum Bayar' ?></strong>
                    </small>

                    <?php if ($row['status'] === 'dikembalikan' && $row['denda'] > 0): ?>
                        <span class="denda-teks">+ Denda <?= rupiah($row['denda']) ?></span>
                    <?php endif; ?>

                    <?php if ($row['status'] === 'dikembalikan' && !empty($row['tanggal_kembali_aktual'])): ?>
                        <br><small>Dikembalikan: <?= date('d-m-Y', strtotime($row['tanggal_kembali_aktual'])) ?></small>
                    <?php endif; ?>
                </div>

                <!-- Status dan tombol -->
                <div class="riwayat-kanan">
                    <span class="status status-<?= $row['status'] ?>">
                        <?= $label_status[$row['status']] ?? ucfirst($row['status']) ?>
                    </span>

                    <?php if ($terlambat): ?>
                        <span class="badge-habis">Terlambat</span>
                    <?php endif; ?>

                    <a class="btn-kecil" href="detail.php?id=<?= $row['id_sewa'] ?>">Lihat Detail</a>
                    <?php if ($row['status'] === 'menunggu' && ($row['status_pembayaran'] ?? 'belum_bayar') !== 'lunas'): ?>
                        <a class="btn-kecil" href="bayar.php?id=<?= $row['id_sewa'] ?>">Bayar</a>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>