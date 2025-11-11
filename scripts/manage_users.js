document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('user-search');
    const tableBody = document.getElementById('user-table-body');
    const userCards = document.getElementById('user-cards');
    const pagination = document.querySelector('.pagination');
    const modal = document.getElementById('user-details-modal');
    const modalAvatar = document.getElementById('modal-avatar');
    const modalName = document.getElementById('modal-name');
    const modalRole = document.getElementById('modal-role');
    const modalEmail = document.getElementById('modal-email');
    const modalPhone = document.getElementById('modal-phone');
    const modalCreated = document.getElementById('modal-created');
    const modalUpdated = document.getElementById('modal-updated');
    const modalVerified = document.getElementById('modal-verified');
    const modalRewards = document.getElementById('modal-rewards');
    const modalMessage = document.getElementById('modal-message');
    const closeModal = document.querySelector('.close-modal');
    const saveRoleBtn = document.getElementById('save-role-btn');

    let currentPage = parseInt(new URLSearchParams(window.location.search).get('page')) || 1;
    let currentQuery = '';
    let csrfToken = localStorage.getItem('csrf_token') || '';

    // Helper function to detect mobile view
    const isMobile = () => {
        const isNarrow = window.matchMedia('(max-width: 768px)').matches;
        console.log('isMobile check: isNarrow=', isNarrow, 'width=', window.innerWidth);
        return isNarrow;
    };

    // Fetch CSRF token with retry
    async function fetchCsrfToken(attempt = 1) {
        const maxTokenRetries = 3;
        if (attempt > maxTokenRetries) {
            console.error('Max retries reached for CSRF token fetch');
            return false;
        }
        try {
            const response = await fetch('api/search_users.php?action=get_csrf_token', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (data.success && data.csrf_token) {
                csrfToken = data.csrf_token;
                localStorage.setItem('csrf_token', csrfToken);
                console.log('Updated CSRF token:', csrfToken);
                return true;
            } else {
                console.error(`Attempt ${attempt}: Failed to fetch CSRF token:`, data.message || 'Unknown error');
                await new Promise(resolve => setTimeout(resolve, 1000 * attempt));
                return fetchCsrfToken(attempt + 1);
            }
        } catch (error) {
            console.error(`Attempt ${attempt}: Error fetching CSRF token:`, error);
            await new Promise(resolve => setTimeout(resolve, 1000 * attempt));
            return fetchCsrfToken(attempt + 1);
        }
    }

    // POST request helper with token refresh
    async function makePostRequest(url, body, attempt = 1) {
        const maxTokenRetries = 3;
        if (!csrfToken && !(await fetchCsrfToken())) {
            throw new Error('Failed to obtain CSRF token');
        }
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(body)
            });
            const data = await response.json();
            if (response.status === 403 && attempt <= maxTokenRetries) {
                console.warn(`Attempt ${attempt}: CSRF token invalid, refreshing...`);
                if (await fetchCsrfToken()) {
                    return makePostRequest(url, body, attempt + 1);
                }
                throw new Error('Failed to refresh CSRF token');
            }
            if (data.csrf_token) {
                csrfToken = data.csrf_token;
                localStorage.setItem('csrf_token', csrfToken);
                console.log('CSRF token updated:', csrfToken);
            }
            return { response, data };
        } catch (error) {
            console.error(`Attempt ${attempt}: Error in POST request:`, error);
            throw error;
        }
    }

    // Debounce search input
    let searchTimeout;
    function debounceSearch(query, page) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => fetchUsers(query, page), 300);
    }

    searchInput.addEventListener('input', () => {
        currentQuery = searchInput.value.trim();
        currentPage = 1;
        console.log('Search input:', currentQuery);
        debounceSearch(currentQuery, currentPage);
    });

    // Fetch users from API
    async function fetchUsers(query, page, attempt = 1) {
        if (!csrfToken && !(await fetchCsrfToken())) {
            tableBody.innerHTML = '<tr><td colspan="6">Authentication error</td></tr>';
            userCards.innerHTML = '<p>Authentication error</p>';
            return;
        }
        try {
            const url = `api/search_users.php?query=${encodeURIComponent(query)}&page=${page}`;
            console.log('Fetching:', url);
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });
            if (response.status === 403 && attempt <= 3) {
                console.warn('CSRF token invalid, refreshing...');
                if (await fetchCsrfToken()) {
                    return fetchUsers(query, page, attempt + 1);
                }
                throw new Error('Failed to refresh CSRF token');
            }
            const data = await response.json();
            console.log('API response:', data);
            if (data.success) {
                updateUserDisplay(data.users, data.total_pages, data.current_page);
                if (data.csrf_token) {
                    csrfToken = data.csrf_token;
                    localStorage.setItem('csrf_token', csrfToken);
                    console.log('CSRF token updated:', csrfToken);
                }
                history.pushState({}, '', `?query=${encodeURIComponent(query)}&page=${page}`);
            } else {
                console.error('Fetch error:', data.message);
                tableBody.innerHTML = `<tr><td colspan="6">Error: ${data.message || 'Failed to load users'}</td></tr>`;
                userCards.innerHTML = `<p>Error: ${data.message || 'Failed to load users'}</p>`;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            tableBody.innerHTML = '<tr><td colspan="6">Failed to load users</td></tr>';
            userCards.innerHTML = '<p>Failed to load users</p>';
        }
    }

    // Update table and cards
    function updateUserDisplay(users, totalPages, currentPage) {
        const showCards = isMobile();
        console.log(`Updating display: ${users.length} users, page ${currentPage}/${totalPages}, showCards=${showCards}`);

        try {
            // Update desktop table
            tableBody.innerHTML = users.length === 0 ? `
                <tr><td colspan="6">No users found</td></tr>
            ` : users.map(user => `
                <tr data-user-id="${user.id || ''}"
                    data-email="${user.email || ''}"
                    data-name="${user.name || ''}"
                    data-phone="${user.phone || ''}"
                    data-role="${user.role || ''}"
                    data-verified="${user.is_verified ? 'Yes' : 'No'}"
                    data-picture="${user.profile_picture || ''}"
                    data-created="${user.created_at || ''}"
                    data-updated="${user.updated_at || ''}"
                    data-reward-count="${user.reward_count || '0'}">
                    <td>
                        <div class="action-buttons">
                            <button class="view-details-btn" data-tooltip="View Details" id="view-details-${user.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                    <td>${user.email || 'Unknown'}</td>
                    <td>${user.name || 'Unknown'}</td>
                    <td>${user.role || 'Unknown'}</td>
                    <td>${user.reward_count || '0'}</td>
                    <td>${user.is_verified ? 'Yes' : 'No'}</td>
                </tr>
            `).join('');

            // Update mobile cards
            if (showCards) {
                userCards.innerHTML = users.length === 0 ? `
                    <p>No users found</p>
                ` : users.map(user => `
                    <div class="user-card"
                         data-user-id="${user.id || ''}"
                         data-email="${user.email || ''}"
                         data-name="${user.name || ''}"
                         data-phone="${user.phone || ''}"
                         data-role="${user.role || ''}"
                         data-verified="${user.is_verified ? 'Yes' : 'No'}"
                         data-picture="${user.profile_picture || ''}"
                         data-created="${user.created_at || ''}"
                         data-updated="${user.updated_at || ''}"
                         data-reward-count="${user.reward_count || '0'}">
                        <p><strong>Email:</strong> ${user.email || 'Unknown'}</p>
                        <p><strong>Name:</strong> ${user.name || 'Unknown'}</p>
                        <p><strong>Role:</strong> ${user.role || 'Unknown'}</p>
                        <p><strong>Rewards:</strong> ${user.reward_count || '0'}</p>
                        <p><strong>Verified:</strong> ${user.is_verified ? 'Yes' : 'No'}</p>
                        <div class="action-buttons">
                            <button class="view-details-btn" data-tooltip="View Details" id="view-details-${user.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            } else {
                userCards.innerHTML = '';
            }

            // Toggle visibility
            const tableContainer = document.querySelector('.user-table-container');
            if (!tableContainer || !userCards) {
                console.error('Table or cards container missing');
                return;
            }
            tableContainer.style.display = showCards ? 'none' : 'block';
            userCards.style.display = showCards ? 'block' : 'none';
            if (!showCards && window.innerWidth >= 769) {
                userCards.style.display = 'none';
                tableContainer.style.display = 'block';
                console.log('Forced table display on desktop');
            }

            // Update pagination
            pagination.innerHTML = '';
            if (currentPage > 1) {
                pagination.innerHTML += `<a href="?page=${currentPage - 1}" class="pagination-btn">« Previous</a>`;
            }
            const maxButtons = 5;
            const startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            const endPage = Math.min(totalPages, startPage + maxButtons - 1);
            for (let i = startPage; i <= endPage; i++) {
                pagination.innerHTML += `
                    <a href="?page=${i}" class="pagination-btn ${i === currentPage ? 'active' : ''}">${i}</a>
                `;
            }
            if (currentPage < totalPages) {
                pagination.innerHTML += `<a href="?page=${currentPage + 1}" class="pagination-btn">Next »</a>`;
            }

            // Add pagination listeners
            document.querySelectorAll('.pagination-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = parseInt(btn.textContent) ||
                        (btn.textContent.includes('Previous') ? currentPage - 1 : currentPage + 1);
                    if (page) {
                        currentPage = page;
                        fetchUsers(currentQuery, page);
                    }
                });
            });

            // Add button listeners
            addButtonListeners();
        } catch (error) {
            console.error('Error in updateUserDisplay:', error);
            tableBody.innerHTML = '<tr><td colspan="6">Error rendering table</td></tr>';
            userCards.innerHTML = '<p>Error rendering cards</p>';
        }
    }

    // Add button listeners
    function addButtonListeners() {
        console.log('Adding button listeners...');
        try {
            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.removeEventListener('click', handleViewDetails);
                btn.addEventListener('click', handleViewDetails);
                btn.disabled = false;
            });
        } catch (error) {
            console.error('Error adding button listeners:', error);
        }
    }

    // Function to handle floating labels
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
            });
            input.addEventListener('blur', () => {
                const isFilled = input.value.trim() !== '' || (input.tagName === 'SELECT' && input.value !== '');
                if (!isFilled) {
                    input.classList.remove('active');
                    label.classList.remove('active');
                    if (input.tagName === 'SELECT') input.classList.add('placeholder-selected');
                }
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

    // Handle view details
    function handleViewDetails(event) {
        try {
            console.log('View Details clicked:', event.target.id);
            const parent = event.target.closest('tr') || event.target.closest('.user-card');
            if (!parent || !parent.dataset.userId) {
                console.error('Invalid parent or userId:', parent);
                return;
            }
            modalAvatar.src = parent.dataset.picture || 'https://cdn.pixabay.com/photo/2020/07/01/12/58/icon-5359553_640.png';
            modalName.textContent = parent.dataset.name || 'Unknown';
            modalRole.value = parent.dataset.role || 'Client';
            modalEmail.textContent = parent.dataset.email || 'Unknown';
            modalPhone.textContent = parent.dataset.phone || 'Not provided';
            modalCreated.textContent = parent.dataset.created || 'Unknown';
            modalUpdated.textContent = parent.dataset.updated || 'Unknown';
            modalVerified.textContent = parent.dataset.verified || 'No';
            modalRewards.innerHTML = '<p>Placeholder for rewards data</p>';
            modalMessage.textContent = '';
            modalMessage.className = '';
            modal.dataset.userId = parent.dataset.userId;
            modal.style.display = 'block';
            activateFloatingLabels(modal);
        } catch (error) {
            console.error('Modal error:', error);
            modalMessage.textContent = 'Error loading user details';
            modalMessage.className = 'error';
        }
    }

    // Save role
    saveRoleBtn.addEventListener('click', async () => {
        const userId = modal.dataset.userId;
        const newRole = modalRole.value;
        modalMessage.textContent = 'Saving...';
        modalMessage.className = '';

        try {
            const { response, data } = await makePostRequest('api/search_users.php', {
                user_id: userId,
                role: newRole
            });
            console.log('Role update data:', data);
            if (data.success) {
                modalMessage.textContent = 'Role updated successfully';
                modalMessage.className = 'success';
                const row = tableBody.querySelector(`tr[data-user-id="${userId}"]`);
                const card = userCards.querySelector(`.user-card[data-user-id="${userId}"]`);
                if (row) {
                    row.dataset.role = newRole;
                    row.cells[3].textContent = newRole;
                }
                if (card) {
                    card.dataset.role = newRole;
                    card.querySelector('p:nth-child(3)').innerHTML = `<strong>Role:</strong> ${newRole}`;
                }
                setTimeout(() => {
                    modal.style.display = 'none';
                    modalMessage.textContent = '';
                    modalMessage.className = '';
                }, 1000);
            } else {
                console.error('Role update failed:', data.message);
                modalMessage.textContent = data.message || 'Failed to update role';
                modalMessage.className = 'error';
            }
        } catch (error) {
            console.error('Role update error:', error);
            modalMessage.textContent = 'Failed to update role';
            modalMessage.className = 'error';
        }
    });

    // Close modal
    closeModal.addEventListener('click', () => {
        console.log('Close modal clicked');
        modal.style.display = 'none';
        modalMessage.textContent = '';
        modalMessage.className = '';
    });

    // Close modal on outside click
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            console.log('Outside modal clicked');
            modal.style.display = 'none';
            modalMessage.textContent = '';
            modalMessage.className = '';
        }
    });

    // Handle popstate for browser back/forward
    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const query = params.get('query') || '';
        const page = parseInt(params.get('page')) || 1;
        currentQuery = query;
        currentPage = page;
        searchInput.value = query;
        fetchUsers(query, page);
    });

    // Handle media query changes
    const mediaQuery = window.matchMedia('(max-width: 768px)');
    let resizeTimeout = null;
    mediaQuery.addEventListener('change', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            console.log('Media query changed: isMobile=', isMobile(), 'window.innerWidth=', window.innerWidth);
            fetchUsers(currentQuery, currentPage);
        }, 100);
    });

    // Initial search with URL parameters
    const params = new URLSearchParams(window.location.search);
    currentQuery = params.get('query') || '';
    currentPage = parseInt(params.get('page')) || 1;
    searchInput.value = currentQuery;
    console.log('Initializing user search with query:', currentQuery, 'page:', currentPage);
    fetchUsers(currentQuery, currentPage);
});