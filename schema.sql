-- SQLite Database Schema for Online Voting System

-- Table: admins
-- Stores administrative credentials for approving voters and managing elections
CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table: voters
-- Tracks registration status (pending, approved, rejected) and whether the ballot has been cast
CREATE TABLE IF NOT EXISTS voters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    voter_id_number TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    mobile TEXT,
    address TEXT,
    photo TEXT,
    document_proof TEXT,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected')),
    has_voted INTEGER NOT NULL DEFAULT 0 CHECK(has_voted IN (0, 1)),
    fingerprint_credential TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table: candidates
-- Holds information on candidates running in the election
CREATE TABLE IF NOT EXISTS candidates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    party TEXT NOT NULL,
    photo TEXT,
    votes_count INTEGER NOT NULL DEFAULT 0,
    constituency TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table: votes (Optional Audit Trail)
-- Anonymous ballot log linking a candidate vote without direct voter identification
CREATE TABLE IF NOT EXISTS votes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    candidate_id INTEGER NOT NULL,
    voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
);

-- Table: passkeys
-- Server-verified WebAuthn passkey (fingerprint) credentials per voter.
-- One voter may have several (phone, laptop, security key, ...).
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

-- Table: biometric_logs
-- Audit trail of biometric (face / fingerprint) verification attempts
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

-- Indexes for optimized lookups
CREATE INDEX IF NOT EXISTS idx_voters_status ON voters(status);
CREATE INDEX IF NOT EXISTS idx_voters_voter_id ON voters(voter_id_number);
CREATE INDEX IF NOT EXISTS idx_passkeys_voter ON passkeys(voter_id);