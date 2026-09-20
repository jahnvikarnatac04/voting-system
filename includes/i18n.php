<?php
/**
 * includes/i18n.php
 * ---------------------------------------------------------------------------
 * A small, file-based translation layer for the citizen-facing screens.
 *
 * Design notes
 * ------------
 * · Catalogues live in `lang/<code>.php` and are plain PHP arrays. Dropping a
 *   new file in that directory is all it takes to add a language; the switcher
 *   discovers it automatically.
 * · Anything missing from a catalogue falls back to English, and then to the
 *   key itself. A partially translated page therefore degrades to English
 *   rather than rendering blank or throwing.
 * · The chosen language is remembered in the session AND in a cookie, so it
 *   survives both navigation and logging out.
 *
 * Usage
 * -----
 *   require_once __DIR__ . '/../includes/i18n.php';   // after session_start()
 *   echo t('kiosk.search_placeholder');
 *   <?= current_lang() ?>            // for <html lang="...">
 *   <?php render_lang_switcher(); ?> // the language strip
 * ---------------------------------------------------------------------------
 */

/** The fallback catalogue. Every other language is layered on top of it. */
const I18N_FALLBACK = 'en';

/** Cookie that remembers the choice across sessions. */
const I18N_COOKIE = 'voting_lang';

/**
 * Every catalogue in lang/, discovered from disk.
 *
 * @return array<string, array{name: string, native: string}>
 */
function i18n_available(): array
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }

    $available = [];
    foreach (glob(dirname(__DIR__) . '/lang/*.php') ?: [] as $file) {
        $code = basename($file, '.php');
        if (!preg_match('/^[a-z]{2}(-[A-Za-z]{2,4})?$/', $code)) {
            continue;
        }
        $meta = i18n_catalogue($code);
        $available[$code] = [
            'name'   => (string)($meta['_name'] ?? strtoupper($code)),
            'native' => (string)($meta['_native'] ?? $meta['_name'] ?? strtoupper($code)),
        ];
    }

    ksort($available);
    return $available;
}

/**
 * Load one catalogue (cached).
 *
 * @return array<string, string>
 */
function i18n_catalogue(string $code): array
{
    static $cache = [];

    if (isset($cache[$code])) {
        return $cache[$code];
    }

    $file = dirname(__DIR__) . '/lang/' . $code . '.php';
    if (!is_file($file)) {
        return $cache[$code] = [];
    }

    $data = require $file;
    if (!is_array($data)) {
        $data = [];
    }

    // Keep only scalar values so a malformed catalogue cannot inject markup.
    $clean = [];
    foreach ($data as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $clean[$key] = $value;
        }
    }
    return $cache[$code] = $clean;
}

/**
 * Resolve the language for this request: ?lang= wins, then the session, then
 * the cookie, then the fallback. An unknown code is ignored rather than
 * trusted, so a bad query string cannot select a missing catalogue.
 */
function i18n_resolve(): string
{
    $available = i18n_available();
    $valid = function (string $code) use ($available): bool {
        return isset($available[$code]);
    };

    $requested = isset($_GET['lang']) ? strtolower(trim((string)$_GET['lang'])) : '';
    $session   = isset($_SESSION[I18N_COOKIE]) ? (string)$_SESSION[I18N_COOKIE] : '';
    $cookie    = isset($_COOKIE[I18N_COOKIE]) ? strtolower(trim((string)$_COOKIE[I18N_COOKIE])) : '';

    $code = I18N_FALLBACK;
    if ($requested !== '' && $valid($requested)) {
        $code = $requested;
    } elseif ($session !== '' && $valid($session)) {
        $code = $session;
    } elseif ($cookie !== '' && $valid($cookie)) {
        $code = $cookie;
    }

    // Persist the choice whenever it differs from what we already hold, so a
    // ?lang= link and a cookie-only visit both end up remembered.
    if ($session !== $code) {
        $_SESSION[I18N_COOKIE] = $code;
    }
    if ($cookie !== $code && !headers_sent()) {
        setcookie(I18N_COOKIE, $code, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'httponly' => false,   // read back by the switcher; not sensitive
            'samesite' => 'Lax',
        ]);
    }

    return $code;
}

/** The active language code, e.g. "en" or "hi". */
function current_lang(): string
{
    return $GLOBALS['__i18n_lang'] ?? I18N_FALLBACK;
}

/** Is this language written right-to-left? (No current catalogue is, but the
 *  attribute is emitted correctly if one ever is.) */
function i18n_is_rtl(?string $code = null): bool
{
    $code = $code ?? current_lang();
    return in_array($code, ['ar', 'fa', 'he', 'ur'], true);
}

/**
 * Translate a key.
 *
 * `:name` placeholders in the catalogue are replaced from $vars.
 */
function t(string $key, array $vars = []): string
{
    $lang = current_lang();

    $text = i18n_catalogue($lang)[$key]
        ?? i18n_catalogue(I18N_FALLBACK)[$key]
        ?? $key;

    if ($vars !== []) {
        foreach ($vars as $name => $value) {
            $text = str_replace(':' . $name, (string)$value, $text);
        }
    }

    return $text;
}

/** Like t(), but escaped for direct output into HTML. */
function te(string $key, array $vars = []): string
{
    return htmlspecialchars(t($key, $vars), ENT_QUOTES, 'UTF-8');
}

/**
 * The current URL with ?lang=<code>, keeping every other query parameter so a
 * switch never drops the page's own state.
 */
function lang_url(string $code): string
{
    $path  = $_SERVER['SCRIPT_NAME'] ?? '/';
    $query = $_GET;
    $query['lang'] = $code;

    return htmlspecialchars($path . '?' . http_build_query($query), ENT_QUOTES, 'UTF-8');
}

/**
 * Render the language strip. Only shown when more than one catalogue exists,
 * so a single-language install is unchanged.
 */
function render_lang_switcher(): void
{
    $available = i18n_available();
    if (count($available) < 2) {
        return;
    }

    $current = current_lang();
    $label   = t('_language');

    echo '<nav class="lang-bar" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '    <span class="lang-bar-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ':</span>' . "\n";

    foreach ($available as $code => $info) {
        $active = ($code === $current);
        printf(
            '    <a class="lang-opt%s" href="%s" hreflang="%s" lang="%s"%s>%s</a>' . "\n",
            $active ? ' is-active' : '',
            lang_url($code),
            htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
            $active ? ' aria-current="true"' : '',
            htmlspecialchars($info['native'], ENT_QUOTES, 'UTF-8')
        );
    }

    echo '</nav>' . "\n";
}

/* -------------------------------------------------------------------------
   Boot. Requiring this file is enough; callers do not have to do anything.
   ------------------------------------------------------------------------- */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$GLOBALS['__i18n_lang'] = i18n_resolve();
