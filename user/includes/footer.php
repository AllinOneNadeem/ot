<?php
// ============================================================
// USER FOOTER - Closing Tags, Right Panel, Modals, JavaScript
// ============================================================
?>

        <!-- ============================================================
        RIGHT PANEL - Service Selection
        ============================================================ -->
        <div class="right-panel" id="right-panel">
            <div class="rp-swipe-indicator" onclick="closeRightPanel()"></div>
            <div id="rp-content">
                <div class="right-panel-placeholder">
                    <i class="fa-regular fa-hand-pointer" style="font-size: 48px; display: block; margin-bottom: 15px; color: var(--accent);"></i>
                    <h3 style="margin-bottom:5px;">Select a Service</h3>
                    <p style="font-size:14px; color:var(--text-muted);">Choose a service from the dashboard to get a number.</p>
                </div>
            </div>
        </div>

    </div> <!-- End app-container -->

    <!-- ============================================================
    MODALS
    ============================================================ -->

    <!-- UPI Modal -->
    <div class="modal-overlay" id="upi-modal" onclick="if(event.target===this) closeModal('upi-modal')">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-weight:700;"><i class="fa-brands fa-google-pay" style="color:#4285f4;"></i> Pay with UPI</h3>
                <button class="close-modal" onclick="closeModal('upi-modal')">✕</button>
            </div>
            <div style="background:var(--bg-body); padding:20px; border-radius:12px; margin-bottom:20px;">
                <p style="color:var(--text-muted); font-size:13px;">Scan QR or pay to this UPI</p>
                <div style="font-size:20px; font-weight:700; color:var(--accent); margin:10px 0; word-break:break-all;">
                    <?php echo htmlspecialchars($sitePayments['paytm_upi'] ?? 'your_upi@paytm'); ?>
                </div>
                <?php if(!empty($sitePayments['paytm_qr'])): ?>
                    <img src="<?php echo htmlspecialchars($sitePayments['paytm_qr']); ?>" style="max-width:200px; margin:10px auto; display:block; border-radius:8px;">
                <?php endif; ?>
                <button onclick="copyUPI()" style="background:var(--accent); color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px; margin-top:10px;">Copy UPI</button>
            </div>
            <p style="font-size:13px; color:var(--text-muted);">Minimum deposit: ₹<?php echo htmlspecialchars($sitePayments['min_deposit'] ?? 10); ?></p>
        </div>
    </div>

    <!-- BharatPe Modal -->
    <div class="modal-overlay" id="bharatpe-modal" onclick="if(event.target===this) closeModal('bharatpe-modal')">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-weight:700;"><i class="fa-solid fa-building-columns" style="color:#4CAF50;"></i> Pay with BharatPe</h3>
                <button class="close-modal" onclick="closeModal('bharatpe-modal')">✕</button>
            </div>
            <div style="background:var(--bg-body); padding:20px; border-radius:12px; margin-bottom:20px;">
                <p style="color:var(--text-muted); font-size:13px;">Scan QR or pay to this UPI</p>
                <div style="font-size:20px; font-weight:700; color:var(--accent); margin:10px 0; word-break:break-all;">
                    <?php echo htmlspecialchars($sitePayments['bharatpe_upi'] ?? ''); ?>
                </div>
                <?php if(!empty($sitePayments['bharatpe_qr'])): ?>
                    <img src="<?php echo htmlspecialchars($sitePayments['bharatpe_qr']); ?>" style="max-width:200px; margin:10px auto; display:block; border-radius:8px;">
                <?php endif; ?>
                <button onclick="copyBharatPe()" style="background:var(--accent); color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px; margin-top:10px;">Copy UPI</button>
            </div>
            <p style="font-size:13px; color:var(--text-muted);">Minimum deposit: ₹<?php echo htmlspecialchars($sitePayments['min_deposit'] ?? 10); ?></p>
        </div>
    </div>

    <!-- TRX Modal -->
    <div class="modal-overlay" id="trx-modal" onclick="if(event.target===this) closeModal('trx-modal')">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="font-weight:700;"><i class="fa-brands fa-bitcoin" style="color:#f7931a;"></i> Pay with TRX</h3>
                <button class="close-modal" onclick="closeModal('trx-modal')">✕</button>
            </div>
            <div style="background:var(--bg-body); padding:20px; border-radius:12px; margin-bottom:20px;">
                <p style="color:var(--text-muted); font-size:13px;">Send TRX to this address</p>
                <div style="font-size:14px; font-weight:700; color:var(--accent); margin:10px 0; word-break:break-all;">
                    <?php echo htmlspecialchars($sitePayments['trx_address'] ?? 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); ?>
                </div>
                <button onclick="copyTRX()" style="background:var(--accent); color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer; font-size:13px; margin-top:10px;">Copy Address</button>
            </div>
        </div>
    </div>

    <!-- ============================================================
    JAVASCRIPT
    ============================================================ -->
    <script>
        // ============================================================
        // THEME TOGGLE
        // ============================================================
        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            const btn = document.getElementById('theme-btn');
            const icon = btn.querySelector('i');
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'fa-solid fa-sun';
            } else {
                icon.className = 'fa-solid fa-moon';
            }
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            const themeBtn = document.getElementById('theme-btn');
            if (themeBtn) {
                themeBtn.querySelector('i').className = 'fa-solid fa-sun';
            }
        }

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
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        // ============================================================
        // COPY FUNCTIONS
        // ============================================================
        function copyUPI() {
            const upi = '<?php echo htmlspecialchars($sitePayments['paytm_upi'] ?? ''); ?>';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(upi).then(() => {
                    alert('UPI copied: ' + upi);
                }).catch(() => {
                    alert('UPI: ' + upi);
                });
            } else {
                alert('UPI: ' + upi);
            }
        }

        function copyBharatPe() {
            const upi = '<?php echo htmlspecialchars($sitePayments['bharatpe_upi'] ?? ''); ?>';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(upi).then(() => {
                    alert('BharatPe UPI copied: ' + upi);
                }).catch(() => {
                    alert('BharatPe UPI: ' + upi);
                });
            } else {
                alert('BharatPe UPI: ' + upi);
            }
        }

        function copyTRX() {
            const trx = '<?php echo htmlspecialchars($sitePayments['trx_address'] ?? 'TXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'); ?>';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(trx).then(() => {
                    alert('TRX address copied!');
                }).catch(() => {
                    alert('TRX: ' + trx);
                });
            } else {
                alert('TRX: ' + trx);
            }
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
                        if(card.style.display !== 'none') hasVisible = true;
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
        // SERVICE SELECTION
        // ============================================================
        let selectedServiceId = null;
        let selectedCountry = null;

        function selectServiceForPurchase(serviceId, code, name, price) {
            selectedServiceId = serviceId;
            selectedCountry = null;
            
            document.querySelectorAll('.service-card').forEach(c => c.classList.remove('active'));
            document.querySelector(`.service-card[data-code="${code}"]`)?.classList.add('active');
            
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
            
            const formData = new FormData();
            formData.append('action', 'get_countries_for_service');
            formData.append('service_id', serviceId);
            
            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
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
            })
            .catch(err => {
                document.getElementById('country-grid').innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--danger);">Error loading countries</div>`;
            });
            
            if (window.innerWidth <= 900) {
                openRightPanel();
            }
        }

        function selectCountry(countryId, countryName) {
            selectedCountry = countryId;
            document.querySelectorAll('.country-item').forEach(c => c.classList.remove('active'));
            document.querySelector(`.country-item[data-country-id="${countryId}"]`)?.classList.add('active');
            
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
            const oldSection = document.getElementById('server-section');
            if (oldSection) oldSection.remove();
            rpContent.appendChild(serverSection);
            
            const formData = new FormData();
            formData.append('action', 'get_servers_for_country');
            formData.append('country_code', countryId);
            formData.append('service_id', selectedServiceId);
            
            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
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
            })
            .catch(err => {
                document.getElementById('server-grid').innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:20px; color:var(--danger);">Error loading servers</div>`;
            });
        }

        // ============================================================
        // PURCHASE NUMBER
        // ============================================================
        function purchaseNumber(server) {
            if (!confirm(`Purchase ${server.service_name} for ₹${server.price} from ${server.name}?`)) return;
            
            const btn = event.target;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Purchasing...';
            btn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'purchase_service');
            formData.append('service_id', server.service_id);
            
            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message + '\nNumber: ' + data.number);
                    document.getElementById('bal-amt').textContent = data.new_balance;
                    document.getElementById('nav-bal-amt').textContent = data.new_balance;
                    
                    document.getElementById('rp-content').innerHTML = `
                        <div style="text-align:center; padding:20px;">
                            <i class="fa-regular fa-circle-check" style="font-size:48px; color:var(--success);"></i>
                            <h3 style="margin-top:10px;">Number Purchased!</h3>
                            <p style="font-size:24px; font-weight:700; color:var(--accent); margin:10px 0;">${data.number}</p>
                            <p style="color:var(--text-muted);">Check Active Numbers for OTP</p>
                            <button class="btn-primary" style="margin-top:15px;" onclick="window.location.href='?tab=numbers'">View Active Numbers</button>
                        </div>
                    `;
                    
                    const countBadge = document.getElementById('active-count');
                    if (countBadge) {
                        const current = parseInt(countBadge.textContent) || 0;
                        countBadge.textContent = current + 1;
                    }
                    setTimeout(() => location.reload(), 3000);
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
            })
            .finally(() => {
                btn.innerHTML = 'Buy Now';
                btn.disabled = false;
            });
        }

        // ============================================================
        // CHECK SMS & CANCEL NUMBER
        // ============================================================
        function checkSMS(orderId) {
            alert('📩 Checking SMS for order #' + orderId + '...\n\nCheck your active numbers list for OTP.');
        }

        function cancelNumber(orderId) {
            if (!confirm('Are you sure you want to cancel this number?')) return;
            
            const formData = new FormData();
            formData.append('action', 'cancel_order');
            formData.append('order_id', orderId);
            
            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
            })
            .catch(err => {
                alert('Error: ' + err.message);
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
            document.getElementById('custom-amount-input').style.display = 'none';
        }

        function customAmount(el) {
            document.querySelectorAll('.amount-option').forEach(d => {
                d.classList.remove('active');
                d.style.borderColor = 'var(--border-color)';
            });
            el.classList.add('active');
            el.style.borderColor = 'var(--accent)';
            document.getElementById('custom-amount-input').style.display = 'block';
            document.getElementById('custom-amount-value').focus();
            selectedAmount = null;
        }

        function openUPIModal() {
            const customVal = document.getElementById('custom-amount-value')?.value;
            if (customVal && customVal > 0) {
                selectedAmount = customVal;
            }
            if (!selectedAmount || selectedAmount <= 0) {
                alert('Please select an amount first');
                return;
            }
            document.getElementById('upi-modal').classList.add('show');
        }

        function openBharatPeModal() {
            const customVal = document.getElementById('custom-amount-value')?.value;
            if (customVal && customVal > 0) {
                selectedAmount = customVal;
            }
            if (!selectedAmount || selectedAmount <= 0) {
                alert('Please select an amount first');
                return;
            }
            document.getElementById('bharatpe-modal').classList.add('show');
        }

        function openTRXModal() {
            const customVal = document.getElementById('custom-amount-value')?.value;
            if (customVal && customVal > 0) {
                selectedAmount = customVal;
            }
            if (!selectedAmount || selectedAmount <= 0) {
                alert('Please select an amount first');
                return;
            }
            document.getElementById('trx-modal').classList.add('show');
        }

        function processRecharge() {
            const customVal = document.getElementById('custom-amount-value')?.value;
            if (customVal && customVal > 0) {
                selectedAmount = customVal;
            }
            if (!selectedAmount || selectedAmount <= 0) {
                alert('Please select an amount');
                return;
            }
            alert('Recharge of ₹' + selectedAmount + ' initiated! Please complete payment.');
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

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
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
            })
            .catch(err => {
                msgDiv.innerHTML = '<span style="color:var(--danger);">Error: ' + err.message + '</span>';
            });
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
            if (password) formData.append('password', password);

            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Saving...';

            fetch('ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) location.reload();
                btn.disabled = false;
                btn.innerHTML = 'Update Profile';
            })
            .catch(err => {
                alert('Error: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = 'Update Profile';
            });
        }

        // ============================================================
        // LOGOUT
        // ============================================================
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../logout.php';
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
                closeSidebar();
                closeRightPanel();
            }
        });

        // ============================================================
        // ENTER KEY FOR PROMO
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            const promoInput = document.getElementById('promo-code-input');
            if (promoInput) {
                promoInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') applyPromoCode();
                });
            }
            console.log('✅ User Panel Loaded Successfully!');
        });
    </script>

</body>
</html>