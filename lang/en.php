<?php
/**
 * lang/en.php — English source catalogue.
 *
 * This file is the source of truth for the booth kiosk journey. Every other
 * catalogue is layered on top of it: a key missing from lang/hi.php falls back
 * to the text here, and a key missing from here renders as the key itself.
 *
 * Keys beginning with "_" are metadata read by includes/i18n.php.
 */

return [

    /* Metadata ---------------------------------------------------------- */
    '_name'             => 'English',
    '_native'           => 'English',
    '_language'         => 'Language',

    /* Booth kiosk — unlock screen --------------------------------------- */
    'kiosk.brand'                => '🖐️ Booth Biometric Kiosk',
    'kiosk.login_title'          => 'Booth Kiosk — Unlock',
    'kiosk.login_heading'        => 'Unlock This Booth',
    'kiosk.login_intro'          => "Enter this polling station's booth code and PIN to enroll or verify fingerprints on this phone.",
    'kiosk.login_locked'         => 'This kiosk was locked after inactivity. Please unlock again.',
    'kiosk.booth_code'           => 'Booth code',
    'kiosk.booth_code_ph'        => 'e.g. BOOTH-001',
    'kiosk.booth_pin'            => 'Booth PIN',
    'kiosk.booth_pin_ph'         => '••••••',
    'kiosk.unlock_btn'           => 'Unlock Kiosk',
    'kiosk.staff_link'           => 'Election staff → Admin console',

    /* Booth kiosk — home ------------------------------------------------- */
    'kiosk.booth_kiosk_prefix'   => 'Booth Kiosk —',
    'kiosk.lock'                 => 'Lock',
    'kiosk.find_citizen'         => 'Find the citizen',
    'kiosk.search'               => 'Search',
    'kiosk.search_ph'            => 'Name, email, or EPIC…',
    'kiosk.search_aria'          => 'Search citizens by name, email, or EPIC number',
    'kiosk.enroll'               => 'Enroll',
    'kiosk.verify'               => 'Verify',
    'kiosk.walkin_note'          => 'Walk-in citizens must be onboarded by election staff in the admin console first.',

    /* Booth kiosk — verification state ---------------------------------- */
    'kiosk.epic'                 => 'EPIC:',
    'kiosk.constituency'         => 'Constituency:',
    'kiosk.fp_verified'          => 'Fingerprint verified',
    'kiosk.fp_on_file'           => '· fingerprint(s) on file',
    'kiosk.no_fp_title'          => 'No fingerprint enrolled yet — use Enroll first',
    'kiosk.no_fp_use'            => 'This citizen has no fingerprint enrolled yet — use',
    'kiosk.no_ballot_here'       => "The citizen's identity was verified, but no ballot can be issued here.",
    'kiosk.face_required'        => 'One more step — the face check is required before the ballot opens.',
    'kiosk.step1_start_face'     => '🙂 Step 1 — Start Face Check',
    'kiosk.face_passed'          => '✅ Face check passed.',
    'kiosk.step1_label'          => 'Step 1:',
    'kiosk.step1_face'           => 'face check (blink twice).',
    'kiosk.step2_label'          => 'Step 2:',
    'kiosk.step2_scan'           => 'scan the fingerprint to finish.',
    'kiosk.step2_fp'             => 'fingerprint. Both are required.',
    'kiosk.open_ballot'          => '🗳️ Open Ballot for',
    'kiosk.hand_device'          => 'Hand the device to the citizen, let them choose, then confirm.',
    'kiosk.vote_recorded'        => '— vote recorded',
    'kiosk.hand_next'            => '· Hand the device to the next citizen.',
    'kiosk.next_citizen'         => 'Next citizen',
    'kiosk.cancel_other'         => 'Cancel — choose a different citizen',

    /* Booth kiosk — face check ------------------------------------------ */
    'kiosk.face_title'           => 'Face Check —',
    'kiosk.face_brand'           => '🙂 Face Check',
    'kiosk.ref_on_file'          => 'Reference photo on file',
    'kiosk.ref_missing'          => 'No usable reference photo on file yet.',
    'kiosk.preparing'            => 'Preparing…',
    'kiosk.loading_models'       => 'Loading facial recognition models…',
    'kiosk.start_face'           => 'Start Face Check',
    'kiosk.capture_ref'          => 'Capture Reference Photo',
    'kiosk.ref_ready'            => 'Reference ready',
    'kiosk.blink_twice'          => 'Blink twice',
    'kiosk.server_match'         => 'Server match',
    'kiosk.fp_still_required'    => 'Fingerprint is still required after this. Both are checked for the same citizen.',
    'kiosk.ref_alt'              => 'Reference photo',

    /* Booth kiosk — ballot ---------------------------------------------- */
    'kiosk.ballot_title'         => 'Ballot —',
    'kiosk.ballot_brand'         => '🗳️ Ballot',
    'kiosk.cancel'               => 'Cancel',
    'kiosk.constituency_label'   => 'Parliamentary Constituency',
    'kiosk.no_candidates'        => 'No candidates are configured for the constituency',
    'kiosk.no_candidates_hint'   => '. Ask election staff to seed candidates for this constituency.',
    'kiosk.no_ballot_available'  => 'No ballot available at this booth.',
    'kiosk.confirm_vote'         => '✅ Confirm & Cast Vote',
    'kiosk.secrecy_note'         => 'Your choice is secret. Records show only that you voted, never who for.',
    'kiosk.back_to_search'       => 'Back to search',

    /* Booth kiosk — vote result ----------------------------------------- */
    'kiosk.vote_not_recorded'    => 'Vote not recorded',
];
