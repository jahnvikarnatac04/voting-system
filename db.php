<?php
// db.php - Central Database Connection
// Database location. Overridable so automated tests can run against an
// isolated database instead of the live one (see scripts/tests/).
// Default behaviour is unchanged when VOTING_DB_PATH is not set.
$dbPath = getenv('VOTING_DB_PATH');
if ($dbPath === false || $dbPath === '') {
    $dbPath = __DIR__ . '/voting_system.db';
}

try {
    // Connect using PDO (standard & secure for SQLite in PHP)
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Idempotent upgrade path: make sure the passkeys (WebAuthn) table exists
// so fingerprint/passkey login works even if init_db.php was not re-run.
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS passkeys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            voter_id INTEGER NOT NULL,
            credential_id TEXT NOT NULL UNIQUE,
            public_key TEXT NOT NULL,
            alg INTEGER NOT NULL DEFAULT -7,
            sign_count INTEGER NOT NULL DEFAULT 0,
            device_label TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_passkeys_voter ON passkeys(voter_id)");
} catch (PDOException $e) {
    // Table creation failure should not break non-biometric pages.
}

// Biometric verification audit log (face / fingerprint attempts)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS biometric_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            voter_id INTEGER NOT NULL,
            method TEXT NOT NULL DEFAULT 'face',
            matched INTEGER NOT NULL DEFAULT 0,
            distance REAL,
            image_file TEXT DEFAULT '',
            ip_address TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    // Non-fatal if audit table cannot be created.
}

/* ===============================================================
 * Booth / kiosk location model (idempotent upgrade path)
 * ===============================================================
 * A booth is a physical location (polling station) that runs the
 * phone-kiosk enrollment/verification terminal. Each booth has a
 * short code + a PIN used to unlock the kiosk on the device.
 */
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS booths (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            name TEXT NOT NULL,
            state TEXT DEFAULT '',
            constituency TEXT DEFAULT '',
            pin_hash TEXT NOT NULL,
            active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    // Non-fatal: booth features degrade if this cannot be created.
}

// Failed/successful kiosk unlock attempts, used for rate-limiting.
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kiosk_auth_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            booth_code TEXT DEFAULT '',
            ip_address TEXT DEFAULT '',
            success INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_kiosk_attempts_ip ON kiosk_auth_attempts(ip_address, created_at)");
} catch (PDOException $e) {
    // Non-fatal.
}

/**
 * Add a column only when it does not already exist (SQLite has no
 * ADD COLUMN IF NOT EXISTS). Safe to call on every request.
 */
if (!function_exists('vs_add_column')) {
    function vs_add_column(PDO $pdo, string $table, string $column, string $ddl): void
    {
        try {
            $cols = $pdo->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if (strcasecmp((string)$c['name'], $column) === 0) {
                    return;
                }
            }
            $pdo->exec("ALTER TABLE " . $table . " ADD COLUMN " . $ddl);
        } catch (PDOException $e) {
            // Non-fatal.
        }
    }
}

// Where a passkey was enrolled, and where a biometric event happened.
vs_add_column($pdo, 'passkeys', 'booth_id', 'booth_id INTEGER DEFAULT NULL');
vs_add_column($pdo, 'biometric_logs', 'booth_id', 'booth_id INTEGER DEFAULT NULL');

// A voter's Parliamentary Constituency — determines which ballot they get at
// a booth kiosk. Matched against booths.constituency before a ballot is shown.
vs_add_column($pdo, 'voters', 'constituency', "constituency TEXT DEFAULT ''");

// A face reference photo captured in person at the booth kiosk. Used for the
// booth face check when present; falls back to the profile photo when empty.
vs_add_column($pdo, 'voters', 'face_photo', "face_photo TEXT DEFAULT ''");
?>