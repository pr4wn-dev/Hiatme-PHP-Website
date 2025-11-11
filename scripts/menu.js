document.addEventListener('DOMContentLoaded', () => {
    console.log('Menu script loaded');
    const menuButtons = document.querySelectorAll('.menu-btn');
    const dropdown = document.querySelector('.dropdown-menu');
    const loginBtn = document.querySelector('#login-btn');
    const profileBtn = document.querySelector('#profile-btn');
    const avatarLabel = document.querySelector('.avatar-box .avatar-label');
    const avatarImg = document.querySelector('.avatar-box .avatar-img');
    const loginModal = document.querySelector('#login-modal');
    const registerModal = document.querySelector('#register-modal');
    const forgotPasswordModal = document.querySelector('#forgot-password-modal');
    const resetPasswordModal = document.querySelector('#reset-password-modal');
    const profileModal = document.querySelector('#profile-modal');
    const loginForm = document.querySelector('#login-form');
    const registerForm = document.querySelector('#register-form');
    const forgotPasswordForm = document.querySelector('#forgot-password-form');
    const resetPasswordForm = document.querySelector('#reset-password-form');
    const profileForm = document.querySelector('#profile-form');
    const createAccountLink = document.querySelector('#create-account-link');
    const backToLoginLink = document.querySelector('#back-to-login-link');
    const forgotPasswordLink = document.querySelector('#forgot-password-link');
    const backToLoginFromForgotLink = document.querySelector('#back-to-login-from-forgot-link');
    const backToLoginFromResetLink = document.querySelector('#back-to-login-from-reset-link');
    const formToggleButtons = document.querySelectorAll('.form-toggle-btn');
    const avatarImage = document.querySelector('#avatar-image');
    const profilePictureInput = document.querySelector('#profile-picture');
    let currentUser = null;
    let refreshInterval = null;

    // Check for duplicate menu buttons
    if (menuButtons.length !== 1) {
        console.error(`Expected 1 menu button, found ${menuButtons.length}`);
    }
    const menuBtn = menuButtons[0]; // Use the first (and only) menu button

    // Periodic CSRF token refresh (every 10 minutes)
    function startCSRFTokenRefresh() {
        if (!refreshInterval) {
            refreshInterval = setInterval(refreshCSRFToken, 10 * 60 * 1000);
            console.log('CSRF token refresh started');
        }
    }

    function stopCSRFTokenRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
            console.log('CSRF token refresh stopped');
        }
    }

    function refreshCSRFToken() {
        console.log('Attempting to refresh CSRF token');
        fetch('/includes/hiatme_config.php?action=get_csrf_token', {
            method: 'GET',
            credentials: 'include'
        })
            .then(response => {
                if (!response.ok) throw new Error('Failed to fetch CSRF token: ' + response.status);
                return response.json();
            })
            .then(data => {
                if (data.success && data.csrf_token) {
                    updateCSRFToken(data.csrf_token);
                    console.log('CSRF token refreshed:', data.csrf_token);
                } else {
                    console.error('Invalid CSRF token response:', data);
                }
            })
            .catch(error => {
                console.error('Error refreshing CSRF token:', error);
            });
    }

    // Initial token fetch and start refresh
    refreshCSRFToken();
    startCSRFTokenRefresh();

    // Stop refresh when registration modal is opened
    if (registerModal) {
        registerModal.addEventListener('click', () => {
            if (registerModal.style.display === 'block') {
                stopCSRFTokenRefresh();
                console.log('Registration modal opened, token refresh paused');
            }
        });
    }

    // Resume refresh when modal is closed
    window.addEventListener('click', (event) => {
        if (event.target === registerModal && registerModal.style.display === 'block') {
            registerModal.style.display = 'none';
            startCSRFTokenRefresh();
            console.log('Registration modal closed, token refresh resumed');
        }
    });

    // Existing user state logic
    try {
        if (typeof initialUser !== 'undefined') {
            if (initialUser) {
                currentUser = initialUser;
                localStorage.setItem('currentUser', JSON.stringify(currentUser));
                console.log('Initial user set from server:', currentUser);
            } else {
                currentUser = null;
                localStorage.removeItem('currentUser');
                console.log('No initial user from server');
            }
        } else {
            const storedUser = localStorage.getItem('currentUser');
            if (storedUser) {
                currentUser = JSON.parse(storedUser);
                console.log('User loaded from localStorage:', currentUser);
            } else {
                console.log('No user in localStorage');
            }
        }
    } catch (error) {
        console.error('Error loading user state:', error);
    }

    function updateMenuState() {
        try {
            if (!avatarLabel || !avatarImg || !loginBtn) {
                console.error('Menu elements not found:', { avatarLabel, avatarImg, loginBtn });
                return;
            }
            if (currentUser) {
                avatarLabel.textContent = `${currentUser.name} (${currentUser.email})`;
                avatarImg.src = currentUser.profile_picture || 'images/avatar.png';
                loginBtn.textContent = 'Logout';
                loginBtn.href = '#';
                if (profileBtn) profileBtn.style.display = 'block';
            } else {
                avatarLabel.textContent = 'Guest@localhost';
                avatarImg.src = 'images/avatar.png';
                loginBtn.textContent = 'Login';
                loginBtn.href = '#';
                if (profileBtn) profileBtn.style.display = 'none';
            }
            console.log('Menu state updated:', currentUser ? currentUser.email : 'none');
        } catch (error) {
            console.error('Error updating menu state:', error);
        }
    }

    function showModal(modalId) {
        try {
            if (loginModal) loginModal.style.display = 'none';
            if (registerModal) registerModal.style.display = 'none';
            if (forgotPasswordModal) forgotPasswordModal.style.display = 'none';
            if (resetPasswordModal) resetPasswordModal.style.display = 'none';

            const modal = document.querySelector(modalId);
            if (modal) {
                modal.style.display = 'block';
                console.log(`Showing modal: ${modalId}`);
                if (modalId === '#register-modal') {
                    stopCSRFTokenRefresh();
                } else {
                    startCSRFTokenRefresh();
                }
            }

            formToggleButtons.forEach(btn => {
                const btnTarget = btn.getAttribute('data-target');
                if (btnTarget === modalId.replace('#', '')) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
        } catch (error) {
            console.error('Error showing modal:', error);
        }
    }

    function toggleButtonState(button, isLoading) {
        const spinner = button.querySelector('.spinner');
        if (isLoading) {
            button.disabled = true;
            button.style.cursor = 'not-allowed';
            button.style.opacity = '0.7';
            if (spinner) spinner.style.display = 'inline-block';
        } else {
            button.disabled = false;
            button.style.cursor = 'pointer';
            button.style.opacity = '1';
            if (spinner) spinner.style.display = 'none';
        }
    }

    function withTimeout(promise, ms, button, messageDiv, errorMessage) {
        const timeout = new Promise((_, reject) =>
            setTimeout(() => reject(new Error(errorMessage)), ms)
        );
        return Promise.race([promise, timeout]).catch(error => {
            toggleButtonState(button, false);
            messageDiv.innerText = error.message;
            messageDiv.classList.remove('success');
            messageDiv.classList.add('error');
            messageDiv.style.display = 'block';
            console.error('Request timed out:', error.message);
            throw error;
        });
    }

    function updateCSRFToken(newToken) {
        const csrfInputs = document.querySelectorAll('input[name="csrf_token"]');
        csrfInputs.forEach(input => {
            input.value = newToken;
            console.log('CSRF token updated in form:', input.closest('form').id, newToken);
        });
    }

    // Validate CSRF token with retries
    async function validateCSRFToken(csrfToken, retries = 3) {
        console.log('Validating CSRF token:', csrfToken);
        for (let attempt = 1; attempt <= retries; attempt++) {
            try {
                const response = await fetch('/includes/hiatme_config.php?action=validate_csrf_token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken
                    },
                    body: 'action=validate_csrf_token',
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success) {
                    console.log('CSRF token validated successfully');
                    return true;
                } else {
                    console.warn(`CSRF validation attempt ${attempt} failed:`, data.message);
                }
            } catch (error) {
                console.error(`CSRF validation attempt ${attempt} error:`, error);
            }

            // Fetch a fresh token if validation fails
            try {
                const response = await fetch('/includes/hiatme_config.php?action=get_csrf_token', {
                    method: 'GET',
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success && data.csrf_token) {
                    updateCSRFToken(data.csrf_token);
                    console.log('Fetched fresh CSRF token:', data.csrf_token);
                    return true;
                }
            } catch (error) {
                console.error(`Failed to fetch fresh token on attempt ${attempt}:`, error);
            }
        }
        console.error('CSRF token validation failed after retries');
        return false;
    }

    // Menu button and dropdown logic
    if (menuBtn && dropdown) {
        menuBtn.addEventListener('click', (event) => {
            event.preventDefault();
            dropdown.classList.toggle('active');
            console.log('Menu toggled:', dropdown.classList.contains('active'));
        });

        document.addEventListener('click', (event) => {
            const isClickInsideModal = (loginModal && loginModal.contains(event.target)) ||
                (registerModal && registerModal.contains(event.target)) ||
                (forgotPasswordModal && registerModal.contains(event.target)) ||
                (resetPasswordModal && resetPasswordModal.contains(event.target));
            const isClickInsideMenu = menuBtn.contains(event.target) || dropdown.contains(event.target);
            if (!isClickInsideMenu && !isClickInsideModal) {
                dropdown.classList.remove('active');
                console.log('Menu closed: Click outside');
            }
        });

        const menuLinks = dropdown.querySelectorAll('a');
        menuLinks.forEach(link => {
            link.addEventListener('click', () => {
                dropdown.classList.remove('active');
                console.log('Menu closed: Menu link clicked');
            });
        });
    } else {
        console.error('Menu button or dropdown not found:', { menuBtn, dropdown });
    }

    // Login/logout button logic
    if (loginBtn) {
        loginBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (currentUser) {
                console.log('Logout initiated');
                fetch('/includes/hiatme_config.php?action=get_csrf_token', {
                    method: 'GET',
                    credentials: 'include'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`Failed to fetch CSRF token: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success || !data.csrf_token) throw new Error('Invalid CSRF token response');
                        console.log('Fresh CSRF token for logout:', data.csrf_token);
                        return fetch('/includes/hiatme_config.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-CSRF-Token': data.csrf_token
                            },
                            body: 'action=logout',
                            credentials: 'include'
                        });
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            console.log('Logout successful:', data.message);
                            currentUser = null;
                            localStorage.removeItem('currentUser');
                            updateMenuState();
                            updateCSRFToken(data.csrf_token);
                            window.location.href = 'index.php';
                        } else {
                            throw new Error(data.message || 'Logout failed');
                        }
                    })
                    .catch(error => {
                        console.error('Logout error:', error);
                        currentUser = null;
                        localStorage.removeItem('currentUser');
                        updateMenuState();
                        window.location.href = 'index.php';
                    });
            } else {
                console.log('Showing login modal');
                showModal('#login-modal');
            }
        });
    }

    if (profileBtn) {
        profileBtn.addEventListener('click', (e) => {
            console.log('Profile link clicked, navigating to profile.php');
        });
    }

    if (createAccountLink) {
        createAccountLink.addEventListener('click', (e) => {
            e.preventDefault();
            showModal('#register-modal');
        });
    }

    if (backToLoginLink) {
        backToLoginLink.addEventListener('click', (e) => {
            e.preventDefault();
            showModal('#login-modal');
        });
    }

    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            showModal('#forgot-password-modal');
        });
    }

    if (backToLoginFromForgotLink) {
        backToLoginFromForgotLink.addEventListener('click', (e) => {
            e.preventDefault();
            showModal('#login-modal');
        });
    }

    if (backToLoginFromResetLink) {
        backToLoginFromResetLink.addEventListener('click', (e) => {
            e.preventDefault();
            showModal('#login-modal');
        });
    }

    formToggleButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const targetModal = button.getAttribute('data-target');
            console.log(`Toggle button clicked: ${button.textContent}, targeting ${targetModal}`);
            showModal(`#${targetModal}`);
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target === loginModal) loginModal.style.display = 'none';
        if (event.target === forgotPasswordModal) forgotPasswordModal.style.display = 'none';
        if (event.target === resetPasswordModal) resetPasswordModal.style.display = 'none';
    });

    if (avatarImage && profilePictureInput) {
        const triggerFileInput = () => {
            profilePictureInput.click();
            console.log('Avatar image clicked/tapped, triggering file input');
        };

        avatarImage.addEventListener('click', triggerFileInput);
        avatarImage.addEventListener('touchstart', (e) => {
            e.preventDefault();
            triggerFileInput();
        });

        profilePictureInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    avatarImage.src = event.target.result;
                    console.log('New profile picture selected, preview updated');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    function updateEyeIconColor(input, icon) {
        try {
            const computedStyle = window.getComputedStyle(input);
            const bgColor = computedStyle.backgroundColor;
            const rgbMatch = bgColor.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
            if (rgbMatch) {
                const r = parseInt(rgbMatch[1]);
                const g = parseInt(rgbMatch[2]);
                const b = parseInt(rgbMatch[3]);
                if (r > 200 && g > 200 && b > 200) {
                    icon.style.color = '#666';
                    icon.style.fill = '#666';
                } else {
                    icon.style.color = '#fff';
                    icon.style.fill = '#fff';
                }
            }
        } catch (error) {
            console.error('Error updating eye icon color:', error);
        }
    }

    const togglePasswordButtons = document.querySelectorAll('.toggle-password');
    togglePasswordButtons.forEach(button => {
        const targetId = button.getAttribute('data-target');
        const passwordInput = document.querySelector(`#${targetId}`);
        const icon = button.querySelector('i');

        if (passwordInput && icon) {
            updateEyeIconColor(passwordInput, icon);
            passwordInput.addEventListener('focus', () => updateEyeIconColor(passwordInput, icon));
            passwordInput.addEventListener('blur', () => updateEyeIconColor(passwordInput, icon));
            passwordInput.addEventListener('input', () => updateEyeIconColor(passwordInput, icon));
            button.addEventListener('click', () => {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
                updateEyeIconColor(passwordInput, icon);
            });
        }
    });

    // Login Form Submission
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.querySelector('#email').value;
            const password = document.querySelector('#password').value;
            const csrfToken = document.querySelector('#login-form input[name="csrf_token"]').value;
            const messageDiv = document.querySelector('#login-message');
            const submitButton = loginForm.querySelector('button[type="submit"]');

            messageDiv.innerText = 'Validating...';
            messageDiv.classList.remove('success', 'error');
            messageDiv.style.display = 'block';
            toggleButtonState(submitButton, true);

            try {
                const isValidToken = await validateCSRFToken(csrfToken);
                if (!isValidToken) {
                    toggleButtonState(submitButton, false);
                    messageDiv.innerText = 'Session expired. Please refresh the page.';
                    messageDiv.classList.add('error');
                    return;
                }

                messageDiv.innerText = 'Logging in...';
                const fetchPromise = fetch('/includes/hiatme_config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': document.querySelector('#login-form input[name="csrf_token"]').value
                    },
                    body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`,
                    credentials: 'include'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        toggleButtonState(submitButton, false);
                        if (data.success) {
                            currentUser = {
                                email: data.email,
                                name: data.name,
                                phone: data.phone,
                                profile_picture: data.profile_picture
                            };
                            localStorage.setItem('currentUser', JSON.stringify(currentUser));
                            updateMenuState();
                            updateCSRFToken(data.csrf_token);
                            messageDiv.innerText = 'Login successful';
                            messageDiv.classList.remove('error');
                            messageDiv.classList.add('success');
                            setTimeout(() => {
                                loginModal.style.display = 'none';
                                window.location.href = 'index.php';
                            }, 1000);
                        } else {
                            messageDiv.innerText = data.message || 'Login failed';
                            messageDiv.classList.remove('success');
                            messageDiv.classList.add('error');
                        }
                    });

                await withTimeout(fetchPromise, 15000, submitButton, messageDiv, 'Request timed out. Please try again.');
            } catch (error) {
                toggleButtonState(submitButton, false);
                console.error('Login fetch error:', error);
                messageDiv.innerText = 'An error occurred. Please try again.';
                messageDiv.classList.add('error');
            }
        });
    }

    // Register Form Submission
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.querySelector('#reg-name').value;
            const email = document.querySelector('#reg-email').value;
            const phone = document.querySelector('#reg-phone').value;
            const password = document.querySelector('#reg-password').value;
            const confirmPassword = document.querySelector('#confirm-password').value;
            const csrfToken = document.querySelector('#register-form input[name="csrf_token"]').value;
            const messageDiv = document.querySelector('#register-message');
            const submitButton = registerForm.querySelector('button[type="submit"]');

            messageDiv.innerText = 'Validating...';
            messageDiv.classList.remove('success', 'error');
            messageDiv.style.display = 'block';
            toggleButtonState(submitButton, true);

            if (password !== confirmPassword) {
                toggleButtonState(submitButton, false);
                messageDiv.innerText = 'Passwords do not match';
                messageDiv.classList.remove('success');
                messageDiv.classList.add('error');
                return;
            }

            try {
                console.log('Register form submission started, validating token:', csrfToken);
                const isValidToken = await validateCSRFToken(csrfToken);
                if (!isValidToken) {
                    toggleButtonState(submitButton, false);
                    messageDiv.innerText = 'Session expired. Please refresh the page.';
                    messageDiv.classList.add('error');
                    console.error('Registration failed: Invalid CSRF token after retries');
                    return;
                }

                messageDiv.innerText = 'Registering...';
                const fetchPromise = fetch('/includes/hiatme_config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': document.querySelector('#register-form input[name="csrf_token"]').value
                    },
                    body: `action=register&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}&password=${encodeURIComponent(password)}`,
                    credentials: 'include'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        toggleButtonState(submitButton, false);
                        if (data.success) {
                            updateCSRFToken(data.csrf_token);
                            const successMessage = data.message || 'Registration successful';
                            messageDiv.innerText = successMessage;
                            messageDiv.classList.remove('error');
                            messageDiv.classList.add('success');
                            console.log('Registration successful:', successMessage);
                            setTimeout(() => {
                                const loginMessageDiv = document.querySelector('#login-message');
                                if (loginMessageDiv) {
                                    loginMessageDiv.innerText = successMessage;
                                    loginMessageDiv.classList.remove('error');
                                    loginMessageDiv.classList.add('success');
                                    loginMessageDiv.style.display = 'block';
                                }
                                showModal('#login-modal');
                            }, 1000);
                        } else {
                            messageDiv.innerText = data.message || 'Registration failed';
                            messageDiv.classList.remove('success');
                            messageDiv.classList.add('error');
                            console.error('Registration failed:', data.message);
                        }
                    });

                await withTimeout(fetchPromise, 15000, submitButton, messageDiv, 'Request timed out. Please try again.');
            } catch (error) {
                toggleButtonState(submitButton, false);
                console.error('Register fetch error:', error);
                messageDiv.innerText = 'An error occurred. Please try again.';
                messageDiv.classList.add('error');
            }
        });
    }

    // Forgot Password Form Submission
    if (forgotPasswordForm) {
        forgotPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.querySelector('#forgot-email').value;
            const csrfToken = document.querySelector('#forgot-password-form input[name="csrf_token"]').value;
            const messageDiv = document.querySelector('#forgot-message');
            const submitButton = forgotPasswordForm.querySelector('button[type="submit"]');

            messageDiv.innerText = 'Validating...';
            messageDiv.classList.remove('success', 'error');
            messageDiv.style.display = 'block';
            toggleButtonState(submitButton, true);

            try {
                const isValidToken = await validateCSRFToken(csrfToken);
                if (!isValidToken) {
                    toggleButtonState(submitButton, false);
                    messageDiv.innerText = 'Session expired. Please refresh the page.';
                    messageDiv.classList.add('error');
                    return;
                }

                messageDiv.innerText = 'Processing...';
                const fetchPromise = fetch('/includes/hiatme_config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': document.querySelector('#forgot-password-form input[name="csrf_token"]').value
                    },
                    body: `action=forgot_password&email=${encodeURIComponent(email)}`,
                    credentials: 'include'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        toggleButtonState(submitButton, false);
                        if (data.success) {
                            updateCSRFToken(data.csrf_token);
                        }
                        messageDiv.innerText = data.message || 'An error occurred';
                        messageDiv.classList.remove(data.success ? 'error' : 'success');
                        messageDiv.classList.add(data.success ? 'success' : 'error');
                    });

                await withTimeout(fetchPromise, 15000, submitButton, messageDiv, 'Request timed out. Please try again.');
            } catch (error) {
                toggleButtonState(submitButton, false);
                console.error('Forgot password fetch error:', error);
                messageDiv.innerText = 'An error occurred. Please try again.';
                messageDiv.classList.add('error');
            }
        });
    }

    // Reset Password Form Submission
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = document.querySelector('#reset-token').value;
            const newPassword = document.querySelector('#new-password').value;
            const confirmPassword = document.querySelector('#confirm-new-password').value;
            const csrfToken = document.querySelector('#reset-password-form input[name="csrf_token"]').value;
            const messageDiv = document.querySelector('#reset-message');
            const submitButton = resetPasswordForm.querySelector('button[type="submit"]');

            messageDiv.innerText = 'Validating...';
            messageDiv.classList.remove('success', 'error');
            messageDiv.style.display = 'block';
            toggleButtonState(submitButton, true);

            if (newPassword !== confirmPassword) {
                toggleButtonState(submitButton, false);
                messageDiv.innerText = 'Passwords do not match';
                messageDiv.classList.remove('success');
                messageDiv.classList.add('error');
                return;
            }

            try {
                const isValidToken = await validateCSRFToken(csrfToken);
                if (!isValidToken) {
                    toggleButtonState(submitButton, false);
                    messageDiv.innerText = 'Session expired. Please refresh the page.';
                    messageDiv.classList.add('error');
                    return;
                }

                messageDiv.innerText = 'Resetting password...';
                const fetchPromise = fetch('/includes/hiatme_config.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': document.querySelector('#reset-password-form input[name="csrf_token"]').value
                    },
                    body: `action=reset_password&token=${encodeURIComponent(token)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmPassword)}`,
                    credentials: 'include'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        toggleButtonState(submitButton, false);
                        if (data.success) {
                            currentUser = { email: data.email, name: data.name };
                            localStorage.setItem('currentUser', JSON.stringify(currentUser));
                            updateMenuState();
                            updateCSRFToken(data.csrf_token);
                            messageDiv.innerText = data.message || 'Password reset successful';
                            messageDiv.classList.remove('error');
                            messageDiv.classList.add('success');
                            setTimeout(() => { resetPasswordModal.style.display = 'none'; }, 1000);
                        } else {
                            messageDiv.innerText = data.message || 'Failed to reset password';
                            messageDiv.classList.remove('success');
                            messageDiv.classList.add('error');
                        }
                    });

                await withTimeout(fetchPromise, 15000, submitButton, messageDiv, 'Request timed out. Please try again.');
            } catch (error) {
                toggleButtonState(submitButton, false);
                console.error('Reset password fetch error:', error);
                messageDiv.innerText = 'An error occurred. Please try again.';
                messageDiv.classList.add('error');
            }
        });
    }

    // Profile Form Submission
    if (profileForm) {
        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const csrfToken = document.querySelector('#profile-form input[name="csrf_token"]').value;
            const messageDiv = document.querySelector('#profile-message');
            const submitButton = profileForm.querySelector('button[type="submit"]');

            messageDiv.innerText = 'Validating...';
            messageDiv.classList.remove('success', 'error');
            messageDiv.style.display = 'block';
            toggleButtonState(submitButton, true);

            try {
                const isValidToken = await validateCSRFToken(csrfToken);
                if (!isValidToken) {
                    toggleButtonState(submitButton, false);
                    messageDiv.innerText = 'Session expired. Please refresh the page.';
                    messageDiv.classList.add('error');
                    return;
                }

                messageDiv.innerText = 'Updating profile...';
                const formData = new FormData(profileForm);
                formData.append('action', 'update_profile');
                const fetchPromise = fetch('/includes/hiatme_config.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': document.querySelector('#profile-form input[name="csrf_token"]').value
                    },
                    body: formData,
                    credentials: 'include'
                })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        toggleButtonState(submitButton, false);
                        if (data.success) {
                            currentUser = {
                                email: data.user.email,
                                name: data.user.name,
                                phone: data.user.phone,
                                profile_picture: data.user.profile_picture
                            };
                            localStorage.setItem('currentUser', JSON.stringify(currentUser));
                            updateMenuState();
                            updateCSRFToken(data.csrf_token);
                            messageDiv.innerText = data.message || 'Profile updated successfully';
                            messageDiv.classList.remove('error');
                            messageDiv.classList.add('success');
                        } else {
                            messageDiv.innerText = data.message || 'Failed to update profile';
                            messageDiv.classList.remove('success');
                            messageDiv.classList.add('error');
                        }
                    });

                await withTimeout(fetchPromise, 15000, submitButton, messageDiv, 'Request timed out. Please try again.');
            } catch (error) {
                toggleButtonState(submitButton, false);
                console.error('Profile update fetch error:', error);
                messageDiv.innerText = 'An error occurred. Please try again.';
                messageDiv.classList.add('error');
            }
        });
    }

    // Form input floating label logic
    function activateFloatingLabels(container) {
        const inputs = container.querySelectorAll('.form-group input:not([type="file"]), .form-group select, .form-group textarea');
        inputs.forEach(input => {
            const label = input.nextElementSibling?.tagName === 'LABEL' ? input.nextElementSibling : null;
            if (!label) return;

            // Initial state
            const isFilled = input.value.trim() !== '' || (input.tagName === 'SELECT' && input.value !== '');
            if (isFilled) {
                input.classList.add('active');
                label.classList.add('active');
                if (input.tagName === 'SELECT') input.classList.remove('placeholder-selected');
            } else {
                input.classList.remove('active');
                label.classList.remove('active');
                if (input.tagName === 'SELECT') input.classList.add('placeholder-selected');
            }

            // Handle input and change events
            input.addEventListener('input', () => {
                const isFilled = input.value.trim() !== '' || (input.tagName === 'SELECT' && input.value !== '');
                if (isFilled) {
                    input.classList.add('active');
                    label.classList.add('active');
                    if (input.tagName === 'SELECT') input.classList.remove('placeholder-selected');
                } else {
                    input.classList.remove('active');
                    label.classList.remove('active');
                    if (input.tagName === 'SELECT') input.classList.add('placeholder-selected');
                }
            });

            // Handle focus and blur
            input.addEventListener('focus', () => {
                input.classList.add('active');
                label.classList.add('active');
                if (input.tagName === 'SELECT') input.classList.remove('placeholder-selected');
                if (container === registerForm) stopCSRFTokenRefresh();
            });
            input.addEventListener('blur', () => {
                const isFilled = input.value.trim() !== '' || (input.tagName === 'SELECT' && input.value !== '');
                if (!isFilled) {
                    input.classList.remove('active');
                    label.classList.remove('active');
                    if (input.tagName === 'SELECT') input.classList.add('placeholder-selected');
                }
                if (container === registerForm) startCSRFTokenRefresh();
            });

            // Autofill detection
            if (input.tagName !== 'SELECT') {
                const checkAutofill = () => {
                    if (input.matches(':-webkit-autofill') || (input.value && !input.classList.contains('active'))) {
                        input.classList.add('active');
                        label.classList.add('active');
                    }
                };
                setTimeout(checkAutofill, 100); // Check after load
                input.addEventListener('animationstart', (e) => {
                    if (e.animationName === 'autofill') checkAutofill();
                });
            }
        });
    }

    // Apply floating labels to all forms on load
    const forms = [loginForm, registerForm, forgotPasswordForm, resetPasswordForm, profileForm];
    forms.forEach(form => {
        if (form) activateFloatingLabels(form);
    });

    // Apply floating labels to modals when shown
    const modals = [
        loginModal,
        registerModal,
        forgotPasswordModal,
        resetPasswordModal,
        profileModal
    ];
    modals.forEach(modal => {
        if (modal) {
            modal.addEventListener('transitionend', () => {
                if (modal.style.display === 'block') {
                    activateFloatingLabels(modal);
                }
            });
        }
    });

    // Reset token handling
    const urlParams = new URLSearchParams(window.location.search);
    const resetToken = urlParams.get('reset_token');
    if (resetToken) {
        const tokenInput = document.querySelector('#reset-token');
        if (tokenInput) {
            tokenInput.value = resetToken;
            showModal('#reset-password-modal');
        }
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    updateMenuState();
});