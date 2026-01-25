<?php
/**
 * ROI Calculator API
 * Calculates solar savings and generates AI analysis
 */

header('Content-Type: application/json');
require_once '../../includes/public-init.php';
require_once '../../includes/groq-config.php';

// No auth check required for calculator
/*
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
*/

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['system_cost'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing system cost']);
    exit;
}

try {
    // 1. Get Market Data
    $stmt = $pdo->query("SELECT get_market_data('fuel_price', 'petrol_per_litre') as petrol, 
                                get_market_data('fuel_price', 'diesel_per_litre') as diesel");
    $prices = $stmt->fetch();

    $petrolPrice = $prices['petrol'] > 0 ? $prices['petrol'] : 1100;
    $dieselPrice = $prices['diesel'] > 0 ? $prices['diesel'] : 1400;

    // 2. Perform Calculations
    $systemCost = floatval($input['system_cost']);
    $mode = $input['mode'] ?? 'simple';
    $breakdownText = "";

    $monthlySpend = 0;
    $genDesc = "";

    if ($mode === 'advanced') {
        // --- ADVANCED MODE (Manual Inputs) ---
        $fuelCost = floatval($input['fuel_cost'] ?? 0);
        $fuelFreq = $input['fuel_freq'] ?? 'monthly';
        $utilityCost = floatval($input['utility_cost'] ?? 0);
        $utilityFreq = $input['utility_freq'] ?? 'monthly';

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
        if ($utilityFreq == 'weekly')
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
        $genKva = floatval($input['gen_kva'] ?? 0);
        $hoursDay = floatval($input['hours_day'] ?? 0);
        $fuelType = $input['fuel_type'] ?? 'petrol';

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

    // Savings
    $monthlySavings = $monthlySpend - $solarMonthlyCost;
    $annualSavings = $monthlySavings * 12;
    $fiveYearSavings = ($annualSavings * 5) - $systemCost; // Net savings after paying off system

    // Payback Period (avoid divide by zero)
    $paybackMonths = $monthlySavings > 1000 ? $systemCost / $monthlySavings : 999;

    $breakdownText .= "\n\nSolar Maint. (deducted): -₦" . number_format($solarMonthlyCost);
    $breakdownText .= "\nNet Monthly Savings: ₦" . number_format($monthlySavings);

    // Data Structure for AI
    $finData = [
        'system_cost' => number_format($systemCost),
        'gen_type' => $genDesc,
        'current_monthly_spend' => number_format($monthlySpend),
        'monthly_savings' => number_format($monthlySavings),
        'payback_months' => ($paybackMonths > 120) ? "10+ Years" : round($paybackMonths, 1),
        'five_year_savings' => number_format($fiveYearSavings),
        'breakdown_text' => $breakdownText
    ];

    // 3. AI Analysis
    $systemPrompt = "You are a Financial Analyst for Green Energy in Nigeria. 
    Analyze the solar investment data provided. 
    Write a short, engaging summary (HTML format, <p> tags) highlighting the financial wisdom of this switch.
    Emphasize the 'Payback Period' and 'Cash Flow' benefits. 
    Mention inflation protection. Keep it mostly positive but realistic.
    IMPORTANT: ALWAYS use the Nigerian Naira symbol '₦' for currency. Do NOT use any other symbol.";

    $userPrompt = "Analyze this customer ROI: " . json_encode($finData);

    $aiAnalysis = callGroqAPI($userPrompt, $systemPrompt, ['max_tokens' => 300]);

    // 4. Return Response
    echo json_encode([
        'success' => true,
        'data' => [
            'monthly_savings_fmt' => '₦' . number_format($monthlySavings, 0),
            'payback_months' => ($paybackMonths > 120) ? "> 10 Years" : round($paybackMonths, 1),
            'five_year_savings_fmt' => '₦' . number_format($fiveYearSavings, 0),
            'breakdown_text' => $breakdownText,
            'raw' => $finData
        ],
        'ai_analysis' => $aiAnalysis
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
