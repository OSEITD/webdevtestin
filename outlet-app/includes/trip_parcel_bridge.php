<?php
class TripParcelBridge {
    private $sessionKey = 'trip_parcel_bridge';
    private $companyId;

    public function __construct() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
            throw new Exception('Authentication and company context required');
        }

        $this->companyId = $_SESSION['company_id'];

        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = [
                'company_id' => $this->companyId,
                'active_trip_id' => null,
                'pending_parcels' => [],
                'workflow_mode' => null,
                'context' => []
            ];
        }

        if ($_SESSION[$this->sessionKey]['company_id'] !== $this->companyId) {
            $this->reset();
            $_SESSION[$this->sessionKey]['company_id'] = $this->companyId;
        }
    }

    public function setActiveTrip($tripId, $tripDetails = []) {
        if (!$this->validateTripBelongsToTenant($tripId)) {
            throw new Exception('Trip does not belong to current company');
        }

        $_SESSION[$this->sessionKey]['active_trip_id'] = $tripId;
        $_SESSION[$this->sessionKey]['context']['trip'] = $tripDetails;
        $_SESSION[$this->sessionKey]['workflow_mode'] = 'trip_first';
    }

    public function addPendingParcel($parcelId, $parcelDetails = []) {
        if (!$this->validateParcelBelongsToTenant($parcelId)) {
            throw new Exception('Parcel does not belong to current company');
        }

        $_SESSION[$this->sessionKey]['pending_parcels'][$parcelId] = $parcelDetails;
        $_SESSION[$this->sessionKey]['workflow_mode'] = 'parcel_first';
    }

    private function validateTripBelongsToTenant($tripId) {
        try {
            require_once 'MultiTenantSupabaseHelper.php';
            $supabase = new MultiTenantSupabaseHelper($this->companyId);
            $trip = $supabase->get('trips', "id=eq.$tripId", 'id');
            return !empty($trip);
        } catch (Exception $e) {
            error_log("Trip validation error: " . $e->getMessage());
            return false;
        }
    }

    private function validateParcelBelongsToTenant($parcelId) {
        try {
            require_once 'MultiTenantSupabaseHelper.php';
            $supabase = new MultiTenantSupabaseHelper($this->companyId);
            $parcel = $supabase->get('parcels', "id=eq.$parcelId", 'id');
            return !empty($parcel);
        } catch (Exception $e) {
            error_log("Parcel validation error: " . $e->getMessage());
            return false;
        }
    }

    public function getActiveTrip() {
        return [
            'trip_id' => $_SESSION[$this->sessionKey]['active_trip_id'],
            'details' => $_SESSION[$this->sessionKey]['context']['trip'] ?? []
        ];
    }

    public function getPendingParcels() {
        return $_SESSION[$this->sessionKey]['pending_parcels'] ?? [];
    }

    public function clearPendingParcels() {
        $_SESSION[$this->sessionKey]['pending_parcels'] = [];
    }

    public function getNavigationUrls() {
        return [
            'trip_wizard' => 'trip_wizard.php?context=' . urlencode(json_encode($this->getContext())),
            'parcel_registration' => 'parcel_registration.php?context=' . urlencode(json_encode($this->getContext())),
            'dashboard' => 'outlet_dashboard.php'
        ];
    }

    public function getContext() {
        return $_SESSION[$this->sessionKey];
    }

    public function setWorkflowMode($mode) {
        $_SESSION[$this->sessionKey]['workflow_mode'] = $mode;
    }

    public function hasActiveWorkflow() {
        $context = $this->getContext();
        return !empty($context['active_trip_id']) || !empty($context['pending_parcels']);
    }

    public function getWorkflowSuggestions() {
        $context = $this->getContext();
        $suggestions = [];

        if (!empty($context['active_trip_id'])) {
            $suggestions[] = [
                'type' => 'success',
                'message' => 'Trip selected! You can now register parcels for this trip.',
                'action' => 'parcel_registration.php',
                'action_text' => 'Register Parcels'
            ];
        }

        if (!empty($context['pending_parcels'])) {
            $count = count($context['pending_parcels']);
            $suggestions[] = [
                'type' => 'info',
                'message' => "You have {$count} parcel(s) waiting for trip assignment.",
                'action' => 'trip_wizard.php',
                'action_text' => 'Create Trip'
            ];
        }

        return $suggestions;
    }

    public function reset() {
        $_SESSION[$this->sessionKey] = [
            'active_trip_id' => null,
            'pending_parcels' => [],
            'workflow_mode' => null,
            'context' => []
        ];
    }
}

$bridge = new TripParcelBridge();
?>
