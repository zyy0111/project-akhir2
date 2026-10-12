<?php
// Mulai session (dipakai nanti untuk login)
session_start();

// Alamat dasar project, dipakai untuk membuat link & path file
define('BASE_URL', '/terracamp');

// Data koneksi database XAMPP (bawaan)
$host = 'localhost';
$user = 'root';
$pass = '';          // kosong untuk XAMPP bawaan
$db   = 'terracamp2';

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}

// Supaya huruf/karakter khusus tampil benar
mysqli_set_charset($koneksi, 'utf8mb4');