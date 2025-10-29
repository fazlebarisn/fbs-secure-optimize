/**
 * Login Security JavaScript for FBS Secure Optimize Plugin
 * 
 * @package FBS_Optimize
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    // Add rate limiting notice
    const loginForm = document.getElementById('loginform');
    if (loginForm) {
        const notice = document.createElement('div');
        notice.className = 'fbsseop-login-warning';
        notice.innerHTML = fbsseopLoginSecurity.loginWarning || 'For security purposes, login attempts are limited. Multiple failed attempts will result in temporary IP blocking.';
        loginForm.parentNode.insertBefore(notice, loginForm);
    }
});
