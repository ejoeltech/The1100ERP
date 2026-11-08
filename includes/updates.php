<?php
// Update discovery that works on zip-deployed copies (no .git directory).
// Compares APP_VERSION against the latest GitHub release, falling back to tags
// when a repository has no releases yet.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/version.php';

if (!function_exists('appNormalizeVersion')) {
    /** "v1.2.0-dev" -> "1.2.0-dev" */
    function appNormalizeVersion($v)
    {
        $v = trim((string)$v);
        if ($v === '') return '';
        return preg_replace('/^[vV]\s*/', '', $v);
    }
}

if (!function_exists('appVersionIsNewer')) {
    /** True when $candidate is a strictly newer version than $current. */
    function appVersionIsNewer($candidate, $current)
    {
        $c = appNormalizeVersion($candidate);
        $l = appNormalizeVersion($current);
        if ($c === '' || $l === '') return false;
        return version_compare($c, $l, '>');
    }
}

if (!function_exists('appGithubToken')) {
    /** Optional read-only GitHub token for private release repositories. */
    function appGithubToken()
    {
        foreach (['GITHUB_TOKEN', 'GH_TOKEN'] as $key) {
            $token = getenv($key);
            if ($token !== false && trim((string)$token) !== '') return trim((string)$token);
        }
        return '';
    }
}

if (!function_exists('appHttpGetJson')) {
    /** Minimal GitHub GET helper: returns [status, body, error]. */
    function appHttpGetJson($url, $timeout = 8)
    {
        $headers = [
            'User-Agent: Eleven100-ERP-Updater',
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        $token = appGithubToken();
        if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
        $status = 0;
        $body = '';
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($body === false) {
                $error = curl_error($ch);
                $body = '';
            }
            curl_close($ch);
            return ['status' => $status, 'body' => $body, 'error' => $error];
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 3,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
        if ($body === false) {
            $error = 'HTTP request failed';
            $body = '';
        }
        return ['status' => $status, 'body' => $body, 'error' => $error];
    }
}

if (!function_exists('appDownloadHostAllowed')) {
    /** Only GitHub-owned HTTPS hosts may serve update assets/checksums. */
    function appDownloadHostAllowed($url)
    {
        $parts = parse_url((string)$url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        $host = strtolower($parts['host']);
        return in_array($host, [
            'github.com',
            'api.github.com',
            'objects.githubusercontent.com',
            'github-releases.githubusercontent.com',
            'release-assets.githubusercontent.com',
        ], true);
    }
}

if (!function_exists('appHttpDownloadFile')) {
    /**
     * Download a GitHub asset to a local file with a hard size cap.
     * Returns the number of bytes written. Throws on any failure.
     */
    function appHttpDownloadFile($url, $dest, $maxBytes = 26214400, $timeout = 60)
    {
        if (!appDownloadHostAllowed($url)) {
            throw new Exception('Update download URL is not a GitHub-owned HTTPS host.');
        }
        $headers = ['User-Agent: Eleven100-ERP-Updater', 'Accept: application/octet-stream'];
        $token = appGithubToken();
        if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;

        if (function_exists('curl_init')) {
            $fp = fopen($dest, 'wb');
            if (!$fp) throw new Exception('Cannot create the update download file.');
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'Eleven100-ERP-Updater',
            ]);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effective = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            if ($ok === false || $status < 200 || $status >= 300) {
                @unlink($dest);
                throw new Exception('Update download failed' . ($err !== '' ? ': ' . $err : ' (HTTP ' . $status . ')'));
            }
            if ($effective !== '' && !appDownloadHostAllowed($effective)) {
                @unlink($dest);
                throw new Exception('Update download redirected outside GitHub.');
            }
            $size = (int)filesize($dest);
            if ($size <= 0 || $size > $maxBytes) {
                @unlink($dest);
                throw new Exception('Update download is empty or larger than the 25 MB limit.');
            }
            return $size;
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
        ]]);
        $in = @fopen($url, 'rb', false, $ctx);
        if (!$in) throw new Exception('Cannot open the update download URL.');
        $out = fopen($dest, 'wb');
        if (!$out) { fclose($in); throw new Exception('Cannot create the update download file.'); }
        $total = 0;
        while (!feof($in)) {
            $chunk = fread($in, 8192);
            if ($chunk === false) break;
            $total += strlen($chunk);
            if ($total > $maxBytes) { fclose($in); fclose($out); @unlink($dest); throw new Exception('Update download exceeds the 25 MB limit.'); }
            fwrite($out, $chunk);
        }
        fclose($in);
        fclose($out);
        if ($total <= 0) { @unlink($dest); throw new Exception('Update download is empty.'); }
        return $total;
    }
}

if (!function_exists('appUpdateCacheFile')) {
    function appUpdateCacheFile($repo)
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
            . '1100erp-update-' . md5(strtolower((string)$repo)) . '.json';
    }
}

if (!function_exists('appWriteUpdateCache')) {
    function appWriteUpdateCache($repo, $data)
    {
        $file = appUpdateCacheFile($repo);
        @file_put_contents($file, json_encode(['ts' => time(), 'data' => $data]), LOCK_EX);
    }
}

if (!function_exists('githubLatestRelease')) {
    /**
     * Latest release (or best tag if no release exists) for owner/repo.
     * Results are cached for 10 minutes to stay well inside GitHub's limits.
     *
     * @throws Exception on invalid repo or unreachable API (stale cache is used if available).
     */
    function githubLatestRelease($repo, $ttl = 600)
    {
        if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', (string)$repo)) {
            throw new Exception('Invalid repository name. Use owner/repo.');
        }

        $cached = null;
        $cacheFile = appUpdateCacheFile($repo);
        if (is_file($cacheFile)) {
            $raw = @file_get_contents($cacheFile);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded) && isset($decoded['ts'], $decoded['data']) && is_array($decoded['data'])) {
                $cached = $decoded;
                // Older cache entries predate release-asset metadata; refresh
                // them so the updater can find the ZIP/checksum assets.
                if (array_key_exists('assets', $decoded['data']) && time() - (int)$decoded['ts'] < $ttl) {
                    $decoded['data']['cached'] = true;
                    return $decoded['data'];
                }
            }
        }

        $api = 'https://api.github.com/repos/' . $repo;
        $release = appHttpGetJson($api . '/releases/latest');

        if ($release['status'] === 200) {
            $r = json_decode($release['body'], true);
            if (is_array($r) && !empty($r['tag_name'])) {
                $tag = (string)$r['tag_name'];
                $assets = [];
                foreach ((array)($r['assets'] ?? []) as $asset) {
                    if (!is_array($asset) || empty($asset['name']) || empty($asset['browser_download_url'])) continue;
                    $assets[] = [
                        'id' => (int)($asset['id'] ?? 0),
                        'name' => (string)$asset['name'],
                        'url' => (string)$asset['browser_download_url'],
                        'size' => (int)($asset['size'] ?? 0),
                        'content_type' => (string)($asset['content_type'] ?? ''),
                    ];
                }
                $data = [
                    'tag' => $tag,
                    'version' => appNormalizeVersion($tag),
                    'name' => (string)($r['name'] ?? $tag),
                    'html_url' => (string)($r['html_url'] ?? ($api . '/releases/tag/' . rawurlencode($tag))),
                    'published_at' => (string)($r['published_at'] ?? ''),
                    'notes' => (string)($r['body'] ?? ''),
                    'assets' => $assets,
                    'source' => 'release',
                ];
                appWriteUpdateCache($repo, $data);
                return $data;
            }
        } elseif ($release['status'] !== 404) {
            // Rate limit / network: prefer stale data over a false "up to date".
            if ($cached) {
                $cached['data']['cached'] = true;
                $cached['data']['warning'] = 'GitHub API unavailable (HTTP ' . $release['status'] . '); showing last known result.';
                return $cached['data'];
            }
            $detail = $release['error'] !== '' ? ' ' . $release['error'] : '';
            throw new Exception('GitHub API error (HTTP ' . $release['status'] . ').' . $detail);
        }

        // No releases yet: fall back to the highest semver tag.
        $tags = appHttpGetJson($api . '/tags?per_page=100');
        if ($tags['status'] !== 200) {
            if ($cached) {
                $cached['data']['cached'] = true;
                $cached['data']['warning'] = 'Tag lookup failed (HTTP ' . $tags['status'] . '); showing last known result.';
                return $cached['data'];
            }
            throw new Exception('No releases found and tag lookup failed (HTTP ' . $tags['status'] . ').');
        }

        $list = json_decode($tags['body'], true);
        $best = null;
        if (is_array($list)) {
            foreach ($list as $t) {
                if (empty($t['name'])) continue;
                $v = appNormalizeVersion($t['name']);
                if ($v === '') continue;
                if ($best === null || version_compare($v, $best['version'], '>')) {
                    $best = ['tag' => (string)$t['name'], 'version' => $v];
                }
            }
        }

        if ($best === null) {
            if ($cached) {
                $cached['data']['cached'] = true;
                return $cached['data'];
            }
            throw new Exception('No releases or tags found for ' . $repo . '.');
        }

        $data = [
            'tag' => $best['tag'],
            'version' => $best['version'],
            'name' => $best['tag'],
            'html_url' => $api . '/releases/tag/' . rawurlencode($best['tag']),
            'published_at' => '',
            'notes' => '',
            'assets' => [],
            'source' => 'tag',
        ];
        appWriteUpdateCache($repo, $data);
        return $data;
    }
}

if (!function_exists('appFindUpdateAssets')) {
    /**
     * Pick the update ZIP and its required checksum asset from a release.
     * Returns ['zip' => asset|null, 'checksum' => asset|null, 'ready' => bool].
     */
    function appFindUpdateAssets($release)
    {
        $assets = is_array($release['assets'] ?? null) ? $release['assets'] : [];
        $zip = null;
        foreach ($assets as $asset) {
            if (preg_match('/^1100erp-v[0-9][0-9A-Za-z._-]*-(update|hotfix)\.zip$/i', (string)$asset['name'])) {
                $zip = $asset;
                break;
            }
        }
        $checksum = null;
        if ($zip) {
            foreach ($assets as $asset) {
                if (strcasecmp((string)$asset['name'], $zip['name'] . '.sha256') === 0) {
                    $checksum = $asset;
                    break;
                }
            }
        }
        return ['zip' => $zip, 'checksum' => $checksum, 'ready' => (bool)($zip && $checksum)];
    }
}

if (!function_exists('appAssetApiUrl')) {
    /** Prefer the authenticated API asset URL for private repositories. */
    function appAssetApiUrl($repo, $asset)
    {
        if (!empty($asset['id'])) {
            return 'https://api.github.com/repos/' . $repo . '/releases/assets/' . (int)$asset['id'];
        }
        return (string)($asset['url'] ?? '');
    }
}

if (!function_exists('appUpdateStatus')) {
    /** Unified update status used by the About page and Maintenance tab. */
    function appUpdateStatus($repo, $currentVersion = null)
    {
        if ($currentVersion === null) $currentVersion = defined('APP_VERSION') ? APP_VERSION : '';
        $latest = githubLatestRelease($repo);
        return [
            'success' => true,
            'repo' => $repo,
            'local_version' => $currentVersion,
            'latest_version' => $latest['version'],
            'latest_tag' => $latest['tag'],
            'latest_url' => $latest['html_url'],
            'release_name' => $latest['name'],
            'published_at' => $latest['published_at'],
            'update_available' => appVersionIsNewer($latest['version'], $currentVersion),
            'assets' => $latest['assets'] ?? [],
            'source' => $latest['source'],
            'cached' => !empty($latest['cached']),
            'warning' => $latest['warning'] ?? null,
        ];
    }
}
