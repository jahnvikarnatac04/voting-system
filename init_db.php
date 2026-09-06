<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Create Admins Table (Supports multiple admins and roles)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'admin' CHECK(role IN ('super_admin', 'admin')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. Create Voters Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fullname TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            voter_id_number TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            mobile TEXT,
            address TEXT,
            photo TEXT DEFAULT 'default.png',
            document_proof TEXT,
            status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
            has_voted INTEGER NOT NULL DEFAULT 0,
            voting TEXT DEFAULT 'no',
            fingerprint_credential TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 3. Add fingerprint_credential column if missing (upgrade path)
    try {
        $pdo->exec("ALTER TABLE voters ADD COLUMN fingerprint_credential TEXT DEFAULT NULL");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    try {
        $pdo->exec("ALTER TABLE voters ADD COLUMN mobile TEXT");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    try {
        $pdo->exec("ALTER TABLE voters ADD COLUMN address TEXT");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    // 2.5 Create Passkeys (WebAuthn / Fingerprint) Table
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
        );
    ");

    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_passkeys_voter ON passkeys(voter_id)");
    } catch (PDOException $e) {
        // ignore
    }

    // 2.6 Create Biometric Verification Audit Log Table
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
        );
    ");

    // 3. Create Candidates Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS candidates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            party TEXT NOT NULL,
            photo TEXT DEFAULT 'default.png',
            votes_count INTEGER NOT NULL DEFAULT 0,
            constituency TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 4. Define Multiple Default Admins
    $initial_admins = [
        [
            'name'     => 'Chief Election Officer',
            'username' => 'admin',
            'email'    => 'admin@example.com',
            'password' => 'admin123',
            'role'     => 'super_admin'
        ],
        [
            'name'     => 'Verification Officer',
            'username' => 'officer1',
            'email'    => 'officer1@example.com',
            'password' => 'officer123',
            'role'     => 'admin'
        ]
    ];

    $insert_admin = $pdo->prepare("
        INSERT OR IGNORE INTO admins (name, username, email, password, role) 
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($initial_admins as $adm) {
        $hashed_password = password_hash($adm['password'], PASSWORD_DEFAULT);
        $insert_admin->execute([
            $adm['name'],
            $adm['username'],
            $adm['email'],
            $hashed_password,
            $adm['role']
        ]);
    }

    echo "<h3 style='color:green;'>Database setup completed successfully!</h3>";
    echo "<p>Admin Accounts Configured:</p>";
    echo "<ul>";
    foreach ($initial_admins as $adm) {
        echo "<li><strong>" . htmlspecialchars($adm['name']) . " (" . htmlspecialchars($adm['role']) . "):</strong><br>"
           . "Username: <code>" . htmlspecialchars($adm['username']) . "</code> | Password: <code>" . htmlspecialchars($adm['password']) . "</code></li><br>";
    }
    echo "</ul>";
    echo "<p><a href='admin/login.php'>Go to Admin Login &rarr;</a></p>";

} catch (PDOException $e) {
    die("<h3 style='color:red;'>Setup Failed:</h3> " . $e->getMessage());
}
?>