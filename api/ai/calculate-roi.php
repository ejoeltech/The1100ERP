<?php
/**
 * ROI Calculator API
 * Calculates solar savings and generates AI analysis
 */

header('Content-Type: application/json');
require_once '../../includes/public-init.php';
require_once '../../includes/groq-config.php';
require_once '../../includes/ai-rate-limiter.php';

if (empty($_SESSION['user_id']) && getSetting('ai_enable_public_access', '1') !== '1') {
    http_response_code(403);
    echo json_encode(['error' => 'Public AI access is disabled.']);
    exit;
}

// No auth check required for calculator
/*
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
*/

$input = json_decode(file_get_contents('php://input'), true);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request body']);
    exit;
}

$systemCost = filter_var($input['system_cost'] ?? null, FILTER_VALIDATE_FLOAT);
if ($systemCost === false || !is_finite($systemCost) || $systemCost < 10000) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a valid system cost of at least ₦10,000.']);
    exit;
}
$mode = in_array(($input['mode'] ?? 'simple'), ['simple', 'advanced'], true) ? $input['mode'] : 'simple';
$offsetPct = filter_var($input['offset_pct'] ?? 85, FILTER_VALIDATE_FLOAT);
if ($offsetPct === false || $offsetPct < 0 || $offsetPct > 100) {
    http_response_code(422);
    echo json_encode(['error' => 'Solar offset must be between 0 and 100%.']);
    exit;
}
$offset = $offsetPct / 100.0;

try {
    // 1. Get Market Data
    $stmt = $pdo->query("SELECT get_market_data('fuel_price', 'petrol_per_litre') as petrol, 
                                get_market_data('fuel_price', 'diesel_per_litre') as diesel");
    $prices = $stmt->fetch();

    $petrolPrice = $prices['petrol'] > 0 ? $prices['petrol'] : 1100;
    $dieselPrice = $prices['diesel'] > 0 ? $prices['diesel'] : 1400;

    // 2. Perform Calculations
    $breakdownText = "";
    $amount = function ($value, $label) {
        $n = filter_var($value ?? 0, FILTER_VALIDATE_FLOAT);
        if ($n === false || !is_finite($n) || $n < 0) {
            throw new InvalidArgumentException($label . ' must be a valid non-negative number.');
        }
        return (float)$n;
    };
    $frequency = function ($value) {
        return in_array($value, ['daily', 'weekly', 'monthly'], true) ? $value : 'monthly';
    };

    $monthlySpend = 0;
    $genDesc = "";

    if ($mode === 'advanced') {
        // --- ADVANCED MODE (Manual Inputs) ---
        $fuelCost = $amount($input['fuel_cost'] ?? 0, 'Fuel cost');
        $fuelFreq = $frequency($input['fuel_freq'] ?? 'monthly');
        $utilityCost = $amount($input['utility_cost'] ?? 0, 'Utility cost');
        $utilityFreq = $frequency($input['utility_freq'] ?? 'monthly');
        if ($fuelCost <= 0 && $utilityCost <= 0) {
            throw new InvalidArgumentException('Enter at least one fuel or utility cost for advanced mode.');
        }

        // Normalize Fuel to Monthly
        $monthlyFuel = 0;
        if ($fuelFreq == 'daily')
            $monthlyFuel = $fuelCost * 30;
        elseif ($fuelFreq == 'weekly')
            $monthlyFuel = $fuelCost * 4.33;
        else
            $monthlyFuel = $fuelCost; // monthly

        // Normalize Utility to Monthly
        $monthlyUtility = 0;
        if ($utilityFreq == 'daily')
            $monthlyUtility = $utilityCost * 30;
        elseif ($utilityFreq == 'weekly')
            $monthlyUtility = $utilityCost * 4.33;
        else
            $monthlyUtility = $utilityCost; // monthly

        $monthlySpend = $monthlyFuel + $monthlyUtility;

        // Generate Breakdown Text
        if ($monthlyFuel > 0) {
            $breakdownText .= "Fuel (" . ucfirst($fuelFreq) . "): ₦" . number_format($fuelCost) . "\n";
            $breakdownText .= "➔ Monthly Fuel: ₦" . number_format($monthlyFuel) . "\n";
        }
        if ($monthlyUtility > 0) {
            $breakdownText .= "Utility (" . ucfirst($utilityFreq) . "): ₦" . number_format($utilityCost) . "\n";
            $breakdownText .= "➔ Monthly Utility: ₦" . number_format($monthlyUtility) . "\n";
        }
        $breakdownText .= "--------------------------\n";
        $breakdownText .= "Total Monthly Spend: ₦" . number_format($monthlySpend);

        $genDesc = "Manual Spend: ₦" . number_format($monthlySpend) . "/mo";

    } else {
        // --- SIMPLE MODE (Estimation) ---
        $genKva = $amount($input['gen_kva'] ?? 0, 'Generator size');
        if (!in_array($genKva, [2.5, 5.0, 10.0, 20.0], true)) {
            throw new InvalidArgumentException('Choose a valid generator size.');
        }
        $hoursDay = $amount($input['hours_day'] ?? 0, 'Daily generator hours');
        if ($hoursDay > 24) {
            throw new InvalidArgumentException('Daily generator hours cannot exceed 24.');
        }
        $fuelType = in_array(($input['fuel_type'] ?? 'petrol'), ['petrol', 'diesel'], true)
            ? $input['fuel_type'] : 'petrol';

        // Consumption rates (Litres per hour)
        $consumptionRate = ($fuelType === 'diesel') ? (0.22 * $genKva) : (0.35 * $genKva);

        // Limits for small gens
        if ($genKva <= 3.0 && $fuelType == 'petrol')
            $consumptionRate = 0.8;
        if ($genKva == 5.0 && $fuelType == 'diesel')
            $consumptionRate = 1.2;
        if ($genKva == 5.0 && $fuelType == 'petrol')
            $consumptionRate = 1.5;

        $dailyLitres = $consumptionRate * $hoursDay;
        $pricePerLitre = ($fuelType === 'diesel') ? $dieselPrice : $petrolPrice;

        $dailyFuelCost = $dailyLitres * $pricePerLitre;

        // Maintenance (15% of fuel)
        $dailyMaintenance = $dailyFuelCost * 0.15;

        $totalDailySpend = $dailyFuelCost + $dailyMaintenance;
        $monthlySpend = $totalDailySpend * 30;

        // Generate Breakdown Text
        $breakdownText .= "Gen: {$genKva}kVA ($fuelType) @ {$hoursDay}hrs/day\n";
        $breakdownText .= "Fuel Price: ₦{$pricePerLitre}/L\n";
        $breakdownText .= "Consumption: ~" . number_format($consumptionRate, 2) . " L/hr\n";
        $breakdownText .= "Daily Fuel Cost: ₦" . number_format($dailyFuelCost) . "\n";
        $breakdownText .= "Daily Maint. (est 15%): ₦" . number_format($dailyMaintenance) . "\n";
        $breakdownText .= "--------------------------\n";
        $breakdownText .= "Monthly Total: ₦" . number_format($monthlySpend);

        $genDesc = "{$genKva}kVA ($fuelType)";
    }

    $annualSpend = $monthlySpend * 12;

    // Solar Maintenance (Cleaning panels, etc) - minimal
    $solarAnnualMaintenance = $systemCost * 0.01; // 1% per year
    $solarMonthlyCost = $solarAnnualMaintenance / 12;

    // Solar offsets only part of the current spend; residual generator/grid
    // spend remains. This prevents the old 100%-displacement overstatement.
    $monthlySavings = ($monthlySpend * $offset) - $solarMonthlyCost;
    $annualSavings = $monthlySavings * 12;
    $fiveYearSavings = ($annualSavings * 5) - $systemCost;

    // Payback Period (calculated for any positive savings; null when unrecoverable)
    $paybackMonths = $monthlySavings > 0 ? ($systemCost / $monthlySavings) : null;
    if ($paybackMonths === null) {
        $paybackLabel = 'Not recoverable at this offset';
    } elseif ($paybackMonths >= 12) {
        $years = floor($paybackMonths / 12);
        $months = (int)round(fmod($paybackMonths, 12));
        $paybackLabel = $years . 'y' . ($months > 0 ? ' ' . $months . 'm' : '');
    } else {
        $paybackLabel = (int)round($paybackMonths) . ' months';
    }

    $breakdownText .= "\n\nAssumed solar offset: " . number_format($offsetPct, 0) . '% of current spend';
    $breakdownText .= "\nSolar Maint. (deducted): -₦" . number_format($solarMonthlyCost);
    $breakdownText .= "\nNet Monthly Savings: ₦" . number_format($monthlySavings);

    // Data Structure for AI
    $finData = [
        'system_cost' => number_format($systemCost),
        'gen_type' => $genDesc,
        'current_monthly_spend' => number_format($monthlySpend),
        'assumed_offset_percent' => number_format($offsetPct, 0),
        'monthly_savings' => number_format($monthlySavings),
        'payback_months' => $paybackMonths === null ? null : round($paybackMonths, 1),
        'five_year_net_savings' => number_format($fiveYearSavings),
        'breakdown_text' => $breakdownText
    ];

    // AI analysis is optional: the numeric result must survive a provider outage.
    $aiAnalysis = '';
    $aiWarning = '';
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $rateLimiter = new AiRateLimiter('roi_calculator', $userId);
        $limitCheck = $rateLimiter->checkLimit($finData);

        if (!empty($limitCheck['cached'])) {
            $cached = $limitCheck['data'];
            $aiAnalysis = is_string($cached) ? $cached : json_encode($cached);
        } else {
            $requestHash = AiRateLimiter::generateRequestHash($finData);
            $startedAt = microtime(true);
            $companyName = erpCompanyName();
            $systemPrompt = "You are a Financial Analyst for {$companyName} in Nigeria.
Analyze the solar investment data provided.
Write a short, plain-text summary (maximum 3 short paragraphs) highlighting the financial wisdom of this switch.
Emphasize the payback period and cash-flow benefits. Mention inflation protection but stay realistic.
Refer to the company as {$companyName}. Never call it Eleven100 ERP, 1100ERP, or any other application/default name.
Use the Nigerian Naira symbol '₦'. Do not output HTML, Markdown, bold markers or bullet lists.";
            $userPrompt = "Analyze this customer ROI: " . json_encode($finData);
            $aiAnalysis = callGroqAPI($userPrompt, $systemPrompt, ['max_tokens' => 500, 'skip_rate_limit' => true]);
            $aiAnalysis = trim(strip_tags((string) $aiAnalysis));
            // Models still slip in Markdown emphasis/headings; flatten it.
            $aiAnalysis = preg_replace('/\*\*(.*?)\*\*/s', '$1', $aiAnalysis);
            $aiAnalysis = preg_replace('/^\s*#{1,6}\s*/m', '', $aiAnalysis);
            $aiAnalysis = preg_replace('/^\s*[-*]\s+/m', "\u{2022} ", $aiAnalysis);
            $aiAnalysis = preg_replace("/\n{3,}/", "\n\n", $aiAnalysis);
            $rateLimiter->logUsage(
                $requestHash,
                (strlen($userPrompt) + strlen((string) $aiAnalysis)) / 4,
                microtime(true) - $startedAt,
                true
            );
            $rateLimiter->cacheResponse($requestHash, $finData, $aiAnalysis);
        }
    } catch (Throwable $aiError) {
        error_log('ROI AI analysis failed: ' . $aiError->getMessage());
        $aiWarning = 'The AI narrative is temporarily unavailable; the calculated figures are still valid.';
    }

    $money = function ($value) {
        return ($value < 0 ? '-₦' : '₦') . number_format(abs($value), 0);
    };

    // 4. Return Response
    echo json_encode([
        'success' => true,
        'data' => [
            'monthly_savings_fmt' => $money($monthlySavings),
            'payback_months' => $paybackMonths === null ? null : round($paybackMonths, 1),
            'payback_label' => $paybackLabel,
            'five_year_savings_fmt' => $money($fiveYearSavings),
            'breakdown_text' => $breakdownText,
            'raw' => $finData
        ],
        'ai_analysis' => $aiAnalysis,
        'ai_warning' => $aiWarning
    ]);

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('ROI calculation failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Calculation failed. Please try again.']);
}
