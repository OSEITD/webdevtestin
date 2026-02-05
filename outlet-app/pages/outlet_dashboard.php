<?php

ob_start();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error.log');

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/security_headers.php';

EnvLoader::load();

SecurityHeaders::apply();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_guard.php';
require_once '../includes/company_helper.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit(); 
}

$current_user = getCurrentUser();

$companyInfo = null;
$outletInfo = null;

if (isset($_SESSION['company_id']) && !empty($_SESSION['company_id'])) {
    $supabaseUrl = EnvLoader::get('SUPABASE_URL');
    $accessToken = $_SESSION['access_token'] ?? '';
    $companyInfo = getCompanyInfo($_SESSION['company_id'], $supabaseUrl, $accessToken);
    
    
    if (isset($_SESSION['outlet_id']) && !empty($_SESSION['outlet_id'])) {
        $outletId = $_SESSION['outlet_id'];
        $supabaseKey = EnvLoader::get('SUPABASE_ANON_KEY');
        $headers = [
            'apikey: ' . $supabaseKey,
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $outletUrl = "$supabaseUrl/rest/v1/outlets?id=eq.$outletId&select=outlet_name,location,address";
        $ch = curl_init($outletUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $outlets = json_decode($response, true);
            if (!empty($outlets) && is_array($outlets)) {
                $outletInfo = $outlets[0];
            }
        }
    }
}

$brandingColors = getCompanyBrandingColors($companyInfo);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>
        <?php
        $pageTitle = 'Outlet Dashboard';
        if ($companyInfo && $companyInfo['subdomain']) {
            $pageTitle .= ' - ' . formatSubdomainDisplay($companyInfo['subdomain'], $companyInfo['company_name']);
        } elseif ($current_user['company_name']) {
            $pageTitle .= ' - ' . htmlspecialchars($current_user['company_name']);
        }
        echo $pageTitle;
        ?>
    </title>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/outlet-dashboard.css?v=<?php echo time(); ?>">

    <style>
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, <?php echo $brandingColors['primary']; ?> 0%, <?php echo $brandingColors['secondary']; ?> 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .loading-overlay.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loading-container {
            text-align: center;
            color: white;
        }

        .loading-animation {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 2rem;
        }

        
        .truck-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 80px;
        }

        .truck {
            position: relative;
            width: 100%;
            height: 100%;
            animation: truckBounce 1s ease-in-out infinite;
        }

        @keyframes truckBounce {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .truck-body {
            position: absolute;
            bottom: 20px;
            left: 10px;
            width: 70px;
            height: 35px;
            background: white;
            border-radius: 5px 5px 2px 2px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .truck-cabin {
            position: absolute;
            bottom: 20px;
            right: 15px;
            width: 35px;
            height: 30px;
            background: white;
            border-radius: 0 8px 2px 2px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .truck-cabin::before {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            height: 15px;
            background: rgba(59, 130, 246, 0.3);
            border-radius: 3px;
        }

        .truck-wheel {
            position: absolute;
            bottom: 8px;
            width: 18px;
            height: 18px;
            background: #374151;
            border-radius: 50%;
            border: 3px solid white;
            animation: wheelRotate 0.8s linear infinite;
        }

        .truck-wheel:first-of-type {
            left: 15px;
        }

        .truck-wheel:last-of-type {
            right: 20px;
        }

        @keyframes wheelRotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        
        .road {
            position: absolute;
            bottom: 30px;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }

        .road::before {
            content: '';
            position: absolute;
            top: 50%;
            left: -100%;
            width: 200%;
            height: 100%;
            background: repeating-linear-gradient(
                to right,
                transparent 0px,
                transparent 20px,
                white 20px,
                white 40px
            );
            animation: roadMove 1s linear infinite;
            transform: translateY(-50%);
        }

        @keyframes roadMove {
            from { left: -100%; }
            to { left: 0%; }
        }

        
        .package {
            position: absolute;
            width: 30px;
            height: 30px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            animation: packageFloat 3s ease-in-out infinite;
        }

        .package::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60%;
            height: 2px;
            background: rgba(59, 130, 246, 0.5);
        }

        .package::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(90deg);
            width: 60%;
            height: 2px;
            background: rgba(59, 130, 246, 0.5);
        }

        .package:nth-child(1) {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .package:nth-child(2) {
            top: 15%;
            right: 15%;
            animation-delay: 1s;
        }

        .package:nth-child(3) {
            bottom: 25%;
            left: 20%;
            animation-delay: 2s;
        }

        .package:nth-child(4) {
            bottom: 30%;
            right: 10%;
            animation-delay: 1.5s;
        }

        @keyframes packageFloat {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.7;
            }
            50% {
                transform: translateY(-20px) rotate(10deg);
                opacity: 1;
            }
        }

        
        .loading-text {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .loading-subtext {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 2rem;
        }

        
        .loading-progress {
            width: 300px;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
            margin: 0 auto;
        }

        .loading-progress-bar {
            height: 100%;
            background: white;
            border-radius: 10px;
            animation: progressLoad 2s ease-in-out infinite;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        @keyframes progressLoad {
            0% {
                width: 0%;
                transform: translateX(0);
            }
            50% {
                width: 70%;
            }
            100% {
                width: 100%;
                transform: translateX(0);
            }
        }

        
        .loading-steps {
            margin-top: 2rem;
            font-size: 0.875rem;
            opacity: 0.8;
            min-height: 24px;
        }

        .loading-step {
            display: none;
            animation: fadeInOut 2s ease-in-out;
        }

        .loading-step.active {
            display: block;
        }

        @keyframes fadeInOut {
            0%, 100% { opacity: 0; }
            10%, 90% { opacity: 1; }
        }

        
        @media (max-width: 768px) {
            .loading-animation {
                width: 160px;
                height: 160px;
            }

            .truck-container {
                width: 100px;
                height: 70px;
            }

            .package {
                width: 24px;
                height: 24px;
            }

            .loading-text {
                font-size: 1.25rem;
            }

            .loading-subtext {
                font-size: 0.875rem;
            }

            .loading-progress {
                width: 250px;
            }
        }

        
        .company-header {
            background: linear-gradient(135deg, <?php echo $brandingColors['primary']; ?> 0%, <?php echo $brandingColors['secondary']; ?> 100%);
            color: white;
            padding: 1.5rem 2rem;
            margin: -1rem -1rem 2rem -1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .company-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
            opacity: 0.1;
            pointer-events: none;
        }

        .company-header-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .company-branding {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .company-logo {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .company-logo i {
            font-size: 24px;
            color: white;
        }

        .company-info h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .company-subdomain {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 500;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .subdomain-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .company-status {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4CAF50;
            animation: statusPulse 2s infinite;
        }

        @keyframes statusPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.2); }
        }

        .company-contact {
            font-size: 0.8rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .company-header {
                padding: 1rem;
                margin: -1rem -1rem 1.5rem -1rem;
            }

            .company-header-content {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .company-status {
                align-items: flex-start;
                text-align: left;
            }

            .company-info h1 {
                font-size: 1.5rem;
            }

            .status-indicator {
                font-size: 0.8rem;
                padding: 0.4rem 0.8rem;
            }
        }

        .dashboard-header {
            margin-top: 0;
        }

        .dashboard-header h1 {
            color: <?php echo $brandingColors['text']; ?>;
            font-size: 1.6rem;
        }

        .dashboard-header .subtitle {
            color: #6b7280;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translate3d(0, -100%, 0);
            }
            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        .metric-card.clickable {
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .metric-card.clickable:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .metric-card.clickable:active {
            transform: translateY(0);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .metric-card.updating {
            animation: pulseUpdate 0.5s ease-in-out;
        }

        @keyframes pulseUpdate {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); box-shadow: 0 0 20px rgba(74, 144, 226, 0.3); }
            100% { transform: scale(1); }
        }

        .value.updated {
            animation: valueUpdate 0.6s ease-in-out;
        }

        @keyframes valueUpdate {
            0% { color: inherit; }
            50% { color: #4CAF50; transform: scale(1.1); }
            100% { color: inherit; transform: scale(1); }
        }

        .click-hint {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.9);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.7em;
            color: #666;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .metric-card.clickable:hover .click-hint {
            opacity: 1;
        }

        .live-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 8px;
            height: 8px;
            background: #4CAF50;
            border-radius: 50%;
            animation: livePulse 2s infinite;
        }

        @keyframes livePulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .live-indicator.disconnected {
            background: #f44336;
            animation: none;
        }

        .update-notification {
          position: fixed;
          top: 20px;
          right: 20px;
          background: #4CAF50;
          color: white;
          padding: 12px 20px;
          border-radius: 8px;
          box-shadow: 0 4px 12px rgba(0,0,0,0.15);
          transform: translateX(100%);
          transition: transform 0.3s ease;
          z-index: 10001;
          opacity: 0;
          pointer-events: none;
        }

        .update-notification.show {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }

        @media (max-width: 768px) {
            .modal table {
                font-size: 0.8em;
            }

            .modal th, .modal td {
                padding: 6px !important;
            }

            .metrics-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .metric-card {
                padding: 12px 10px;
                min-height: 85px;
            }

            .metric-card .value {
                font-size: 22px;
            }

            .metric-card .label {
                font-size: 11px;
            }

            .metric-card .icon {
                font-size: 22px;
                margin-bottom: 6px;
            }
        }

        @media (max-width: 480px) {
            .metrics-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        .revenue-card {
            position: relative;
            overflow: hidden;
        }

        .revenue-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .revenue-card:hover::before {
            opacity: 1;
        }

        .recent-activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 0 5px;
        }

        .urgent-card {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF5252 100%);
            color: white;
        }

        .urgent-card .icon {
            color: white;
        }

        .urgent-card .label {
            color: rgba(255,255,255,0.9);
        }

        .urgent-card .click-hint {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .unavailable-card {
            background: linear-gradient(135deg, #FFA726 0%, #FF9800 100%);
            color: white;
        }

        .unavailable-card .icon {
            color: white;
        }

        .unavailable-card .label {
            color: rgba(255,255,255,0.9);
        }

        .unavailable-card .click-hint {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.4rem;
            font-weight: 600;
            color: #2E0D2A;
            margin: 2rem 0 1rem 0;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f1f1;
        }

        .section-title i {
            color: #4A1C40;
            font-size: 1.2rem;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
        }

        @media (max-width: 480px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }
        }

        .metric-card.loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .metric-card.loading .value {
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { opacity: 0.5; }
            50% { opacity: 1; }
            100% { opacity: 0.5; }
        }

        .dashboard-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        .dashboard-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: slideInDown 0.3s ease;
        }

        .dashboard-modal-header {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-modal-header h3 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .dashboard-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .dashboard-modal-close:hover {
            background: rgba(255,255,255,0.3);
        }

        .dashboard-modal-body {
            padding: 2rem;
        }

        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .dashboard-table th {
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
        }

        .dashboard-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .dashboard-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.pending { background: #FFF3CD; color: #856404; }
        .status-badge.in-transit { background: #D1ECF1; color: #0C5460; }
        .status-badge.delivered { background: #D4EDDA; color: #155724; }
        .status-badge.delayed { background: #F8D7DA; color: #721C24; }
        .status-badge.urgent { background: #F8D7DA; color: #721C24; font-weight: 600; }
        .status-badge.available { background: #D4EDDA; color: #155724; }
        .status-badge.unavailable { background: #F8D7DA; color: #721C24; }
        .status-badge.scheduled { background: #D1ECF1; color: #0C5460; }
        .status-badge.completed { background: #D4EDDA; color: #155724; }

        .activity-controls {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .activity-stats {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: 0.85rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            min-width: 150px;
            border-radius: 0.85rem;
            background: linear-gradient(135deg, rgba(46, 13, 42, 0.12), rgba(46, 13, 42, 0.04));
            border: 1px solid rgba(46, 13, 42, 0.14);
            box-shadow: 0 8px 18px -12px rgba(46, 13, 42, 0.4);
            color: #2E0D2A;
            position: relative;
            overflow: hidden;
        }

        .stat-item::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.28), rgba(255, 255, 255, 0));
            pointer-events: none;
        }

        .stat-item i {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.65);
            color: currentColor;
            font-size: 0.95rem;
            flex-shrink: 0;
            position: relative;
            z-index: 1;
        }

        .stat-item .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: rgba(46, 13, 42, 0.95);
            line-height: 1;
            position: relative;
            z-index: 1;
        }

        .stat-item .stat-label {
            font-size: 0.82rem;
            color: rgba(46, 13, 42, 0.75);
            letter-spacing: 0.02em;
            text-transform: uppercase;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .stat-item.priority {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.18), rgba(220, 53, 69, 0.05));
            border-color: rgba(220, 53, 69, 0.25);
            color: #b3261e;
        }

        .activity-filters {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .activity-filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }

        .filter-select,
        .filter-search {
            padding: 0.65rem 0.85rem;
            border: 1px solid rgba(46, 13, 42, 0.16);
            border-radius: 0.65rem;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.9);
            color: #2E0D2A;
            min-width: 180px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-select:focus,
        .filter-search:focus {
            border-color: rgba(74, 28, 64, 0.65);
            box-shadow: 0 0 0 3px rgba(74, 28, 64, 0.12);
            outline: none;
        }

        .filter-search {
            width: 220px;
        }

        .refresh-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: #fff;
            border: none;
            padding: 0.65rem 1.1rem;
            border-radius: 0.65rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 0.01em;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .refresh-btn i {
            font-size: 0.85rem;
        }

        .refresh-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px -10px rgba(46, 13, 42, 0.55);
        }

        .refresh-btn.spinning i {
            animation: spin 1s linear infinite;
        }

        .reset-filters {
            color: rgba(46, 13, 42, 0.65);
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            padding: 0.45rem 0.65rem;
            border-radius: 0.5rem;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .reset-filters:hover {
            background: rgba(46, 13, 42, 0.08);
            color: rgba(46, 13, 42, 0.9);
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .activity-table-wrapper {
            margin-top: 1.25rem;
            border-radius: 1rem;
            border: 1px solid rgba(46, 13, 42, 0.1);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 15px 35px -20px rgba(46, 13, 42, 0.45);
        }

        .recent-activity-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        .recent-activity-table thead th {
            background: linear-gradient(135deg, rgba(46, 13, 42, 0.95), rgba(74, 28, 64, 0.85));
            color: #fff;
            padding: 0.85rem 1rem;
            font-size: 0.78rem;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            text-align: left;
        }

        .recent-activity-table tbody tr {
            transition: background 0.18s ease, transform 0.18s ease;
        }

        .recent-activity-table tbody tr:nth-child(even) {
            background: rgba(46, 13, 42, 0.03);
        }

        .recent-activity-table tbody tr:hover {
            background: rgba(74, 28, 64, 0.08);
        }

        .recent-activity-table td {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(46, 13, 42, 0.08);
            vertical-align: top;
            font-size: 0.93rem;
            color: rgba(46, 13, 42, 0.85);
        }

        .recent-activity-table td:first-child {
            white-space: nowrap;
            min-width: 150px;
            font-weight: 600;
            color: rgba(46, 13, 42, 0.75);
        }

        .recent-activity-table td:last-child {
            text-align: left;
        }

        .recent-activity-table .action-cell {
            text-align: center;
            min-width: 70px;
        }

        .btn-delete-activity {
            background: transparent;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 1rem;
            padding: 6px 8px;
            border-radius: 6px;
        }
        .btn-delete-activity:hover { color: #b91c1c; }
        .btn-delete-activity:disabled { opacity: 0.6; cursor: default; }


        .recent-activity-table .message-cell {
            max-width: 420px;
        }

        .recent-activity-table .message-title {
            font-weight: 600;
            color: #2E0D2A;
            display: block;
            margin-bottom: 0.25rem;
        }

        .recent-activity-table .message-text {
            color: rgba(46, 13, 42, 0.68);
            line-height: 1.45;
        }

        .type-pill,
        .priority-pill,
        .status-pill,
        .parcel-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.65rem;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.01em;
        }

        .type-pill {
            background: rgba(46, 13, 42, 0.08);
            color: #4A1C40;
        }

        .type-pill i {
            font-size: 0.85rem;
        }

        .type-pill.type-parcel_created { background: #ECFDF5; color: #047857; }
        .type-pill.type-parcel_status_change { background: #EFF6FF; color: #1D4ED8; }
        .type-pill.type-delivery_assigned { background: #FEF3C7; color: #B45309; }
        .type-pill.type-delivery_completed { background: #EEFDF3; color: #047857; }
        .type-pill.type-driver_unavailable { background: #FEE2E2; color: #B91C1C; }
        .type-pill.type-payment_received { background: #F3E8FF; color: #6D28D9; }
        .type-pill.type-urgent_delivery { background: #FFF4E6; color: #C2410C; }
        .type-pill.type-system_alert { background: #F4F4F5; color: #4338CA; }
        .type-pill.type-customer_inquiry { background: #E0F7FA; color: #0E7490; }

        .priority-pill {
            color: #2E0D2A;
            background: rgba(46, 13, 42, 0.08);
        }
        .priority-pill.priority-urgent { background: #FEE2E2; color: #B91C1C; }
        .priority-pill.priority-high { background: #FDE68A; color: #B45309; }
        .priority-pill.priority-medium { background: #E0F2F1; color: #0F766E; }
        .priority-pill.priority-low { background: #EDE9FE; color: #5B21B6; }
    .priority-pill.priority-default { background: rgba(46, 13, 42, 0.08); color: #2E0D2A; }

        .status-pill {
            justify-content: flex-start;
            background: rgba(46, 13, 42, 0.08);
            color: #3B1B35;
        }
        .status-pill::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.65);
        }
        .status-pill.status-unread { background: #E0E7FF; color: #1D4ED8; }
        .status-pill.status-read { background: #DCFCE7; color: #15803D; }
        .status-pill.status-dismissed { background: #FFE4E6; color: #BE123C; }
        .status-pill.status-archived { background: #EDE9FE; color: #5B21B6; }
        .status-pill.status-default { background: rgba(46, 13, 42, 0.08); color: #3B1B35; }

        .parcel-chip {
            background: rgba(74, 28, 64, 0.08);
            color: #4A1C40;
        }

        .parcel-chip i {
            font-size: 0.8rem;
        }

        .table-empty-message {
            text-align: center;
            padding: 2.5rem 1.5rem;
            color: rgba(46, 13, 42, 0.6);
            font-weight: 500;
        }

        .activity-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.25rem;
        }

        .activity-footer .view-all-link {
            color: #4A1C40;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .activity-footer .view-all-link i {
            font-size: 0.8rem;
        }

        .activity-footer .view-all-link:hover {
            text-decoration: underline;
        }

        .activity-footer .records-note {
            font-size: 0.8rem;
            color: rgba(46, 13, 42, 0.6);
        }

        .table-muted {
            color: rgba(46, 13, 42, 0.45);
            font-style: italic;
        }

        @media (max-width: 1024px) {
            .activity-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .activity-filters {
                justify-content: flex-start;
            }

            .recent-activity-table {
                min-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .filter-select,
            .filter-search {
                min-width: unset;
                flex: 1 1 160px;
            }

            .activity-table-wrapper {
                overflow-x: auto;
            }

            .recent-activity-table td:last-child {
                text-align: left;
            }

            .status-pill {
                justify-content: flex-start;
            }
        }

        .activity-timeline {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-height: 500px;
            overflow-y: auto;
        }

        .activity-group {
            margin-bottom: 25px;
        }

        .activity-date-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid rgba(46, 13, 42, 0.1);
        }

        .activity-date-header h4 {
            margin: 0;
            color: #2E0D2A;
            font-size: 1.1em;
            font-weight: 600;
        }

        .activity-date-header .date-badge {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            margin-left: 10px;
            font-weight: 500;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f8f9fa;
            position: relative;
            transition: background 0.2s;
        }

        .activity-item:hover {
            background: rgba(46, 13, 42, 0.05);
            border-radius: 8px;
            margin: 0 -10px;
            padding: 15px 10px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1em;
            position: relative;
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
            box-shadow: 0 2px 8px rgba(46, 13, 42, 0.3);
        }

        .activity-icon.priority::before {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: #dc3545;
            border-radius: 50%;
            border: 2px solid white;
        }

        .activity-content {
            flex: 1;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .activity-title {
            font-weight: 600;
            color: #2E0D2A;
            margin: 0;
            font-size: 1em;
        }

        .activity-time {
            font-size: 0.8em;
            color: #666;
            background: #f8f9fa;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .activity-description {
            color: #666;
            font-size: 0.9em;
            line-height: 1.4;
            margin: 5px 0;
        }

        .activity-meta {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 8px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8em;
            color: #777;
        }

        .activity-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .action-btn {
            background: none;
            border: 1px solid #ddd;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            cursor: pointer;
            transition: all 0.2s;
            color: #666;
        }

        .action-btn:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .action-btn.primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .action-btn.success {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .activity-loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .activity-loading i {
            font-size: 2em;
            margin-bottom: 10px;
            color: #007bff;
        }

        .no-activity {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-activity i {
            font-size: 3em;
            color: #ddd;
            margin-bottom: 15px;
        }

        .load-more-btn {
            width: 100%;
            background: #f8f9fa;
            border: 2px dashed #ddd;
            color: #666;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1em;
            margin-top: 20px;
        }

        .load-more-btn:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .activity-type-specific-colors {
            background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%);
        }
        .activity-icon.parcel-created { background: linear-gradient(135deg, #4CAF50 0%, #388E3C 100%); }
        .activity-icon.parcel-received { background: linear-gradient(135deg, #2E0D2A 0%, #4A1C40 100%); }
        .activity-icon.parcel-dispatched { background: linear-gradient(135deg, #FF6B6B 0%, #E57373 100%); }
        .activity-icon.parcel-delivered { background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%); }
        .activity-icon.parcel-returned { background: linear-gradient(135deg, #F44336 0%, #EF5350 100%); }
        .activity-icon.vehicle-assigned { background: linear-gradient(135deg, #6A2A62 0%, #8E24AA 100%); }
        .activity-icon.payment-received { background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%); }
        .activity-icon.issue-reported { background: linear-gradient(135deg, #F44336 0%, #EF5350 100%); }
        .activity-icon.customer-inquiry { background: linear-gradient(135deg, #FF9800 0%, #FFB74D 100%); color: #333; }

        @media (max-width: 768px) {
            .recent-activity-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .activity-controls {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }

            .activity-stats {
                width: 100%;
                justify-content: space-between;
            }

            .activity-item {
                gap: 10px;
            }

            .activity-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .activity-meta {
                flex-wrap: wrap;
                gap: 10px;
            }

            .activity-actions {
                width: 100%;
                justify-content: space-between;
            }
        }

        /* Collapsible section styles */
        .collapsible-section { margin-bottom: 1.6rem; border-radius:8px; background: #fff; padding: 0.6rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .section-title { display:flex; align-items:center; justify-content:space-between; gap:10px; font-size:1.05rem; margin:0; padding:0.5rem 0.6rem; }
        .section-title span { display:flex; align-items:center; gap:10px; font-weight:600; color:#111827; }
        .collapse-toggle { background:transparent; border:0; font-size:1rem; cursor:pointer; padding:6px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; color:#374151; }
        .collapse-toggle i { transition: transform 0.25s ease; }
        .collapse-content { overflow:hidden; transition:max-height 0.3s ease, padding 0.2s ease; max-height:2000px; }
        .collapse-content.collapsed { max-height:0; padding:0; }
        .collapse-toggle[aria-expanded="false"] i { transform: rotate(-90deg); }
    </style>

</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-container">
            <div class="loading-animation">
                <!-- Floating Packages -->
                <div class="package"></div>
                <div class="package"></div>
                <div class="package"></div>
                <div class="package"></div>
                
                <!-- Delivery Truck -->
                <div class="truck-container">
                    <div class="truck">
                        <div class="truck-body"></div>
                        <div class="truck-cabin"></div>
                        <div class="truck-wheel"></div>
                        <div class="truck-wheel"></div>
                    </div>
                </div>
                
                <!-- Road -->
                <div class="road"></div>
            </div>
            
            <div class="loading-text">Preparing Your Dashboard</div>
            <div class="loading-subtext">Setting up your courier workspace...</div>
            
            <div class="loading-progress">
                <div class="loading-progress-bar"></div>
            </div>
            
            <div class="loading-steps">
                <div class="loading-step active" data-step="1">
                    <i class="fas fa-check-circle"></i> Loading company information...
                </div>
                <div class="loading-step" data-step="2">
                    <i class="fas fa-check-circle"></i> Fetching outlet data...
                </div>
                <div class="loading-step" data-step="3">
                    <i class="fas fa-check-circle"></i> Preparing analytics...
                </div>
                <div class="loading-step" data-step="4">
                    <i class="fas fa-check-circle"></i> Almost ready...
                </div>
            </div>
        </div>
    </div>

    <div class="mobile-dashboard">
        <?php include '../includes/navbar.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <div class="menu-overlay" id="menuOverlay"></div>

        <main class="main-content">
            <?php if ($companyInfo): ?>
            <div class="company-header">
                <div class="company-header-content">
                    <div class="company-branding">
                        <div class="company-logo">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="company-info">
                            <h1><?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Company Name'); ?></h1>
                            <div class="company-subdomain">
                                <?php if ($outletInfo && !empty($outletInfo['outlet_name'])): ?>
                                    <span><?php echo htmlspecialchars($outletInfo['outlet_name']); ?></span>
                                    <?php 
                                    $locationDisplay = '';
                                    if (!empty($outletInfo['location'])) {
                                        $locationDisplay = $outletInfo['location'];
                                    } elseif (!empty($outletInfo['address'])) {
                                        $locationDisplay = $outletInfo['address'];
                                    }
                                    if ($locationDisplay): 
                                    ?>
                                        <span class="subdomain-badge"><?php echo htmlspecialchars($locationDisplay); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span><?php echo isset($companyInfo['subdomain']) && isset($companyInfo['company_name']) ? formatSubdomainDisplay($companyInfo['subdomain'], $companyInfo['company_name']) : 'N/A'; ?></span>
                                    <?php if (isset($companyInfo['subdomain']) && $companyInfo['subdomain']): ?>
                                    <span class="subdomain-badge"><?php echo htmlspecialchars($companyInfo['subdomain']); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="company-status">
                        <div class="status-indicator">
                            <div class="status-dot"></div>
                            <span><?php echo ucfirst($companyInfo['status'] ?? 'active'); ?> System</span>
                        </div>
                        <div class="company-contact">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($current_user['full_name'] ?? $current_user['email'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif ($current_user['company_name']): ?>
            <div class="company-header">
                <div class="company-header-content">
                    <div class="company-branding">
                        <div class="company-logo">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="company-info">
                            <h1><?php echo htmlspecialchars($current_user['company_name']); ?></h1>
                            <div class="company-subdomain">
                                <span>Outlet Management System</span>
                            </div>
                        </div>
                    </div>

                    <div class="company-status">
                        <div class="status-indicator">
                            <div class="status-dot"></div>
                            <span>Active System</span>
                        </div>
                        <div class="company-contact">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($current_user['full_name'] ?? $current_user['email'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-header">
                <h1>Outlet Dashboard</h1>
                <p class="subtitle">Overview of daily operations and key metrics for your outlet.</p>
            </div>

            <div class="dashboard-content">
                <div class="collapsible-section">
                    <h2 class="section-title">
                        <span><i class="fas fa-box"></i> Parcels Overview</span>
                        <button class="collapse-toggle" aria-expanded="false" aria-controls="section-parcels"><i class="fas fa-chevron-down"></i></button>
                    </h2>
                    <div id="section-parcels" class="collapse-content collapsed" aria-hidden="true">
                        <div class="metrics-grid">
                            <div class="metric-card clickable" onclick="showParcelsPendingAtOutlet()" data-card="pending">
                                <div class="live-indicator" id="liveIndicator"></div>
                                <i class="fas fa-box-open icon"></i>
                                <div class="value" id="pendingAtOutletCount">0</div>
                                <div class="label">Parcels at Outlet</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showParcelsInTransit()" data-card="transit">
                                <i class="fas fa-truck-moving icon"></i>
                                <div class="value" id="inTransitCount">0</div>
                                <div class="label">Parcels in Transit</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showParcelsCompleted()" data-card="completed">
                                <i class="fas fa-check-circle icon"></i>
                                <div class="value" id="completedCount">0</div>
                                <div class="label">Delivered Today</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable urgent-card" onclick="showParcelsDelayedUrgent()" data-card="urgent">
                                <i class="fas fa-exclamation-triangle icon"></i>
                                <div class="value" id="delayedUrgentCount">0</div>
                                <div class="label">Delayed / Urgent</div>
                                <div class="click-hint">Click to view details</div>
                            </div>
                        </div>
                    </div>
                </div> 

                <div class="collapsible-section">
                    <h2 class="section-title">
                        <span><i class="fas fa-route"></i> Trip Status</span>
                        <button class="collapse-toggle" aria-expanded="false" aria-controls="section-trips"><i class="fas fa-chevron-down"></i></button>
                    </h2>
                    <div id="section-trips" class="collapse-content collapsed" aria-hidden="true">
                        <div class="metrics-grid">
                            <div class="metric-card clickable" onclick="showUpcomingTrips()" data-card="upcoming-trips">
                                <i class="fas fa-clock icon"></i>
                                <div class="value" id="upcomingTripsCount">0</div>
                                <div class="label">Upcoming Trips</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showInTransitTrips()" data-card="transit-trips">
                                <i class="fas fa-shipping-fast icon"></i>
                                <div class="value" id="inTransitTripsCount">0</div>
                                <div class="label">In-Transit Trips</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showCompletedTrips()" data-card="completed-trips">
                                <i class="fas fa-check-double icon"></i>
                                <div class="value" id="completedTripsCount">0</div>
                                <div class="label">Completed Trips Today</div>
                                <div class="click-hint">Click to view details</div>
                            </div>
                        </div>
                    </div>
                </div> 

                <div class="collapsible-section">
                    <h2 class="section-title">
                        <span><i class="fas fa-truck"></i> Vehicle Availability</span>
                        <button class="collapse-toggle" aria-expanded="false" aria-controls="section-vehicles"><i class="fas fa-chevron-down"></i></button>
                    </h2>
                    <div id="section-vehicles" class="collapse-content collapsed" aria-hidden="true">
                        <div class="metrics-grid">
                            <div class="metric-card clickable" onclick="showVehicleAvailability()" data-card="available-vehicles">
                                <i class="fas fa-truck icon"></i>
                                <div class="value" id="availableVehiclesCount">0</div>
                                <div class="label">Available Vehicles</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable unavailable-card" onclick="showVehicleUnavailability()" data-card="unavailable-vehicles">
                                <i class="fas fa-truck-medical icon"></i>
                                <div class="value" id="unavailableVehiclesCount">0</div>
                                <div class="label">Unavailable Vehicles</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showAssignedTrips()" data-card="assigned-trips">
                                <i class="fas fa-route icon"></i>
                                <div class="value" id="assignedTripsCount">0</div>
                                <div class="label">Assigned to Trips</div>
                                <div class="click-hint">Click to view details</div>
                            </div>
                        </div>
                    </div>
                </div> 

                <div class="collapsible-section">
                    <h2 class="section-title">
                        <span><i class="fas fa-chart-line"></i> Revenue Snapshot</span>
                        <button class="collapse-toggle" aria-expanded="false" aria-controls="section-revenue"><i class="fas fa-chevron-down"></i></button>
                    </h2>
                    <div id="section-revenue" class="collapse-content collapsed" aria-hidden="true">
                        <div class="metrics-grid">
                            <div class="metric-card clickable revenue-card" onclick="showRevenueToday()" data-card="revenue-today" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white;">
                                <i class="fas fa-money-bill-wave icon" style="color: white;"></i>
                                <div class="value" id="revenueTodayCount" style="color: white;">ZMW 0.00</div>
                                <div class="label" style="color: rgba(255,255,255,0.9);">Revenue Today</div>
                                <div class="click-hint" style="background: rgba(255,255,255,0.2); color: white;">Click for details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showRevenueWeek()" data-card="revenue-week">
                                <i class="fas fa-calendar-week icon"></i>
                                <div class="value" id="revenueWeekCount">ZMW 0.00</div>
                                <div class="label">Revenue This Week</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showCODCollections()" data-card="cod-collections">
                                <i class="fas fa-hand-holding-usd icon"></i>
                                <div class="value" id="codCollectionsCount">ZMW 0.00</div>
                                <div class="label">COD Collections</div>
                                <div class="click-hint">Click to view details</div>
                            </div>

                            <div class="metric-card clickable" onclick="showTransactionCount()" data-card="transactions">
                                <i class="fas fa-receipt icon"></i>
                                <div class="value" id="transactionCount">0</div>
                        <div class="label">Transactions Today</div>
                        <div class="click-hint">Click to view details</div>
                    </div>
                </div>
                    </div>
                </div>

                <h2 class="section-title">Quick Actions</h2>
                <div class="quick-actions-grid">
                    <a href="trip_wizard.php" class="action-button">
                        <i class="fas fa-route"></i>
                        <span>Create Trip</span>
                    </a>
                    <a href="parcel_registration.php" class="action-button">
                        <i class="fas fa-plus-circle"></i>
                        <span>Register Parcel</span>
                    </a>
                    <a href="parcel_management.php" class="action-button">
                        <i class="fas fa-qrcode"></i>
                        <span>Scan Parcel</span>
                    </a>
                    <a href="parcels_at_outlet.php" class="action-button">
                        <i class="fas fa-box"></i>
                        <span>View All Parcels</span>
                    </a>
                </div>

                <?php
                    $activityTypes = [
                        'all' => 'All Types',
                        'parcel_created' => 'Parcel Created',
                        'parcel_status_change' => 'Parcel Status Change',
                        'delivery_assigned' => 'Delivery Assigned',
                        'delivery_completed' => 'Delivery Completed',
                        'driver_unavailable' => 'Driver Unavailable',
                        'payment_received' => 'Payment Received',
                        'urgent_delivery' => 'Urgent Delivery',
                        'system_alert' => 'System Alert',
                        'customer_inquiry' => 'Customer Inquiry'
                    ];

                    $statusOptions = [
                        'all' => 'All Statuses',
                        'unread' => 'Unread',
                        'read' => 'Read',
                        'dismissed' => 'Dismissed',
                        'archived' => 'Archived'
                    ];

                    $notificationIconMap = [
                        'parcel_created' => 'fas fa-box',
                        'parcel_status_change' => 'fas fa-box-open',
                        'delivery_assigned' => 'fas fa-truck',
                        'delivery_completed' => 'fas fa-check-circle',
                        'driver_unavailable' => 'fas fa-user-times',
                        'payment_received' => 'fas fa-credit-card',
                        'urgent_delivery' => 'fas fa-exclamation-triangle',
                        'system_alert' => 'fas fa-info-circle',
                        'customer_inquiry' => 'fas fa-question-circle'
                    ];

                    $priorityLabelMap = [
                        'urgent' => 'Urgent',
                        'high' => 'High',
                        'medium' => 'Medium',
                        'low' => 'Low',
                        'default' => 'Standard'
                    ];

                    $statusLabelMap = [
                        'unread' => 'Unread',
                        'read' => 'Read',
                        'dismissed' => 'Dismissed',
                        'archived' => 'Archived',
                        'default' => 'Open'
                    ];

                    $companyId = $_SESSION['company_id'];
                    $outletId = $_SESSION['outlet_id'];
                    $supabaseUrl = EnvLoader::get('SUPABASE_URL');
                    $supabaseKey = EnvLoader::get('SUPABASE_SERVICE_KEY');

                    $filterType = $_GET['activity_type'] ?? 'all';
                    if (!array_key_exists($filterType, $activityTypes)) {
                        $filterType = 'all';
                    }

                    $filterStatus = $_GET['activity_status'] ?? 'all';
                    if (!array_key_exists($filterStatus, $statusOptions)) {
                        $filterStatus = 'all';
                    }

                    $searchTerm = trim($_GET['activity_search'] ?? '');

                    $queryParams = [
                        "company_id=eq.$companyId",
                        "outlet_id=eq.$outletId"
                    ];

                    if ($filterType !== 'all') {
                        $queryParams[] = "notification_type=eq.$filterType";
                    }

                    if ($filterStatus !== 'all') {
                        $queryParams[] = "status=eq.$filterStatus";
                    }

                    if ($searchTerm !== '') {
                        $encodedSearch = rawurlencode('%' . $searchTerm . '%');
                        $orConditions = [
                            "title.ilike.$encodedSearch",
                            "message.ilike.$encodedSearch"
                        ];

                        if (ctype_digit($searchTerm)) {
                            $orConditions[] = "parcel_id.eq.$searchTerm";
                        }

                        $queryParams[] = 'or=(' . implode(',', $orConditions) . ')';
                    }

                    $queryString = implode('&', $queryParams);
                    $queryUrl = "$supabaseUrl/rest/v1/notifications?$queryString&order=created_at.desc&limit=15&select=id,title,message,notification_type,status,priority,parcel_id,created_at,data";

                    $context = stream_context_create([
                        'http' => [
                            'method' => 'GET',
                            'header' => [
                                "apikey: $supabaseKey",
                                "Authorization: Bearer $supabaseKey",
                                "Content-Type: application/json",
                                "Prefer: count=exact"
                            ]
                        ]
                    ]);

                    $response = @file_get_contents($queryUrl, false, $context);
                    $recentActivityError = null;
                    $notifications = [];

                    if ($response === false) {
                        $recentActivityError = 'We could not load recent activities right now. Please try again shortly.';
                    } else {
                        $decoded = json_decode($response, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $notifications = $decoded;
                        } else {
                            $recentActivityError = 'Unexpected response from activity feed. Please refresh the page.';
                        }
                    }

                    $totalActivities = count($notifications);
                    $totalAvailableActivities = $totalActivities;

                    if (!empty($http_response_header ?? [])) {
                        foreach ($http_response_header as $headerLine) {
                            if (stripos($headerLine, 'Content-Range:') === 0 && preg_match('/\\d+-\\d+\/(\\d+|\\*)/', $headerLine, $matches)) {
                                if ($matches[1] !== '*') {
                                    $totalAvailableActivities = (int) $matches[1];
                                }
                                break;
                            }
                        }
                    }

                    $todayActivities = 0;
                    $priorityActivities = 0;
                    $todayDate = (new DateTime())->format('Y-m-d');

                    foreach ($notifications as $note) {
                        $createdAt = $note['created_at'] ?? null;
                        if ($createdAt && date('Y-m-d', strtotime($createdAt)) === $todayDate) {
                            $todayActivities++;
                        }

                        $priorityLevel = strtolower($note['priority'] ?? '');
                        if (in_array($priorityLevel, ['urgent', 'high'], true)) {
                            $priorityActivities++;
                        }
                    }

                    $isFiltered = ($filterType !== 'all') || ($filterStatus !== 'all') || ($searchTerm !== '');
                ?>
                <div class="recent-activity-header">
                    <h2 class="section-title">Recent Activity</h2>
                    <div class="activity-controls">
                        <div class="activity-stats">
                            <span class="stat-item" id="totalActivities">
                                <i class="fas fa-list"></i>
                                <span class="stat-value"><?= number_format($totalAvailableActivities) ?></span>
                                <span class="stat-label">Total</span>
                            </span>
                            <span class="stat-item" id="todayActivities">
                                <i class="fas fa-calendar-day"></i>
                                <span class="stat-value"><?= number_format($todayActivities) ?></span>
                                <span class="stat-label">Today</span>
                            </span>
                            <span class="stat-item priority" id="priorityActivities">
                                <i class="fas fa-exclamation-circle"></i>
                                <span class="stat-value"><?= number_format($priorityActivities) ?></span>
                                <span class="stat-label">Priority</span>
                            </span>
                        </div>
                        <div class="activity-filters">
                            <form method="get" class="activity-filter-form">
                                <select name="activity_type" class="filter-select" aria-label="Filter by activity type">
                                    <?php foreach ($activityTypes as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $value === $filterType ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="activity_status" class="filter-select" aria-label="Filter by status">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $value === $filterStatus ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="activity_search" class="filter-search" placeholder="Search title, parcel, or message..." value="<?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?>" aria-label="Search activities" />
                                <button type="submit" class="refresh-btn" title="Apply filters">
                                    <i class="fas fa-filter"></i>
                                    <span>Apply</span>
                                </button>
                                <a href="outlet_dashboard.php" class="reset-filters" title="Reset filters">
                                    <i class="fas fa-undo"></i>
                                    Reset
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="recent-activity-container">
                    <div class="activity-table-wrapper">
                        <table class="recent-activity-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Parcel</th>
                                    <th>Details</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($recentActivityError): ?>
                                <tr>
                                    <td colspan="6" class="table-empty-message"><?= htmlspecialchars($recentActivityError) ?></td>
                                </tr>
                            <?php elseif (!empty($notifications)): ?>
                                <?php foreach ($notifications as $note): ?>
                                    <?php
                                        $typeKey = strtolower($note['notification_type'] ?? '');
                                        $typeLabel = $activityTypes[$typeKey] ?? ucwords(str_replace('_', ' ', $typeKey ?: 'Notification'));
                                        $typeIcon = $notificationIconMap[$typeKey] ?? 'fas fa-bell';
                                        $typeClassSuffix = preg_replace('/[^a-z0-9_-]+/i', '', $typeKey ?: 'general');
                                        if ($typeClassSuffix === '') {
                                            $typeClassSuffix = 'general';
                                        }
                                        $typeClass = 'type-' . $typeClassSuffix;

                                        $priorityRaw = strtolower($note['priority'] ?? '');
                                        $priorityKey = $priorityRaw ?: 'medium';
                                        if (!isset($priorityLabelMap[$priorityKey])) {
                                            $priorityLabel = ucwords(str_replace('_', ' ', $priorityKey));
                                            $priorityClassSuffix = preg_replace('/[^a-z0-9_-]+/i', '', $priorityKey);
                                            if ($priorityClassSuffix === '') {
                                                $priorityClassSuffix = 'default';
                                            }
                                        } else {
                                            $priorityLabel = $priorityLabelMap[$priorityKey];
                                            $priorityClassSuffix = $priorityKey;
                                        }
                                        $priorityClass = 'priority-' . $priorityClassSuffix;

                                        $statusRaw = strtolower($note['status'] ?? '');
                                        $statusKey = $statusRaw ?: 'default';
                                        if (!isset($statusLabelMap[$statusKey])) {
                                            $statusLabel = ucwords(str_replace('_', ' ', $statusKey));
                                            $statusClassSuffix = preg_replace('/[^a-z0-9_-]+/i', '', $statusKey);
                                            if ($statusClassSuffix === '') {
                                                $statusClassSuffix = 'default';
                                            }
                                        } else {
                                            $statusLabel = $statusLabelMap[$statusKey];
                                            $statusClassSuffix = $statusKey;
                                        }
                                        $statusClass = 'status-' . $statusClassSuffix;

                                        $createdAt = $note['created_at'] ?? null;
                                        $timeDisplay = $createdAt ? date('M j, Y H:i', strtotime($createdAt)) : '—';

                                        $metadata = [];
                                        if (!empty($note['data'])) {
                                            if (is_array($note['data'])) {
                                                $metadata = $note['data'];
                                            } elseif (is_string($note['data'])) {
                                                $decodedData = json_decode($note['data'], true);
                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                                                    $metadata = $decodedData;
                                                }
                                            }
                                        }

                                        $parcelReference = $metadata['parcel_track_number']
                                            ?? $metadata['tracking_number']
                                            ?? $metadata['track_number']
                                            ?? null;

                                        if (!$parcelReference && isset($note['parcel_id']) && $note['parcel_id'] !== null) {
                                            $parcelReference = '#' . $note['parcel_id'];
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($timeDisplay) ?></td>
                                        <td>
                                            <span class="type-pill <?= htmlspecialchars($typeClass) ?>">
                                                <i class="<?= htmlspecialchars($typeIcon) ?>" aria-hidden="true"></i>
                                                <?= htmlspecialchars($typeLabel) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($parcelReference): ?>
                                                <span class="parcel-chip"><i class="fas fa-hashtag" aria-hidden="true"></i><?= htmlspecialchars($parcelReference) ?></span>
                                            <?php else: ?>
                                                <span class="table-muted">Not linked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="message-cell">
                                            <span class="message-title"><?= htmlspecialchars($note['title'] ?? 'Notification') ?></span>
                                            <span class="message-text"><?= htmlspecialchars($note['message'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <span class="priority-pill <?= htmlspecialchars($priorityClass) ?>"><?= htmlspecialchars($priorityLabel) ?></span>
                                        </td>
                                        <td>
                                            <span class="status-pill <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                        </td>
                                        <td class="action-cell">
                                            <button class="btn-icon btn-delete-activity" data-id="<?= htmlspecialchars($note['id']) ?>" title="Delete activity">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="table-empty-message">No recent activity found.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!$recentActivityError): ?>
                    <div class="activity-footer">
                        <span class="records-note">
                            Showing <?= number_format($totalActivities) ?> of <?= number_format($totalAvailableActivities) ?> <?= $isFiltered ? 'matching activities' : 'latest activities' ?>
                        </span>
                        <a class="view-all-link" href="notifications.php">
                            View all notifications
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="dashboardModal" class="dashboard-modal">
        <div class="dashboard-modal-content">
            <div class="dashboard-modal-header">
                <h3 id="modalTitle">Details</h3>
                <button class="dashboard-modal-close" onclick="closeDashboardModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="dashboard-modal-body" id="modalBody">
            </div>
        </div>
    </div>

    <div id="updateNotification" class="update-notification">
        <i class="fas fa-sync-alt"></i>
        Dashboard updated
    </div>

    <script src="../assets/js/notifications.js?v=<?php echo time(); ?>"></script>

    <script src="../assets/js/sidebar-toggle.js?v=<?php echo time(); ?>"></script>
    <script src="../assets/js/outlet-dashboard.js?v=<?php echo time(); ?>"></script>

    <script>
    // Recent activity delete handler
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.btn-delete-activity');
        if (!btn) return;
        if (!confirm('Delete this activity?')) return;
        const id = btn.dataset.id;
        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const form = new URLSearchParams();
            form.append('notification_id', id);
            const resp = await fetch('../api/notifications.php?action=delete', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form.toString()
            });
            const data = await resp.json();
            if (data && data.success) {
                const row = btn.closest('tr');
                row.style.transition = 'opacity 0.2s, height 0.2s, transform 0.2s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-10px)';
                setTimeout(() => row.remove(), 220);
            } else {
                alert('Delete failed: ' + (data?.error || data?.message || 'Unknown error'));
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        } catch (err) {
            console.error('Delete error', err);
            alert('Failed to delete activity. See console for details.');
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    });
    </script>
    
    <?php if ($_SESSION['role'] === 'outlet_manager'): ?>
    <script>
    
    if (window.location.search.includes('reset_notifications=1')) {
        sessionStorage.removeItem('notification_prompt_dismissed');
        
        const url = new URL(window.location);
        url.searchParams.delete('reset_notifications');
        window.history.replaceState({}, '', url);
    }
    
    
    (function() {
        console.log('[Outlet Manager Push] ===== INITIALIZING PUSH SYSTEM =====');
        console.log('[Outlet Manager Push] Timestamp:', new Date().toISOString());
        console.log('[Outlet Manager Push] Current URL:', window.location.href);
        console.log('[Outlet Manager Push] Current path:', window.location.pathname);
        console.log('[Outlet Manager Push] User role: outlet_manager');
        console.log('[Outlet Manager Push] Session storage dismissed:', sessionStorage.getItem('notification_prompt_dismissed'));
        console.log('[Outlet Manager Push] Local storage vapid version:', localStorage.getItem('outlet_vapid_version'));
        console.log('[Outlet Manager Push] Local storage last check:', localStorage.getItem('outlet_push_last_check'));
        console.log('[Outlet Manager Push] Notification API available:', 'Notification' in window);
        console.log('[Outlet Manager Push] Service Worker API available:', 'serviceWorker' in navigator);
        console.log('[Outlet Manager Push] Current notification permission:', Notification.permission);
        console.log('[Outlet Manager Push] VAPID_PUBLIC_KEY loaded:', '<?php echo htmlspecialchars(EnvLoader::get('VAPID_PUBLIC_KEY')); ?>' ? 'YES' : 'NO');

        const VAPID_PUBLIC_KEY = '<?php echo htmlspecialchars(EnvLoader::get('VAPID_PUBLIC_KEY')); ?>';
        
        function showSuccessMessage() {
            const message = document.createElement('div');
            message.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
                z-index: 10001;
                display: flex;
                align-items: center;
                gap: 12px;
                animation: slideInRight 0.3s ease-out;
            `;
            message.innerHTML = `
                <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                <div>
                    <div style="font-weight: 600; font-size: 15px;">Notifications Enabled!</div>
                    <div style="font-size: 13px; opacity: 0.95;">You'll receive trip and parcel updates</div>
                </div>
            `;
            document.body.appendChild(message);
            
            setTimeout(() => {
                message.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => message.remove(), 300);
            }, 4000);
        }
        
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }
        
        async function subscribeToPush(registration, existingSubscription = null, forceNew = false) {
            try {
                console.log('[Outlet Manager Push] ===== SUBSCRIBE TO PUSH STARTED =====');
                console.log('[Outlet Manager Push] Parameters:', { forceNew, hasExisting: !!existingSubscription });
                
                let subscription = existingSubscription;
                
                
                if (forceNew || !subscription) {
                    
                    if (existingSubscription && forceNew) {
                        console.log('[Outlet Manager Push] Unsubscribing from old subscription...');
                        await existingSubscription.unsubscribe();
                        console.log('[Outlet Manager Push] Old subscription unsubscribed');
                    }
                    
                    console.log('[Outlet Manager Push] Creating new subscription with current VAPID keys...');
                    console.log('[Outlet Manager Push] VAPID key length:', VAPID_PUBLIC_KEY.length);
                    
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
                    });
                    
                    console.log('[Outlet Manager Push] ✅ New subscription created successfully');
                    console.log('[Outlet Manager Push] Subscription endpoint:', subscription.endpoint);
                }
                
                
                // Use relative path to ensure correct API is called
                const timestamp = Date.now();
                const apiPath = './manager_save_push_subscription.php?v=' + timestamp;
                
                console.log('[Outlet Manager Push] Current pathname:', window.location.pathname);
                console.log('[Outlet Manager Push] API path:', apiPath);
                console.log('[Outlet Manager Push] Cache-busting timestamp:', timestamp);
                
                const payload = {
                    subscription: subscription.toJSON(),
                    endpoint: subscription.endpoint,
                    keys: {
                        p256dh: subscription.toJSON().keys.p256dh,
                        auth: subscription.toJSON().keys.auth
                    },
                    user_role: 'outlet_manager'
                };
                
                console.log('[Outlet Manager Push] Sending subscription to API...');
                console.log('[Outlet Manager Push] Payload:', payload);
                
                const response = await fetch(apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                console.log('[Outlet Manager Push] API response status:', response.status);
                console.log('[Outlet Manager Push] API response ok:', response.ok);
                console.log('[Outlet Manager Push] API response headers:', Object.fromEntries(response.headers));
                
                const responseText = await response.text();
                console.log('[Outlet Manager Push] API raw response:', responseText);
                
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('[Outlet Manager Push] Failed to parse JSON response:', e);
                    console.error('[Outlet Manager Push] Response was:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                console.log('[Outlet Manager Push] API response result:', result);
                
                if (result.success) {
                    console.log('✅ Push notifications enabled for outlet manager');
                    console.log('[Outlet Manager Push] Subscription ID:', result.subscription_id);
                    localStorage.setItem('outlet_push_last_check', Date.now().toString());
                    return true;
                } else {
                    console.error('[Outlet Manager Push] ❌ Failed to save subscription:', result.error);
                    return false;
                }
            } catch (error) {
                console.error('[Outlet Manager Push] ❌ Error subscribing to push:', error);
                console.error('[Outlet Manager Push] Error stack:', error.stack);
                return false;
            }
        }
        
        function showNotificationPrompt(onEnable) {
            console.log('[Outlet Manager Push] showNotificationPrompt called');
            
            
            if (sessionStorage.getItem('notification_prompt_dismissed')) {
                console.log('[Outlet Manager Push] Prompt was dismissed, not showing');
                return;
            }
            
            console.log('[Outlet Manager Push] Creating notification banner...');
            
            const isPermissionDenied = Notification.permission === 'denied';
            const title = isPermissionDenied ? 'Re-enable Push Notifications' : 'Enable Push Notifications';
            const description = isPermissionDenied 
                ? 'Notifications were previously blocked. Click below to allow notifications for trip updates.'
                : 'Get real-time updates on trip status, parcel deliveries, and driver activities';
            const buttonText = isPermissionDenied ? 'Allow Notifications' : 'Enable Notifications';
            
            
            const banner = document.createElement('div');
            banner.id = 'notification-prompt-banner';
            banner.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                animation: slideDown 0.3s ease-out;
            `;
            
            banner.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px; flex: 1;">
                    <i class="fas fa-bell" style="font-size: 24px;"></i>
                    <div>
                        <div style="font-weight: 600; font-size: 15px; margin-bottom: 4px;">
                            ${isPermissionDenied ? 'Re-enable Push Notifications' : 'Enable Push Notifications'}
                        </div>
                        <div style="font-size: 13px; opacity: 0.95;">
                            ${isPermissionDenied 
                                ? 'Notifications were previously blocked. Click below to allow notifications for trip updates.'
                                : 'Get real-time updates on trip status, parcel deliveries, and driver activities'}
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button id="enable-notifications-btn" style="
                        background: white;
                        color: #667eea;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 6px;
                        font-weight: 600;
                        cursor: pointer;
                        font-size: 14px;
                        transition: transform 0.2s;
                    ">
                        ${buttonText}
                    </button>
                    <button id="dismiss-notifications-btn" style="
                        background: transparent;
                        color: white;
                        border: 1px solid rgba(255,255,255,0.5);
                        padding: 10px 16px;
                        border-radius: 6px;
                        font-weight: 500;
                        cursor: pointer;
                        font-size: 14px;
                    ">
                        Later
                    </button>
                </div>
            `;
            
            document.body.insertBefore(banner, document.body.firstChild);
            
            
            if (!document.getElementById('notification-prompt-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-prompt-styles';
                style.textContent = `
                    @keyframes slideDown {
                        from {
                            transform: translateY(-100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateY(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideInRight {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOutRight {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                    @keyframes pulse {
                        0%, 100% {
                            transform: scale(1);
                            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
                        }
                        50% {
                            transform: scale(1.02);
                            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.6);
                        }
                    }
                    #enable-notifications-btn:hover {
                        transform: scale(1.05);
                    }
                    #dismiss-notifications-btn:hover {
                        background: rgba(255,255,255,0.1);
                    }
                `;
                document.head.appendChild(style);
            }
            
            
            document.getElementById('enable-notifications-btn').addEventListener('click', async () => {
                banner.remove();
                await onEnable();
            });
            
            
            document.getElementById('dismiss-notifications-btn').addEventListener('click', () => {
                banner.remove();
                sessionStorage.setItem('notification_prompt_dismissed', 'true');
            });
        }
        
        async function initPushNotifications() {
            try {
                console.log('[Outlet Manager Push] ===== INIT STARTED =====');
                console.log('[Outlet Manager Push] Function called successfully');
                console.log('[Outlet Manager Push] Timestamp:', new Date().toISOString());
                console.log('[Outlet Manager Push] User agent:', navigator.userAgent);
                console.log('[Outlet Manager Push] Notification API available:', 'Notification' in window);
                console.log('[Outlet Manager Push] Service Worker API available:', 'serviceWorker' in navigator);
                console.log('[Outlet Manager Push] Current permission:', Notification.permission);
                
                
                const hasNotification = 'Notification' in window;
                const hasServiceWorker = 'serviceWorker' in navigator;
                
                if (!hasNotification || !hasServiceWorker) {
                    console.log('[Outlet Manager Push] Push notifications not supported');
                    return;
                }
                
                
                const currentPath = window.location.pathname;
                console.log('[Outlet Manager Push] Current path:', currentPath);
                
                // Calculate the base path to outlet-app root
                let basePath = '';
                if (currentPath.includes('/outlet-app/')) {
                    basePath = currentPath.substring(0, currentPath.indexOf('/outlet-app/') + '/outlet-app/'.length - 1);
                } else if (currentPath.includes('/pages/')) {
                    // For outlet.localhost setup where outlet-app is at root
                    basePath = '';
                } else {
                    basePath = '';
                }
                
                const swPath = basePath + '/sw-manager.js';
                
                console.log('[Outlet Manager Push] Base path:', basePath);
                console.log('[Outlet Manager Push] Service Worker path:', swPath);
                console.log('[Outlet Manager Push] Full SW URL:', new URL(swPath, window.location.href).href);
                
                const registration = await navigator.serviceWorker.register(swPath);
                await navigator.serviceWorker.ready;
                console.log('[Outlet Manager Push] Service Worker registered:', registration.scope);
                
                
                const existingSubscription = await registration.pushManager.getSubscription();
                console.log('[Outlet Manager Push] Existing subscription:', existingSubscription ? 'YES' : 'NO');
                if (existingSubscription) {
                    console.log('[Outlet Manager Push] Existing subscription details:', {
                        endpoint: existingSubscription.endpoint,
                        keys: existingSubscription.toJSON().keys
                    });
                }
                
                if (existingSubscription) {
                    console.log('[Outlet Manager Push] ✅ Browser has subscription, verifying with server...');
                    
                    // Always save to ensure it's in the database
                    console.log('[Outlet Manager Push] Ensuring subscription is saved to database...');
                    try {
                        await subscribeToPush(registration, existingSubscription, false);
                        localStorage.setItem('outlet_vapid_version', '1');
                        localStorage.setItem('outlet_push_last_check', Date.now().toString());
                        console.log('[Outlet Manager Push] ✅ Subscription verified and saved');
                    } catch (error) {
                        console.error('[Outlet Manager Push] Failed to save subscription:', error);
                    }
                    return;
                }
                
                console.log('[Outlet Manager Push] No existing subscription, checking permission...');
                console.log('[Outlet Manager Push] Permission status:', Notification.permission);
                
                // Show prompt for all permission states except when explicitly dismissed
                console.log('[Outlet Manager Push] No existing subscription, showing prompt...');
                console.log('[Outlet Manager Push] Session storage dismissed:', sessionStorage.getItem('notification_prompt_dismissed'));
                
                if (Notification.permission === 'default' || Notification.permission === 'granted') {
                    console.log('[Outlet Manager Push] Calling showNotificationPrompt...');
                    showNotificationPrompt(async () => {
                        try {
                            console.log('[Outlet Manager Push] User clicked enable, requesting permission...');
                            const permission = await Notification.requestPermission();
                            console.log('[Outlet Manager Push] Permission result:', permission);
                            if (permission === 'granted') {
                                console.log('[Outlet Manager Push] Permission granted, subscribing...');
                                const success = await subscribeToPush(registration);
                                if (success) {
                                    console.log('[Outlet Manager Push] Subscription successful, showing success message');
                                    showSuccessMessage();
                                    localStorage.setItem('outlet_vapid_version', '1');
                                } else {
                                    console.error('[Outlet Manager Push] Subscription failed');
                                    alert('Failed to save notification subscription. Please try again.');
                                }
                            } else {
                                console.log('[Outlet Manager Push] Permission denied by user');
                                alert('Please allow notifications to receive trip updates');
                            }
                        } catch (error) {
                            console.error('[Outlet Manager Push] Error enabling notifications:', error);
                            alert('Failed to enable notifications. Please try again.');
                        }
                    });
                } else {
                    console.log('[Outlet Manager Push] Permission denied and prompt dismissed, not showing prompt');
                }
                
            } catch (error) {
                console.error('[Outlet Manager Push] Push notification init error:', error);
            }
        }
        
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPushNotifications);
        } else {
            initPushNotifications();
        }
    })();
    </script>
    <?php endif; ?>

    <!-- Loading Overlay Control Script -->
    <script>
        (function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            const steps = document.querySelectorAll('.loading-step');
            let currentStep = 0;
            let stepInterval;
            const startTime = Date.now();
            const minimumLoadTime = 800; 
            let pageLoaded = false;
            let dataLoaded = false;
            
            
            window.markDashboardDataLoaded = function() {
                console.log('[Loading Overlay] Dashboard data loaded');
                dataLoaded = true;
                checkAndHideLoading();
            };
            
            
            function showNextStep() {
                if (currentStep < steps.length) {
                    steps.forEach(step => step.classList.remove('active'));
                    steps[currentStep].classList.add('active');
                    currentStep++;
                }
            }
            
            
            stepInterval = setInterval(showNextStep, 400); 
            
            
            function hideLoadingOverlay() {
                const elapsedTime = Date.now() - startTime;
                const remainingTime = Math.max(0, minimumLoadTime - elapsedTime);
                
                console.log('[Loading Overlay] Hiding in ' + remainingTime + 'ms');
                
                
                setTimeout(() => {
                    clearInterval(stepInterval);
                    
                    
                    if (steps.length > 0) {
                        steps.forEach(step => step.classList.remove('active'));
                        steps[steps.length - 1].classList.add('active');
                    }
                    
                    
                    setTimeout(() => {
                        loadingOverlay.classList.add('hidden');
                        
                        
                        setTimeout(() => {
                            if (loadingOverlay && loadingOverlay.parentNode) {
                                loadingOverlay.parentNode.removeChild(loadingOverlay);
                            }
                        }, 500);
                    }, 300); 
                }, remainingTime);
            }
            
            
            function checkAndHideLoading() {
                if (pageLoaded && dataLoaded) {
                    console.log('[Loading Overlay] Both page and data ready, hiding overlay');
                    hideLoadingOverlay();
                }
            }
            
            
            function markPageLoaded() {
                if (!pageLoaded) {
                    console.log('[Loading Overlay] Page DOM loaded');
                    pageLoaded = true;
                    checkAndHideLoading();
                }
            }
            
            
            if (document.readyState === 'complete') {
                markPageLoaded();
            } else {
                window.addEventListener('load', markPageLoaded);
            }
            
            
            setTimeout(() => {
                if (!pageLoaded || !dataLoaded) {
                    console.log('[Loading Overlay] Timeout reached, forcing hide. Page:', pageLoaded, 'Data:', dataLoaded);
                    pageLoaded = true;
                    dataLoaded = true;
                    hideLoadingOverlay();
                }
            }, 5000);
            
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', markPageLoaded);
            } else {
                markPageLoaded();
            }
        })();
    </script>

    <script>
        // Dashboard collapsible toggles
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.collapsible-section').forEach(function (section) {
                const btn = section.querySelector('.collapse-toggle');
                const content = section.querySelector('.collapse-content');
                if (!btn || !content) return;
                // Initialize heights and ARIA states
                if (btn.getAttribute('aria-expanded') === 'true') {
                    content.classList.remove('collapsed');
                    content.style.maxHeight = content.scrollHeight + 'px';
                    content.setAttribute('aria-hidden', 'false');
                } else {
                    content.classList.add('collapsed');
                    content.style.maxHeight = '0px';
                    content.setAttribute('aria-hidden', 'true');
                }

                // Handle toggle with smooth transitions
                btn.addEventListener('click', function (e) {
                    const expanded = btn.getAttribute('aria-expanded') === 'true';
                    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');

                    if (expanded) {
                        // Collapse: set current height then animate to 0
                        content.style.maxHeight = content.scrollHeight + 'px';
                        // Force reflow so the transition runs
                        // eslint-disable-next-line no-unused-expressions
                        content.offsetHeight;
                        content.style.maxHeight = '0px';
                        content.classList.add('collapsed');
                        content.setAttribute('aria-hidden', 'true');
                    } else {
                        // Expand: remove collapsed class and expand to full height
                        content.classList.remove('collapsed');
                        content.style.maxHeight = content.scrollHeight + 'px';
                        content.setAttribute('aria-hidden', 'false');
                    }
                });

                // After transition ends, clear inline maxHeight when expanded so content can grow/shrink naturally
                content.addEventListener('transitionend', function () {
                    if (btn.getAttribute('aria-expanded') === 'true') {
                        content.style.maxHeight = '';
                        content.setAttribute('aria-hidden', 'false');
                    } else {
                        content.style.maxHeight = '0px';
                        content.setAttribute('aria-hidden', 'true');
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php

ob_end_flush();
?>