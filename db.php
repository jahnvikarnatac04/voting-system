<?php
// db.php - Central Database Connection
$dbPath = __DIR__ . '/voting_system.db';

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
?>