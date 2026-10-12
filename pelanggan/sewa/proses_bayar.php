<?php
require_once '../../includes/auth_check.php';
wajib_login();

date_default_timezone_set('Asia/Jakarta');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Hanya boleh lewat tombol (POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id_user  = (int) $_SESSION['id_user'];
$id_sewa  = (int) ($_POST['id_sewa'] ?? 0);
$metode   = trim($_POST['metode'] ?? '');

$metode_boleh = ['QRIS', 'DANA', 'OVO', 'GoPay', 'Transfer Bank'];
$bank_boleh   = ['BCA', 'BRI', 'Mandiri', 'BNI'];

if ($id_sewa <= 0 || !in_array($metode, $metode_boleh, true)) {
    $_SESSION['pesan_error'] = 'Metode pembayaran tidak valid.';
    header('Location: index.php');
    exit;
}

// Validasi inputan biasa sesuai metode (QRIS tanpa input tambahan)
$detail = null;

if (in_array($metode, ['DANA', 'OVO', 'GoPay'], true)) {
    $nomor = preg_replace('/\D/', '', $_POST['nomor_ewallet'] ?? '');
    if (strlen($nomor) < 10 || strlen($nomor) > 15) {
        $_SESSION['pesan_error'] = 'Nomor ' . $metode . ' harus 10-15 digit angka.';
        header('Location: bayar.php?id=' . $id_sewa);
        exit;
    }
    $detail = $nomor;
} elseif ($metode === 'Transfer Bank') {
    $bank = trim($_POST['bank'] ?? '');
    $rek  = preg_replace('/\D/', '', $_POST['nomor_rekening'] ?? '');
    if (!in_array($bank, $bank_boleh, true)) {
        $_SESSION['pesan_error'] = 'Pilih bank yang valid.';
        header('Location: bayar.php?id=' . $id_sewa);
        exit;
    }
    if (strlen($rek) < 10 || strlen($rek) > 16) {
        $_SESSION['pesan_error'] = 'Nomor rekening harus 10-16 digit angka.';
        header('Location: bayar.php?id=' . $id_sewa);
        exit;
    }
    $detail = $bank . ' ' . $rek;
}

try {
    mysqli_begin_transaction($koneksi);

    // Kunci baris + pastikan milik saya
    $stmt = mysqli_prepare($koneksi,
        "SELECT status, status_pembayaran FROM penyewaan
         WHERE id_sewa = ? AND id_user = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'ii', $id_sewa, $id_user);
    mysqli_stmt_execute($stmt);
    $sewa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$sewa) {
        throw new RuntimeException('Data penyewaan tidak ditemukan.');
    }
    if ($sewa['status_pembayaran'] === 'lunas') {
        throw new RuntimeException('Penyewaan ini sudah dibayar.');
    }
    if ($sewa['status'] !== 'menunggu') {
        throw new RuntimeException('Penyewaan ini sudah diproses sehingga tidak bisa dibayar.');
    }

    $stmt = mysqli_prepare($koneksi,
        "UPDATE penyewaan
         SET status_pembayaran = 'lunas', metode_pembayaran = ?, detail_pembayaran = ?, tanggal_bayar = NOW()
         WHERE id_sewa = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $metode, $detail, $id_sewa);
    mysqli_stmt_execute($stmt);

    mysqli_commit($koneksi);

    $_SESSION['pesan'] = 'Pembayaran berhasil (' . $metode . '). Tunggu persetujuan admin.';
    header('Location: detail.php?id=' . $id_sewa);
    exit;

} catch (RuntimeException $e) {
    mysqli_rollback($koneksi);
    $_SESSION['pesan_error'] = $e->getMessage();
    header('Location: detail.php?id=' . $id_sewa);
    exit;

} catch (Throwable $e) {
    mysqli_rollback($koneksi);
    $_SESSION['pesan_error'] = 'Pembayaran gagal. Silakan coba lagi.';
    header('Location: bayar.php?id=' . $id_sewa);
    exit;
}
