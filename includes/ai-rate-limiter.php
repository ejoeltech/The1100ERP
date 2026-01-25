<?php
/**
 * AI Rate Limiter
 * Handles rate limiting, usage tracking, and caching for AI features
 */

class AiRateLimiter
{
    private $pdo;
    private $toolName;
    private $userId;
    private $ipAddress;

    public function __construct($toolName, $userId = null, $ipAddress = null)
    {
        global $pdo;
        $this->pdo = $pdo;
        $this->toolName = $toolName;
        $this->userId = $userId;
        $this->ipAddress = $ipAddress ?? self::getClientIP();
    }

    /**
     * Get real client IP address
     */
    public static function getClientIP()
    {
        $ip = $_SERVER['REMOTE_ADDR'];

        // Check for proxy headers (only trust if from known proxies)
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedIPs = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($forwardedIPs[0]);
        }

        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0'; // Fallback
        }

        return $ip;
    }

    /**
     * Generate hash for request params
     */
    public static function generateRequestHash($params)
    {
        // Remove variable elements
        unset($params['_'], $params['timestamp'], $params['mode']);

        // Sort for consistency
        ksort($params);

        return hash('sha256', json_encode($params));
    }

    /**
     * Check if request is allowed (main method)
     */
    public function checkLimit($requestParams)
    {
        // Check if AI is emergency disabled
        if (getSetting('ai_emergency_disable', '0') === '1') {
            throw new Exception('AI features are temporarily disabled. Please contact administrator.');
        }

        // Check cache first
        if (getSetting('ai_enable_caching', '1') === '1') {
            $requestHash = self::generateRequestHash($requestParams);
            $cached = $this->getCachedResponse($requestHash);

            if ($cached) {
                $this->incrementCacheHit($requestHash);
                return [
                    'allowed' => true,
                    'cached' => true,
                    'data' => json_decode($cached['response_data'], true),
                    'remaining' => $this->getRemainingRequests()
                ];
            }
        }

        // Get limits
        $limits = $this->getLimits();

        // Check hourly limit
        $hourlyUsage = $this->getUsageCount('hour');
        if ($hourlyUsage >= $limits['hourly']) {
            throw new Exception("Hourly limit of {$limits['hourly']} requests exceeded. Try again in 1 hour.");
        }

        // Check daily limit
        $dailyUsage = $this->getUsageCount('day');
        if ($dailyUsage >= $limits['daily']) {
            throw new Exception("Daily limit of {$limits['daily']} requests exceeded. Try again tomorrow.");
        }

        // Check monthly budget (global limit)
        $monthlyCost = $this->getMonthlyCost();
        $budget = (float) getSetting('ai_monthly_budget_usd', 100);

        if ($monthlyCost >= $budget) {
            // Auto-disable if over budget
            setSetting('ai_emergency_disable', '1');
            throw new Exception('Monthly API budget exceeded. AI features disabled. Contact administrator.');
        }

        return [
            'allowed' => true,
            'cached' => false,
            'remaining' => [
                'hourly' => $limits['hourly'] - $hourlyUsage,
                'daily' => $limits['daily'] - $dailyUsage,
                'budget_remaining' => $budget - $monthlyCost
            ]
        ];
    }

    /**
     * Get rate limits for current user/IP
     */
    private function getLimits()
    {
        if ($this->userId) {
            // Authenticated user limits
            return [
                'hourly' => (int) getSetting('ai_user_hourly_limit', 10),
                'daily' => (int) getSetting('ai_user_daily_limit', 50)
            ];
        } else {
            // IP-based limits (public users)
            return [
                'hourly' => (int) getSetting('ai_ip_hourly_limit', 5),
                'daily' => (int) getSetting('ai_ip_daily_limit', 20)
            ];
        }
    }

    /**
     * Get usage count for time period
     */
    private function getUsageCount($period)
    {
        $timeCondition = $period === 'hour'
            ? "created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            : "DATE(created_at) = CURDATE()";

        $identifierCondition = $this->userId
            ? "user_id = ?"
            : "ip_address = ? AND user_id IS NULL";

        $identifier = $this->userId ?? $this->ipAddress;

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM ai_usage_logs 
            WHERE tool_name = ? 
            AND $identifierCondition 
            AND $timeCondition
            AND success = TRUE
        ");

        $stmt->execute([$this->toolName, $identifier]);
        $result = $stmt->fetch();

        return (int) $result['count'];
    }

    /**
     * Get total monthly API cost
     */
    private function getMonthlyCost()
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(cost_usd), 0) as total 
            FROM ai_usage_logs 
            WHERE MONTH(created_at) = MONTH(NOW()) 
            AND YEAR(created_at) = YEAR(NOW())
        ");

        $stmt->execute();
        $result = $stmt->fetch();

        return (float) $result['total'];
    }

    /**
     * Get remaining requests
     */
    public function getRemainingRequests()
    {
        $limits = $this->getLimits();
        $hourlyUsage = $this->getUsageCount('hour');
        $dailyUsage = $this->getUsageCount('day');

        return [
            'hourly' => max(0, $limits['hourly'] - $hourlyUsage),
            'daily' => max(0, $limits['daily'] - $dailyUsage)
        ];
    }

    /**
     * Log API usage
     */
    public function logUsage($requestHash, $tokensUsed = 0, $processingTime = 0, $success = true, $errorMessage = null)
    {
        // Estimate cost (Groq pricing: ~$0.005 per 1000 tokens)
        $costUsd = ($tokensUsed / 1000) * 0.005;

        $stmt = $this->pdo->prepare("
            INSERT INTO ai_usage_logs (
                user_id, ip_address, tool_name, endpoint, request_hash,
                tokens_used, cost_usd, processing_time, success, error_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $this->userId,
            $this->ipAddress,
            $this->toolName,
            $_SERVER['REQUEST_URI'] ?? '',
            $requestHash,
            $tokensUsed,
            $costUsd,
            $processingTime,
            $success,
            $errorMessage
        ]);
    }

    /**
     * Cache response
     */
    public function cacheResponse($requestHash, $requestParams, $response)
    {
        if (getSetting('ai_enable_caching', '1') !== '1') {
            return;
        }

        $ttlHours = (int) getSetting('ai_cache_ttl_hours', 24);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+$ttlHours hours"));

        $stmt = $this->pdo->prepare("
            INSERT INTO ai_request_cache (
                request_hash, tool_name, request_params, response_data, expires_at
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                hit_count = hit_count + 1,
                last_accessed = NOW()
        ");

        $stmt->execute([
            $requestHash,
            $this->toolName,
            json_encode($requestParams),
            json_encode($response),
            $expiresAt
        ]);
    }

    /**
     * Get cached response
     */
    private function getCachedResponse($requestHash)
    {
        $stmt = $this->pdo->prepare("
            SELECT response_data 
            FROM ai_request_cache 
            WHERE request_hash = ? 
            AND expires_at > NOW()
        ");

        $stmt->execute([$requestHash]);
        return $stmt->fetch();
    }

    /**
     * Increment cache hit count
     */
    private function incrementCacheHit($requestHash)
    {
        $stmt = $this->pdo->prepare("
            UPDATE ai_request_cache 
            SET hit_count = hit_count + 1, last_accessed = NOW() 
            WHERE request_hash = ?
        ");

        $stmt->execute([$requestHash]);
    }

    /**
     * Clear all cache
     */
    public static function clearAllCache()
    {
        global $pdo;
        $pdo->exec("TRUNCATE TABLE ai_request_cache");
    }

    /**
     * Get usage statistics
     */
    public static function getStatistics($days = 30)
    {
        global $pdo;

        // Daily costs
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, 
                   SUM(cost_usd) as cost,
                   COUNT(*) as requests
            FROM ai_usage_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        $dailyCosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top users
        $topUsers = $pdo->query("
            SELECT u.username, 
                   COUNT(*) as requests,
                   SUM(l.cost_usd) as cost
            FROM ai_usage_logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND l.user_id IS NOT NULL
            GROUP BY l.user_id, u.username
            ORDER BY requests DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Top IPs
        $topIPs = $pdo->query("
            SELECT ip_address, 
                   COUNT(*) as requests,
                   SUM(cost_usd) as cost
            FROM ai_usage_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND user_id IS NULL
            GROUP BY ip_address
            ORDER BY requests DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Tool usage
        $toolStats = $pdo->query("
            SELECT tool_name, 
                   COUNT(*) as requests,
                   SUM(cost_usd) as cost
            FROM ai_usage_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY tool_name
            ORDER BY requests DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Cache stats
        $cacheStats = $pdo->query("
            SELECT COUNT(*) as total_cached,
                   SUM(hit_count) as total_hits,
                   AVG(hit_count) as avg_hits
            FROM ai_request_cache
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'daily_costs' => $dailyCosts,
            'top_users' => $topUsers,
            'top_ips' => $topIPs,
            'tool_stats' => $toolStats,
            'cache_stats' => $cacheStats
        ];
    }
}
