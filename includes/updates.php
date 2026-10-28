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

if (!function_exists('appHttpGetJson')) {
    /** Minimal GitHub GET helper: returns [status, body, error]. */
    function appHttpGetJson($url, $timeout = 8)
    {
        $headers = [
            'User-Agent: Eleven100-ERP-Updater',
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
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
                if (time() - (int)$decoded['ts'] < $ttl) {
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
                $data = [
                    'tag' => $tag,
                    'version' => appNormalizeVersion($tag),
                    'name' => (string)($r['name'] ?? $tag),
                    'html_url' => (string)($r['html_url'] ?? ($api . '/releases/tag/' . rawurlencode($tag))),
                    'published_at' => (string)($r['published_at'] ?? ''),
                    'notes' => (string)($r['body'] ?? ''),
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
            'source' => 'tag',
        ];
        appWriteUpdateCache($repo, $data);
        return $data;
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
            'source' => $latest['source'],
            'cached' => !empty($latest['cached']),
            'warning' => $latest['warning'] ?? null,
        ];
    }
}
