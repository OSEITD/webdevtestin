<?php

require_once '../includes/header.php';

?>
  <!-- Supabase JS (browser) -->
  <script src="https://unpkg.com/@supabase/supabase-js@2"></script>

  <style>
    .modal { display:none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
    .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 780px; border-radius: 8px; position: relative; }
    .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    .btn { background-color: #3b82f6; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; }
    .btn:disabled { opacity: 0.6; cursor: not-allowed; }
    /* Map UI removed: delivery and picker maps were removed from this page */
    .muted { color:#666; font-size: 0.9rem; }
    .pill { display:inline-block; padding: 4px 10px; border-radius: 999px; background:#eef2ff; color:#3730a3; font-size: 12px; }
  </style>
</head>

<body class="bg-gray-100 min-h-screen">
  <div class="mobile-dashboard">

    <!-- Main Content Area for Parcel Details -->
    <main class="main-content">
      <div class="content-container">
        <h1>Parcel Details</h1>
        <p class="subtitle" id="trackingNumber">Tracking Number: Loading...</p>
        <!-- Parcel Status Display -->
        <div id="parcelStatusDisplay" class="parcel-status-display status-pending">Loading...</div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
          <!-- Recipient Information -->
          <div class="detail-section">
            <h2>Recipient Information</h2>
            <div class="detail-item">
              <i class="fas fa-user"></i>
              <div>
                <p class="label">Recipient Name</p>
                <p class="value" id="recipientName"></p>
              </div>
            </div>
            <div class="detail-item">
              <i class="fas fa-map-marker-alt"></i>
              <div>
                <p class="label">Delivery Address</p>
                <p class="value" id="deliveryAddress"></p>
              </div>
            </div>
            <div class="detail-item">
              <i class="fas fa-phone"></i>
              <div>
                <p class="label">Contact Number</p>
                <p class="value" id="contactNumber"></p>
              </div>
            </div>
          </div>

          <!-- Delivery Details -->
          <div class="detail-section">
            <h2>Delivery Details</h2>
            <div class="detail-item">
              <i class="fas fa-clock"></i>
              <div>
                <p class="label">Scheduled Delivery Time</p>
                <p class="value" id="scheduledDeliveryTime"></p>
              </div>
            </div>
            <div class="detail-item">
              <i class="fas fa-clipboard-list"></i>
              <div>
                <p class="label">Delivery Notes</p>
                <p class="value" id="deliveryNotes"></p>
              </div>
            </div>

            <!-- Destination outlet quick view -->
            <div class="detail-item">
              <i class="fa-solid fa-location-crosshairs"></i>
              <div>
                <p class="label">Destination Outlet</p>
                <p class="value">
                  <span id="destinationOutletName"></span>
                  <span id="destinationOutletMeta" class="muted"></span>
                </p>
              </div>
            </div>

          <!-- Action Buttons -->
          <div class="md:col-span-2 action-buttons-group">
            <button type="button" class="action-btn contact" id="contactRecipientBtn">
              <i class="fas fa-phone"></i> Contact Recipient
            </button>

            <!-- Contact Recipient Modal -->
            <div id="contactRecipientModal" class="modal">
              <div class="modal-content" style="width:320px;">
                <span class="close" id="closeContactModal">&times;</span>
                <h2>Contact Recipient</h2>
                <p><strong>Phone:</strong> <a href="#" id="contactPhoneLink"></a></p>
                <p><strong>Send SMS:</strong> <a href="#" id="contactSmsLink">Send SMS</a></p>
                <p><strong>Address:</strong> <span id="contactAddress"></span></p>
              </div>
            </div>

            <!-- Update status removed from this page; status changes are handled elsewhere -->
          </div>
        </div>
      </div>
    </main>
  </div>

  <script>
    // ====== CONFIG: Supabase ======
    const SUPABASE_URL = 'https://xerpchdsykqafrsxbqef.supabase.co';
    const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1Mjc2NDk1NywiZXhwIjoyMDY4MzQwOTU3fQ.LEzV6B20wOKypjnGX6jZMos_HG_9OHOT2OqPrdRVmpQ';
    // Use let instead of const to allow redeclaration if included multiple times
    // Use a unique name to avoid conflicts with global 'supabase' identifier
    const supabaseClient = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

    // Session outlet id from PHP
  const CURRENT_OUTLET_ID = <?php echo isset($outletId) ? json_encode($outletId) : 'null'; ?>;


    /* Delivery map removed from this page to simplify UI and avoid extra dependencies */

    // ====== UI elements ======
    const menuBtn = document.getElementById('menuBtn');
    const closeMenu = document.getElementById('closeMenu');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');

    const contactRecipientBtn = document.getElementById('contactRecipientBtn');
    const contactRecipientModal = document.getElementById('contactRecipientModal');
    const closeContactModalBtn = document.getElementById('closeContactModal');
    const contactPhoneLink = document.getElementById('contactPhoneLink');
    const contactSmsLink = document.getElementById('contactSmsLink');
    const contactAddressSpan = document.getElementById('contactAddress');

  // Update status removed from this page - these elements are intentionally not present
    const parcelStatusDisplay = document.getElementById('parcelStatusDisplay');
    const trackingNumberElem = document.getElementById('trackingNumber');

    const saveDestinationBtn = document.getElementById('saveDestinationBtn');
    const destinationOutletName = document.getElementById('destinationOutletName');
    const destinationOutletMeta = document.getElementById('destinationOutletMeta');

    // ====== Parcel data ======
    async function fetchParcelDetails() {
      const urlParams = new URLSearchParams(window.location.search);
      const trackNumber = urlParams.get('track');
      if (!trackNumber) {
        alert('No tracking number specified.');
        return;
      }

      try {
        const response = await fetch(`../api/fetch_parcel_details.php?track_number=${encodeURIComponent(trackNumber)}`);
        if (!response.ok) throw new Error('Failed to fetch parcel details');
        const json = await response.json();
        if (!json || !json.success) throw new Error(json?.error || 'API returned error');
        const parcel = json.data || json;

        trackingNumberElem.textContent = `Tracking Number: ${parcel.track_number || 'N/A'}`;
        parcelStatusDisplay.textContent = parcel.status || 'N/A';
        parcelStatusDisplay.className = 'parcel-status-display';
        if (parcel.status) {
          parcelStatusDisplay.classList.add(`status-${parcel.status.toLowerCase().replace(/\s/g, '-')}`);
        }

        document.getElementById('recipientName').textContent = parcel.receiver_name || 'N/A';
        document.getElementById('deliveryAddress').textContent = parcel.receiver_address || 'N/A';
        document.getElementById('contactNumber').textContent = parcel.receiver_phone || 'N/A';
        document.getElementById('scheduledDeliveryTime').textContent = parcel.delivery_date || 'N/A';
        document.getElementById('deliveryNotes').textContent = parcel.package_details || 'N/A';

        // If parcel already has destination outlet, show it
        if (parcel.destination_outlet_name) {
          destinationOutletName.textContent = parcel.destination_outlet_name;
        }
      } catch (error) {
        console.error('Error fetching parcel details:', error);
        alert('Error loading parcel details.');
      }
    }

    // Destination picker and company outlets logic removed (maps and picker UI)

    // Picker helper removed

    async function saveDestinationForParcel() {
      // No client-side map/picker on this page; saving requires server-side data.
      // If no selected outlet (picker removed), bail out to avoid accidental calls.
      if (!window.selectedOutlet) return;

      const selectedOutlet = window.selectedOutlet;
      const urlParams = new URLSearchParams(window.location.search);
      const trackNumber = urlParams.get('track');
      if (!trackNumber) {
        alert('No tracking number specified.');
        return;
      }

      // Save destination using the API endpoint
      const response = await fetch('./api/save_destination.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          track_number: trackNumber,
          destination_outlet_id: selectedOutlet.id,
          destination_outlet_name: selectedOutlet.name
        })
      });

      const result = await response.json();
      if (!response.ok || result.error) {
        console.error('Error saving destination:', result.error);
        showMessageBox('Failed to save destination. Please try again.');
        return;
      }


      const { error } = await supabaseClient
        .from('parcels')
        .update({
          destination_outlet_id: selectedOutlet.id,
          destination_outlet_name: selectedOutlet.name
        })
        .eq('track_number', trackNumber);

      if (error) {
        console.error(error);
        showMessageBox('Failed to save destination. Check RLS and permissions.');
        return;
      }

      // Reflect in UI
      destinationOutletName.textContent = selectedOutlet.name;
      destinationOutletMeta.textContent = selectedOutlet.address ? ` — ${selectedOutlet.address}` : '';
      // Map-related UI removed; nothing to move or close here.
      showMessageBox('Destination outlet saved.');

    }

    // ====== Existing UI logic (menus, modals) ======
    function toggleMenu() {
      sidebar?.classList.toggle('show');
      menuOverlay?.classList.toggle('show');
      document.body.style.overflow = sidebar?.classList.contains('show') ? 'hidden' : '';
    }

    // Contact Recipient Modal
    contactRecipientBtn.addEventListener('click', () => {
      const phone = document.getElementById('contactNumber').textContent.trim();
      const address = document.getElementById('deliveryAddress').textContent.trim();

      if (phone) {
        contactPhoneLink.href = `tel:${phone}`;
        contactPhoneLink.textContent = phone;
        contactSmsLink.href = `sms:${phone}`;
      } else {
        contactPhoneLink.href = '#';
        contactPhoneLink.textContent = 'No phone number available';
        contactSmsLink.href = '#';
      }
      contactAddressSpan.textContent = address || 'No address available';
      contactRecipientModal.style.display = 'block';
    });
    closeContactModalBtn.addEventListener('click', () => contactRecipientModal.style.display = 'none');
    window.addEventListener('click', (e) => { if (e.target === contactRecipientModal) contactRecipientModal.style.display = 'none'; });

  // Status update UI removed from this page; handled in the Trips/Parcels admin flows.

    // Fetch parcel details on page load
    document.addEventListener('DOMContentLoaded', fetchParcelDetails);

    // Status update removed: no client-side handler on this page.

    // Sidebar toggles (if present in DOM)
    document.getElementById('menuBtn')?.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
    document.getElementById('closeMenu')?.addEventListener('click', toggleMenu);
    document.getElementById('menuOverlay')?.addEventListener('click', toggleMenu);
    document.querySelectorAll('.menu-items a').forEach(item => item.addEventListener('click', toggleMenu));

    // Driver-assignment UI removed from this page.

  // Destination picker events removed (picker/map UI was removed)

    // Message box helper
    function showMessageBox(message) {
      const overlay = document.createElement('div');
      overlay.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex; justify-content: center; align-items: center; z-index: 1000;`;
      overlay.id = 'messageBoxOverlay';

      const box = document.createElement('div');
      box.style.cssText = `
        background-color: white; padding: 2rem; border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); text-align: center; max-width: 90%; width: 400px;`;

      const p = document.createElement('p');
      p.textContent = message;
      p.style.cssText = `font-size: 1.1rem; margin-bottom: 1.2rem; color: #333;`;

      const btn = document.createElement('button');
      btn.textContent = 'OK';
      btn.className = 'btn';
      btn.addEventListener('click', () => document.body.removeChild(overlay));

      box.appendChild(p); box.appendChild(btn);
      overlay.appendChild(box);
      document.body.appendChild(overlay);
    }
  </script>
</body>
</html>
