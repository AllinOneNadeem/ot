<!-- ============================================================
TAB: RECHARGE - COMPLETE WITH PAYMENT METHODS
============================================================ -->
<div class="content-section <?php echo $activeTab == 'recharge' ? 'active' : ''; ?>" id="tab-recharge">
    <h2 style="font-size:20px; font-weight:800; margin-bottom:5px;">💰 Recharge Wallet</h2>
    <p style="color:var(--text-muted); margin-bottom:25px; font-size:14px;">Add funds to your account</p>

    <!-- Promo Code -->
    <div class="promo-box" style="background:var(--bg-card); border:1px dashed var(--accent); border-radius:16px; padding:20px; margin-bottom:25px;">
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <i class="fa-solid fa-ticket" style="color:var(--accent); font-size:24px;"></i>
            <div style="flex:1; min-width:200px;">
                <div style="font-weight:600; font-size:14px;">🎫 Promo Code</div>
                <div class="promo-input-group" style="display:flex; gap:10px; margin-top:5px;">
                    <input type="text" id="promo-code-input" placeholder="Enter promo code..." style="flex:1; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-body); color:var(--text-main); outline:none;">
                    <button onclick="applyPromoCode()" style="padding:10px 20px; background:var(--accent); color:white; border:none; border-radius:8px; font-weight:600; cursor:pointer;">Apply</button>
                </div>
                <div id="promo-message" style="font-size:13px; margin-top:5px;"></div>
            </div>
        </div>
    </div>

    <!-- Amount Selection -->
    <div style="margin-bottom:25px;">
        <div style="font-weight:600; margin-bottom:12px;">Select Amount</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <?php $amounts = ['100', '200', '500', '1000', '2000']; foreach($amounts as $amt): ?>
                <div class="amount-option" onclick="selectAmount(this, '<?php echo $amt; ?>')" style="padding:10px 20px; border:2px solid var(--border-color); border-radius:12px; cursor:pointer; font-weight:600; transition:0.2s;">₹<?php echo $amt; ?></div>
            <?php endforeach; ?>
            <div class="amount-option" onclick="customAmount(this)" style="padding:10px 20px; border:2px dashed var(--border-color); border-radius:12px; cursor:pointer; font-weight:600; transition:0.2s;">Custom</div>
        </div>
        <div id="custom-amount-input" style="display:none; margin-top:10px;">
            <input type="number" id="custom-amount-value" placeholder="Enter amount..." style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-body); color:var(--text-main);">
        </div>
    </div>

    <!-- Payment Methods -->
    <div style="margin-bottom:25px;">
        <div style="font-weight:600; margin-bottom:12px;">Select Payment Method</div>
        
        <!-- Paytm/UPI -->
        <div class="payment-method" onclick="openPaymentModal('upi')" style="display:flex; align-items:center; justify-content:space-between; padding:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; margin-bottom:10px; cursor:pointer; transition:0.2s;">
            <div style="display:flex; align-items:center; gap:12px;">
                <i class="fa-brands fa-google-pay" style="color:#4285f4; font-size:28px;"></i>
                <div>
                    <div style="font-weight:600;">UPI / Paytm</div>
                    <div style="font-size:12px; color:var(--text-muted);">Pay via any UPI app</div>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:var(--text-muted);"></i>
        </div>
        
        <!-- BharatPe -->
        <?php if(!empty($sitePayments['bharatpe_upi'])): ?>
        <div class="payment-method" onclick="openPaymentModal('bharatpe')" style="display:flex; align-items:center; justify-content:space-between; padding:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; margin-bottom:10px; cursor:pointer; transition:0.2s;">
            <div style="display:flex; align-items:center; gap:12px;">
                <i class="fa-solid fa-building-columns" style="color:#4CAF50; font-size:28px;"></i>
                <div>
                    <div style="font-weight:600;">BharatPe</div>
                    <div style="font-size:12px; color:var(--text-muted);">Pay with BharatPe UPI</div>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:var(--text-muted);"></i>
        </div>
        <?php endif; ?>
        
        <!-- TRX Crypto -->
        <div class="payment-method" onclick="openPaymentModal('trx')" style="display:flex; align-items:center; justify-content:space-between; padding:16px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:12px; margin-bottom:10px; cursor:pointer; transition:0.2s;">
            <div style="display:flex; align-items:center; gap:12px;">
                <i class="fa-brands fa-bitcoin" style="color:#f7931a; font-size:28px;"></i>
                <div>
                    <div style="font-weight:600;">TRX (Tron)</div>
                    <div style="font-size:12px; color:var(--text-muted);">Send TRX to wallet</div>
                </div>
            </div>
            <i class="fa-solid fa-chevron-right" style="color:var(--text-muted);"></i>
        </div>
    </div>
</div>