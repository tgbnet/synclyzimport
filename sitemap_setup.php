<?php
/**
 * Standalone sitemap/root.txt generator.
 *
 * Drop this file into a website /www (document root) folder, open it once in a
 * browser, enter the site URL and optional excludes, then save. On each later
 * visit it refreshes sitemap.xml and root.txt when they are older than 12 hours.
 */

declare(strict_types=1);

const CONFIG_FILE = '.sitemap_setup_config.php';
const SITEMAP_FILE = 'sitemap.xml';
const ROOT_FILE = 'root.txt';
const REFRESH_SECONDS = 43200; // 12 hours
const MAX_SITEMAP_URLS = 50000; // Google sitemap limit
const MAX_SITEMAP_BYTES = 52428800; // 50MB uncompressed Google sitemap limit
const SITEMAP_PART_PREFIX = 'sitemap-part-';

$rootDir = realpath(__DIR__) ?: __DIR__;
$configPath = $rootDir . DIRECTORY_SEPARATOR . CONFIG_FILE;
$messages = [];
$errors = [];
session_start();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_site_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return rtrim($url, '/');
}

function default_site_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return $host === '' ? '' : (($https ? 'https://' : 'http://') . $host);
}

function parse_excludes(string $input): array
{
    $lines = preg_split('/\R/', $input) ?: [];
    $excludes = [];
    foreach ($lines as $line) {
        $line = trim(str_replace('\\', '/', $line));
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $excludes[] = trim($line, '/');
    }
    return array_values(array_unique($excludes));
}

function load_config(string $configPath): array
{
    if (!is_file($configPath)) {
        return [];
    }
    $config = require $configPath;
    return is_array($config) ? $config : [];
}

function save_config(string $configPath, array $config): bool
{
    $php = "<?php\nreturn " . var_export($config, true) . ";\n";
    return file_put_contents($configPath, $php, LOCK_EX) !== false;
}

function is_excluded(string $relativePath, array $excludes): bool
{
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');
    foreach ($excludes as $exclude) {
        $exclude = trim(str_replace('\\', '/', (string)$exclude), '/');
        if ($exclude === '') {
            continue;
        }
        if ($relativePath === $exclude || str_starts_with($relativePath . '/', $exclude . '/')) {
            return true;
        }
        if (fnmatch($exclude, $relativePath, FNM_PATHNAME | FNM_CASEFOLD)) {
            return true;
        }
    }
    return false;
}

function same_site_url(string $url, string $siteUrl): bool
{
    $urlHost = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    $siteHost = strtolower((string)(parse_url($siteUrl, PHP_URL_HOST) ?? ''));
    return $urlHost !== '' && $siteHost !== '' && $urlHost === $siteHost;
}

function canonical_url_for_file(string $filePath, string $fallbackUrl, string $siteUrl): string
{
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['html', 'htm', 'php'], true) || !is_readable($filePath)) {
        return $fallbackUrl;
    }

    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        return $fallbackUrl;
    }
    $html = fread($handle, 262144) ?: '';
    fclose($handle);

    if (!preg_match('~<link\s+[^>]*rel=["\']?canonical["\']?[^>]*>~i', $html, $tag)) {
        return $fallbackUrl;
    }
    if (!preg_match('~href=["\']([^"\']+)["\']~i', $tag[0], $href)) {
        return $fallbackUrl;
    }

    $canonical = html_entity_decode(trim($href[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($canonical === '' || !preg_match('~^https?://~i', $canonical) || !same_site_url($canonical, $siteUrl)) {
        return $fallbackUrl;
    }

    return $canonical;
}

function collect_urls(string $rootDir, string $siteUrl, array $excludes): array
{
    $allowedExtensions = ['html', 'htm', 'php'];
    $internalExcludes = [CONFIG_FILE, basename(__FILE__), SITEMAP_FILE, ROOT_FILE, '.git'];
    $excludes = array_values(array_unique(array_merge($excludes, $internalExcludes)));
    $urls = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
            function (SplFileInfo $file, string $key, RecursiveDirectoryIterator $iterator) use ($rootDir, $excludes): bool {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($rootDir))), '/');
                if ($file->isDir() && is_excluded($relative, $excludes)) {
                    return false;
                }
                return true;
            }
        )
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($rootDir))), '/');
        if (is_excluded($relative, $excludes)) {
            continue;
        }
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        // Do not publish directory-only URLs for application folders. Only the
        // document-root index becomes the homepage; nested indexes stay as files.
        $urlPath = preg_match('~^index\.(php|html?)$~i', $relative) ? '' : trim($relative, '/');
        $location = $siteUrl . ($urlPath === '' ? '/' : '/' . implode('/', array_map('rawurlencode', explode('/', $urlPath))));
        $location = canonical_url_for_file($file->getPathname(), $location, $siteUrl);
        $urls[$location] = [
            'loc' => $location,
            'lastmod' => date('c', $file->getMTime()),
        ];
    }

    ksort($urls);
    if ($urls === []) {
        $urls[$siteUrl . '/'] = ['loc' => $siteUrl . '/', 'lastmod' => date('c')];
    }
    return array_values($urls);
}

function build_urlset_xml(array $urls): string
{
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $urlset = $xml->createElement('urlset');
    $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->appendChild($urlset);

    foreach ($urls as $item) {
        $url = $xml->createElement('url');
        $loc = $xml->createElement('loc');
        $loc->appendChild($xml->createTextNode($item['loc']));
        $url->appendChild($loc);
        $lastmod = $xml->createElement('lastmod');
        $lastmod->appendChild($xml->createTextNode($item['lastmod']));
        $url->appendChild($lastmod);
        $urlset->appendChild($url);
    }

    return (string)$xml->saveXML();
}

function build_sitemap_index_xml(array $sitemaps): string
{
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $index = $xml->createElement('sitemapindex');
    $index->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    $xml->appendChild($index);

    foreach ($sitemaps as $item) {
        $sitemap = $xml->createElement('sitemap');
        $loc = $xml->createElement('loc');
        $loc->appendChild($xml->createTextNode($item['loc']));
        $sitemap->appendChild($loc);
        $lastmod = $xml->createElement('lastmod');
        $lastmod->appendChild($xml->createTextNode($item['lastmod']));
        $sitemap->appendChild($lastmod);
        $index->appendChild($sitemap);
    }

    return (string)$xml->saveXML();
}

function estimated_sitemap_entry_bytes(array $url): int
{
    $loc = htmlspecialchars((string)$url['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
    $lastmod = htmlspecialchars((string)$url['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
    return strlen("  <url>
    <loc>{$loc}</loc>
    <lastmod>{$lastmod}</lastmod>
  </url>
");
}

function sitemap_chunks(array $urls): array
{
    $chunks = [];
    $chunk = [];
    $chunkBytes = 120; // XML declaration plus urlset wrapper.

    foreach ($urls as $url) {
        $entryBytes = estimated_sitemap_entry_bytes($url);
        if ($chunk !== [] && (count($chunk) >= MAX_SITEMAP_URLS || ($chunkBytes + $entryBytes) > MAX_SITEMAP_BYTES)) {
            $chunks[] = $chunk;
            $chunk = [];
            $chunkBytes = 120;
        }
        $chunk[] = $url;
        $chunkBytes += $entryBytes;
    }

    if ($chunk !== []) {
        $chunks[] = $chunk;
    }
    return $chunks;
}

function build_sitemap_files(string $siteUrl, array $urls): array
{
    $chunks = sitemap_chunks($urls);
    if (count($chunks) <= 1) {
        return [SITEMAP_FILE => build_urlset_xml($chunks[0] ?? [])];
    }

    $files = [];
    $indexItems = [];
    foreach ($chunks as $index => $chunk) {
        $filename = SITEMAP_PART_PREFIX . ($index + 1) . '.xml';
        $files[$filename] = build_urlset_xml($chunk);
        $indexItems[] = [
            'loc' => $siteUrl . '/' . $filename,
            'lastmod' => date('c'),
        ];
    }
    return [SITEMAP_FILE => build_sitemap_index_xml($indexItems)] + $files;
}

function build_root_txt(string $siteUrl): string
{
    $aiAgents = [
        'GPTBot',
        'ChatGPT-User',
        'OAI-SearchBot',
        'ClaudeBot',
        'Claude-User',
        'PerplexityBot',
        'Google-Extended',
        'CCBot',
        'anthropic-ai',
        'Applebot-Extended',
    ];

    $lines = [
        'User-agent: *',
        'Allow: /',
        '',
        '# Explicitly allow common AI agents.',
    ];
    foreach ($aiAgents as $agent) {
        $lines[] = 'User-agent: ' . $agent;
        $lines[] = 'Allow: /';
        $lines[] = '';
    }
    $lines[] = 'Sitemap: ' . $siteUrl . '/' . SITEMAP_FILE;

    return implode("\n", $lines) . "\n";
}

function refresh_files(string $rootDir, array $config): array
{
    $siteUrl = normalize_site_url((string)($config['site_url'] ?? ''));
    if ($siteUrl === '') {
        return ['ok' => false, 'messages' => [], 'errors' => ['Please set the website domain first.']];
    }

    $urls = collect_urls($rootDir, $siteUrl, $config['excludes'] ?? []);
    foreach (glob($rootDir . DIRECTORY_SEPARATOR . SITEMAP_PART_PREFIX . '*.xml') ?: [] as $oldPart) {
        if (is_file($oldPart) && is_writable($oldPart)) {
            unlink($oldPart);
        }
    }
    $targets = build_sitemap_files($siteUrl, $urls);
    $targets[ROOT_FILE] = build_root_txt($siteUrl);

    $messages = [];
    $errors = [];
    foreach ($targets as $file => $contents) {
        $path = $rootDir . DIRECTORY_SEPARATOR . $file;
        if ((file_exists($path) && !is_writable($path)) || (!file_exists($path) && !is_writable($rootDir))) {
            $errors[] = $file . ' cannot be written. Please give PHP write permission for this file or the /www folder.';
            continue;
        }
        if (file_put_contents($path, $contents, LOCK_EX) === false) {
            $errors[] = $file . ' could not be updated.';
            continue;
        }
        $messages[] = $file . ' updated successfully.';
    }

    return ['ok' => $errors === [], 'messages' => $messages, 'errors' => $errors, 'count' => count($urls)];
}

$config = load_config($configPath);
if (!isset($config['site_url'])) {
    $config['site_url'] = default_site_url();
}
if (!isset($config['excludes']) || !is_array($config['excludes'])) {
    $config['excludes'] = ['admin', 'private', 'app', 'application', 'system', 'storage', 'vendor', 'node_modules', 'cache', 'tmp', 'logs', '*.bak'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_pin'])) {
    if (!empty($config['admin_pin_hash']) && password_verify((string)$_POST['login_pin'], (string)$config['admin_pin_hash'])) {
        $_SESSION['sitemap_setup_auth'] = true;
        $messages[] = 'Unlocked settings.';
    } else {
        $errors[] = 'Security PIN is not correct.';
    }
}

$isConfigured = is_file($configPath) && !empty($config['admin_pin_hash']);
$isAuthenticated = !$isConfigured || !empty($_SESSION['sitemap_setup_auth']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['site_url']) && $isAuthenticated) {
    $newPin = trim((string)($_POST['admin_pin'] ?? ''));
    if (!$isConfigured && strlen($newPin) < 6) {
        $errors[] = 'Please create a security PIN with at least 6 characters.';
    } elseif ($newPin !== '') {
        $config['admin_pin_hash'] = password_hash($newPin, PASSWORD_DEFAULT);
    }
    $config['site_url'] = normalize_site_url((string)($_POST['site_url'] ?? ''));
    $config['excludes'] = parse_excludes((string)($_POST['excludes'] ?? ''));
    $config['updated_at'] = date('c');
    if ($config['site_url'] === '') {
        $errors[] = 'Website domain is required.';
    } elseif ($errors !== []) {
        // Show the errors above and do not write a partial setup.
    } elseif (!save_config($configPath, $config)) {
        $errors[] = 'Configuration could not be saved. Please make the /www folder writable by PHP.';
    } else {
        $messages[] = 'Settings saved.';
        $result = refresh_files($rootDir, $config);
        $messages = array_merge($messages, $result['messages']);
        $errors = array_merge($errors, $result['errors']);
        if ($result['ok']) {
            $messages[] = 'Im fine. ' . (int)$result['count'] . ' page(s) added to sitemap.xml.';
        }
    }
} elseif (is_file($configPath)) {
    $sitemapPath = $rootDir . DIRECTORY_SEPARATOR . SITEMAP_FILE;
    $rootPath = $rootDir . DIRECTORY_SEPARATOR . ROOT_FILE;
    $needsRefresh = !is_file($sitemapPath) || !is_file($rootPath) || filemtime($sitemapPath) < (time() - REFRESH_SECONDS) || filemtime($rootPath) < (time() - REFRESH_SECONDS);
    if ($needsRefresh) {
        $result = refresh_files($rootDir, $config);
        $messages = array_merge($messages, $result['messages']);
        $errors = array_merge($errors, $result['errors']);
        if ($result['ok']) {
            $messages[] = 'Im fine. Auto update finished.';
        }
    } else {
        $messages[] = 'Im fine. Files are already up to date; automatic refresh runs every 12 hours.';
    }
}

$excludeText = implode("\n", $config['excludes']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Sitemap Setup</title>
    <style>
        body { background:#f6f7fb; color:#172033; font-family:Arial,sans-serif; margin:0; padding:32px; }
        main { background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(0,0,0,.08); max-width:760px; margin:auto; padding:28px; }
        h1 { margin-top:0; }
        label { display:block; font-weight:700; margin-top:18px; }
        input, textarea { border:1px solid #c9d0df; border-radius:8px; box-sizing:border-box; font:inherit; padding:12px; width:100%; }
        textarea { min-height:170px; }
        button { background:#1867f2; border:0; border-radius:8px; color:#fff; cursor:pointer; font-size:16px; font-weight:700; margin-top:18px; padding:12px 18px; }
        .ok, .err { border-radius:8px; margin:12px 0; padding:12px; }
        .ok { background:#eaf8ef; color:#14532d; }
        .err { background:#fdecec; color:#7f1d1d; }
        .help { color:#5b6475; font-size:14px; }
        code { background:#eef1f7; border-radius:4px; padding:2px 5px; }
    </style>
</head>
<body>
<main>
    <h1>Sitemap & root.txt setup</h1>
    <p class="help">Put this file in your website <code>/www</code> folder. This page creates or updates <code>sitemap.xml</code> and <code>root.txt</code>. Reopen this page anytime; it auto-updates files when they are older than 12 hours.</p>
    <p class="help"><strong>Security:</strong> after setup, settings are protected by your PIN. Keep this filename hard to guess or remove it if you do not need browser-based updates.</p>

    <?php foreach ($messages as $message): ?>
        <div class="ok"><?= h($message) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="err"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if (!$isAuthenticated): ?>
        <form method="post">
            <label for="login_pin">Security PIN</label>
            <input id="login_pin" name="login_pin" type="password" required>
            <button type="submit">Unlock settings</button>
        </form>
    <?php else: ?>
    <form method="post">
        <label for="site_url">Website domain</label>
        <input id="site_url" name="site_url" placeholder="https://example.com" value="<?= h((string)$config['site_url']) ?>" required>
        <p class="help">Use the public address visitors see, for example <code>https://example.com</code>.</p>

        <label for="admin_pin">Security PIN<?= $isConfigured ? ' (leave blank to keep current PIN)' : '' ?></label>
        <input id="admin_pin" name="admin_pin" type="password" <?= $isConfigured ? '' : 'required minlength="6"' ?>>
        <p class="help">Needed before anyone can change these settings later.</p>

        <label for="excludes">Exclude files or folders</label>
        <textarea id="excludes" name="excludes" placeholder="admin&#10;private&#10;cache&#10;*.bak"><?= h($excludeText) ?></textarea>
        <p class="help">One item per line. Examples: <code>admin</code>, <code>private/file.php</code>, <code>cache</code>, <code>*.bak</code>.</p>

        <button type="submit">Save settings and update now</button>
    </form>
    <?php endif; ?>
</main>
</body>
</html>
