<?php
require_once '../../includes/auth_check.php';
wajib_login();

date_default_timezone_set('Asia/Jakarta');

// Supaya kalau ada kesalahan database, PHP "melempar" error yang bisa kita tangkap
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Hanya boleh lewat tombol (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../rencana/index.php');
    exit;
}

$id_user    = (int) $_SESSION['id_user'];
$id_rencana = (int) $_POST['id_rencana'];
$balik      = '../rencana/detail.php?id=' . $id_rencana;

// 1. Pastikan rencana ini milik saya
$stmt = mysqli_prepare($koneksi,
    "SELECT * FROM rencana_camping WHERE id_rencana = ? AND id_user = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id_rencana, $id_user);
mysqli_stmt_execute($stmt);
$rencana = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$rencana) {
    $_SESSION['pesan_error'] = 'Rencana tidak ditemukan.';
    header('Location: ../rencana/index.php');
    exit;
}

// 2. Cek: apakah rencana ini sudah diajukan dan masih aktif?
$stmt = mysqli_prepare($koneksi,
    "SELECT id_sewa FROM penyewaan
     WHERE id_rencana = ? AND status IN ('menunggu', 'dipinjam')");
mysqli_stmt_bind_param($stmt, 'i', $id_rencana);
mysqli_stmt_execute($stmt);
$sudah = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($sudah) {
    $_SESSION['pesan_error'] = 'Rencana ini sudah diajukan dan masih diproses.';
    header('Location: ' . $balik);
    exit;
}

// 3. Cek tanggal
if ($rencana['tanggal_mulai'] < date('Y-m-d')) {
    $_SESSION['pesan_error'] = 'Tanggal mulai rencana sudah lewat. Buat rencana baru dengan tanggal yang benar.';
    header('Location: ' . $balik);
    exit;
}

// 4. Ambil alat di rencana (dengan harga dan stok TERBARU)
$stmt = mysqli_prepare($koneksi,
    "SELECT ri.id_alat, ri.jumlah, a.nama_alat, a.harga_sewa_per_hari, a.stok_tersedia
     FROM rencana_item ri
     JOIN alat a ON ri.id_alat = a.id_alat
     WHERE ri.id_rencana = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_rencana);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$items = [];
while ($row = mysqli_fetch_assoc($hasil)) {
    $items[] = $row;
}

if (count($items) === 0) {
    $_SESSION['pesan_error'] = 'Rencana masih kosong. Tambahkan alat dulu.';
    header('Location: ' . $balik);
    exit;
}

// 5. Cek stok semua alat
foreach ($items as $it) {
    if ($it['jumlah'] > $it['stok_tersedia']) {
        $_SESSION['pesan_error'] = 'Stok ' . $it['nama_alat'] . ' tidak cukup. Tersedia: ' . $it['stok_tersedia'] . '.';
        header('Location: ' . $balik);
        exit;
    }
}

// 6. Hitung durasi, tanggal kembali, dan total biaya
$durasi      = (int) $rencana['durasi_hari'];
$tgl_sewa    = $rencana['tanggal_mulai'];
$tgl_kembali = date('Y-m-d', strtotime($tgl_sewa . ' +' . $durasi . ' days'));

$total = 0;
foreach ($items as $it) {
    $total += $it['harga_sewa_per_hari'] * $it['jumlah'] * $durasi;
}

// 7. Simpan sebagai satu paket (transaction)
try {
    mysqli_begin_transaction($koneksi);

    // a. Data penyewaan (status otomatis "menunggu")
    $stmt = mysqli_prepare($koneksi,
        "INSERT INTO penyewaan
            (id_user, id_rencana, tanggal_sewa, durasi_hari, tanggal_kembali_rencana, total_biaya)
         VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'iisisi',
        $id_user, $id_rencana, $tgl_sewa, $durasi, $tgl_kembali, $total);
    mysqli_stmt_execute($stmt);

    $id_sewa = mysqli_insert_id($koneksi);

    // b. Isi alatnya, satu baris per alat (harga dikunci di sini)
    $stmt = mysqli_prepare($koneksi,
        "INSERT INTO detail_penyewaan (id_sewa, id_alat, jumlah, harga_satuan, subtotal)
         VALUES (?, ?, ?, ?, ?)");

    foreach ($items as $it) {
        $harga    = (int) $it['harga_sewa_per_hari'];
        $jumlah   = (int) $it['jumlah'];
        $subtotal = $harga * $jumlah * $durasi;
        $id_alat  = (int) $it['id_alat'];

        mysqli_stmt_bind_param($stmt, 'iiiii', $id_sewa, $id_alat, $jumlah, $harga, $subtotal);
        mysqli_stmt_execute($stmt);
    }

    mysqli_commit($koneksi);   // semua berhasil: simpan

} catch (Throwable $e) {
    mysqli_rollback($koneksi); // ada yang gagal: batalkan semua
    $_SESSION['pesan_error'] = 'Pengajuan gagal disimpan. Silakan coba lagi.';
    header('Location: ' . $balik);
    exit;
}

$_SESSION['pesan'] = 'Pengajuan sewa berhasil dibuat. Silakan selesaikan pembayaran.';
header('Location: bayar.php?id=' . $id_sewa);
exit;