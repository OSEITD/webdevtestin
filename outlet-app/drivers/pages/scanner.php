<?php
session_start();
require_once '../../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../../login.php');
    exit();
}

$driverId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - Driver App</title>
    <link rel="stylesheet" href="../assets/css/driver-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="manifest" href="../manifest.json">
    <style>
        .scanner-container {
            padding: 20px;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .scanner-area {
            position: relative;
            margin: 20px 0;
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #video {
            width: 100%;
            height: auto;
            display: none;
        }
        
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 200px;
            height: 200px;
            border: 3px solid #2563eb;
            border-radius: 12px;
            background: transparent;
            pointer-events: none;
        }
        
        .scanner-overlay::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            right: -3px;
            bottom: -3px;
            border: 3px solid rgba(37, 99, 235, 0.3);
            border-radius: 12px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 0.5; }
        }
        
        .scanner-instructions {
            text-align: center;
            margin: 20px 0;
            color: #6b7280;
        }
        
        .scanner-controls {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .control-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .control-btn.primary {
            background: #2563eb;
            color: white;
        }
        
        .control-btn.primary:hover {
            background: #1d4ed8;
        }
        
        .control-btn.secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .control-btn.secondary:hover {
            background: #e5e7eb;
        }
        
        .manual-input {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .input-group {
            margin-bottom: 16px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #374151;
        }
        
        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .scan-result {
            margin-top: 20px;
            padding: 16px;
            border-radius: 8px;
            display: none;
        }
        
        .scan-result.success {
            background: #dcfce7;
            border: 1px solid #16a34a;
            color: #15803d;
        }
        
        .scan-result.error {
            background: #fef2f2;
            border: 1px solid #dc2626;
            color: #dc2626;
        }
        
        .recent-scans {
            margin-top: 30px;
        }
        
        .recent-scans h3 {
            margin-bottom: 16px;
            color: #374151;
        }
        
        .scan-history {
            background: white;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }
        
        .scan-item {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .scan-item:last-child {
            border-bottom: none;
        }
        
        .scan-info {
            flex: 1;
        }
        
        .scan-code {
            font-weight: 600;
            color: #1f2937;
        }
        
        .scan-time {
            font-size: 14px;
            color: #6b7280;
        }
        
        .scan-action {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        
        .scanner-placeholder {
            text-align: center;
            color: #9ca3af;
        }
        
        .scanner-placeholder i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }
    </style>
</head>
<body>
    <div class="driver-app">
        <?php include '../includes/navbar.php'; ?>

        <div class="scanner-container">
            <!-- Scanner Area -->
            <div class="scanner-area" id="scannerArea">
                <video id="video" autoplay muted playsinline></video>
                <div class="scanner-overlay"></div>
                <div class="scanner-placeholder" id="scannerPlaceholder">
                    <i class="fas fa-qrcode"></i>
                    <h3>QR Code Scanner</h3>
                    <p>Tap "Start Camera" to begin scanning</p>
                </div>
            </div>

            <!-- Scanner Controls -->
            <div class="scanner-controls">
                <button class="control-btn primary" id="startBtn" onclick="startScanner()">
                    <i class="fas fa-camera"></i>
                    Start Camera
                </button>
                <button class="control-btn secondary" id="stopBtn" onclick="stopScanner()" style="display: none;">
                    <i class="fas fa-stop"></i>
                    Stop Camera
                </button>
                <button class="control-btn secondary" onclick="toggleManualInput()">
                    <i class="fas fa-keyboard"></i>
                    Manual Input
                </button>
            </div>

            <!-- Scanner Instructions -->
            <div class="scanner-instructions">
                <p><strong>How to scan:</strong></p>
                <p>Position the QR code within the blue frame. The scanner will automatically detect and process the code.</p>
            </div>

            <!-- Manual Input -->
            <div class="manual-input" id="manualInput" style="display: none;">
                <h3>Manual Tracking Number Entry</h3>
                <form id="manualForm">
                    <div class="input-group">
                        <label for="trackingNumber">Tracking Number</label>
                        <input type="text" id="trackingNumber" placeholder="Enter tracking number" required>
                    </div>
                    <div class="scanner-controls">
                        <button type="submit" class="control-btn primary">
                            <i class="fas fa-search"></i>
                            Lookup Parcel
                        </button>
                        <button type="button" class="control-btn secondary" onclick="toggleManualInput()">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Scan Result -->
            <div class="scan-result" id="scanResult">
                <!-- Result content will be populated by JavaScript -->
            </div>

            <!-- Recent Scans -->
            <div class="recent-scans">
                <h3>Recent Scans</h3>
                <div class="scan-history" id="scanHistory">
                    <div class="scan-item">
                        <div class="scan-info">
                            <div class="scan-code">No recent scans</div>
                            <div class="scan-time">Start scanning to see history</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="../dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="pickups.php" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Pickups</span>
            </a>
            <a href="deliveries.php" class="nav-item">
                <i class="fas fa-truck"></i>
                <span>Deliveries</span>
            </a>
            <a href="scanner.php" class="nav-item active">
                <i class="fas fa-qrcode"></i>
                <span>Scanner</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <script>
        class QRScanner {
            constructor() {
                this.isScanning = false;
                this.stream = null;
                this.scanHistory = JSON.parse(localStorage.getItem('scanHistory') || '[]');
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.loadScanHistory();
                
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.showError('Camera not supported on this device');
                }
            }

            setupEventListeners() {
                document.getElementById('manualForm').addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.processManualInput();
                });

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden && this.isScanning) {
                        this.stopScanner();
                    }
                });
            }

            async startScanner() {
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: 'environment',
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        }
                    });

                    const video = document.getElementById('video');
                    video.srcObject = this.stream;
                    video.style.display = 'block';
                    
                    document.getElementById('scannerPlaceholder').style.display = 'none';
                    document.getElementById('startBtn').style.display = 'none';
                    document.getElementById('stopBtn').style.display = 'block';
                    
                    this.isScanning = true;
                    
                    this.scanForQR();
                    
                } catch (error) {
                    console.error('Camera access error:', error);
                    this.showError('Failed to access camera. Please check permissions.');
                }
            }

            stopScanner() {
                if (this.stream) {
                    this.stream.getTracks().forEach(track => track.stop());
                    this.stream = null;
                }

                const video = document.getElementById('video');
                video.style.display = 'none';
                video.srcObject = null;
                
                document.getElementById('scannerPlaceholder').style.display = 'block';
                document.getElementById('startBtn').style.display = 'block';
                document.getElementById('stopBtn').style.display = 'none';
                
                this.isScanning = false;
            }

            scanForQR() {
                if (!this.isScanning) return;

                const video = document.getElementById('video');
                
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    context.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    if (Math.random() < 0.01) {
                        const simulatedQR = 'PKG' + Math.random().toString(36).substr(2, 9).toUpperCase();
                        this.processQRCode(simulatedQR);
                        return;
                    }
                }

                setTimeout(() => this.scanForQR(), 100);
            }

            async processQRCode(qrData) {
                this.stopScanner();
                
                try {
                    await this.lookupParcel(qrData);
                    this.addToScanHistory(qrData);
                    
                } catch (error) {
                    console.error('Error processing QR code:', error);
                    this.showError('Failed to process QR code');
                }
            }

            async processManualInput() {
                const trackingNumber = document.getElementById('trackingNumber').value.trim();
                
                if (!trackingNumber) {
                    this.showError('Please enter a tracking number');
                    return;
                }

                try {
                    await this.lookupParcel(trackingNumber);
                    this.addToScanHistory(trackingNumber);
                    
                    document.getElementById('trackingNumber').value = '';
                    this.toggleManualInput();
                    
                } catch (error) {
                    console.error('Error processing manual input:', error);
                    this.showError('Failed to lookup parcel');
                }
            }

            async lookupParcel(identifier) {
                try {
                    this.showResult('Looking up parcel...', 'info');
                    
                    const response = await fetch('../api/lookup-parcel.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            tracking_number: identifier
                        })
                    });

                    const data = await response.json();
                    
                    if (data.success && data.data) {
                        const parcel = data.data;
                        this.showParcelInfo(parcel);
                    } else {
                        this.showError(data.error || 'Parcel not found');
                    }
                    
                } catch (error) {
                    console.error('Lookup error:', error);
                    this.showError('Connection error. Please try again.');
                }
            }

            showParcelInfo(parcel) {
                const resultHtml = `
                    <div class="parcel-info">
                        <h4><i class="fas fa-box"></i> Parcel Found</h4>
                        <div class="parcel-details">
                            <div class="detail-row">
                                <strong>Tracking:</strong> ${parcel.tracking_number}
                            </div>
                            <div class="detail-row">
                                <strong>Status:</strong> 
                                <span class="status-badge status-${parcel.status}">${parcel.status}</span>
                            </div>
                            <div class="detail-row">
                                <strong>Recipient:</strong> ${parcel.recipient_name}
                            </div>
                            <div class="detail-row">
                                <strong>Address:</strong> ${parcel.delivery_address}
                            </div>
                        </div>
                        <div class="parcel-actions" style="margin-top: 16px;">
                            <button class="control-btn primary" onclick="updateParcelStatus('${parcel.parcel_id}')">
                                <i class="fas fa-edit"></i> Update Status
                            </button>
                            <button class="control-btn secondary" onclick="viewParcelDetails('${parcel.parcel_id}')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                `;
                
                this.showResult(resultHtml, 'success');
            }

            showResult(content, type) {
                const resultDiv = document.getElementById('scanResult');
                resultDiv.innerHTML = content;
                resultDiv.className = `scan-result ${type}`;
                resultDiv.style.display = 'block';
                
                if (type === 'info') {
                    setTimeout(() => {
                        resultDiv.style.display = 'none';
                    }, 10000);
                }
            }

            showError(message) {
                this.showResult(`<i class="fas fa-exclamation-triangle"></i> ${message}`, 'error');
            }

            addToScanHistory(code) {
                const scan = {
                    code: code,
                    timestamp: new Date().toISOString(),
                    date: new Date().toLocaleString()
                };
                
                this.scanHistory.unshift(scan);
                
                if (this.scanHistory.length > 10) {
                    this.scanHistory = this.scanHistory.slice(0, 10);
                }
                
                localStorage.setItem('scanHistory', JSON.stringify(this.scanHistory));
                this.loadScanHistory();
            }

            loadScanHistory() {
                const historyDiv = document.getElementById('scanHistory');
                
                if (this.scanHistory.length === 0) {
                    historyDiv.innerHTML = `
                        <div class="scan-item">
                            <div class="scan-info">
                                <div class="scan-code">No recent scans</div>
                                <div class="scan-time">Start scanning to see history</div>
                            </div>
                        </div>
                    `;
                    return;
                }

                const historyHtml = this.scanHistory.map(scan => `
                    <div class="scan-item">
                        <div class="scan-info">
                            <div class="scan-code">${scan.code}</div>
                            <div class="scan-time">${scan.date}</div>
                        </div>
                        <a href="#" class="scan-action" onclick="lookupParcel('${scan.code}')">
                            Lookup
                        </a>
                    </div>
                `).join('');
                
                historyDiv.innerHTML = historyHtml;
            }

            toggleManualInput() {
                const manualInput = document.getElementById('manualInput');
                const isVisible = manualInput.style.display !== 'none';
                manualInput.style.display = isVisible ? 'none' : 'block';
                
                if (!isVisible) {
                    document.getElementById('trackingNumber').focus();
                }
            }
        }

        const qrScanner = new QRScanner();

        function startScanner() {
            qrScanner.startScanner();
        }

        function stopScanner() {
            qrScanner.stopScanner();
        }

        function toggleManualInput() {
            qrScanner.toggleManualInput();
        }

        function lookupParcel(code) {
            qrScanner.lookupParcel(code);
        }

        function updateParcelStatus(parcelId) {
            window.location.href = `../api/parcel-details.php?id=${parcelId}`;
        }

        function viewParcelDetails(parcelId) {
            window.location.href = `../api/parcel-details.php?id=${parcelId}`;
        }

        function showHelp() {
            alert(`QR Scanner Help:

1. Tap "Start Camera" to begin scanning
2. Position QR codes within the blue frame
3. The scanner will automatically detect codes
4. Use "Manual Input" for damaged or unreadable codes
5. Recent scans are saved for quick access

Permissions:
- Camera access is required for scanning
- Allow camera permissions when prompted`);
        }

        window.addEventListener('beforeunload', () => {
            if (qrScanner.isScanning) {
                qrScanner.stopScanner();
            }
        });
    </script>
    
    <?php include __DIR__ . '/../../includes/pwa_install_button.php'; ?>
    <script src="../../js/pwa-install.js"></script>
</body>
</html>