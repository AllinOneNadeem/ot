<?php
// ============================================================
// ADMIN FOOTER - COMPLETE FIXED (Bottom Left)
// ============================================================
?>

            </div> <!-- End of content-area -->
        </div> <!-- End of main-content -->
    </div> <!-- End of app-container -->

    <!-- ============================================================
    FOOTER - Admin Panel Footer (Bottom Left)
    ============================================================ -->
    <footer class="admin-footer">
        <div class="footer-content">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteSettings['site_name'] ?? 'OTPX'); ?> - Admin Panel &nbsp;|&nbsp; Version 2.0 &nbsp;|&nbsp; PHP <?php echo phpversion(); ?></span>
        </div>
    </footer>

    <!-- ============================================================
    JAVASCRIPT
    ============================================================ -->
    <script>
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

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        // ============================================================
        // NOTIFICATION
        // ============================================================
        function showNotification() {
            alert('📢 No new notifications.');
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

        console.log('✅ Admin Panel Loaded Successfully!');
        console.log('👤 Admin:', '<?php echo htmlspecialchars($adminUser['email'] ?? ''); ?>');
    </script>

    <style>
        /* ============================================================
           ADMIN FOOTER - BOTTOM LEFT
           ============================================================ */
        .admin-footer {
            background: var(--bg-card, #ffffff);
            border-top: 1px solid var(--border-color, #e2e8f0);
            padding: 12px 25px;
            flex-shrink: 0;
            width: 100%;
            margin-top: auto;
            text-align: left;
        }

        .admin-footer .footer-content {
            font-size: 13px;
            color: var(--text-muted, #64748b);
        }

        /* ============================================================
           OVERLAY
           ============================================================ */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 50;
            backdrop-filter: blur(3px);
        }

        .overlay.active {
            display: block;
        }

        /* ============================================================
           MODAL OVERLAY
           ============================================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: var(--bg-card, #ffffff);
            max-width: 550px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: var(--radius, 12px);
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            animation: fadeIn 0.3s ease;
        }

        .modal-box .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
        }

        .modal-box .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main, #0f172a);
        }

        .modal-box .modal-header .close-btn {
            background: transparent;
            border: none;
            font-size: 22px;
            color: var(--text-muted, #64748b);
            cursor: pointer;
        }

        .modal-box .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color, #e2e8f0);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .admin-footer {
                padding: 10px 15px;
                text-align: center;
            }
        }
    </style>

</body>
</html>