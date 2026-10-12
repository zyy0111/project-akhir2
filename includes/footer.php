</main>

<footer class="site-footer">
    <div class="footer-grid">

        <div>
            <?php if (!empty($logo_file)): ?>
                <img class="footer-logo" src="<?= BASE_URL ?>/assets/img/<?= $logo_file ?>" alt="TerraCamp">
            <?php else: ?>
                <div class="footer-brand">TerraCamp</div>
            <?php endif; ?>
            <p>Prepare Your Gear, Start Your Adventure.</p>
        </div>

        <div>
            <h4>Menu</h4>
            <p><a href="<?= BASE_URL ?>/index.php">Beranda</a></p>
            <p><a href="<?= BASE_URL ?>/pelanggan/alat.php">Katalog</a></p>
            <p><a href="<?= BASE_URL ?>/index.php#cara-sewa">Cara Sewa</a></p>
        </div>

        <div>
            <h4>Layanan</h4>
            <p>Sewa Alat Camping</p>
            <p>Perencanaan Kebutuhan Camping</p>
            <p>Pengembalian &amp; Denda</p>
        </div>

        <div>
            <h4>Kontak</h4>
            <p>Jl. Kesadaran No. 7, Jakarta</p>
            <p>+62 123 456 789</p>
            <p>hello@terracamp.id</p>
        </div>

    </div>

    <div class="footer-bawah">
        &copy; <?= date('Y') ?> TerraCamp. All rights reserved.
    </div>
</footer>

</body>
</html>