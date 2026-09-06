<?php
/**
 * seed_real_data.php
 * ---------------------------------------------------------------
 * Makes the database realistic using REAL public election data
 * (Lok Sabha 2024, General Election to the 18th Lok Sabha):
 *
 *   CANDIDATES -> real contesting candidates per Parliamentary
 *   Constituency (sourced from ECI-affiliated result aggregators /
 *   MyNeta-ADR affidavit records; verified 2024 figures).
 *
 *   VOTERS -> realistic DEMO citizen records. IMPORTANT: the real
 *   electoral roll (electoralsearch.eci.gov.in / voters.eci.gov.in)
 *   is personal citizen data. It is only searchable individually
 *   (name + relative + captcha) and is NOT bulk-downloadable or
 *   licensed for reuse in an application database. These voter rows
 *   are therefore synthetic but realistic (Indian names, EPIC-style
 *   ids, regional addresses) purely for demonstrating the software.
 *
 * Safe to re-run: skips records that already exist.
 * Demo voter password for every seeded voter:  Voter@123
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/db.php';

// ---------- 1. Schema upgrade: constituency column on candidates ----------
try {
    $pdo->exec("ALTER TABLE candidates ADD COLUMN constituency TEXT DEFAULT ''");
    echo "✓ Added candidates.constituency column\n";
} catch (PDOException $e) {
    echo "· candidates.constituency already present\n";
}

// ---------- 2. Real Lok Sabha 2024 candidates (name, party, PC) ----------
// Constituency labels match the portal selector in index.php.
$real_candidates = [
    ['Varanasi (PC-77)', [
        ['Narendra Modi',            'Bharatiya Janata Party (BJP)'],
        ['Ajay Rai',                 'Indian National Congress (INC)'],
        ['Ather Jamal Lari',         'Bahujan Samaj Party (BSP)'],
        ['Kolisetty Shiva Kumar',    'Yuga Thulasi Party'],
        ['Gagan Prakash Yadav',      'Apna Dal (Kamerawadi)'],
        ['Dinesh Kumar Yadav',       'Independent'],
        ['Sanjay Kumar Tiwari',      'Independent'],
    ]],
    ['Gandhinagar (PC-06)', [
        ['Amit Shah',                'Bharatiya Janata Party (BJP)'],
        ['Sonal Ramanbhai Patel',    'Indian National Congress (INC)'],
        ['Mohammedanish Desai',      'Bahujan Samaj Party (BSP)'],
        ['Shahnawazkhan Sultankhan Pathan', 'Independent'],
        ['Malek Makbul Shakib',      'Independent'],
        ['Parikh Rajivbhai Kalabhai','Independent'],
    ]],
    ['Rae Bareli (PC-36)', [
        ['Rahul Gandhi',             'Indian National Congress (INC)'],
        ['Dinesh Pratap Singh',      'Bharatiya Janata Party (BJP)'],
        ['Thakur Prasad Yadav',      'Bahujan Samaj Party (BSP)'],
        ['Horilal',                  'Independent'],
        ['Sudarshan Ram',            'Independent'],
        ['Mo Mobin',                 'Apna Dal (Kamerawadi)'],
    ]],
    ['Hyderabad (PC-09)', [
        ['Asaduddin Owaisi',         'All India Majlis-e-Ittehadul Muslimeen (AIMIM)'],
        ['Madhavi Latha Kompella',   'Bharatiya Janata Party (BJP)'],
        ['Mohammed Waliullah Sameer','Indian National Congress (INC)'],
        ['Srinivas Yadav Gaddam',    'Bharat Rashtra Samithi (BRS)'],
        ['Mekala Raghuma Reddy',     'Yuga Thulasi Party'],
        ['K.S.Krishna',              'Bahujan Samaj Party (BSP)'],
        ['Syed Anwar',               'Independent'],
        ['Amjad Khan',               'Independent'],
    ]],
];

// Remove only the old generic placeholders (no constituency), then insert real rows.
$placeholders = ['Rahul Sharma', 'Priya Patel', 'Amit Verma', 'Candidate Alpha', 'Candidate Beta', 'Candidate Gamma', 'Narendra Modi', 'Rahul Gandhi', 'Arvind Kejriwal'];
$ph = $pdo->prepare("DELETE FROM candidates WHERE constituency = '' AND name = ?");
foreach ($placeholders as $p) {
    $ph->execute([$p]);
}

$dup_check = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE name = ? AND constituency = ?");
$insert_c  = $pdo->prepare("INSERT INTO candidates (name, party, photo, votes_count, constituency) VALUES (?, ?, 'default.png', 0, ?)");

$cand_added = 0;
foreach ($real_candidates as [$constituency, $list]) {
    foreach ($list as [$name, $party]) {
        $dup_check->execute([$name, $constituency]);
        if ((int)$dup_check->fetchColumn() === 0) {
            $insert_c->execute([$name, $party, $constituency]);
            $cand_added++;
        }
    }
}
echo "✓ Seeded {$cand_added} real Lok Sabha 2024 candidate(s)\n\n";

// ---------- 3. Realistic DEMO voters (synthetic — see header note) ----------
$demo_password = password_hash('Voter@123', PASSWORD_DEFAULT);

// region => [first name pool, surname pool, district, state, EPIC prefix, mobile prefix]
$regions = [
    'Varanasi (PC-77)' => [
        ['Ravi','Amit','Sunita','Pooja','Vikash','Anjali','Rohit','Neha','Sanjay','Kavita','Manoj','Sushma','Alok','Rekha','Deepak','Shweta','Arjun','Priyanka'],
        ['Sharma','Verma','Gupta','Yadav','Singh','Pandey','Mishra','Tiwari','Srivastava','Jaiswal','Patel','Maurya','Chaurasia','Bind','Kushwaha'],
        'Varanasi, Uttar Pradesh', 'Uttar Pradesh', 'UPV', '9',
    ],
    'Gandhinagar (PC-06)' => [
        ['Rajesh','Nilesh','Hetal','Kinjal','Mehul','Darshana','Jayesh','Bhavna','Chirag','Rina','Kiran','Mital','Suresh','Usha','Paresh','Dimple'],
        ['Patel','Shah','Desai','Joshi','Trivedi','Dave','Mehta','Panchal','Prajapati','Solanki','Rathod','Chaudhary','Thakor','Vaghela','Raval'],
        'Gandhinagar, Gujarat', 'Gujarat', 'GJH', '7',
    ],
    'Rae Bareli (PC-36)' => [
        ['Ram','Sita','Ganesh','Shakuntala','Ramesh','Savitri','Dinesh','Mamta','Ashok','Santosh','Pawan','Rekha','Shyam','Lalita','Vijay','Guddu','Bablu','Anita'],
        ['Prasad','Verma','Yadav','Singh','Gupta','Pandey','Tiwari','Kushwaha','Pal','Patel','Chaudhary','Kori','Pasi','Gautam','Shukla'],
        'Rae Bareli, Uttar Pradesh', 'Uttar Pradesh', 'UPR', '8',
    ],
    'Hyderabad (PC-09)' => [
        ['Mohammed','Abdul','Rahima','Fatima','Syed','Ayesha','Imran','Salma','Naveen','Padma','Srinivas','Kavitha','Ravi','Shakeela','Arif','Sameera','Venkat','Lakshmi'],
        ['Ahmed','Khan','Hussain','Begum','Ali','Syed','Rao','Reddy','Naik','Kumari','Prasad','Sultana','Baig','Qureshi','Ansari','Sharma','Goud','Raju'],
        'Hyderabad, Telangana', 'Telangana', 'TSH', '6',
    ],
];

function realistic_mobile($prefix)
{
    // Indian mobile numbers start 6-9; keep unique by varying the last digits
    $rest = (string)random_int(10000000, 99999999);
    return $prefix . $rest;
}

$check_voter = $pdo->prepare("SELECT COUNT(*) FROM voters WHERE email = ? OR voter_id_number = ?");
$insert_v    = $pdo->prepare("INSERT INTO voters (fullname, email, voter_id_number, mobile, address, password, photo, document_proof, status, has_voted) VALUES (?, ?, ?, ?, ?, ?, 'default.png', '', ?, 0)");

$voter_added = 0;
$seed_index  = 1; // keeps generated ids unique & deterministic enough
foreach ($regions as $constituency => [$firsts, $lasts, $place, $state, $epic_pfx, $mob_pfx]) {
    for ($i = 0; $i < 6; $i++) {
        $first = $firsts[array_rand($firsts)];
        $last  = $lasts[array_rand($lasts)];
        $full  = $first . ' ' . $last;

        $email = strtolower($first . '.' . $last . $seed_index) . '@gmail.com';
        $epic  = $epic_pfx . str_pad((string)$seed_index, 7, '0', STR_PAD_LEFT);
        $addr  = "House No. " . random_int(1, 999) . ", Ward " . random_int(1, 20) . ", {$place} — {$constituency}";

        $check_voter->execute([$email, $epic]);
        if ((int)$check_voter->fetchColumn() > 0) {
            $seed_index++;
            continue;
        }

        // A couple of pending / rejected citizens per region for admin realism
        $r = $i % 6;
        $status = ($r === 4) ? 'pending' : (($r === 5) ? 'rejected' : 'approved');

        $mobile = realistic_mobile($mob_pfx);
        $insert_v->execute([$full, $email, $epic, $mobile, $addr, $demo_password, $status]);
        $voter_added++;
        $seed_index++;
    }
}
echo "✓ Seeded {$voter_added} realistic demo voter(s) — password for all: Voter@123\n\n";

// ---------- 4. Summary ----------
$pc = $pdo->query("SELECT constituency, COUNT(*) c FROM candidates GROUP BY constituency")->fetchAll(PDO::FETCH_ASSOC);
echo "Registered candidate ballots by constituency:\n";
foreach ($pc as $row) {
    echo "  - " . htmlspecialchars($row['constituency'] ?: '(unassigned / visible everywhere)') . ": " . (int)$row['c'] . " candidate(s)\n";
}
$vs = $pdo->query("SELECT status, COUNT(*) c FROM voters GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
echo "\nVoter records by status:\n";
foreach ($vs as $row) {
    echo "  - " . htmlspecialchars($row['status']) . ": " . (int)$row['c'] . "\n";
}
echo "\nDone. Pick a region on the portal and its real ballot will appear on the voter dashboard.\n";
