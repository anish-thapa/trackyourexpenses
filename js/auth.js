// js/auth.js

// LOG 0 REMOVED: console.log('auth.js: SCRIPT LOADED AND STARTING TO EXECUTE.');

document.addEventListener('DOMContentLoaded', function() {
    // LOG 1 REMOVED: console.log('auth.js: DOMContentLoaded event fired.');

    function displayPageMessage(message, type) {
        const pageMessageArea = document.getElementById('page-message-area');
        if (pageMessageArea) {
            const messageTypeClass = type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger');
            pageMessageArea.innerHTML = `
                <div class="alert ${messageTypeClass} alert-dismissible fade show mt-3" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        } else {
            console.warn("auth.js: Message area 'page-message-area' not found. Using native alert().");
            alert(`${type.toUpperCase()}: ${message}`);
        }
    }

    // --- Registration Form Handler ---
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(registerForm);
            const submitButton = registerForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;
            submitButton.disabled = true; submitButton.textContent = 'Processing...';
            fetch('api/auth_handler.php', { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                displayPageMessage(data.message, data.status);
                if (data.status === 'success') registerForm.reset();
            }).catch(err => {
                console.error("Register fetch error:", err);
                displayPageMessage('Network error during registration.', 'error');
            }).finally(() => { submitButton.disabled = false; submitButton.textContent = originalButtonText; });
        });
    }

    // --- Login Form Handler ---
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(loginForm);
            const submitButton = loginForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;
            submitButton.disabled = true; submitButton.textContent = 'Logging In...';
            fetch('api/auth_handler.php', { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                displayPageMessage(data.message, data.status);
                if (data.status === 'success' && data.redirect) {
                    setTimeout(() => { window.location.href = data.redirect; }, 1000);
                } else if (data.status !== 'success') { // Re-enable button only on login fail
                    submitButton.disabled = false;
                    submitButton.textContent = originalButtonText;
                }
            }).catch(err => {
                console.error("Login fetch error:", err);
                displayPageMessage('Network error during login.', 'error');
                submitButton.disabled = false; submitButton.textContent = originalButtonText; // Also re-enable on catch
            });
            // Note: finally block was removed for login to avoid re-enabling button if successful redirect is planned
        });
    }

    // --- Profile Details Form Handler ---
    const profileDetailsForm = document.getElementById('profileDetailsForm');
    // LOG 2 REMOVED: console.log('auth.js: Attempting to find profileDetailsForm. Found:', profileDetailsForm);

    if (profileDetailsForm) {
        // LOG 3 REMOVED: console.log('auth.js: profileDetailsForm FOUND. Attaching submit listener.');
        profileDetailsForm.addEventListener('submit', function(event) {
            // LOG 4 (submit event) can be kept if desired during active dev, or removed:
            // console.log('auth.js: profileDetailsForm SUBMIT EVENT FIRED. Intercepting...');
            event.preventDefault();

            const submitButton = profileDetailsForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Updating...';
            const formData = new FormData(profileDetailsForm);

            fetch('api/auth_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                // LOG 5 REMOVED: console.log('auth.js: profileDetailsForm AJAX success. Response:', data);
                displayPageMessage(data.message, data.status);
                if (data.status === 'success') {
                    const newUsername = formData.get('username');
                    const navbarUsernameElement = document.querySelector('#navbarDropdownUser');
                    if (navbarUsernameElement && newUsername) {
                        let currentHTML = navbarUsernameElement.innerHTML;
                        let iconHTML = '';
                        const iconMatch = currentHTML.match(/<i class="[^"]+ fas fa-user-circle me-1"><\/i>/); // More specific icon match
                        if(iconMatch) iconHTML = iconMatch[0]; else iconHTML = '<i class="fas fa-user-circle me-1"></i>'; // Fallback icon
                        navbarUsernameElement.innerHTML = iconHTML + ' ' + newUsername; // Add space
                    }
                }
            })
            .catch(error => {
                console.error('auth.js: Profile Details Update Fetch Error:', error); // LOG 6 (Kept as it's an actual error)
                displayPageMessage('A network error occurred while updating details. Please try again.', 'error');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            });
        });
    } else {
        // LOG 2 (Alternative) REMOVED: console.warn('auth.js: profileDetailsForm NOT FOUND on this page.');
    }

    // --- Change Password Form Handler ---
    const changePasswordForm = document.getElementById('changePasswordForm');
    // LOG 7 REMOVED: console.log('auth.js: Attempting to find changePasswordForm. Found:', changePasswordForm);

    if (changePasswordForm) {
        // LOG 8 REMOVED: console.log('auth.js: changePasswordForm FOUND. Attaching submit listener.');
        changePasswordForm.addEventListener('submit', function(event) {
            // LOG 9 (submit event) can be kept if desired during active dev, or removed:
            // console.log('auth.js: changePasswordForm SUBMIT EVENT FIRED. Intercepting...');
            event.preventDefault();

            const submitButton = changePasswordForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Changing...';
            const formData = new FormData(changePasswordForm);

            fetch('api/auth_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! Status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                // LOG 10 REMOVED: console.log('auth.js: changePasswordForm AJAX success. Response:', data);
                displayPageMessage(data.message, data.status);
                if (data.status === 'success') {
                    changePasswordForm.reset();
                }
            })
            .catch(error => {
                console.error('auth.js: Change Password Fetch Error:', error); // LOG 11 (Kept as it's an actual error)
                displayPageMessage('A network error occurred while changing password. Please try again.', 'error');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            });
        });
    } else {
        // LOG 7 (Alternative) REMOVED: console.warn('auth.js: changePasswordForm NOT FOUND on this page.');
    }
});