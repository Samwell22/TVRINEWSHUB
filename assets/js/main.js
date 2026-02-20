/**
 * TVRI Sulut News Hub - Main JS
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // NAVBAR SCROLL EFFECT
    const navbar = document.querySelector('.main-header');
    const topBar = document.querySelector('.top-bar');
    
    function handleScroll() {
        const scrollY = window.pageYOffset || document.documentElement.scrollTop;
        
        // Add scrolled class for enhanced shadow
        if (navbar) {
            if (scrollY > 60) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
        
        // Keep top-bar stable to prevent layout jitter on public pages
        if (topBar) {
            topBar.classList.remove('top-bar-hidden');
        }
    }
    
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // run on load
    
    // SCROLL TO TOP BUTTON
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    
    if (scrollTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('show');
            } else {
                scrollTopBtn.classList.remove('show');
            }
        }, { passive: true });
        
        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // LAZY LOAD VIDEO
    // Ketika thumbnail video diklik, load video player
    const videoThumbnails = document.querySelectorAll('.video-thumbnail');
    
    videoThumbnails.forEach(function(thumbnail) {
        thumbnail.addEventListener('click', function() {
            const videoContainer = this.closest('.video-container');
            const videoType = this.getAttribute('data-video-type'); // 'file' atau 'youtube'
            const videoSrc = this.getAttribute('data-video-src');
            
            if (videoType === 'youtube') {
                // Load YouTube iframe
                const iframe = document.createElement('iframe');
                iframe.setAttribute('src', videoSrc + '?autoplay=1');
                iframe.setAttribute('frameborder', '0');
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                iframe.setAttribute('allowfullscreen', 'true');
                iframe.classList.add('video-player');
                
                // Replace thumbnail with iframe
                videoContainer.innerHTML = '';
                videoContainer.appendChild(iframe);
                
            } else if (videoType === 'file') {
                // Load local video file
                const video = document.createElement('video');
                video.setAttribute('controls', 'true');
                video.setAttribute('autoplay', 'true');
                video.classList.add('video-player');
                
                const source = document.createElement('source');
                source.setAttribute('src', videoSrc);
                source.setAttribute('type', 'video/mp4');
                
                video.appendChild(source);
                
                // Replace thumbnail with video
                videoContainer.innerHTML = '';
                videoContainer.appendChild(video);
            }
        });
    });
    
    // SMOOTH SCROLL FOR ANCHOR LINKS
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    
    anchorLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Jangan handle jika href hanya "#" atau untuk modal/collapse
            if (href === '#' || this.hasAttribute('data-bs-toggle')) {
                return;
            }
            
            e.preventDefault();
            
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // IMAGE LAZY LOADING (untuk berita grid)
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.getAttribute('data-src');
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(function(img) {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for browsers that don't support IntersectionObserver
        lazyImages.forEach(function(img) {
            img.src = img.getAttribute('data-src');
            img.removeAttribute('data-src');
        });
    }
    
    // NAVBAR ACTIVE STATE
    // (handled by .scrolled class in CSS)
    
    // AUTO CLOSE NAVBAR ON MOBILE WHEN LINK CLICKED
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    navLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    toggle: false
                });
                bsCollapse.hide();
            }
        });
    });
    
    // SEARCH MODAL - AUTO FOCUS ON INPUT
    const searchModal = document.getElementById('searchModal');
    if (searchModal) {
        searchModal.addEventListener('shown.bs.modal', function() {
            const searchInput = searchModal.querySelector('input[name="q"]');
            if (searchInput) {
                searchInput.focus();
            }
        });
    }
    
    // TOOLTIP INITIALIZATION (Bootstrap 5)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // VIEW COUNTER (untuk halaman detail berita)
    // Increment view count via AJAX
    const newsArticle = document.querySelector('[data-news-id]');
    if (newsArticle) {
        const newsId = newsArticle.getAttribute('data-news-id');
        
        // Send AJAX request to increment views
        fetch('ajax/increment_views.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'news_id=' + newsId
        })
        .then(response => response.json())
        .then(data => {
            // View counted
        })
        .catch(() => { /* silent fail */ });
    }
    
    // SHARE BUTTONS
    const shareButtons = document.querySelectorAll('.share-btn');
    
    shareButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            const title = this.getAttribute('data-title');
            const platform = this.getAttribute('data-platform');
            
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
                    break;
                case 'twitter':
                    shareUrl = 'https://twitter.com/intent/tweet?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title);
                    break;
                case 'whatsapp':
                    shareUrl = 'https://wa.me/?text=' + encodeURIComponent(title + ' ' + url);
                    break;
                case 'telegram':
                    shareUrl = 'https://t.me/share/url?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(title);
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        });
    });
    
    // PRINT BUTTON
    const printBtn = document.querySelector('.print-btn');
    if (printBtn) {
        printBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
        });
    }
    
    // READ MORE / READ LESS TOGGLE
    const readMoreBtns = document.querySelectorAll('.read-more-btn');
    
    readMoreBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const content = this.previousElementSibling;
            content.classList.toggle('expanded');
            
            if (content.classList.contains('expanded')) {
                this.textContent = 'Baca Lebih Sedikit';
            } else {
                this.textContent = 'Baca Selengkapnya';
            }
        });
    });
    
});

// UTILITY FUNCTIONS
/**
 * Escape HTML entities to prevent XSS in dynamic content.
 * Global utility — used by inline scripts across the site.
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Link berhasil disalin!');
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('Link berhasil disalin!');
    }
}
