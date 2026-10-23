// ============================================================
// ADMIN PANEL JAVASCRIPT - Complete
// ============================================================

// ============================================================
// PAGE TITLE UPDATE
// ============================================================
const tabTitles = {
    dashboard: 'Dashboard',
    users: 'Users Management',
    services: 'Services Management',
    orders: 'Orders Management',
    payments: 'Payments',
    'payment-settings': 'Payment Settings',
    api: 'API Settings',
    settings: 'Site Settings',
    support: 'Support',
    broadcast: 'Broadcast',
    footer: 'Footer Management',
    promo: 'Promo Codes',
    pages: 'Pages Management',
    banners: 'Banner Management',
    profile: 'Profile'
};

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('active');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('active');
}

// ============================================================
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

// Close modal on overlay click
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });
});

// ============================================================
// NOTIFICATION
// ============================================================
function showNotification() {
    alert('📢 No new notifications.');
}

// ============================================================
// AJAX HELPER
// ============================================================
function ajaxRequest(url, data, method = 'POST') {
    return fetch(url, {
        method: method,
        body: data
    })
    .then(response => response.json())
    .catch(error => {
        console.error('AJAX Error:', error);
        return { success: false, message: 'Network error' };
    });
}

// ============================================================
// USER FUNCTIONS
// ============================================================
let currentFilter = 'all';

function filterUsers(filter) {
    if (filter) {
        currentFilter = filter;
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const btn = document.getElementById('filter-' + filter);
        if (btn) btn.classList.add('active');
    }

    const search = document.getElementById('user-search').value.toLowerCase();
    const rows = document.querySelectorAll('#users-table-body tr');

    rows.forEach(row => {
        const email = row.querySelector('.user-email')?.textContent?.toLowerCase() || '';
        const banned = row.dataset.banned === '1';
        let show = true;

        if (search && !email.includes(search)) show = false;
        if (currentFilter === 'online' && banned) show = false;
        if (currentFilter === 'banned' && !banned) show = false;

        row.style.display = show ? '' : 'none';
    });
}

function addUser(e) {
    e.preventDefault();
    const name = document.getElementById('new-user-name').value.trim();
    const email = document.getElementById('new-user-email').value.trim();
    const password = document.getElementById('new-user-password').value;
    const role = document.getElementById('new-user-role').value;
    const balance = document.getElementById('new-user-balance').value;

    if (!name || !email || password.length < 6) {
        alert('Please fill all fields correctly.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_user');
    formData.append('name', name);
    formData.append('email', email);
    formData.append('password', password);
    formData.append('role', role);
    formData.append('balance', balance);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function editUser(id) {
    const formData = new FormData();
    formData.append('action', 'get_user');
    formData.append('user_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        if (data.success) {
            const user = data.user;
            document.getElementById('edit-user-id').value = user.id;
            document.getElementById('edit-user-name').value = user.name || '';
            document.getElementById('edit-user-email').value = user.email;
            document.getElementById('edit-user-role').value = user.role;
            document.getElementById('edit-user-balance').value = user.balance;
            document.getElementById('edit-user-banned').value = user.banned;
            openModal('editUserModal');
        } else {
            alert(data.message || 'Error loading user data');
        }
    });
}

function saveUserEdit(e) {
    e.preventDefault();
    const id = document.getElementById('edit-user-id').value;
    const name = document.getElementById('edit-user-name').value.trim();
    const email = document.getElementById('edit-user-email').value.trim();
    const role = document.getElementById('edit-user-role').value;
    const balance = document.getElementById('edit-user-balance').value;
    const banned = document.getElementById('edit-user-banned').value;

    const formData = new FormData();
    formData.append('action', 'update_user');
    formData.append('user_id', id);
    formData.append('name', name);
    formData.append('email', email);
    formData.append('role', role);
    formData.append('balance', balance);
    formData.append('banned', banned);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('editUserModal');
            location.reload();
        }
    });
}

function toggleBan(id, status) {
    const action = status ? 'unban' : 'ban';
    if (!confirm(`Are you sure you want to ${action} this user?`)) return;

    const formData = new FormData();
    formData.append('action', 'toggle_ban');
    formData.append('user_id', id);
    formData.append('status', status);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function openBalanceModal(userId, email) {
    document.getElementById('balance-user-id').value = userId;
    document.getElementById('balance-user-email').value = email;
    document.getElementById('balance-amount').value = '';
    document.getElementById('balance-reason').value = '';

    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (row) {
        const balance = row.querySelector('.user-balance')?.textContent || '0.00';
        document.getElementById('balance-current').value = '₹' + balance;
    }

    openModal('balanceModal');
}

function updateBalance(e) {
    e.preventDefault();
    const userId = document.getElementById('balance-user-id').value;
    const action = document.getElementById('balance-action').value;
    const amount = document.getElementById('balance-amount').value;
    const reason = document.getElementById('balance-reason').value.trim();

    if (!amount || amount <= 0) {
        alert('Please enter a valid amount.');
        return;
    }

    if (!confirm(`Are you sure you want to ${action} ₹${amount} from this user?`)) return;

    const formData = new FormData();
    formData.append('action', action === 'add' ? 'add_balance' : 'cut_balance');
    formData.append('user_id', userId);
    formData.append('amount', amount);
    formData.append('reason', reason || (action === 'add' ? 'Admin added balance' : 'Admin cut balance'));

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('balanceModal');
            location.reload();
        }
    });
}

function resetPassword(userId) {
    const newPassword = prompt('Enter new password for this user (minimum 6 characters):');
    if (!newPassword) return;

    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters.');
        return;
    }

    if (!confirm(`Are you sure you want to reset password for user #${userId}?`)) return;

    const formData = new FormData();
    formData.append('action', 'reset_password');
    formData.append('user_id', userId);
    formData.append('password', newPassword);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function loginAsUser(userId) {
    if (!confirm(`Are you sure you want to login as this user?`)) return;

    const formData = new FormData();
    formData.append('action', 'login_as_user');
    formData.append('user_id', userId);

    ajaxRequest('ajax.php', formData).then(data => {
        if (data.success) {
            window.location.href = '../index.php';
        } else {
            alert(data.message || 'Error logging in as user');
        }
    });
}

// ============================================================
// SERVICE FUNCTIONS
// ============================================================
function filterServices() {
    const search = document.getElementById('service-search').value.toLowerCase();
    const rows = document.querySelectorAll('#services-table-body tr');

    rows.forEach(row => {
        const name = row.dataset.name || '';
        const code = row.dataset.code || '';
        const match = name.includes(search) || code.includes(search);
        row.style.display = match ? '' : 'none';
    });
}

function addService(e) {
    e.preventDefault();
    const code = document.getElementById('new-service-code').value.trim().toUpperCase();
    const name = document.getElementById('new-service-name').value.trim();
    const price = document.getElementById('new-service-price').value;
    const server_id = document.getElementById('new-service-server').value;
    const logo = document.getElementById('new-service-logo').value.trim();
    const api_id = document.getElementById('new-service-api-id').value.trim();

    if (!code || !name || !price) {
        alert('Please fill all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_service');
    formData.append('code', code);
    formData.append('name', name);
    formData.append('price', price);
    formData.append('server_id', server_id);
    formData.append('logo', logo);
    formData.append('api_service_id', api_id);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function editService(id) {
    const formData = new FormData();
    formData.append('action', 'get_service');
    formData.append('service_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        if (data.success) {
            const service = data.service;
            document.getElementById('edit-service-id').value = service.id;
            document.getElementById('edit-service-code').value = service.code;
            document.getElementById('edit-service-name').value = service.name;
            document.getElementById('edit-service-price').value = service.price;
            document.getElementById('edit-service-server').value = service.server_id;
            document.getElementById('edit-service-logo').value = service.logo || '';
            document.getElementById('edit-service-popular').value = service.popular || 0;
            document.getElementById('edit-service-cutback').value = service.cutback || 0;
            document.getElementById('edit-service-cancel-disable').value = service.cancel_disable || 2;
            openModal('editServiceModal');
        } else {
            alert(data.message || 'Error loading service data');
        }
    });
}

function saveServiceEdit(e) {
    e.preventDefault();
    const id = document.getElementById('edit-service-id').value;
    const code = document.getElementById('edit-service-code').value.trim().toUpperCase();
    const name = document.getElementById('edit-service-name').value.trim();
    const price = document.getElementById('edit-service-price').value;
    const server_id = document.getElementById('edit-service-server').value;
    const logo = document.getElementById('edit-service-logo').value.trim();
    const popular = document.getElementById('edit-service-popular').value;
    const cutback = document.getElementById('edit-service-cutback').value;
    const cancel_disable = document.getElementById('edit-service-cancel-disable').value;

    if (!code || !name || !price) {
        alert('Please fill all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_service');
    formData.append('service_id', id);
    formData.append('code', code);
    formData.append('name', name);
    formData.append('price', price);
    formData.append('server_id', server_id);
    formData.append('logo', logo);
    formData.append('popular', popular);
    formData.append('cutback', cutback);
    formData.append('cancel_disable', cancel_disable);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('editServiceModal');
            location.reload();
        }
    });
}

function deleteService(id) {
    if (!confirm('Are you sure you want to delete this service?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_service');
    formData.append('service_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function syncServices() {
    if (!confirm('Sync services from API providers?')) return;

    const formData = new FormData();
    formData.append('action', 'sync_services');

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// API PROVIDER FUNCTIONS
// ============================================================
function addApiProvider(e) {
    e.preventDefault();
    const name = document.getElementById('new-provider-name').value.trim();
    const provider_type = document.getElementById('new-provider-type').value;
    const api_key = document.getElementById('new-provider-api-key').value.trim();

    if (!name || !api_key) {
        alert('Please fill all fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_api_provider');
    formData.append('name', name);
    formData.append('provider_type', provider_type);
    formData.append('api_key', api_key);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function deleteApiProvider(id) {
    if (!confirm('Are you sure you want to delete this API provider?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_api_provider');
    formData.append('provider_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// IMPORT SERVICES
// ============================================================
let currentProviderId = 0;
let selectedCountries = [];
let allCountries = [];
let allServices = [];

function openImportModal(providerId) {
    currentProviderId = providerId;
    selectedCountries = [];
    allCountries = [];
    allServices = [];
    document.getElementById('import-step-1').style.display = 'block';
    document.getElementById('import-step-2').style.display = 'none';
    openModal('importServicesModal');
    loadCountries(providerId);
}

function loadCountries(providerId) {
    const countryList = document.getElementById('country-list');
    countryList.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading countries...</div>';

    const formData = new FormData();
    formData.append('action', 'get_countries');
    formData.append('provider_id', providerId);

    ajaxRequest('ajax.php', formData).then(data => {
        if (data.success) {
            allCountries = data.countries;
            countryList.innerHTML = '';
            allCountries.forEach(country => {
                const div = document.createElement('div');
                div.innerHTML = `
                    <label style="display:flex; align-items:center; gap:8px; padding:8px; border:1px solid var(--border-color); border-radius:8px; cursor:pointer;">
                        <input type="checkbox" value="${country.id}" onchange="toggleCountry('${country.id}')">
                        ${country.emoji || '🌍'} ${country.country}
                    </label>
                `;
                countryList.appendChild(div);
            });
        } else {
            countryList.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--danger);">' + data.message + '</div>';
        }
    });
}

function toggleCountry(id) {
    const index = selectedCountries.indexOf(id);
    if (index === -1) {
        selectedCountries.push(id);
    } else {
        selectedCountries.splice(index, 1);
    }
}

function selectAllCountries() {
    document.querySelectorAll('#country-list input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
        if (!selectedCountries.includes(cb.value)) {
            selectedCountries.push(cb.value);
        }
    });
}

function deselectAllCountries() {
    document.querySelectorAll('#country-list input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    selectedCountries = [];
}

function goToStep2() {
    if (selectedCountries.length === 0) {
        alert('Please select at least one country.');
        return;
    }
    document.getElementById('import-step-1').style.display = 'none';
    document.getElementById('import-step-2').style.display = 'block';
    loadServicesForCountries();
}

function goToStep1() {
    document.getElementById('import-step-2').style.display = 'none';
    document.getElementById('import-step-1').style.display = 'block';
}

function loadServicesForCountries() {
    const serviceList = document.getElementById('service-list');
    serviceList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Loading services...</div>';

    let allLoadedServices = [];
    let completed = 0;

    selectedCountries.forEach(countryId => {
        const formData = new FormData();
        formData.append('action', 'get_services_by_country');
        formData.append('provider_id', currentProviderId);
        formData.append('country_code', countryId);
        formData.append('margin_type', document.getElementById('margin-type').value);
        formData.append('margin_value', document.getElementById('margin-value').value);

        ajaxRequest('ajax.php', formData).then(data => {
            completed++;
            if (data.success && data.services) {
                data.services.forEach(service => {
                    service.country_id = countryId;
                    allLoadedServices.push(service);
                });
            }

            if (completed === selectedCountries.length) {
                allServices = allLoadedServices;
                displayServices(allServices);
            }
        });
    });
}

function displayServices(services) {
    const serviceList = document.getElementById('service-list');
    if (services.length === 0) {
        serviceList.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted);">No services found.</div>';
        return;
    }

    let html = `
        <table style="width:100%; font-size:13px;">
            <thead>
                <tr>
                    <th style="padding:8px;"><input type="checkbox" onchange="toggleAllServices(this.checked)"></th>
                    <th style="padding:8px; text-align:left;">Service</th>
                    <th style="padding:8px; text-align:left;">Code</th>
                    <th style="padding:8px; text-align:right;">Price (INR)</th>
                    <th style="padding:8px; text-align:right;">With Margin</th>
                </tr>
            </thead>
            <tbody>
    `;

    services.forEach((service, index) => {
        const serviceName = service.name || service.code.toUpperCase();
        html += `
            <tr>
                <td style="padding:8px; text-align:center;">
                    <input type="checkbox" class="service-checkbox" data-index="${index}" onchange="updateSelectedCount()">
                </td>
                <td style="padding:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        ${service.logo ? `<img src="${service.logo}" style="width:24px; height:24px; border-radius:4px; object-fit:contain;" onerror="this.style.display='none'">` : ''}
                        <span>${serviceName}</span>
                    </div>
                </td>
                <td style="padding:8px;"><code style="font-size:12px;">${service.code}</code></td>
                <td style="padding:8px; text-align:right;">₹${service.original_price.toFixed(2)}</td>
                <td style="padding:8px; text-align:right; font-weight:bold; color:var(--accent);">₹${service.price_with_margin.toFixed(2)}</td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    serviceList.innerHTML = html;
    updateSelectedCount();
}

function toggleAllServices(checked) {
    document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = checked);
    updateSelectedCount();
}

function selectAllServices() {
    document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = true);
    updateSelectedCount();
}

function deselectAllServices() {
    document.querySelectorAll('.service-checkbox').forEach(cb => cb.checked = false);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.service-checkbox:checked').length;
    document.getElementById('selected-count').textContent = checked;
}

function updateMarginPreview() {
    if (selectedCountries.length > 0) {
        loadServicesForCountries();
    }
}

function importSelectedServices() {
    const checkedBoxes = document.querySelectorAll('.service-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one service to import.');
        return;
    }

    const selected = [];
    checkedBoxes.forEach(cb => {
        const index = parseInt(cb.dataset.index);
        selected.push(allServices[index]);
    });

    if (!confirm(`Import ${selected.length} services?`)) return;

    const formData = new FormData();
    formData.append('action', 'import_selected_services');
    formData.append('provider_id', currentProviderId);
    formData.append('country_code', selected[0].country_id || '0');
    formData.append('selected_services', JSON.stringify(selected));
    formData.append('margin_type', document.getElementById('margin-type').value);
    formData.append('margin_value', document.getElementById('margin-value').value);

    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('importServicesModal');
            location.reload();
        }
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i> Import Selected';
    });
}

function importAllServices() {
    if (!confirm('Import services from all active providers?')) return;

    const rows = document.querySelectorAll('#api-providers-table tr');
    const providerIds = [];
    rows.forEach(row => {
        const id = row.querySelector('td:first-child')?.textContent?.replace('#', '');
        if (id) providerIds.push(id);
    });

    if (providerIds.length === 0) {
        alert('No providers found.');
        return;
    }

    let completed = 0;
    let totalServers = 0;
    let totalServices = 0;
    let errors = [];

    providerIds.forEach(providerId => {
        const formData = new FormData();
        formData.append('action', 'import_services');
        formData.append('provider_id', providerId);

        ajaxRequest('ajax.php', formData).then(data => {
            completed++;
            if (data.success) {
                totalServers += data.total_servers || 0;
                totalServices += data.total_services || 0;
            } else {
                errors.push('Provider ' + providerId + ': ' + data.message);
            }

            if (completed === providerIds.length) {
                let msg = 'Import completed!\nTotal Servers: ' + totalServers + '\nTotal Services: ' + totalServices;
                if (errors.length > 0) {
                    msg += '\n\nErrors:\n' + errors.join('\n');
                }
                alert(msg);
                location.reload();
            }
        });
    });
}

// ============================================================
// ORDER FUNCTIONS
// ============================================================
function updateOrderStatus(id, status) {
    if (!confirm(`Update order #${id} to ${status}?`)) return;

    const formData = new FormData();
    formData.append('action', 'update_order_status');
    formData.append('order_id', id);
    formData.append('status', status);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// PAYMENT FUNCTIONS
// ============================================================
function updatePaymentStatus(id, status) {
    if (!confirm(`Update payment #${id} to ${status}?`)) return;

    const formData = new FormData();
    formData.append('action', 'update_payment_status');
    formData.append('payment_id', id);
    formData.append('status', status);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// SETTINGS FUNCTIONS
// ============================================================
function saveSettings(e) {
    e.preventDefault();
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    formData.append('action', 'save_settings');

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function savePaymentSettings(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'save_payment_settings');

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Settings';
    });
}

function updateCurrencyRate(e) {
    e.preventDefault();
    const rate = document.getElementById('usd-to-inr').value;

    if (!rate || rate <= 0) {
        alert('Please enter a valid rate.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_currency_rate');
    formData.append('usd_to_inr', rate);

    const btn = document.querySelector('#currencyRateForm button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    }).finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Update Rate';
    });
}

function saveSupportSettings(e) {
    e.preventDefault();
    const form = document.getElementById('supportForm');
    const formData = new FormData(form);
    formData.append('action', 'save_support_settings');

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function saveFooterSettings(e) {
    e.preventDefault();
    const form = document.getElementById('footerForm');
    const formData = new FormData(form);
    formData.append('action', 'save_footer_settings');

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

function addFooterLink(e) {
    e.preventDefault();
    const title = document.getElementById('new-footer-link-title').value.trim();
    const url = document.getElementById('new-footer-link-url').value.trim();

    if (!title || !url) {
        alert('Please fill all fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_footer_link');
    formData.append('title', title);
    formData.append('url', url);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('addFooterLinkModal');
            location.reload();
        }
    });
}

function deleteFooterLink(id) {
    if (!confirm('Are you sure you want to delete this footer link?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_footer_link');
    formData.append('link_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// BROADCAST FUNCTIONS
// ============================================================
function sendBroadcast(e) {
    e.preventDefault();
    const title = document.getElementById('broadcast-title').value.trim();
    const message = document.getElementById('broadcast-message').value.trim();
    const target = document.getElementById('broadcast-target').value;

    if (!title || !message) {
        alert('Please fill title and message.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'send_broadcast');
    formData.append('title', title);
    formData.append('message', message);
    formData.append('target', target);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            document.getElementById('broadcastForm').reset();
        }
    });
}

// ============================================================
// PROMO FUNCTIONS
// ============================================================
function addPromo(e) {
    e.preventDefault();
    const code = document.getElementById('new-promo-code').value.trim().toUpperCase();
    const name = document.getElementById('new-promo-name').value.trim();
    const amount = document.getElementById('new-promo-amount').value;
    const type = document.getElementById('new-promo-type').value;
    const max_uses = document.getElementById('new-promo-max-uses').value || null;
    const for_new_users = document.getElementById('new-promo-for-new').value;
    const valid_from = document.getElementById('new-promo-valid-from').value || null;
    const valid_until = document.getElementById('new-promo-valid-until').value || null;

    if (!code || !amount) {
        alert('Please fill all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_promo');
    formData.append('code', code);
    formData.append('name', name);
    formData.append('amount', amount);
    formData.append('type', type);
    formData.append('max_uses', max_uses);
    formData.append('for_new_users', for_new_users);
    if (valid_from) formData.append('valid_from', valid_from);
    if (valid_until) formData.append('valid_until', valid_until);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('addPromoModal');
            location.reload();
        }
    });
}

function deletePromo(id) {
    if (!confirm('Are you sure you want to delete this promo code?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_promo');
    formData.append('promo_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// PAGE FUNCTIONS
// ============================================================
function addPage(e) {
    e.preventDefault();
    const title = document.getElementById('new-page-title').value.trim();
    const slug = document.getElementById('new-page-slug').value.trim().toLowerCase();
    const content = document.getElementById('new-page-content').value;
    const status = document.getElementById('new-page-status').value;

    if (!title || !slug || !content) {
        alert('Please fill all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_page');
    formData.append('title', title);
    formData.append('slug', slug);
    formData.append('content', content);
    formData.append('status', status);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('addPageModal');
            location.reload();
        }
    });
}

function editPage(id) {
    const formData = new FormData();
    formData.append('action', 'get_page');
    formData.append('page_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        if (data.success) {
            const page = data.page;
            document.getElementById('edit-page-id').value = page.id;
            document.getElementById('edit-page-title').value = page.title;
            document.getElementById('edit-page-slug').value = page.slug;
            document.getElementById('edit-page-content').value = page.content;
            document.getElementById('edit-page-status').value = page.status;
            openModal('editPageModal');
        } else {
            alert(data.message || 'Error loading page data');
        }
    });
}

function savePageEdit(e) {
    e.preventDefault();
    const id = document.getElementById('edit-page-id').value;
    const title = document.getElementById('edit-page-title').value.trim();
    const slug = document.getElementById('edit-page-slug').value.trim().toLowerCase();
    const content = document.getElementById('edit-page-content').value;
    const status = document.getElementById('edit-page-status').value;

    if (!title || !slug || !content) {
        alert('Please fill all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_page');
    formData.append('page_id', id);
    formData.append('title', title);
    formData.append('slug', slug);
    formData.append('content', content);
    formData.append('status', status);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('editPageModal');
            location.reload();
        }
    });
}

function deletePage(id) {
    if (!confirm('Are you sure you want to delete this page?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_page');
    formData.append('page_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// BANNER FUNCTIONS
// ============================================================
function addBanner(e) {
    e.preventDefault();
    const title = document.getElementById('new-banner-title').value.trim();
    const subtitle = document.getElementById('new-banner-subtitle').value.trim();
    const image_url = document.getElementById('new-banner-image').value.trim();
    const link_url = document.getElementById('new-banner-link').value.trim();
    const position = document.getElementById('new-banner-position').value;
    const order = document.getElementById('new-banner-order').value;
    const status = document.getElementById('new-banner-status').value;

    if (!title || !image_url) {
        alert('Please fill all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'add_banner');
    formData.append('title', title);
    formData.append('subtitle', subtitle);
    formData.append('image_url', image_url);
    formData.append('link_url', link_url);
    formData.append('position', position);
    formData.append('order', order);
    formData.append('status', status);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('addBannerModal');
            location.reload();
        }
    });
}

function editBanner(id) {
    const formData = new FormData();
    formData.append('action', 'get_banner');
    formData.append('banner_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        if (data.success) {
            const banner = data.banner;
            document.getElementById('edit-banner-id').value = banner.id;
            document.getElementById('edit-banner-title').value = banner.title;
            document.getElementById('edit-banner-subtitle').value = banner.subtitle || '';
            document.getElementById('edit-banner-image').value = banner.image_url;
            document.getElementById('edit-banner-link').value = banner.link_url || '';
            document.getElementById('edit-banner-position').value = banner.position;
            document.getElementById('edit-banner-order').value = banner.order;
            document.getElementById('edit-banner-status').value = banner.status;
            openModal('editBannerModal');
        } else {
            alert(data.message || 'Error loading banner data');
        }
    });
}

function saveBannerEdit(e) {
    e.preventDefault();
    const id = document.getElementById('edit-banner-id').value;
    const title = document.getElementById('edit-banner-title').value.trim();
    const subtitle = document.getElementById('edit-banner-subtitle').value.trim();
    const image_url = document.getElementById('edit-banner-image').value.trim();
    const link_url = document.getElementById('edit-banner-link').value.trim();
    const position = document.getElementById('edit-banner-position').value;
    const order = document.getElementById('edit-banner-order').value;
    const status = document.getElementById('edit-banner-status').value;

    if (!title || !image_url) {
        alert('Please fill all required fields.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_banner');
    formData.append('banner_id', id);
    formData.append('title', title);
    formData.append('subtitle', subtitle);
    formData.append('image_url', image_url);
    formData.append('link_url', link_url);
    formData.append('position', position);
    formData.append('order', order);
    formData.append('status', status);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            closeModal('editBannerModal');
            location.reload();
        }
    });
}

function deleteBanner(id) {
    if (!confirm('Are you sure you want to delete this banner?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_banner');
    formData.append('banner_id', id);

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// PROFILE FUNCTIONS
// ============================================================
function saveProfile(e) {
    e.preventDefault();
    const name = document.getElementById('profile-name').value.trim();
    const email = document.getElementById('profile-email').value.trim();
    const password = document.getElementById('profile-password').value;
    const confirm = document.getElementById('profile-password-confirm').value;

    if (!name || !email) {
        alert('Name and email are required.');
        return;
    }

    if (password && password !== confirm) {
        alert('Passwords do not match.');
        return;
    }

    if (password && password.length < 6) {
        alert('Password must be at least 6 characters.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'update_admin_profile');
    formData.append('name', name);
    formData.append('email', email);
    if (password) {
        formData.append('password', password);
    }

    ajaxRequest('ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) location.reload();
    });
}

// ============================================================
// DASHBOARD STATS
// ============================================================
function loadDashboardStats() {
    const formData = new FormData();
    formData.append('action', 'get_dashboard_stats');

    ajaxRequest('ajax.php', formData).then(data => {
        if (data.success) {
            document.getElementById('today-orders').textContent = data.today?.orders || 0;
            document.getElementById('today-revenue').textContent = '₹' + (parseFloat(data.today?.revenue) || 0).toFixed(2);
            document.getElementById('week-orders').textContent = data.week?.orders || 0;
            document.getElementById('week-revenue').textContent = '₹' + (parseFloat(data.week?.revenue) || 0).toFixed(2);
            document.getElementById('month-orders').textContent = data.month?.orders || 0;
            document.getElementById('month-revenue').textContent = '₹' + (parseFloat(data.month?.revenue) || 0).toFixed(2);

            let servicesHTML = '';
            if (data.topServices && data.topServices.length > 0) {
                data.topServices.forEach((s, i) => {
                    servicesHTML += `
                        <div style="display:flex; justify-content:space-between; padding:8px 12px; border-bottom:1px solid var(--border-color);">
                            <span>${i+1}. ${s.service_name}</span>
                            <span style="font-weight:600; color:var(--accent);">${s.count} orders</span>
                        </div>
                    `;
                });
            } else {
                servicesHTML = '<p style="color:var(--text-muted); text-align:center; padding:20px;">No data</p>';
            }
            document.getElementById('top-services-list').innerHTML = servicesHTML;

            let usersHTML = '';
            if (data.topUsers && data.topUsers.length > 0) {
                data.topUsers.forEach((u, i) => {
                    usersHTML += `
                        <div style="display:flex; justify-content:space-between; padding:8px 12px; border-bottom:1px solid var(--border-color);">
                            <span>${i+1}. ${u.email}</span>
                            <span style="font-weight:600; color:var(--accent);">${u.orders} orders | ₹${parseFloat(u.spent).toFixed(2)}</span>
                        </div>
                    `;
                });
            } else {
                usersHTML = '<p style="color:var(--text-muted); text-align:center; padding:20px;">No data</p>';
            }
            document.getElementById('top-users-list').innerHTML = usersHTML;
        }
    });
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(el => {
            el.classList.remove('show');
        });
    }
});

// ============================================================
// INITIALIZATION
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Load dashboard stats if on dashboard page
    if (document.getElementById('today-orders')) {
        loadDashboardStats();
    }

    // Discount - Show/Hide Service Select
    const applyTo = document.getElementById('discount-apply-to');
    if (applyTo) {
        applyTo.addEventListener('change', function() {
            const serviceGroup = document.getElementById('discount-service-group');
            serviceGroup.style.display = this.value === 'specific' ? 'block' : 'none';
        });
    }

    // Edit Server - Headers toggle
    const editHeaders = document.getElementById('edit-server-has-headers');
    if (editHeaders) {
        editHeaders.addEventListener('change', function() {
            document.getElementById('edit-headers-container').style.display = this.value === '1' ? 'block' : 'none';
        });
    }

    console.log('✅ Admin JS Loaded Successfully!');
});