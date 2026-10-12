<?php
require_once '../../includes/auth_check.php';
wajib_admin();

// Supaya kesalahan database bisa ditangkap oleh try/catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Hanya boleh lewat tombol (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id_sewa = (int) $_POST['id_sewa'];

try {
    mysqli_begin_transaction($koneksi);

    // 1. Kunci data penyewaan, lalu pastikan statusnya masih "menunggu"
    // dan pembayarannya sudah lunas
    $stmt = mysqli_prepare($koneksi,
        "SELECT status, status_pembayaran FROM penyewaan WHERE id_sewa = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);
    $sewa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$sewa || $sewa['status'] !== 'menunggu') {
        throw new RuntimeException('Pengajuan ini sudah diproses atau tidak ditemukan.');
    }

    if (($sewa['status_pembayaran'] ?? 'belum_bayar') !== 'lunas') {
        throw new RuntimeException('Belum bisa disetujui karena pelanggan belum membayar.');
    }

    // 2. Ambil alat yang disewa
    $stmt = mysqli_prepare($koneksi,
        "SELECT d.id_alat, d.jumlah, a.nama_alat
         FROM detail_penyewaan d
         JOIN alat a ON d.id_alat = a.id_alat
         WHERE d.id_sewa = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);

    $items = [];
    while ($row = mysqli_fetch_assoc($hasil)) {
        $items[] = $row;
    }

    // 3. Kurangi stok setiap alat (hanya kalau stoknya cukup)
    foreach ($items as $it) {
        $jumlah  = (int) $it['jumlah'];
        $id_alat = (int) $it['id_alat'];

        $stmt = mysqli_prepare($koneksi,
            "UPDATE alat
             SET stok_tersedia = stok_tersedia - ?
             WHERE id_alat = ? AND stok_tersedia >= ?");
        mysqli_stmt_bind_param($stmt, 'iii', $jumlah, $id_alat, $jumlah);
        mysqli_stmt_execute($stmt);

        // Kalau tidak ada baris yang berubah, berarti stok tidak cukup
        if (mysqli_stmt_affected_rows($stmt) === 0) {
            throw new RuntimeException('Stok ' . $it['nama_alat'] . ' tidak cukup.');
        }
    }

    // 4. Ubah status menjadi "dipinjam"
    $stmt = mysqli_prepare($koneksi,
        "UPDATE penyewaan SET status = 'dipinjam' WHERE id_sewa = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);

    mysqli_commit($koneksi);   // semua berhasil: simpan

    $_SESSION['pesan'] = 'Pengajuan disetujui. Alat diserahkan dan stok sudah dikurangi.';

} catch (RuntimeException $e) {
    mysqli_rollback($koneksi); // batalkan semuanya
    $_SESSION['pesan_error'] = $e->getMessage();

} catch (Throwable $e) {
    mysqli_rollback($koneksi);
    $_SESSION['pesan_error'] = 'Terjadi kesalahan saat memproses. Silakan coba lagi.';
}

header('Location: detail.php?id=' . $id_sewa);
exit;