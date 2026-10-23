// ============================================================
// USER PANEL JAVASCRIPT - Complete
// ============================================================

// ============================================================
// THEME TOGGLE
// ============================================================
function toggleTheme() {
    document.body.classList.toggle('dark-mode');
    const btn = document.getElementById('theme-btn');
    if (btn) {
        const icon = btn.querySelector('i');
        if (document.body.classList.contains('dark-mode')) {
            icon.className = 'fa-solid fa-sun';
        } else {
            icon.className = 'fa-solid fa-moon';
        }
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    }
}

// Load saved theme
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        const themeBtn = document.getElementById('theme-btn');
        if (themeBtn) {
            themeBtn.querySelector('i').className = 'fa-solid fa-sun';
        }
    }
});

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

function closeAllPanels() {
    closeSidebar();
    closeRightPanel();
}

// ============================================================
// RIGHT PANEL
// ============================================================
function openRightPanel() {
    document.getElementById('right-panel').classList.add('open');
}

function closeRightPanel() {
    document.getElementById('right-panel').classList.remove('open');
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
// SERVICE SELECTION - Service → Country → Server Flow
// ============================================================
let selectedServiceId = null;
let selectedCountry = null;

function selectServiceForPurchase(serviceId, code, name, price) {
    selectedServiceId = serviceId;
    selectedCountry = null;
    
    // Highlight selected service
    document.querySelectorAll('.service-card').forEach(c => c.classList.remove('active'));
    document.querySelector(`.service-card[data-code="${code}"]`)?.classList.add('active');
    
    // Show country selection in right panel
    document.getElementById('rp-content').innerHTML = `
        <h3 style="font-weight:700; margin-bottom:5px;">${name}</h3>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:15px;">${code} - ₹${price}</p>
        <p style="font-weight:600; margin-bottom:10px;">Select Country</p>
        <div class="country-grid" id="country-grid">
            <div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--text-muted);">
                <i class="fa-solid fa-spinner fa-spin"></i> Loading countries...
            </div>
        </div>
    `;
    
    // Load countries from API
    const formData = new FormData();
    formData.append('action', 'get_countries_for_service');
    formData.append('service_id', serviceId);
    
    ajaxRequest('../user/ajax.php', formData).then(data => {
        const grid = document.getElementById('country-grid');
        if (data.success && data.countries && data.countries.length > 0) {
            grid.innerHTML = '';
            data.countries.forEach(country => {
                const div = document.createElement('div');
                div.className = 'country-item';
                div.innerHTML = `${country.emoji || '🌍'} ${country.country}`;
                div.dataset.countryId = country.id;
                div.onclick = function() {
                    selectCountry(country.id, country.country);
                };
                grid.appendChild(div);
            });
        } else {
            grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--danger);">${data.message || 'No countries found'}</div>`;
        }
    });
    
    if (window.innerWidth <= 900) {
        openRightPanel();
    }
}

function selectCountry(countryId, countryName) {
    selectedCountry = countryId;
    
    // Highlight selected country
    document.querySelectorAll('.country-item').forEach(c => c.classList.remove('active'));
    document.querySelector(`.country-item[data-country-id="${countryId}"]`)?.classList.add('active');
    
    // Show servers for this country
    const rpContent = document.getElementById('rp-content');
    const serverSection = document.createElement('div');
    serverSection.id = 'server-section';
    serverSection.innerHTML = `
        <div style="margin-top:15px; border-top:1px solid var(--border-color); padding-top:15px;">
            <p style="font-weight:600; margin-bottom:10px;">Select Server for ${countryName}</p>
            <div class="server-grid" id="server-grid">
                <div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--text-muted);">
                    <i class="fa-solid fa-spinner fa-spin"></i> Loading servers...
                </div>
            </div>
        </div>
    `;
    
    // Remove old server section if exists
    const oldSection = document.getElementById('server-section');
    if (oldSection) oldSection.remove();
    
    rpContent.appendChild(serverSection);
    
    // Load servers for this country
    const formData = new FormData();
    formData.append('action', 'get_servers_for_country');
    formData.append('country_code', countryId);
    formData.append('service_id', selectedServiceId);
    
    ajaxRequest('../user/ajax.php', formData).then(data => {
        const grid = document.getElementById('server-grid');
        if (data.success && data.servers && data.servers.length > 0) {
            grid.innerHTML = '';
            data.servers.forEach(server => {
                const div = document.createElement('div');
                div.className = 'server-item';
                div.innerHTML = `${server.country_emoji || '🌍'} ${server.name}<br><small style="font-size:11px; color:var(--text-muted);">₹${server.price}</small>`;
                div.dataset.serverId = server.id;
                div.onclick = function() {
                    purchaseNumber(server);
                };
                grid.appendChild(div);
            });
        } else {
            grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--danger);">${data.message || 'No servers found'}</div>`;
        }
    });
}

// ============================================================
// PURCHASE NUMBER - Real Time API Call
// ============================================================
function purchaseNumber(server) {
    if (!confirm(`Purchase ${server.service_name} for ₹${server.price} from ${server.name}?`)) return;
    
    const btn = event.target;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Purchasing...';
    btn.disabled = true;
    
    const formData = new FormData();
    formData.append('service_id', server.service_id);
    
    ajaxRequest('../user/buy-number.php', formData).then(data => {
        if (data.success) {
            alert('✅ ' + data.message + '\nNumber: ' + data.number);
            
            // Update balance
            document.getElementById('bal-amt').textContent = data.new_balance;
            document.getElementById('nav-bal-amt').textContent = data.new_balance;
            
            // Update right panel
            document.getElementById('rp-content').innerHTML = `
                <div style="text-align:center; padding:20px;">
                    <i class="fa-regular fa-circle-check" style="font-size:48px; color:var(--success);"></i>
                    <h3 style="margin-top:10px;">Number Purchased!</h3>
                    <p style="font-size:24px; font-weight:700; color:var(--accent); margin:10px 0;">${data.number}</p>
                    <p style="color:var(--text-muted);">Check Active Numbers for OTP</p>
                    <button class="btn-primary" style="margin-top:15px;" onclick="switchTab('numbers', document.getElementById('nav-numbers'))">View Active Numbers</button>
                </div>
            `;
            
            // Update active count
            const countBadge = document.getElementById('active-count');
            if (countBadge) {
                const current = parseInt(countBadge.textContent) || 0;
                countBadge.textContent = current + 1;
            }
            
            setTimeout(() => location.reload(), 3000);
        } else {
            alert('❌ ' + data.message);
        }
    }).finally(() => {
        btn.innerHTML = 'Buy Now';
        btn.disabled = false;
    });
}

// ============================================================
// RECHARGE FUNCTIONS
// ============================================================
let selectedAmount = null;

function selectAmount(el, amount) {
    document.querySelectorAll('.amount-option').forEach(d => {
        d.classList.remove('active');
        d.style.borderColor = 'var(--border-color)';
    });
    el.classList.add('active');
    el.style.borderColor = 'var(--accent)';
    selectedAmount = amount;
}

function selectCustomAmount() {
    document.querySelectorAll('.amount-option').forEach(d => {
        d.classList.remove('active');
        d.style.borderColor = 'var(--border-color)';
    });
    const el = document.querySelector('.amount-option:last-child');
    el.classList.add('active');
    el.style.borderColor = 'var(--accent)';
    selectedAmount = document.getElementById('custom-amount').value;
}

function openUPIModal() {
    if (!selectedAmount || selectedAmount <= 0) {
        alert('Please select an amount first');
        return;
    }
    document.getElementById('upi-modal').classList.add('show');
}

function openBharatPeModal() {
    if (!selectedAmount || selectedAmount <= 0) {
        alert('Please select an amount first');
        return;
    }
    document.getElementById('bharatpe-modal').classList.add('show');
}

function openTRXModal() {
    if (!selectedAmount || selectedAmount <= 0) {
        alert('Please select an amount first');
        return;
    }
    document.getElementById('trx-modal').classList.add('show');
}

function processRecharge() {
    if (!selectedAmount || selectedAmount <= 0) {
        alert('Please select an amount');
        return;
    }
    alert('Recharge of ₹' + selectedAmount + ' initiated! Please complete payment.\n\nAfter payment, contact support with UTR number.');
}

// ============================================================
// COPY FUNCTIONS
// ============================================================
function copyUPI() {
    const upi = document.querySelector('#upi-modal .modal-content div div')?.textContent || '';
    if (upi) {
        navigator.clipboard.writeText(upi).then(() => {
            alert('UPI copied: ' + upi);
        }).catch(() => {
            alert('UPI: ' + upi);
        });
    }
}

function copyBharatPe() {
    const upi = document.querySelector('#bharatpe-modal .modal-content div div')?.textContent || '';
    if (upi) {
        navigator.clipboard.writeText(upi).then(() => {
            alert('BharatPe UPI copied: ' + upi);
        }).catch(() => {
            alert('BharatPe UPI: ' + upi);
        });
    }
}

function copyTRX() {
    const trx = document.querySelector('#trx-modal .modal-content div div')?.textContent || '';
    if (trx) {
        navigator.clipboard.writeText(trx).then(() => {
            alert('TRX address copied!');
        }).catch(() => {
            alert('TRX: ' + trx);
        });
    }
}

// ============================================================
// PROMO CODE
// ============================================================
function applyPromoCode() {
    const code = document.getElementById('promo-code-input').value.trim().toUpperCase();
    const msgDiv = document.getElementById('promo-message');
    
    if (!code) {
        msgDiv.innerHTML = '<span style="color:var(--danger);">Please enter a promo code.</span>';
        return;
    }

    const formData = new FormData();
    formData.append('action', 'redeem_promo');
    formData.append('code', code);

    ajaxRequest('../user/ajax.php', formData).then(data => {
        if (data.success) {
            msgDiv.innerHTML = '<span style="color:var(--success);">✅ ' + data.message + ' Balance added: ₹' + data.amount + '</span>';
            if (data.new_balance) {
                document.getElementById('bal-amt').textContent = data.new_balance;
                document.getElementById('nav-bal-amt').textContent = data.new_balance;
            }
            setTimeout(() => location.reload(), 2000);
        } else {
            msgDiv.innerHTML = '<span style="color:var(--danger);">❌ ' + data.message + '</span>';
        }
    });
}

// ============================================================
// SERVICE FILTERS
// ============================================================
let currentServerFilter = 'all';

function filterByServer(serverId, btn) {
    try {
        currentServerFilter = serverId;
        
        document.querySelectorAll('.server-filter-btn').forEach(b => {
            b.classList.remove('active');
            b.style.background = 'transparent';
            b.style.color = 'var(--text-muted)';
            b.style.borderColor = 'var(--border-color)';
        });
        if(btn) {
            btn.classList.add('active');
            btn.style.background = 'var(--accent)';
            btn.style.color = 'white';
            btn.style.borderColor = 'var(--accent)';
        }
        
        document.querySelectorAll('.server-group').forEach(group => {
            const groupServerId = group.dataset.serverId;
            if(serverId === 'all' || groupServerId == serverId) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        });
        
        const searchQuery = document.getElementById('service-search');
        if(searchQuery && searchQuery.value) {
            filterServices(searchQuery.value);
        }
    } catch(e) {
        console.log('filterByServer error:', e);
    }
}

function filterServices(query) {
    try {
        const cards = document.querySelectorAll('.service-card');
        const q = query.toLowerCase().trim();
        
        cards.forEach(card => {
            const name = card.querySelector('.service-name')?.textContent?.toLowerCase() || '';
            const code = card.dataset.code?.toLowerCase() || '';
            const match = name.includes(q) || code.includes(q);
            card.style.display = match ? 'flex' : 'none';
        });
        
        document.querySelectorAll('.server-group').forEach(group => {
            let hasVisible = false;
            group.querySelectorAll('.service-card').forEach(card => {
                if(card.style.display !== 'none') {
                    hasVisible = true;
                }
            });
            if(!hasVisible && q) {
                group.style.display = 'none';
            } else if(currentServerFilter === 'all' || group.dataset.serverId == currentServerFilter) {
                group.style.display = 'block';
            }
        });
    } catch(e) {
        console.log('filterServices error:', e);
    }
}

// ============================================================
// HISTORY TABS
// ============================================================
function showHistoryTab(tab, el) {
    document.querySelectorAll('.history-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    
    document.getElementById('history-transactions').style.display = tab === 'transactions' ? 'block' : 'none';
    document.getElementById('history-orders').style.display = tab === 'orders' ? 'block' : 'none';
}

// ============================================================
// UPDATE PROFILE
// ============================================================
function updateProfile(e) {
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
    formData.append('action', 'update_profile');
    formData.append('name', name);
    formData.append('email', email);
    if (password) {
        formData.append('password', password);
    }

    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving...';

    ajaxRequest('../user/ajax.php', formData).then(data => {
        alert(data.message);
        if (data.success) {
            location.reload();
        }
        btn.disabled = false;
        btn.innerHTML = 'Update Profile';
    });
}

// ============================================================
// TAB SWITCHING
// ============================================================
function switchTab(tabId, menuItem) {
    document.querySelectorAll('.menu-item').forEach(item => item.classList.remove('active'));
    if (menuItem) menuItem.classList.add('active');
    
    document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
    const targetSection = document.getElementById('tab-' + tabId);
    if (targetSection) targetSection.classList.add('active');
    
    closeSidebar();
    
    if (window.innerWidth <= 900) {
        closeRightPanel();
    }
}

// ============================================================
// KEYBOARD SHORTCUTS
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(el => {
            el.classList.remove('show');
        });
        closeAllPanels();
    }
});

// ============================================================
// INITIALIZATION
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // Enter key for promo code
    const promoInput = document.getElementById('promo-code-input');
    if (promoInput) {
        promoInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyPromoCode();
        });
    }

    // Enter key for login
    const loginPass = document.getElementById('login-pass');
    if (loginPass) {
        loginPass.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') login();
        });
        document.getElementById('login-user')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') login();
        });
    }

    // Enter key for register
    const regPass = document.getElementById('reg-pass');
    if (regPass) {
        regPass.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') register();
        });
        document.getElementById('reg-user')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') register();
        });
        document.getElementById('reg-name')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') register();
        });
    }

    // Custom amount input
    const customAmount = document.getElementById('custom-amount');
    if (customAmount) {
        customAmount.addEventListener('input', function() {
            selectCustomAmount();
        });
    }

    console.log('✅ User JS Loaded Successfully!');
});