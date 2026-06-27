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
const SEO_REPORT_FILE = 'seo-pages.txt';
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

function normalize_crawled_url(string $url, string $siteUrl): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '' || str_starts_with($url, '#') || preg_match('~^(mailto|tel|javascript):~i', $url)) {
        return '';
    }

    $base = parse_url($siteUrl);
    if (str_starts_with($url, '//')) {
        $url = ($base['scheme'] ?? 'https') . ':' . $url;
    } elseif (str_starts_with($url, '/')) {
        $url = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '') . $url;
    } elseif (!preg_match('~^https?://~i', $url)) {
        $basePath = rtrim(dirname((string)($base['path'] ?? '/')), '/');
        $url = ($base['scheme'] ?? 'https') . '://' . ($base['host'] ?? '') . ($basePath === '' ? '' : $basePath) . '/' . $url;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $path = $parts['path'] ?? '/';
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = rawurlencode(rawurldecode($segment));
    }
    $normalized = strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . '/' . implode('/', $segments);
    if (str_ends_with($path, '/') && !str_ends_with($normalized, '/')) {
        $normalized .= '/';
    }
    if (!empty($parts['query'])) {
        $normalized .= '?' . $parts['query'];
    }
    return rtrim($normalized, '?');
}

function fetch_page(string $url): array
{
    $headers = [];
    $body = '';
    $status = 0;
    $contentType = '';
    $lastmod = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'SitemapSetupBot/1.0',
            CURLOPT_HEADERFUNCTION => function ($curl, string $header) use (&$headers): int {
                $headers[] = trim($header);
                return strlen($header);
            },
        ]);
        $body = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
    } else {
        $context = stream_context_create(['http' => ['timeout' => 12, 'header' => "User-Agent: SitemapSetupBot/1.0\r\n"]]);
        $body = (string)@file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        foreach ($headers as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~i', $header, $match)) {
                $status = (int)$match[1];
            }
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, 13));
            }
        }
    }

    foreach ($headers as $header) {
        if (stripos($header, 'Last-Modified:') === 0) {
            $timestamp = strtotime(trim(substr($header, 14)));
            if ($timestamp !== false) {
                $lastmod = date('c', $timestamp);
            }
        }
    }

    return [
        'ok' => $status >= 200 && $status < 300 && stripos($contentType, 'text/html') !== false,
        'body' => $body,
        'lastmod' => $lastmod,
    ];
}

function html_field(string $html, string $pattern): string
{
    if (!preg_match($pattern, $html, $match)) {
        return '';
    }
    return trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function tag_attribute(string $tag, string $attribute): string
{
    if (!preg_match('~\s' . preg_quote($attribute, '~') . '\s*=\s*(["\'])(.*?)\1~i', $tag, $match)) {
        return '';
    }
    return trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function link_href_by_rel(string $html, string $rel): string
{
    if (!preg_match_all('~<link\s+[^>]*>~i', $html, $matches)) {
        return '';
    }
    foreach ($matches[0] as $tag) {
        if (strcasecmp(tag_attribute($tag, 'rel'), $rel) === 0) {
            return tag_attribute($tag, 'href');
        }
    }
    return '';
}

function meta_content_by_name(string $html, string $name): string
{
    if (!preg_match_all('~<meta\s+[^>]*>~i', $html, $matches)) {
        return '';
    }
    foreach ($matches[0] as $tag) {
        if (strcasecmp(tag_attribute($tag, 'name'), $name) === 0) {
            return tag_attribute($tag, 'content');
        }
    }
    return '';
}

function extract_page_data(string $html, string $url, string $siteUrl): array
{
    $canonical = link_href_by_rel($html, 'canonical');
    $canonical = $canonical === '' ? $url : normalize_crawled_url($canonical, $url);
    if ($canonical === '' || !same_site_url($canonical, $siteUrl)) {
        $canonical = $url;
    }

    preg_match_all('~<a\s+[^>]*href=["\']([^"\']+)["\']~i', $html, $matches);
    $links = [];
    foreach ($matches[1] ?? [] as $href) {
        $link = normalize_crawled_url($href, $url);
        if ($link !== '' && same_site_url($link, $siteUrl)) {
            $links[] = $link;
        }
    }

    return [
        'canonical' => $canonical,
        'title' => html_field($html, '~<title[^>]*>(.*?)</title>~is'),
        'description' => meta_content_by_name($html, 'description'),
        'keywords' => meta_content_by_name($html, 'keywords'),
        'links' => array_values(array_unique($links)),
    ];
}

function collect_urls(string $siteUrl, array $excludes): array
{
    $startUrl = normalize_crawled_url($siteUrl . '/', $siteUrl);
    $queue = [$startUrl];
    $visited = [];
    $pages = [];

    while ($queue !== [] && count($visited) < MAX_SITEMAP_URLS) {
        $url = array_shift($queue);
        if ($url === '' || isset($visited[$url])) {
            continue;
        }
        $path = trim((string)(parse_url($url, PHP_URL_PATH) ?? ''), '/');
        if (is_excluded($path, $excludes)) {
            continue;
        }
        $visited[$url] = true;
        $fetched = fetch_page($url);
        if (!$fetched['ok']) {
            continue;
        }

        $data = extract_page_data($fetched['body'], $url, $siteUrl);
        $loc = $data['canonical'];
        $pages[$loc] = [
            'loc' => $loc,
            'lastmod' => $fetched['lastmod'],
            'title' => $data['title'],
            'description' => $data['description'],
            'keywords' => $data['keywords'],
        ];

        foreach ($data['links'] as $link) {
            $linkPath = trim((string)(parse_url($link, PHP_URL_PATH) ?? ''), '/');
            if (!isset($visited[$link]) && !is_excluded($linkPath, $excludes)) {
                $queue[] = $link;
            }
        }
        $queue = array_values(array_unique($queue));
    }

    ksort($pages);
    return array_values($pages);
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
        if (!empty($item['lastmod'])) {
            $lastmod = $xml->createElement('lastmod');
            $lastmod->appendChild($xml->createTextNode($item['lastmod']));
            $url->appendChild($lastmod);
        }
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

function clean_report_value(string $value): string
{
    return str_replace(["\t", "\r", "\n"], ' ', $value);
}

function build_seo_report(array $pages): string
{
    $lines = ["URL\tTitle\tDescription\tKeywords"];
    foreach ($pages as $page) {
        $lines[] = clean_report_value((string)$page['loc']) . "\t"
            . clean_report_value((string)($page['title'] ?? '')) . "\t"
            . clean_report_value((string)($page['description'] ?? '')) . "\t"
            . clean_report_value((string)($page['keywords'] ?? ''));
    }
    return implode("\n", $lines) . "\n";
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

    $urls = collect_urls($siteUrl, $config['excludes'] ?? []);
    foreach (glob($rootDir . DIRECTORY_SEPARATOR . SITEMAP_PART_PREFIX . '*.xml') ?: [] as $oldPart) {
        if (is_file($oldPart) && is_writable($oldPart)) {
            unlink($oldPart);
        }
    }
    $targets = build_sitemap_files($siteUrl, $urls);
    $targets[ROOT_FILE] = build_root_txt($siteUrl);
    $targets[SEO_REPORT_FILE] = build_seo_report($urls);

    $messages = [];
    $errors = [];
    foreach ($targets as $file => $contents) {
        $path = $rootDir . DIRECTORY_SEPARATOR . $file;
        if ((file_exists($path) && !is_writable($path)) || (!file_exists($path) && !is_writable($rootDir))) {
            $errors[] = $file . ' cannot be written. Please give PHP write permission for this generated file or the /www folder.';
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
    $seoPath = $rootDir . DIRECTORY_SEPARATOR . SEO_REPORT_FILE;
    $needsRefresh = !is_file($sitemapPath) || !is_file($rootPath) || !is_file($seoPath) || filemtime($sitemapPath) < (time() - REFRESH_SECONDS) || filemtime($rootPath) < (time() - REFRESH_SECONDS) || filemtime($seoPath) < (time() - REFRESH_SECONDS);
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
    <p class="help">Put this file in your website <code>/www</code> folder. This page crawls public website pages and creates or updates <code>sitemap.xml</code>, <code>root.txt</code>, and <code>seo-pages.txt</code>. Reopen this page anytime; it auto-updates files when they are older than 12 hours.</p>
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

        <label for="excludes">Exclude website URL paths</label>
        <textarea id="excludes" name="excludes" placeholder="admin&#10;private&#10;cache&#10;*?preview=*"><?= h($excludeText) ?></textarea>
        <p class="help">One URL path or pattern per line. Examples: <code>admin</code>, <code>private/page</code>, <code>cache</code>, <code>*?preview=*</code>.</p>

        <button type="submit">Save settings and update now</button>
    </form>
    <?php endif; ?>
</main>
</body>
</html>
