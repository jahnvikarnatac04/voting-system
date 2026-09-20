<?php
/**
 * kiosk/logout.php — lock the booth kiosk on this device.
 */
require_once __DIR__ . '/_kiosk.php';

kiosk_clear_session();
session_regenerate_id(true);

header('Location: login.php');
exit();
