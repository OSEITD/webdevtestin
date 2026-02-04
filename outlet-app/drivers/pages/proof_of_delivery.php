<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}
$driverName = $_SESSION['full_name'] ?? 'Driver';
$pageTitle = "Proof of Delivery - $driverName";
$parcelId = $_GET['parcel_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .pod-main { max-width: 500px; margin: 2rem auto; }
        .pod-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #eee; padding: 2rem; margin-bottom: 2rem; }
        .pod-actions { display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; margin-top: 2rem; }
        .signature-pad { border: 1px solid #ccc; border-radius: 8px; background: #f9f9f9; }
        .photo-preview { margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="driver-app" id="driverApp">
        <?php include '../includes/navbar.php'; ?>
        <main class="pod-main">
            <h1>Proof of Delivery</h1>
            <div class="pod-card">
                <h2><i class="fas fa-box"></i> Parcel ID: <?php echo htmlspecialchars($parcelId); ?></h2>
                <div><strong>Recipient Name:</strong> <span id="recipientName">Loading...</span></div>
                <div><strong>Recipient Address:</strong> <span id="recipientAddress">Loading...</span></div>
            </div>
            <div class="pod-card">
                <h2><i class="fas fa-signature"></i> Signature</h2>
                <canvas id="signaturePad" width="400" height="120" class="signature-pad"></canvas>
                <button type="button" id="clearSignature" style="margin-top:0.5rem;">Clear Signature</button>
            </div>
            <div class="pod-card">
                <h2><i class="fas fa-camera"></i> Photo Capture</h2>
                <input type="file" id="photoInput" accept="image/*" capture="environment">
                <div id="photoPreview" class="photo-preview"></div>
            </div>
            <div class="pod-card">
                <h2><i class="fas fa-sticky-note"></i> Notes</h2>
                <textarea id="deliveryNotes" rows="3" style="width:100%;border-radius:8px;padding:0.5rem;" placeholder="e.g. Left with security guard"></textarea>
            </div>
            <div class="pod-actions">
                <button class="btn-primary" id="submitPodBtn">Submit Proof of Delivery</button>
            </div>
        </main>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            document.getElementById('recipientName').textContent = 'John Doe';
            document.getElementById('recipientAddress').textContent = '123 Main St, Lusaka';
            
            const canvas = document.getElementById('signaturePad');
            const ctx = canvas.getContext('2d');
            let drawing = false;
            canvas.addEventListener('mousedown', e => { drawing = true; ctx.beginPath(); });
            canvas.addEventListener('mouseup', e => { drawing = false; });
            canvas.addEventListener('mouseout', e => { drawing = false; });
            canvas.addEventListener('mousemove', e => {
                if (!drawing) return;
                const rect = canvas.getBoundingClientRect();
                ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
                ctx.stroke();
            });
            document.getElementById('clearSignature').onclick = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            };
            
            document.getElementById('photoInput').onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        document.getElementById('photoPreview').innerHTML = `<img src="${ev.target.result}" style="max-width:100%;border-radius:8px;" />`;
                    };
                    reader.readAsDataURL(file);
                }
            };
            
            document.getElementById('submitPodBtn').onclick = function() {
                alert('Proof of Delivery submitted! (feature coming soon)');
            };
        });
        </script>
    </div>
</body>
</html>
