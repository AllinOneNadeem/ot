<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$amount = isset($_GET['amount']) ? htmlspecialchars($_GET['amount']) : '0';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .success-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; animation: popIn 0.5s ease-out forwards; }
        .icon-wrapper { width: 80px; height: 80px; background: #dcfce7; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 20px; }
        .icon-wrapper i { font-size: 40px; color: #16a34a; animation: scaleIcon 0.5s ease-in 0.2s both; }
        h1 { color: #1f2937; font-size: 24px; margin-bottom: 10px; }
        p { color: #6b7280; font-size: 15px; margin-bottom: 5px; }
        .amount { font-size: 28px; font-weight: 800; color: #16a34a; margin: 15px 0; }
        .btn { display: inline-block; background: #16a34a; color: white; text-decoration: none; padding: 12px 30px; border-radius: 10px; font-weight: 600; margin-top: 20px; transition: 0.3s; }
        .btn:hover { background: #15803d; transform: translateY(-2px); }
        @keyframes popIn { 0% { opacity: 0; transform: scale(0.9); } 100% { opacity: 1; transform: scale(1); } }
        @keyframes scaleIcon { 0% { transform: scale(0); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="icon-wrapper">
            <i class="fa-solid fa-check"></i>
        </div>
        <h1>Payment Successful!</h1>
        <p>Thank you very much. Your payment has been verified.</p>
        <div class="amount">₹<?php echo $amount; ?></div>
        <p>Your wallet balance has been updated.</p>
        <a href="dashboard.php" class="btn">Go to Dashboard</a>
    </div>
</body>
</html>