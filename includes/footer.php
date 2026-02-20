<!-- MAIN CONTENT END -->
</main>

<!-- FOOTER -->
<footer class="main-footer">
    <div class="container-fluid">
        <div class="row">
            <!-- About Section with Brand -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="footer-brand">
                    <div class="brand-logo">
                        <img src="<?php echo SITE_URL; ?>assets/images/TVRI_Sulawesi_Utara.png" alt="TVRI Sulut">
                    </div>
                    <div>
                        <div class="brand-name">SULUT NEWS HUB</div>
                        <div class="brand-tagline">TVRI Sulawesi Utara</div>
                    </div>
                </div>
                <p class="footer-description">
                    Portal berita resmi TVRI Sulawesi Utara yang menyajikan 
                    informasi terkini, akurat, dan terpercaya untuk masyarakat Sulawesi Utara.
                </p>
                <div class="footer-social">
                    <?php
                    // Reuse social links loaded in header.php
                    $social_links = $_shared_social_links ?? [];
                    ?>
                    
                    <?php if (!empty($social_links['facebook_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['facebook_url']); ?>" target="_blank" class="social-btn">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_links['twitter_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['twitter_url']); ?>" target="_blank" class="social-btn">
                            <i class="fab fa-twitter"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_links['instagram_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['instagram_url']); ?>" target="_blank" class="social-btn">
                            <i class="fab fa-instagram"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($social_links['youtube_url'])): ?>
                        <a href="<?php echo htmlspecialchars($social_links['youtube_url']); ?>" target="_blank" class="social-btn">
                            <i class="fab fa-youtube"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="footer-title">
                    <i class="fas fa-link"></i> Link Cepat
                </h5>
                <ul class="footer-links">
                    <li><a href="<?php echo SITE_URL; ?>"><i class="fas fa-angle-right"></i> Beranda</a></li>
                    <li><a href="<?php echo SITE_URL; ?>video.php"><i class="fas fa-angle-right"></i> Video</a></li>
                    <li><a href="<?php echo SITE_URL; ?>tentang.php"><i class="fas fa-angle-right"></i> Tentang Kami</a></li>
                    <li><a href="<?php echo SITE_URL; ?>admin/login.php"><i class="fas fa-angle-right"></i> Login Admin</a></li>
                </ul>
            </div>
            
            <!-- Kategori -->
            <div class="col-lg-2 col-md-6 mb-4">
                <h5 class="footer-title">
                    <i class="fas fa-list"></i> Kategori
                </h5>
                <ul class="footer-links">
                    <?php
                    // Reuse categories loaded in header.php (limit 6 for footer)
                    $footer_cats = array_slice($_shared_categories ?? [], 0, 6);
                    foreach ($footer_cats as $cat):
                    ?>
                        <li>
                            <a href="<?php echo SITE_URL; ?>kategori.php?slug=<?php echo $cat['slug']; ?>">
                                <i class="fas fa-angle-right"></i> <?php echo htmlspecialchars($cat['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Contact Info -->
            <div class="col-lg-3 col-md-6 mb-4">
                <h5 class="footer-title">
                    <i class="fas fa-address-book"></i> Kontak
                </h5>
                <?php
                // Reuse contact info loaded in header.php
                $contact_info = $_shared_contact_info ?? [];
                ?>
                
                <ul class="footer-contact">
                    <?php if (!empty($contact_info['contact_address'])): ?>
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($contact_info['contact_address']); ?></span>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_info['contact_phone'])): ?>
                        <li>
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($contact_info['contact_phone']); ?></span>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (!empty($contact_info['contact_email'])): ?>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span><?php echo htmlspecialchars($contact_info['contact_email']); ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p class="copyright-text">
                &copy; <?php echo date('Y'); ?> <strong>TVRI Sulawesi Utara</strong>. All Rights Reserved. 
                | Developed by <strong>Tim Magang TVRI Sulut</strong>
            </p>
        </div>
    </div>
</footer>

<!-- SCROLL TO TOP BUTTON -->
<button id="scrollTopBtn" class="scroll-top-btn" title="Kembali ke atas">
    <i class="fas fa-arrow-up"></i>
</button>

<!-- SCRIPTS -->

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo SITE_URL; ?>assets/js/main.js?v=<?php echo ASSET_VERSION; ?>"></script>

<?php
// Persistent connections manage their own lifecycle.
// closeDBConnection($conn); // Disabled — persistent conn (p:) prefix in db.php
?>

</body>
</html>
