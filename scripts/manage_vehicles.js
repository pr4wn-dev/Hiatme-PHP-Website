// manage_vehicles.js
document.addEventListener('DOMContentLoaded', () => {
    const vehicleTableBody = document.getElementById('vehicle-table-body');
    const vehicleCards = document.getElementById('vehicle-cards');
    const vehicleSearch = document.getElementById('vehicle-search');
    const vehicleDetailsModal = document.getElementById('vehicle-details-modal');
    const createVehicleModal = document.getElementById('create-vehicle-modal');
    const vehicleIssuesModal = document.getElementById('vehicle-issues-modal');
    const issueResolutionModal = document.getElementById('issue-resolution-modal');
    const createVehicleBtn = document.getElementById('create-vehicle-btn');
    let currentVehicleId = null;
    let csrfToken = localStorage.getItem('csrf_token') || '';

    // List of common vehicle parts for checkboxes
    const vehicleParts = [
        'Brake Pads', 'Brake Rotors', 'Tires', 'Battery', 'Alternator',
        'Spark Plugs', 'Oil Filter', 'Air Filter', 'Fuel Pump', 'Water Pump',
        'Radiator', 'Thermostat', 'Starter Motor', 'Ignition Coil', 'Timing Belt',
        'Serpentine Belt', 'Shock Absorbers', 'Struts', 'Control Arms', 'Ball Joints',
        'Wheel Bearings', 'Exhaust Pipe', 'Catalytic Converter', 'Oxygen Sensor', 'Headlight Assembly'
    ];

    // Helper function to update CSRF token from response
    function updateCsrfTokenFromResponse(data) {
        if (data.csrf_token) {
            csrfToken = data.csrf_token;
            localStorage.setItem('csrf_token', csrfToken);
            console.log('Updated CSRF token:', csrfToken);
        }
    }

    const maxTokenRetries = 3;
    const isMobile = () => {
        const isNarrow = window.matchMedia('(max-width: 768px)').matches;
        console.log('isMobile check: isNarrow=', isNarrow, 'width=', window.innerWidth);
        return isNarrow;
    };

    // Fetch CSRF token with retry
    async function fetchCsrfToken(attempt = 1) {
        if (attempt > maxTokenRetries) {
            console.error('Max retries reached for CSRF token fetch');
            return false;
        }
        try {
            const response = await fetch('/api/search_vehicles.php?action=get_csrf_token', {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            if (data.success && data.csrf_token) {
                updateCsrfTokenFromResponse(data);
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
            updateCsrfTokenFromResponse(data);
            return { response, data };
        } catch (error) {
            console.error(`Attempt ${attempt}: Error in POST request:`, error);
            throw error;
        }
    }

    // Debounce search input
    let searchTimeout = null;
    function debounceSearch(query, page) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => searchVehicles(query, page), 300);
    }

    // Search vehicles
    async function searchVehicles(query = '', page = 1, attempt = 1) {
        if (!csrfToken && !(await fetchCsrfToken())) {
            vehicleTableBody.innerHTML = `<tr><td style="width: 120px;"></td><td style="width: 200px;" colspan="6">Authentication error</td></tr>`;
            vehicleCards.innerHTML = `<p>Authentication error</p>`;
            return;
        }
        try {
            console.log(`Searching vehicles: query="${query}", page=${page}`);
            const url = `/api/search_vehicles.php?query=${encodeURIComponent(query)}&page=${page}`;
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });
            if (response.status === 403 && attempt <= maxTokenRetries) {
                console.warn('CSRF token invalid, refreshing...');
                if (await fetchCsrfToken()) {
                    return searchVehicles(query, page, attempt + 1);
                }
                throw new Error('Failed to refresh CSRF token');
            }
            const data = await response.json();
            console.log('API response:', data);
            if (data.success) {
                updateVehicleTable(data.vehicles, data.total_pages, data.current_page, data.total_vehicles);
                updateCsrfTokenFromResponse(data);
                const newUrl = `?query=${encodeURIComponent(query)}&page=${page}`;
                history.pushState({ query, page }, '', newUrl);
            } else {
                console.error('Search failed:', data.message);
                vehicleTableBody.innerHTML = `<tr><td style="width: 120px;"></td><td style="width: 200px;" colspan="6">${data.message || 'Error fetching vehicles'}</td></tr>`;
                vehicleCards.innerHTML = `<p>${data.message || 'Error fetching vehicles'}</p>`;
            }
        } catch (error) {
            console.error('Error searching vehicles:', error);
            vehicleTableBody.innerHTML = `<tr><td style="width: 120px;"></td><td style="width: 200px;" colspan="6">Error fetching vehicles</td></tr>`;
            vehicleCards.innerHTML = `<p>Error fetching vehicles</p>`;
        }
    }

    // Update vehicle table and cards
    function updateVehicleTable(vehicles, totalPages, currentPage, totalVehicles) {
        console.log(`Updating table/cards: ${vehicles.length} vehicles, page ${currentPage}/${totalPages}`);
        const showCards = isMobile();

        try {
            // Update desktop table
            vehicleTableBody.innerHTML = vehicles.length === 0 ? `
                <tr><td style="width: 120px;"></td><td style="width: 200px;" colspan="6">No vehicles found</td></tr>
            ` : vehicles.map(vehicle => {
                if (!vehicle.vehicle_id) {
                    console.warn('Invalid vehicle data:', vehicle);
                    return '';
                }
                return `
                <tr data-vehicle-id="${vehicle.vehicle_id}"
                    data-image-location="${vehicle.image_location || ''}"
                    data-make="${vehicle.make || ''}"
                    data-model="${vehicle.model || ''}"
                    data-vin="${vehicle.vin || ''}"
                    data-color="${vehicle.color || ''}"
                    data-license-plate="${vehicle.license_plate || ''}"
                    data-year="${vehicle.year || ''}"
                    data-current-user-id="${vehicle.current_user_id || ''}"
                    data-current-user-name="${vehicle.current_user_name || ''}"
                    data-last-user-id="${vehicle.last_user_id || ''}"
                    data-last-user-name="${vehicle.last_user_name || ''}"
                    data-date-assigned="${vehicle.date_assigned || ''}"
                    data-date-last-used="${vehicle.date_last_used || ''}">
                    <td style="width: 120px;">
                        <div class="action-buttons">
                            <button class="view-details-btn" data-tooltip="View Details" id="view-details-${vehicle.vehicle_id}"><i class="fas fa-eye"></i></button>
                            <button class="issues-vehicle-btn" data-tooltip="Issues" id="issues-${vehicle.vehicle_id}"><i class="fas fa-wrench"></i></button>
                            <button class="delete-vehicle-btn" data-tooltip="Delete" id="delete-${vehicle.vehicle_id}"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                    <td style="width: 200px;">${vehicle.current_user_name || 'None'}</td>
                    <td style="width: 150px;">${(vehicle.vin || 'Unknown').slice(-6)}</td>
                    <td style="width: 100px;">${vehicle.year || 'Unknown'}</td>
                    <td style="width: 150px;">${vehicle.make || 'Unknown'}</td>
                    <td style="width: 150px;">${vehicle.model || 'Unknown'}</td>
                    <td style="width: 150px;">${vehicle.license_plate || 'Unknown'}</td>
                </tr>`;
            }).join('');

            // Update mobile cards
            if (showCards) {
                vehicleCards.innerHTML = vehicles.length === 0 ? `
                    <p>No vehicles found</p>
                ` : vehicles.map(vehicle => {
                    if (!vehicle.vehicle_id) {
                        console.warn('Invalid vehicle data for card:', vehicle);
                        return '';
                    }
                    console.log(`Rendering card for vehicle ${vehicle.vehicle_id}`);
                    return `
                    <div class="vehicle-card"
                         data-vehicle-id="${vehicle.vehicle_id}"
                         data-image-location="${vehicle.image_location || ''}"
                         data-make="${vehicle.make || ''}"
                         data-model="${vehicle.model || ''}"
                         data-vin="${vehicle.vin || ''}"
                         data-color="${vehicle.color || ''}"
                         data-license-plate="${vehicle.license_plate || ''}"
                         data-year="${vehicle.year || ''}"
                         data-current-user-id="${vehicle.current_user_id || ''}"
                         data-current-user-name="${vehicle.current_user_name || ''}"
                         data-last-user-id="${vehicle.last_user_id || ''}"
                         data-last-user-name="${vehicle.last_user_name || ''}"
                         data-date-assigned="${vehicle.date_assigned || ''}"
                         data-date-last-used="${vehicle.date_last_used || ''}">
                        <p><strong>Make/Model:</strong> ${vehicle.make || 'Unknown'} ${vehicle.model || 'Unknown'}</p>
                        <p><strong>Year:</strong> ${vehicle.year || 'Unknown'}</p>
                        <p><strong>VIN (Last 6):</strong> ${(vehicle.vin || 'Unknown').slice(-6)}</p>
                        <p><strong>License Plate:</strong> ${vehicle.license_plate || 'Unknown'}</p>
                        <p><strong>Current User:</strong> ${vehicle.current_user_name || 'None'}</p>
                        <div class="action-buttons">
                            <button class="view-details-btn" data-tooltip="View Details" id="view-details-${vehicle.vehicle_id}"><i class="fas fa-eye"></i></button>
                            <button class="issues-vehicle-btn" data-tooltip="Issues" id="issues-${vehicle.vehicle_id}"><i class="fas fa-wrench"></i></button>
                            <button class="delete-vehicle-btn" data-tooltip="Delete" id="delete-${vehicle.vehicle_id}"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>`;
                }).join('');
            } else {
                vehicleCards.innerHTML = '';
            }

            // Toggle visibility
            console.log('Toggling display: showCards=', showCards, 'window.innerWidth=', window.innerWidth);
            const tableContainer = document.querySelector('.vehicle-table-container');
            if (!tableContainer || !vehicleCards) {
                console.error('Table or cards container missing');
                return;
            }
            tableContainer.style.display = showCards ? 'none' : 'block';
            vehicleCards.style.display = showCards ? 'block' : 'none';
            if (!showCards && window.innerWidth >= 769) {
                vehicleCards.style.display = 'none';
                tableContainer.style.display = 'block';
                console.log('Forced table display on desktop');
            }

            // Update pagination
            const pagination = document.querySelector('.pagination');
            let paginationHTML = '';
            if (currentPage > 1) {
                paginationHTML += `<a href="#" class="pagination-btn" data-page="${currentPage - 1}">« Previous</a>`;
            }
            const maxButtons = 5;
            const startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            const endPage = Math.min(totalPages, startPage + maxButtons - 1);
            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <a href="#" class="pagination-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</a>
                `;
            }
            if (currentPage < totalPages) {
                paginationHTML += `<a href="#" class="pagination-btn" data-page="${currentPage + 1}">Next »</a>`;
            }
            pagination.innerHTML = paginationHTML;

            // Add pagination listeners
            document.querySelectorAll('.pagination-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = parseInt(btn.dataset.page);
                    if (page) {
                        searchVehicles(vehicleSearch.value, page);
                    }
                });
            });

            // Add button listeners
            addButtonListeners();
        } catch (error) {
            console.error('Error in updateVehicleTable:', error);
            vehicleTableBody.innerHTML = `<tr><td style="width: 120px;"></td><td style="width: 200px;" colspan="6">Error rendering table</td></tr>`;
            vehicleCards.innerHTML = `<p>Error rendering cards</p>`;
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
            document.querySelectorAll('.issues-vehicle-btn').forEach(btn => {
                btn.removeEventListener('click', handleIssues);
                btn.addEventListener('click', handleIssues);
                btn.disabled = false;
            });
            document.querySelectorAll('.delete-vehicle-btn').forEach(btn => {
                btn.removeEventListener('click', handleDelete);
                btn.addEventListener('click', handleDelete);
                btn.disabled = false;
            });
        } catch (error) {
            console.error('Error adding button listeners:', error);
        }
    }

    // Function to style select placeholders
    function styleSelectPlaceholders() {
        const selects = document.querySelectorAll('select.modal-input');
        selects.forEach(select => {
            const updatePlaceholderStyle = () => {
                if (select.value === '') {
                    select.classList.add('placeholder-selected');
                } else {
                    select.classList.remove('placeholder-selected');
                }
            };
            updatePlaceholderStyle();
            select.removeEventListener('change', updatePlaceholderStyle); // Prevent duplicate listeners
            select.addEventListener('change', updatePlaceholderStyle);
        });
    }

    function handleViewDetails(event) {
        try {
            console.log('View Details clicked:', event.target.id);
            const parent = event.target.closest('tr') || event.target.closest('.vehicle-card');
            if (!parent || !parent.dataset.vehicleId) {
                console.error('Invalid parent or vehicleId:', parent);
                return;
            }
            currentVehicleId = parent.dataset.vehicleId;
            populateVehicleDetailsModal(parent);
            vehicleDetailsModal.style.display = 'block';
        } catch (error) {
            console.error('Error in View Details:', error);
        }
    }

    async function handleIssues(event) {
        try {
            console.log('Issues clicked:', event.target.id);
            const parent = event.target.closest('tr') || event.target.closest('.vehicle-card');
            if (!parent || !parent.dataset.vehicleId) {
                console.error('Invalid parent or vehicleId:', parent);
                return;
            }
            currentVehicleId = parent.dataset.vehicleId;
            const make = parent.dataset.make || 'Unknown';
            const model = parent.dataset.model || 'Unknown';
            document.getElementById('issues-modal-make-model').textContent = `${make} ${model}`;
            vehicleIssuesModal.style.display = 'block';
            activateFloatingLabels(document.querySelector('#vehicle-issues-modal'));
            styleSelectPlaceholders(); // Apply placeholder styling
            await fetchVehicleIssues(currentVehicleId);
        } catch (error) {
            console.error('Error in Issues:', error);
        }
    }

    async function handleDelete(event) {
        try {
            console.log('Delete clicked:', event.target.id);
            if (!confirm('Are you sure you want to delete this vehicle?')) return;
            const parent = event.target.closest('tr') || event.target.closest('.vehicle-card');
            if (!parent || !parent.dataset.vehicleId) {
                console.error('Invalid parent or vehicleId:', parent);
                return;
            }
            const vehicleId = parent.dataset.vehicleId;
            const { response, data } = await makePostRequest('/api/search_vehicles.php', {
                action: 'delete_vehicle',
                vehicle_id: vehicleId
            });
            if (data.success) {
                parent.remove();
            } else {
                alert('Failed to delete vehicle: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting vehicle:', error);
            alert('Error deleting vehicle');
        }
    }

    function populateVehicleDetailsModal(parent) {
        try {
            console.log('Populating modal for vehicle:', parent.dataset.vehicleId);
            document.getElementById('modal-make').value = parent.dataset.make || '';
            document.getElementById('modal-model').value = parent.dataset.model || '';
            document.getElementById('modal-vin').value = parent.dataset.vin || '';
            document.getElementById('modal-color').value = parent.dataset.color || '';
            document.getElementById('modal-license-plate').value = parent.dataset.licensePlate || '';
            document.getElementById('modal-year').value = parent.dataset.year || '';
            document.getElementById('modal-image-location').value = parent.dataset.imageLocation || '';
            document.getElementById('modal-current-user-name').textContent = parent.dataset.currentUserName || 'None';
            document.getElementById('modal-last-user-name').textContent = parent.dataset.lastUserName || 'None';
            document.getElementById('modal-date-assigned').textContent = parent.dataset.dateAssigned || 'N/A';
            document.getElementById('modal-date-last-used').textContent = parent.dataset.dateLastUsed || 'N/A';
            document.getElementById('modal-make-model').textContent = `${parent.dataset.make || 'Unknown'} ${parent.dataset.model || 'Unknown'}`;
            document.getElementById('modal-message').textContent = '';
            activateFloatingLabels(document.querySelector('#vehicle-details-modal'));
        } catch (error) {
            console.error('Error populating modal:', error);
        }
    }

    async function fetchVehicleIssues(vehicleId, attempt = 1) {
        if (!csrfToken && !(await fetchCsrfToken())) {
            document.getElementById('issues-list').innerHTML = '<p>Authentication error</p>';
            return;
        }
        try {
            console.log(`Fetching issues for vehicle ${vehicleId}`);
            const response = await fetch(`/api/search_vehicles.php?action=get_issues&vehicle_id=${vehicleId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });
            if (response.status === 403 && attempt <= maxTokenRetries) {
                console.warn('CSRF token invalid, refreshing...');
                if (await fetchCsrfToken()) {
                    return fetchVehicleIssues(vehicleId, attempt + 1);
                }
                throw new Error('Failed to refresh CSRF token');
            }
            const data = await response.json();
            if (data.success) {
                displayIssues(data.issues);
                document.getElementById('issue-type').value = '';
                activateFloatingLabels(document.querySelector('#vehicle-issues-modal'));
                styleSelectPlaceholders(); // Re-apply placeholder styling after reset
            } else {
                document.getElementById('issues-list').innerHTML = '<p>No issues found</p>';
                showMessage('issues-modal-message', data.message || 'Error fetching issues', false);
            }
        } catch (error) {
            console.error('Error fetching issues:', error);
            document.getElementById('issues-list').innerHTML = '<p>Error fetching issues</p>';
            showMessage('issues-modal-message', 'Error fetching issues', false);
        }
    }

    function displayIssues(issues) {
        try {
            console.log('Displaying issues:', issues);
            const issuesList = document.getElementById('issues-list');
            issuesList.innerHTML = issues.length === 0 ? '<p>No issues found</p>' : issues.map(issue => {
                let partsHtml = '';
                if (issue.parts_replaced) {
                    const parts = issue.parts_replaced.split(',').map(part => part.trim());
                    partsHtml = `
                        <div class="parts-section" style="display: none;">
                            <p><strong>Parts Replaced:</strong></p>
                            <ul style="margin: 0; padding-left: 20px;">
                                ${parts.map(part => `<li>${part}</li>`).join('')}
                            </ul>
                        </div>
                        <button class="toggle-parts-btn" style="margin-top: 5px; padding: 5px 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Show Parts</button>
                    `;
                }
                return `
                    <div class="issue-item" data-issue-id="${issue.issue_id}">
                        <p><strong>Type:</strong> ${issue.issue_type}</p>
                        <p><strong>Description:</strong> ${issue.description || 'No description'}</p>
                        <p><strong>Status:</strong> ${issue.status}</p>
                        <p><strong>Created:</strong> ${new Date(issue.created_at).toLocaleString()}</p>
                        ${issue.status === 'Resolved' && issue.resolution_id ? `
                            <p><strong>Resolved:</strong> ${new Date(issue.resolved_at).toLocaleString()}</p>
                            <div class="resolution-details">
                                <p><strong>Work Done:</strong> ${issue.work_done}</p>
                                ${issue.invoice_number ? `<p><strong>Invoice:</strong> ${issue.invoice_number}</p>` : ''}
                                ${issue.labor_hours ? `<p><strong>Labor Hours:</strong> ${issue.labor_hours}</p>` : ''}
                                ${issue.repair_cost ? `<p><strong>Repair Cost:</strong> $${parseFloat(issue.repair_cost).toFixed(2)}</p>` : ''}
                                ${issue.mechanic_notes ? `<p><strong>Mechanic Notes:</strong> ${issue.mechanic_notes}</p>` : ''}
                                ${issue.repair_category ? `<p><strong>Category:</strong> ${issue.repair_category}</p>` : ''}
                                ${partsHtml}
                            </div>
                        ` : ''}
                        <div class="action-buttons">
                            ${issue.status === 'Open' ? `
                                <button class="resolve-issue-btn" data-issue-id="${issue.issue_id}">Resolve</button>
                            ` : ''}
                            ${issue.status === 'Resolved' ? `
                                <button class="edit-resolution-btn" data-issue-id="${issue.issue_id}">Edit Resolution</button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');

            // Add toggle parts listeners
            issuesList.querySelectorAll('.toggle-parts-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const partsSection = btn.previousElementSibling;
                    if (partsSection.style.display === 'none') {
                        partsSection.style.display = 'block';
                        btn.textContent = 'Hide Parts';
                    } else {
                        partsSection.style.display = 'none';
                        btn.textContent = 'Show Parts';
                    }
                });
            });

            addIssueResolutionListeners();
        } catch (error) {
            console.error('Error displaying issues:', error);
        }
    }

    // Render parts checkboxes with unique IDs and no inline styles
    function renderPartsCheckboxes(container, selectedParts = []) {
        container.innerHTML = ''; // Clear existing checkboxes
        vehicleParts.forEach((part, index) => {
            const checkboxId = `part-checkbox-${index}-${Date.now()}`; // Unique ID
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = checkboxId;
            checkbox.name = 'parts_replaced';
            checkbox.value = part;
            checkbox.className = 'part-checkbox';
            if (selectedParts.includes(part)) {
                checkbox.checked = true;
            }

            const label = document.createElement('label');
            label.htmlFor = checkboxId; // Associate with checkbox
            label.textContent = part;
            label.className = 'part-checkbox-label';

            const div = document.createElement('div');
            div.className = 'part-checkbox-container';
            div.appendChild(checkbox);
            div.appendChild(label);
            container.appendChild(div);
        });
    }

    function addIssueResolutionListeners() {
        try {
            // Handle Resolve button
            document.querySelectorAll('.resolve-issue-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    try {
                        const issueId = btn.dataset.issueId;
                        console.log(`Opening resolution modal for issue ${issueId}`);
                        // Populate parts checkboxes
                        const partsContainer = document.querySelector('.parts-checkboxes');
                        renderPartsCheckboxes(partsContainer, []);
                        // Reset form
                        const form = document.getElementById('resolution-form');
                        form.reset();
                        document.getElementById('resolution-modal-title').textContent = 'Resolve Issue';
                        document.getElementById('save-resolution-btn').style.display = 'block';
                        document.getElementById('edit-resolution-btn').style.display = 'none';
                        document.getElementById('resolution-modal-message').textContent = '';
                        issueResolutionModal.dataset.issueId = issueId;
                        issueResolutionModal.style.display = 'block';
                        activateFloatingLabels(document.querySelector('#issue-resolution-modal'));
                        styleSelectPlaceholders(); // Apply placeholder styling
                    } catch (error) {
                        console.error('Error opening resolution modal:', error);
                        showMessage('issues-modal-message', 'Error opening resolution form', false);
                    }
                });
            });

            // Handle Edit Resolution button
            document.querySelectorAll('.edit-resolution-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    try {
                        const issueId = parseInt(btn.dataset.issueId);
                        console.log(`Editing resolution for issue ${issueId}`);
                        const response = await fetch(`/api/search_vehicles.php?action=get_issues&vehicle_id=${currentVehicleId}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-Token': csrfToken
                            }
                        });
                        const data = await response.json();
                        console.log('Fetched issues:', data.issues);
                        if (data.success) {
                            const issue = data.issues.find(i => parseInt(i.issue_id) === issueId);
                            if (issue && issue.resolution_id) {
                                console.log('Found issue:', issue);
                                // Populate parts checkboxes
                                const partsContainer = document.querySelector('.parts-checkboxes');
                                const parts = issue.parts_replaced ? issue.parts_replaced.split(',').map(p => p.trim()) : [];
                                renderPartsCheckboxes(partsContainer, parts);
                                // Populate form
                                document.getElementById('resolution-modal-title').textContent = 'Edit Resolution';
                                document.getElementById('resolution-work-done').value = issue.work_done || '';
                                document.getElementById('resolution-invoice-number').value = issue.invoice_number || '';
                                document.getElementById('resolution-labor-hours').value = issue.labor_hours || '';
                                document.getElementById('resolution-repair-cost').value = issue.repair_cost || '';
                                document.getElementById('resolution-mechanic-notes').value = issue.mechanic_notes || '';
                                document.getElementById('resolution-repair-category').value = issue.repair_category || '';
                                document.getElementById('save-resolution-btn').style.display = 'none';
                                document.getElementById('edit-resolution-btn').style.display = 'block';
                                document.getElementById('resolution-modal-message').textContent = '';
                                issueResolutionModal.dataset.issueId = issueId.toString();
                                issueResolutionModal.style.display = 'block';
                                activateFloatingLabels(document.querySelector('#issue-resolution-modal'));
                                styleSelectPlaceholders(); // Apply placeholder styling
                            } else {
                                console.error('Issue not found or no resolution_id for issue:', issueId);
                                showMessage('issues-modal-message', 'Resolution details not found', false);
                            }
                        } else {
                            console.error('API error:', data.message);
                            showMessage('issues-modal-message', data.message || 'Error fetching issue details', false);
                        }
                    } catch (error) {
                        console.error('Error editing resolution:', error);
                        showMessage('issues-modal-message', 'Error editing resolution', false);
                    }
                });
            });
        } catch (error) {
            console.error('Error adding issue resolution listeners:', error);
        }
    }

    async function handleResolutionSubmit(action) {
        try {
            const issueId = issueResolutionModal.dataset.issueId;
            const checkedParts = Array.from(document.querySelectorAll('.parts-checkboxes input[type="checkbox"]:checked')).map(checkbox => checkbox.value);
            const selectedParts = checkedParts.join(',');
            const workDone = document.getElementById('resolution-work-done').value.trim();
            const invoiceNumber = document.getElementById('resolution-invoice-number').value.trim();
            const laborHours = document.getElementById('resolution-labor-hours').value.trim();
            const repairCost = document.getElementById('resolution-repair-cost').value.trim();
            const mechanicNotes = document.getElementById('resolution-mechanic-notes').value.trim();
            const repairCategory = document.getElementById('resolution-repair-category').value;

            // Validate inputs
            if (!workDone) {
                showMessage('resolution-modal-message', 'Work done is required', false);
                return;
            }
            if (!repairCategory) {
                showMessage('resolution-modal-message', 'Please select a repair category', false);
                return;
            }
            if (invoiceNumber && !/^[A-Za-z0-9-]{1,50}$/.test(invoiceNumber)) {
                showMessage('resolution-modal-message', 'Invoice number must be alphanumeric with dashes, max 50 characters', false);
                return;
            }
            if (laborHours && (isNaN(laborHours) || laborHours < 0 || laborHours > 99.99)) {
                showMessage('resolution-modal-message', 'Labor hours must be between 0 and 99.99', false);
                return;
            }
            if (repairCost && (isNaN(repairCost) || repairCost < 0 || repairCost > 999999.99)) {
                showMessage('resolution-modal-message', 'Repair cost must be between 0 and 999999.99', false);
                return;
            }

            const resolutionData = {
                parts_replaced: selectedParts,
                work_done: workDone,
                invoice_number: invoiceNumber,
                labor_hours: laborHours ? parseFloat(laborHours) : null,
                repair_cost: repairCost ? parseFloat(repairCost) : null,
                mechanic_notes: mechanicNotes,
                repair_category: repairCategory
            };
            console.log(`Submitting ${action} for issue ${issueId}:`, JSON.stringify({ action, issue_id: issueId, resolution_data: resolutionData }, null, 2));

            const { response, data } = await makePostRequest('/api/search_vehicles.php', {
                action,
                issue_id: issueId,
                resolution_data: resolutionData
            });
            console.log(`Response for ${action} (status ${response.status}):`, JSON.stringify(data, null, 2));

            if (data.success) {
                showMessage('resolution-modal-message', action === 'resolve_issue_with_details' ? 'Issue resolved successfully' : 'Resolution updated successfully', true);
                setTimeout(() => {
                    closeModal(issueResolutionModal);
                    fetchVehicleIssues(currentVehicleId);
                }, 1000);
            } else {
                showMessage('resolution-modal-message', data.message || `Failed to ${action === 'resolve_issue_with_details' ? 'resolve issue' : 'update resolution'}`, false);
            }
        } catch (error) {
            console.error(`Error in ${action}:`, error);
            showMessage('resolution-modal-message', `Error ${action === 'resolve_issue_with_details' ? 'resolving issue' : 'updating resolution'}`, false);
        }
    }

    // Save resolution handler
    document.getElementById('save-resolution-btn').addEventListener('click', () => {
        handleResolutionSubmit('resolve_issue_with_details');
    });

    // Edit resolution handler
    document.getElementById('edit-resolution-btn').addEventListener('click', () => {
        handleResolutionSubmit('edit_issue_resolution');
    });

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

    function showMessage(elementId, message, isSuccess) {
        try {
            const messageElement = document.getElementById(elementId);
            if (!message) {
                messageElement.textContent = '';
                messageElement.className = '';
                messageElement.style.display = 'none';
                return;
            }
            messageElement.textContent = message;
            messageElement.className = isSuccess ? 'success' : 'error';
            messageElement.style.display = 'block';
            setTimeout(() => {
                messageElement.textContent = '';
                messageElement.className = '';
                messageElement.style.display = 'none';
            }, 3000);
        } catch (error) {
            console.error('Error showing message:', error);
        }
    }

    function closeModal(modal) {
        try {
            modal.style.display = 'none';
            document.getElementById('modal-message').textContent = '';
            document.getElementById('modal-message').className = '';
            document.getElementById('create-modal-message').textContent = '';
            document.getElementById('create-modal-message').className = '';
            document.getElementById('issues-modal-message').textContent = '';
            document.getElementById('issues-modal-message').className = '';
            document.getElementById('issues-modal-message').style.display = 'none';
            document.getElementById('resolution-modal-message').textContent = '';
            document.getElementById('resolution-modal-message').className = '';
            document.getElementById('resolution-modal-message').style.display = 'none';
        } catch (error) {
            console.error('Error closing modal:', error);
        }
    }

    async function saveVehicleDetails() {
        try {
            console.log('Saving vehicle details:', currentVehicleId);
            const vehicleData = {
                make: document.getElementById('modal-make').value.trim(),
                model: document.getElementById('modal-model').value.trim(),
                vin: document.getElementById('modal-vin').value.trim(),
                color: document.getElementById('modal-color').value.trim(),
                license_plate: document.getElementById('modal-license-plate').value.trim(),
                year: parseInt(document.getElementById('modal-year').value) || 0,
                image_location: document.getElementById('modal-image-location').value.trim()
            };
            const { response, data } = await makePostRequest('/api/search_vehicles.php', {
                action: 'update_vehicle',
                vehicle_id: currentVehicleId,
                vehicle_data: vehicleData
            });
            const messageDiv = document.getElementById('modal-message');
            if (data.success) {
                messageDiv.textContent = 'Vehicle updated successfully';
                messageDiv.className = 'success';
                setTimeout(() => closeModal(vehicleDetailsModal), 1000);
                searchVehicles(vehicleSearch.value);
            } else {
                messageDiv.textContent = data.message || 'Failed to update vehicle';
                messageDiv.className = 'error';
            }
        } catch (error) {
            console.error('Error updating vehicle:', error);
            document.getElementById('modal-message').textContent = 'Error updating vehicle';
            document.getElementById('modal-message').className = 'error';
        }
    }

    async function createVehicle() {
        try {
            console.log('Creating vehicle');
            const vehicleData = {
                make: document.getElementById('create-make').value.trim(),
                model: document.getElementById('create-model').value.trim(),
                vin: document.getElementById('create-vin').value.trim(),
                color: document.getElementById('create-color').value.trim(),
                license_plate: document.getElementById('create-license-plate').value.trim(),
                year: parseInt(document.getElementById('create-year').value) || 0,
                image_location: document.getElementById('create-image-location').value.trim()
            };
            const { response, data } = await makePostRequest('/api/search_vehicles.php', {
                action: 'create_vehicle',
                vehicle_data: vehicleData
            });
            const messageDiv = document.getElementById('create-modal-message');
            if (data.success) {
                messageDiv.textContent = 'Vehicle created successfully';
                messageDiv.className = 'success';
                setTimeout(() => closeModal(createVehicleModal), 1000);
                searchVehicles(vehicleSearch.value);
            } else {
                messageDiv.textContent = data.message || 'Failed to create vehicle';
                messageDiv.className = 'error';
            }
        } catch (error) {
            console.error('Error creating vehicle:', error);
            document.getElementById('create-modal-message').textContent = 'Error creating vehicle';
            document.getElementById('create-modal-message').className = 'error';
        }
    }

    async function addIssue() {
        try {
            const issueType = document.getElementById('issue-type').value;
            const description = document.getElementById('issue-description').value.trim();
            if (!issueType) {
                showMessage('issues-modal-message', 'Please select an issue type', false);
                return;
            }
            console.log(`Adding issue for vehicle ${currentVehicleId}: ${issueType}`);
            const { response, data } = await makePostRequest('/api/search_vehicles.php', {
                action: 'add_issue',
                vehicle_id: currentVehicleId,
                issue_type: issueType,
                description: description || null
            });
            if (data.success) {
                document.getElementById('issue-type').value = '';
                document.getElementById('issue-description').value = '';
                showMessage('issues-modal-message', 'Issue added successfully', true);
                await fetchVehicleIssues(currentVehicleId);
            } else {
                showMessage('issues-modal-message', data.message || 'Failed to add issue', false);
            }
        } catch (error) {
            console.error('Error adding issue:', error);
            showMessage('issues-modal-message', 'Error adding issue', false);
        }
    }

    // Event listeners
    vehicleSearch.addEventListener('input', (e) => {
        console.log('Search input:', e.target.value);
        debounceSearch(e.target.value, 1);
    });

    createVehicleBtn.addEventListener('click', () => {
        try {
            console.log('Create vehicle button clicked');
            createVehicleModal.style.display = 'block';
            activateFloatingLabels(document.querySelector('#create-vehicle-modal'));
        } catch (error) {
            console.error('Error opening create modal:', error);
        }
    });

    document.querySelectorAll('.close-modal').forEach(span => {
        span.addEventListener('click', () => {
            try {
                console.log('Closing modal');
                closeModal(span.closest('.modal'));
            } catch (error) {
                console.error('Error closing modal:', error);
            }
        });
    });

    document.getElementById('save-vehicle-btn').addEventListener('click', saveVehicleDetails);
    document.getElementById('create-vehicle-save-btn').addEventListener('click', createVehicle);
    document.getElementById('add-issue-btn').addEventListener('click', addIssue);

    // Handle media query changes
    const mediaQuery = window.matchMedia('(max-width: 768px)');
    let resizeTimeout = null;
    mediaQuery.addEventListener('change', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            console.log('Media query changed: isMobile=', isMobile(), 'window.innerWidth=', window.innerWidth);
            searchVehicles(vehicleSearch.value, parseInt(new URLSearchParams(window.location.search).get('page')) || 1);
        }, 100);
    });

    // Handle popstate for browser back/forward
    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const query = params.get('query') || '';
        const page = parseInt(params.get('page')) || 1;
        searchVehicles(query, page);
    });

    // Initial search with URL parameters
    const params = new URLSearchParams(window.location.search);
    const initialQuery = params.get('query') || '';
    const initialPage = parseInt(params.get('page')) || 1;
    vehicleSearch.value = initialQuery;
    console.log('Initializing vehicle search with query:', initialQuery, 'page:', initialPage);
    searchVehicles(initialQuery, initialPage);

    // Close modal on outside click
    window.addEventListener('click', (event) => {
        try {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target);
            }
        } catch (error) {
            console.error('Error handling modal click:', error);
        }
    });

    // Initialize placeholder styling on page load
    styleSelectPlaceholders();
});