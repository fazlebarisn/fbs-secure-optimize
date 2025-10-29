/**
 * Lazy Loading JavaScript for FBS Secure Optimize Plugin
 * 
 * @package FBS_Optimize
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // Simple lazy loading implementation
    const lazyImages = document.querySelectorAll('img[data-src]');
    const lazyIframes = document.querySelectorAll('iframe[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                img.classList.add('lazy-loaded');
                observer.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
    lazyIframes.forEach(iframe => imageObserver.observe(iframe));
});