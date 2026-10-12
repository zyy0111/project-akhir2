<?php
require_once '../../includes/auth_check.php';
wajib_login();

date_default_timezone_set('Asia/Jakarta');

$id      = (int) ($_GET['id'] ?? 0);
$id_user = (int) $_SESSION['id_user'];

if ($id <= 0) {
    $_SESSION['pesan_error'] = 'Data penyewaan tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Ambil penyewaan milik saya
$stmt = mysqli_prepare($koneksi,
    "SELECT id_sewa, total_biaya, status, status_pembayaran, metode_pembayaran
     FROM penyewaan WHERE id_sewa = ? AND id_user = ?");
mysqli_stmt_bind_param($stmt, 'ii', $id, $id_user);
mysqli_stmt_execute($stmt);
$sewa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$sewa) {
    $_SESSION['pesan_error'] = 'Data penyewaan tidak ditemukan.';
    header('Location: index.php');
    exit;
}

// Sudah lunas -> tidak perlu bayar lagi
if ($sewa['status_pembayaran'] === 'lunas') {
    $_SESSION['pesan'] = 'Penyewaan ini sudah dibayar.';
    header('Location: detail.php?id=' . $id);
    exit;
}

// Hanya yang masih menunggu yang boleh dibayar
if ($sewa['status'] !== 'menunggu') {
    $_SESSION['pesan_error'] = 'Penyewaan ini sudah diproses sehingga tidak bisa dibayar.';
    header('Location: detail.php?id=' . $id);
    exit;
}

// Ambil ringkasan alat untuk ditampilkan
$stmt = mysqli_prepare($koneksi,
    "SELECT d.jumlah, d.subtotal, a.nama_alat
     FROM detail_penyewaan d
     JOIN alat a ON d.id_alat = a.id_alat
     WHERE d.id_sewa = ?
     ORDER BY a.nama_alat");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);

$items = [];
while ($row = mysqli_fetch_assoc($hasil)) {
    $items[] = $row;
}

$metode_boleh = ['QRIS', 'DANA', 'OVO', 'GoPay', 'Transfer Bank'];

$judul_halaman = 'Bayar Sewa #' . $sewa['id_sewa'];
require_once '../../includes/header.php';
?>

<div class="katalog-kepala">
    <h2>Pembayaran Sewa #<?= $sewa['id_sewa'] ?></h2>
    <p class="petunjuk">Simulasi pembayaran — pilih metode lalu klik Bayar. Status langsung menjadi lunas.</p>
</div>

<?php if (isset($_SESSION['pesan_error'])): ?>
    <p class="alert-error"><?= htmlspecialchars($_SESSION['pesan_error']) ?></p>
    <?php unset($_SESSION['pesan_error']); ?>
<?php endif; ?>

<div class="ringkasan-layout">
    <div class="card">
        <h3>Ringkasan Tagihan</h3>
        <?php foreach ($items as $it): ?>
            <p class="baris-info">
                <?= htmlspecialchars($it['nama_alat']) ?> (<?= (int) $it['jumlah'] ?>x)
                — <strong><?= rupiah($it['subtotal']) ?></strong>
            </p>
        <?php endforeach; ?>
        <div class="kotak-total" style="margin-top:12px;">
            <div class="baris total">
                <span>Total Bayar</span>
                <span><?= rupiah($sewa['total_biaya']) ?></span>
            </div>
        </div>
        <p class="catatan-info">Admin baru bisa menyetujui setelah pembayaran lunas.</p>
    </div>

    <div class="card">
        <h3>Pilih Metode</h3>
        <form method="POST" action="proses_bayar.php" id="form-bayar">
            <input type="hidden" name="id_sewa" value="<?= $sewa['id_sewa'] ?>">
            <?php foreach ($metode_boleh as $m): ?>
                <label class="metode-bayar">
                    <input type="radio" name="metode" value="<?= htmlspecialchars($m) ?>" required>
                    <?= logo_pembayaran($m) ?>
                    <span><?= htmlspecialchars($m) ?></span>
                </label>
            <?php endforeach; ?>

            <!-- QR hanya untuk QRIS -->
            <div id="panel-qris" class="panel-dummy" style="display:none;">
                <h4>Scan QRIS Dummy</h4>
                <img class="qr-dummy" src="<?= BASE_URL ?>/assets/img/bayar/dummy-qris.svg" alt="QRIS dummy">
                <p class="catatan-info">Buka kamera / aplikasi apa pun lalu scan gambar dummy ini (simulasi, tidak memotong saldo).</p>
            </div>

            <!-- Input biasa untuk e-wallet -->
            <div id="panel-ewallet" class="panel-dummy" style="display:none;">
                <h4 id="judul-ewallet">Nomor E-Wallet</h4>
                <input type="tel" name="nomor_ewallet" id="nomor-ewallet"
                    class="input-bayar" placeholder="Contoh: 0812xxxxxxx"
                    pattern="[0-9]{10,15}" title="Nomor HP 10-15 digit angka">
                <p class="catatan-info" id="teks-ewallet"></p>
            </div>

            <!-- Input biasa untuk transfer bank -->
            <div id="panel-bank" class="panel-dummy" style="display:none;">
                <h4>Transfer Bank</h4>
                <select name="bank" id="pilih-bank" class="input-bayar">
                    <option value="">-- Pilih Bank --</option>
                    <option value="BCA">BCA</option>
                    <option value="BRI">BRI</option>
                    <option value="Mandiri">Mandiri</option>
                    <option value="BNI">BNI</option>
                </select>
                <input type="text" name="nomor_rekening" id="nomor-rekening"
                    class="input-bayar" placeholder="Nomor rekening (10-16 digit)"
                    pattern="[0-9]{10,16}" title="Nomor rekening 10-16 digit angka">
                <p class="catatan-info">Transfer ke VA dummy sesuai bank yang dipilih sebesar <?= rupiah($sewa['total_biaya']) ?> (simulasi).</p>
            </div>

            <button type="submit" class="btn" style="width:100%; margin-top:12px;"
                onclick="return confirm('Bayar <?= rupiah($sewa['total_biaya']) ?> sekarang?')">
                Saya Sudah Bayar / Bayar Sekarang
            </button>
        </form>
        <a class="btn btn-garis" style="width:100%; text-align:center;" href="detail.php?id=<?= $sewa['id_sewa'] ?>">
            Bayar Nanti
        </a>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('form-bayar');
    var panelQris = document.getElementById('panel-qris');
    var panelEwallet = document.getElementById('panel-ewallet');
    var panelBank = document.getElementById('panel-bank');
    var judulEwallet = document.getElementById('judul-ewallet');
    var inputEwallet = document.getElementById('nomor-ewallet');
    var teksEwallet = document.getElementById('teks-ewallet');
    var pilihBank = document.getElementById('pilih-bank');
    var inputRekening = document.getElementById('nomor-rekening');
    var total = '<?= rupiah($sewa['total_biaya']) ?>';

    function sembunyiSemua() {
        panelQris.style.display = 'none';
        panelEwallet.style.display = 'none';
        panelBank.style.display = 'none';
        inputEwallet.required = false;
        pilihBank.required = false;
        inputRekening.required = false;
    }

    form.addEventListener('change', function (e) {
        if (e.target.name !== 'metode') return;
        sembunyiSemua();
        var v = e.target.value;
        if (v === 'QRIS') {
            panelQris.style.display = 'block';
        } else if (v === 'DANA' || v === 'OVO' || v === 'GoPay') {
            judulEwallet.textContent = 'Nomor ' + v;
            inputEwallet.placeholder = 'Nomor ' + v + ' (contoh: 0812xxxxxxx)';
            teksEwallet.textContent = 'Masukkan nomor ' + v + ' kamu untuk bayar ' + total + ' (simulasi).';
            panelEwallet.style.display = 'block';
            inputEwallet.required = true;
        } else if (v === 'Transfer Bank') {
            panelBank.style.display = 'block';
            pilihBank.required = true;
            inputRekening.required = true;
        }
    });
})();
</script>

<?php require_once '../../includes/footer.php'; ?>
