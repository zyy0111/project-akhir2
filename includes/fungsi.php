<?php
// Kumpulan fungsi bantu yang dipakai di banyak halaman

// Mengubah angka menjadi tulisan rupiah. Contoh: 50000 -> Rp 50.000
function rupiah($angka) {
    return 'Rp ' . number_format((int) $angka, 0, ',', '.');
}

// Memilih emoji berdasarkan nama kategori (dipakai kalau alat belum punya foto)
function ikon_kategori($nama) {
    $n = strtolower($nama);

    if (str_contains($n, 'tenda'))                                  return '⛺';
    if (str_contains($n, 'tidur'))                                  return '🛌';
    if (str_contains($n, 'masak'))                                  return '🍳';
    if (str_contains($n, 'terang') || str_contains($n, 'lampu'))    return '🔦';
    if (str_contains($n, 'carrier') || str_contains($n, 'ransel'))  return '🎒';
    if (str_contains($n, 'aksesoris'))                              return '🧭';

    return '🏕️';
}

// Menampilkan foto alat. Kalau belum ada foto, tampilkan emoji kategori.
function tampil_gambar_alat($gambar, $nama_kategori, $nama_alat) {
    if (!empty($gambar)) {
        $nama_file = basename($gambar);
        $lokasi    = __DIR__ . '/../uploads/alat/' . $nama_file;

        if (file_exists($lokasi)) {
            return '<img src="' . BASE_URL . '/uploads/alat/' . htmlspecialchars($nama_file)
                 . '" alt="' . htmlspecialchars($nama_alat) . '">';
        }
    }

    return '<span class="ikon-alat">' . ikon_kategori($nama_kategori) . '</span>';
}

// Menghitung jumlah hari dari dua tanggal (format 2026-10-07).
// Hasilnya: jumlah hari (1 sampai 30), atau 0 kalau tanggalnya tidak valid.
function hitung_durasi($tanggal_sewa, $tanggal_kembali) {
    $a = DateTime::createFromFormat('Y-m-d', $tanggal_sewa);
    $b = DateTime::createFromFormat('Y-m-d', $tanggal_kembali);

    // Tanggal harus benar-benar ada (bukan 2026-02-31, bukan tulisan asal)
    if (!$a || !$b
        || $a->format('Y-m-d') !== $tanggal_sewa
        || $b->format('Y-m-d') !== $tanggal_kembali) {
        return 0;
    }

    // Tanggal kembali tidak boleh sebelum tanggal sewa
    if ($b < $a) {
        return 0;
    }

    $hari = $a->diff($b)->days;

    // Sewa dan kembali di hari yang sama dihitung 1 hari
    if ($hari < 1) {
        $hari = 1;
    }

    // Maksimal 30 hari (sama seperti aturan di Rencana)
    if ($hari > 30) {
        return 0;
    }

    return $hari;
}

// Menyimpan foto alat yang diunggah.
// Hasilnya ada 3 kemungkinan:
//   - teks (nama file)  -> foto berhasil disimpan
//   - null              -> tidak ada foto yang dipilih (bukan kesalahan)
//   - false             -> ada kesalahan, alasannya diisi ke $pesan_error
function simpan_gambar_alat($file, &$pesan_error) {

    // Tidak ada file yang dipilih
    if (!is_array($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Ada masalah saat mengunggah
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            $pesan_error = 'Ukuran foto terlalu besar. Maksimal 2 MB.';
        } else {
            $pesan_error = 'Foto gagal diunggah. Silakan coba lagi.';
        }
        return false;
    }

    // Batas ukuran 2 MB
    if ($file['size'] > 2 * 1024 * 1024) {
        $pesan_error = 'Ukuran foto terlalu besar. Maksimal 2 MB.';
        return false;
    }

    // Cek isi file benar-benar gambar
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $pesan_error = 'File yang dipilih bukan gambar yang valid.';
        return false;
    }

    // Hanya format yang diizinkan
    $tipe_boleh = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($tipe_boleh[$info[2]])) {
        $pesan_error = 'Format foto harus JPG, PNG, atau WEBP.';
        return false;
    }

    // Siapkan folder tujuan
    $folder = __DIR__ . '/../uploads/alat/';
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    // Nama file baru yang acak
    $nama_file = 'alat_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $tipe_boleh[$info[2]];

    if (!move_uploaded_file($file['tmp_name'], $folder . $nama_file)) {
        $pesan_error = 'Foto gagal disimpan di server.';
        return false;
    }

    return $nama_file;
}

// Menampilkan logo metode pembayaran (SVG lokal di assets/img/bayar/).
// Kalau file tidak ada, tampilkan teks biasa sebagai cadangan.
function logo_pembayaran($metode) {
    $peta = [
        'QRIS'          => 'qris.svg',
        'DANA'          => 'dana.svg',
        'OVO'           => 'ovo.svg',
        'GoPay'         => 'gopay.svg',
        'Transfer Bank' => 'transfer.svg',
    ];

    $nama = htmlspecialchars($metode);

    if (!isset($peta[$metode])) {
        return '<span class="logo-bayar-teks">' . $nama . '</span>';
    }

    return '<img class="logo-bayar" src="' . BASE_URL . '/assets/img/bayar/'
        . $peta[$metode] . '" alt="' . $nama . '" loading="lazy">';
}

// Gambar dummy untuk simulasi pembayaran.
// Hanya QRIS yang memakai QR. Metode lain memakai inputan biasa.
function gambar_dummy_bayar($metode) {
    if ($metode !== 'QRIS') {
        return '';
    }

    return '<img class="qr-dummy" src="' . BASE_URL . '/assets/img/bayar/'
        . 'dummy-qris.svg" alt="Kode dummy QRIS" loading="lazy">';
}

// Menghapus file foto alat dari folder (kalau ada)
function hapus_gambar_alat($nama_file) {
    if (empty($nama_file)) {
        return;
    }

    $lokasi = __DIR__ . '/../uploads/alat/' . basename($nama_file);
    if (is_file($lokasi)) {
        unlink($lokasi);
    }
}