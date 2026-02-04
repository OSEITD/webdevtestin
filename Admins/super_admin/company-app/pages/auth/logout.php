<?php
// Compatibility shim for legacy links that expect logout2.php under pages/auth/
// This forwards execution to the canonical `company-app/auth/logout2.php`.
require_once __DIR__ . '/../../auth/logout.php';
