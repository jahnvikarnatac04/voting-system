<?php
/**
 * lang/hi.php — Hindi catalogue.
 *
 * Covers the booth kiosk journey. Keys absent here fall back to lang/en.php,
 * so this file can be extended a key at a time without ever breaking a page.
 */

return [

    /* Metadata ---------------------------------------------------------- */
    '_name'             => 'Hindi',
    '_native'           => 'हिन्दी',
    '_language'         => 'भाषा',

    /* Booth kiosk — unlock screen --------------------------------------- */
    'kiosk.brand'                => '🖐️ बूथ बायोमेट्रिक कियोस्क',
    'kiosk.login_title'          => 'बूथ कियोस्क — अनलॉक',
    'kiosk.login_heading'        => 'इस बूथ को अनलॉक करें',
    'kiosk.login_intro'          => 'इस मतदान केंद्र का बूथ कोड और पिन दर्ज करें, ताकि इस फ़ोन पर फ़िंगरप्रिंट पंजीकृत या सत्यापित किया जा सके।',
    'kiosk.login_locked'         => 'निष्क्रियता के कारण यह कियोस्क लॉक हो गया था। कृपया दोबारा अनलॉक करें।',
    'kiosk.booth_code'           => 'बूथ कोड',
    'kiosk.booth_code_ph'        => 'जैसे BOOTH-001',
    'kiosk.booth_pin'            => 'बूथ पिन',
    'kiosk.booth_pin_ph'         => '••••••',
    'kiosk.unlock_btn'           => 'कियोस्क अनलॉक करें',
    'kiosk.staff_link'           => 'निर्वाचन कर्मचारी → एडमिन कंसोल',

    /* Booth kiosk — home ------------------------------------------------- */
    'kiosk.booth_kiosk_prefix'   => 'बूथ कियोस्क —',
    'kiosk.lock'                 => 'लॉक करें',
    'kiosk.find_citizen'         => 'मतदाता खोजें',
    'kiosk.search'               => 'खोजें',
    'kiosk.search_ph'            => 'नाम, ईमेल या EPIC…',
    'kiosk.search_aria'          => 'नाम, ईमेल या EPIC संख्या से मतदाता खोजें',
    'kiosk.enroll'               => 'पंजीकरण',
    'kiosk.verify'               => 'सत्यापन',
    'kiosk.walkin_note'          => 'बिना पंजीकरण वाले नागरिकों को पहले एडमिन कंसोल में निर्वाचन कर्मचारी द्वारा जोड़ा जाना चाहिए।',

    /* Booth kiosk — verification state ---------------------------------- */
    'kiosk.epic'                 => 'EPIC:',
    'kiosk.constituency'         => 'निर्वाचन क्षेत्र:',
    'kiosk.fp_verified'          => 'फ़िंगरप्रिंट सत्यापित',
    'kiosk.fp_on_file'           => '· फ़िंगरप्रिंट दर्ज',
    'kiosk.no_fp_title'          => 'अभी कोई फ़िंगरप्रिंट पंजीकृत नहीं है — पहले पंजीकरण करें',
    'kiosk.no_fp_use'            => 'इस नागरिक का अभी कोई फ़िंगरप्रिंट पंजीकृत नहीं है —',
    'kiosk.no_ballot_here'       => 'नागरिक की पहचान सत्यापित हो गई, परंतु यहाँ मतपत्र जारी नहीं किया जा सकता।',
    'kiosk.face_required'        => 'एक और चरण शेष — मतपत्र खुलने से पहले फ़ेस जाँच आवश्यक है।',
    'kiosk.step1_start_face'     => '🙂 चरण 1 — फ़ेस जाँच शुरू करें',
    'kiosk.face_passed'          => '✅ फ़ेस जाँच उत्तीर्ण।',
    'kiosk.step1_label'          => 'चरण 1:',
    'kiosk.step1_face'           => 'फ़ेस जाँच (दो बार पलक झपकाएँ)।',
    'kiosk.step2_label'          => 'चरण 2:',
    'kiosk.step2_scan'           => 'पूरा करने के लिए फ़िंगरप्रिंट स्कैन करें।',
    'kiosk.step2_fp'             => 'फ़िंगरप्रिंट। दोनों आवश्यक हैं।',
    'kiosk.open_ballot'          => '🗳️ मतपत्र खोलें —',
    'kiosk.hand_device'          => 'उपकरण नागरिक को दें, उन्हें चुनने दें, फिर पुष्टि करें।',
    'kiosk.vote_recorded'        => '— मत दर्ज',
    'kiosk.hand_next'            => '· उपकरण अगले नागरिक को दें।',
    'kiosk.next_citizen'         => 'अगला नागरिक',
    'kiosk.cancel_other'         => 'रद्द करें — दूसरा नागरिक चुनें',

    /* Booth kiosk — face check ------------------------------------------ */
    'kiosk.face_title'           => 'फ़ेस जाँच —',
    'kiosk.face_brand'           => '🙂 फ़ेस जाँच',
    'kiosk.ref_on_file'          => 'संदर्भ फ़ोटो उपलब्ध',
    'kiosk.ref_missing'          => 'अभी कोई उपयोगी संदर्भ फ़ोटो उपलब्ध नहीं है।',
    'kiosk.preparing'            => 'तैयार हो रहा है…',
    'kiosk.loading_models'       => 'चेहरा पहचान मॉडल लोड हो रहे हैं…',
    'kiosk.start_face'           => 'फ़ेस जाँच शुरू करें',
    'kiosk.capture_ref'          => 'संदर्भ फ़ोटो लें',
    'kiosk.ref_ready'            => 'संदर्भ तैयार',
    'kiosk.blink_twice'          => 'दो बार पलक झपकाएँ',
    'kiosk.server_match'         => 'सर्वर मिलान',
    'kiosk.fp_still_required'    => 'इसके बाद भी फ़िंगरप्रिंट आवश्यक है। एक ही नागरिक के लिए दोनों की जाँच होती है।',
    'kiosk.ref_alt'              => 'संदर्भ फ़ोटो',

    /* Booth kiosk — ballot ---------------------------------------------- */
    'kiosk.ballot_title'         => 'मतपत्र —',
    'kiosk.ballot_brand'         => '🗳️ मतपत्र',
    'kiosk.cancel'               => 'रद्द करें',
    'kiosk.constituency_label'   => 'संसदीय निर्वाचन क्षेत्र',
    'kiosk.no_candidates'        => 'इस निर्वाचन क्षेत्र के लिए कोई उम्मीदवार निर्धारित नहीं है',
    'kiosk.no_candidates_hint'   => '। इस निर्वाचन क्षेत्र के लिए उम्मीदवार जोड़ने हेतु निर्वाचन कर्मचारी से कहें।',
    'kiosk.no_ballot_available'  => 'इस बूथ पर कोई मतपत्र उपलब्ध नहीं है।',
    'kiosk.confirm_vote'         => '✅ पुष्टि करें और मत डालें',
    'kiosk.secrecy_note'         => 'आपका चुनाव गोपनीय है। रिकॉर्ड में केवल यह दर्ज होता है कि आपने मत दिया, यह नहीं कि किसे।',
    'kiosk.back_to_search'       => 'खोज पर वापस जाएँ',

    /* Booth kiosk — vote result ----------------------------------------- */
    'kiosk.vote_not_recorded'    => 'मत दर्ज नहीं हुआ',
];
