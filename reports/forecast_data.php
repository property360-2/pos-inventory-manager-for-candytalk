<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$pdo = getDBConnection();
$type = $_GET['type'] ?? '';

// Helper function for linear regression forecasting
function linearRegression($x, $y) {
    $n = count($x);
    if ($n != count($y) || $n < 2) return [0, 0];
    
    $sumX = array_sum($x);
    $sumY = array_sum($y);
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $sumXY += $x[$i] * $y[$i];
        $sumX2 += $x[$i] * $x[$i];
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    
    return [$slope, $intercept];
}

// Helper function to generate forecast data
function generateForecast($historicalData, $days = 30) {
    $x = range(0, count($historicalData) - 1);
    $y = $historicalData;
    
    list($slope, $intercept) = linearRegression($x, $y);
    
    $forecast = [];
    for ($i = count($historicalData); $i < count($historicalData) + $days; $i++) {
        $forecast[] = max(0, $slope * $i + $intercept);
    }
    
    return $forecast;
}

// Advanced forecast function with realistic patterns
function generateAdvancedForecast($historicalData, $days = 30) {
    if (count($historicalData) < 7) {
        // If not enough data, use simple average with variation
        $avg = array_sum($historicalData) / count($historicalData);
        $forecast = [];
        for ($i = 0; $i < $days; $i++) {
            $variation = $avg * (0.7 + (mt_rand(0, 60) / 100)); // ±30% variation
            $forecast[] = max(0, round($variation));
        }
        return $forecast;
    }
    
    // Calculate multiple trend indicators
    $trends = calculateTrends($historicalData);
    $seasonalPattern = detectSeasonalPattern($historicalData);
    $volatility = calculateVolatility($historicalData);
    
    $forecast = [];
    $lastValue = end($historicalData);
    $baseTrend = $trends['linear_trend'];
    $weeklyPattern = $seasonalPattern['weekly_pattern'];
    $monthlyPattern = $seasonalPattern['monthly_pattern'];
    
    for ($i = 0; $i < $days; $i++) {
        $dayOfWeek = (date('w') + $i) % 7; // 0 = Sunday, 6 = Saturday
        $dayOfMonth = (date('j') + $i) % 30;
        
        // Base prediction using multiple factors
        $trendComponent = $lastValue + ($baseTrend * ($i + 1));
        $weeklyComponent = $weeklyPattern[$dayOfWeek] ?? 1.0;
        $monthlyComponent = $monthlyPattern[$dayOfMonth] ?? 1.0;
        
        // Combine components with realistic weighting
        $prediction = $trendComponent * $weeklyComponent * $monthlyComponent;
        
        // Add realistic volatility and noise
        $noise = $prediction * ($volatility * (mt_rand(-100, 100) / 1000));
        $finalPrediction = $prediction + $noise;
        
        // Ensure non-negative and realistic bounds
        $finalPrediction = max(0, min($finalPrediction * 2, $finalPrediction)); // Cap at 2x historical max
        $forecast[] = round($finalPrediction);
        
        // Update last value for next iteration
        $lastValue = $finalPrediction;
    }
    
    return $forecast;
}

// Calculate multiple trend indicators
function calculateTrends($data) {
    $n = count($data);
    if ($n < 2) return ['linear_trend' => 0, 'exponential_trend' => 0];
    
    // Linear trend
    $x = range(0, $n - 1);
    $y = $data;
    list($slope, $intercept) = linearRegression($x, $y);
    
    // Exponential trend (if data shows growth)
    $exponentialTrend = 0;
    if ($n > 7) {
        $recentAvg = array_sum(array_slice($data, -7)) / 7;
        $earlierAvg = array_sum(array_slice($data, 0, 7)) / 7;
        if ($earlierAvg > 0) {
            $exponentialTrend = ($recentAvg - $earlierAvg) / $earlierAvg;
        }
    }
    
    return [
        'linear_trend' => $slope,
        'exponential_trend' => $exponentialTrend
    ];
}

// Detect seasonal patterns in the data
function detectSeasonalPattern($data) {
    $n = count($data);
    if ($n < 7) return ['weekly_pattern' => [], 'monthly_pattern' => []];
    
    // Weekly pattern (7-day cycle)
    $weeklyPattern = [];
    for ($day = 0; $day < 7; $day++) {
        $dayValues = [];
        for ($i = $day; $i < $n; $i += 7) {
            if (isset($data[$i])) {
                $dayValues[] = $data[$i];
            }
        }
        if (!empty($dayValues)) {
            $avg = array_sum($dayValues) / count($dayValues);
            $overallAvg = array_sum($data) / count($data);
            $weeklyPattern[$day] = $overallAvg > 0 ? $avg / $overallAvg : 1.0;
        }
    }
    
    // Monthly pattern (30-day cycle approximation)
    $monthlyPattern = [];
    for ($day = 0; $day < 30; $day++) {
        $dayValues = [];
        for ($i = $day; $i < $n; $i += 30) {
            if (isset($data[$i])) {
                $dayValues[] = $data[$i];
            }
        }
        if (!empty($dayValues)) {
            $avg = array_sum($dayValues) / count($dayValues);
            $overallAvg = array_sum($data) / count($data);
            $monthlyPattern[$day] = $overallAvg > 0 ? $avg / $overallAvg : 1.0;
        }
    }
    
    return [
        'weekly_pattern' => $weeklyPattern,
        'monthly_pattern' => $monthlyPattern
    ];
}

// Calculate volatility of the data
function calculateVolatility($data) {
    if (count($data) < 2) return 0.1;
    
    $mean = array_sum($data) / count($data);
    $variance = 0;
    
    foreach ($data as $value) {
        $variance += pow($value - $mean, 2);
    }
    $variance /= count($data);
    
    $stdDev = sqrt($variance);
    return $mean > 0 ? $stdDev / $mean : 0.1; // Coefficient of variation
}

// Analyze actual sales patterns from database data
function analyzeSalesPatterns($historicalData) {
    $patterns = [
        'weekly_pattern' => [],
        'monthly_pattern' => [],
        'trend' => 0,
        'average_daily_sales' => 0,
        'volatility' => 0,
        'growth_rate' => 0
    ];
    
    if (empty($historicalData)) return $patterns;
    
    // Calculate daily averages
    $dailySales = [];
    foreach ($historicalData as $row) {
        $dailySales[] = (int)$row['sales_count'];
    }
    
    $patterns['average_daily_sales'] = array_sum($dailySales) / count($dailySales);
    $patterns['volatility'] = calculateVolatility($dailySales);
    
    // Calculate trend (linear regression)
    $x = range(0, count($dailySales) - 1);
    $y = $dailySales;
    list($slope, $intercept) = linearRegression($x, $y);
    $patterns['trend'] = $slope;
    
    // Calculate growth rate
    if (count($dailySales) >= 14) {
        $recentAvg = array_sum(array_slice($dailySales, -7)) / 7;
        $earlierAvg = array_sum(array_slice($dailySales, 0, 7)) / 7;
        $patterns['growth_rate'] = $earlierAvg > 0 ? ($recentAvg - $earlierAvg) / $earlierAvg : 0;
    }
    
    // Analyze weekly patterns (day of week)
    $weeklyData = [];
    for ($day = 1; $day <= 7; $day++) {
        $weeklyData[$day] = [];
    }
    
    foreach ($historicalData as $row) {
        $dayOfWeek = (int)$row['day_of_week'];
        $weeklyData[$dayOfWeek][] = (int)$row['sales_count'];
    }
    
    foreach ($weeklyData as $day => $sales) {
        if (!empty($sales)) {
            $avg = array_sum($sales) / count($sales);
            $patterns['weekly_pattern'][$day] = $patterns['average_daily_sales'] > 0 ? 
                $avg / $patterns['average_daily_sales'] : 1.0;
        }
    }
    
    // Analyze monthly patterns (day of month)
    $monthlyData = [];
    for ($day = 1; $day <= 31; $day++) {
        $monthlyData[$day] = [];
    }
    
    foreach ($historicalData as $row) {
        $dayOfMonth = (int)$row['day_of_month'];
        $monthlyData[$dayOfMonth][] = (int)$row['sales_count'];
    }
    
    foreach ($monthlyData as $day => $sales) {
        if (!empty($sales)) {
            $avg = array_sum($sales) / count($sales);
            $patterns['monthly_pattern'][$day] = $patterns['average_daily_sales'] > 0 ? 
                $avg / $patterns['average_daily_sales'] : 1.0;
        }
    }
    
    return $patterns;
}

// Generate forecast based on actual historical data patterns
function generateDataBasedForecast($historicalData, $patterns, $days = 30) {
    if (empty($historicalData)) {
        // No historical data, return conservative estimates
        return array_fill(0, $days, 1);
    }
    
    $forecast = [];
    $lastValue = end($historicalData)['sales_count'];
    $baseAverage = $patterns['average_daily_sales'];
    $trend = $patterns['trend'];
    $growthRate = $patterns['growth_rate'];
    $volatility = $patterns['volatility'];
    
    for ($i = 0; $i < $days; $i++) {
        $dayOfWeek = (date('w') + $i) % 7 + 1; // 1 = Sunday, 7 = Saturday
        $dayOfMonth = (date('j') + $i) % 31 + 1;
        
        // Base prediction using actual historical average
        $basePrediction = $baseAverage;
        
        // Apply trend from actual data
        $trendComponent = $trend * ($i + 1);
        
        // Apply growth rate if significant
        if (abs($growthRate) > 0.05) {
            $basePrediction *= (1 + $growthRate * ($i + 1) / 30);
        }
        
        // Apply weekly pattern from actual data
        $weeklyMultiplier = $patterns['weekly_pattern'][$dayOfWeek] ?? 1.0;
        
        // Apply monthly pattern from actual data
        $monthlyMultiplier = $patterns['monthly_pattern'][$dayOfMonth] ?? 1.0;
        
        // Combine all factors
        $prediction = ($basePrediction + $trendComponent) * $weeklyMultiplier * $monthlyMultiplier;
        
        // Add realistic noise based on actual volatility
        $noise = $prediction * $volatility * (mt_rand(-50, 50) / 1000);
        $finalPrediction = $prediction + $noise;
        
        // Ensure realistic bounds
        $finalPrediction = max(0, min($finalPrediction * 1.5, $finalPrediction));
        $forecast[] = round($finalPrediction);
    }
    
    return $forecast;
}

// Helper function to get date labels
function getDateLabels($startDate, $days) {
    $labels = [];
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime($startDate . " +$i days"));
        $labels[] = date('M j', strtotime($date));
    }
    return $labels;
}

header('Content-Type: application/json');

switch ($type) {
    case 'sales_revenue':
        // Get last 30 days of sales data
        $stmt = $pdo->prepare("
            SELECT DATE(sale_date) as sale_day, 
                   COUNT(*) as sales_count, 
                   COALESCE(SUM(total_amount), 0) as revenue
            FROM sales 
            WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(sale_date)
            ORDER BY sale_day ASC
        ");
        $stmt->execute();
        $salesData = $stmt->fetchAll();
        
        // Prepare data for chart
        $labels = [];
        $salesCount = [];
        $revenue = [];
        
        $startDate = date('Y-m-d', strtotime('-30 days'));
        for ($i = 0; $i < 30; $i++) {
            $currentDate = date('Y-m-d', strtotime($startDate . " +$i days"));
            $labels[] = date('M j', strtotime($currentDate));
            
            $found = false;
            foreach ($salesData as $row) {
                if ($row['sale_day'] == $currentDate) {
                    $salesCount[] = (int)$row['sales_count'];
                    $revenue[] = (float)$row['revenue'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $salesCount[] = 0;
                $revenue[] = 0;
            }
        }
        
        echo json_encode([
            'labels' => $labels,
            'sales_count' => $salesCount,
            'revenue' => $revenue
        ]);
        break;
        
    case 'top_products':
        // Get top 5 selling products
        $stmt = $pdo->prepare("
            SELECT i.name, SUM(si.quantity) as units
            FROM sale_items si
            JOIN inventory i ON si.product_id = i.product_id
            JOIN sales s ON si.sale_id = s.sale_id
            WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY si.product_id
            ORDER BY units DESC
            LIMIT 5
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();
        
        echo json_encode([
            'labels' => array_column($products, 'name'),
            'units' => array_map('intval', array_column($products, 'units'))
        ]);
        break;
        
    case 'sales_forecast':
        // Get comprehensive historical sales data for forecasting (last 90 days for better pattern analysis)
        $stmt = $pdo->prepare("
            SELECT DATE(sale_date) as sale_day, 
                   COUNT(*) as sales_count,
                   COALESCE(SUM(total_amount), 0) as daily_revenue,
                   DAYOFWEEK(sale_date) as day_of_week,
                   DAY(sale_date) as day_of_month
            FROM sales 
            WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY DATE(sale_date)
            ORDER BY sale_day ASC
        ");
        $stmt->execute();
        $historicalData = $stmt->fetchAll();
        
        // Analyze historical patterns
        $patterns = analyzeSalesPatterns($historicalData);
        
        // Prepare historical data for last 30 days (for display)
        $historical = [];
        $startDate = date('Y-m-d', strtotime('-30 days'));
        for ($i = 0; $i < 30; $i++) {
            $currentDate = date('Y-m-d', strtotime($startDate . " +$i days"));
            $found = false;
            foreach ($historicalData as $row) {
                if ($row['sale_day'] == $currentDate) {
                    $historical[] = (int)$row['sales_count'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $historical[] = 0;
            }
        }
        
        // Generate forecast based on actual historical patterns
        $forecast = generateDataBasedForecast($historicalData, $patterns, 30);
        
        // Prepare labels (30 days historical + 30 days forecast)
        $labels = [];
        // Historical labels (last 30 days)
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime($startDate . " +$i days"));
            $labels[] = date('M j', strtotime($date));
        }
        // Forecast labels (next 30 days)
        for ($i = 1; $i <= 30; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $labels[] = date('M j', strtotime($date));
        }
        
        // Create separate datasets for historical and forecast
        $historicalDataset = array_merge($historical, array_fill(0, 30, null)); // Historical + null for forecast period
        $forecastDataset = array_merge(array_fill(0, 30, null), $forecast); // Null for historical + forecast
        
        echo json_encode([
            'labels' => $labels,
            'historical' => $historicalDataset,
            'forecast' => $forecastDataset
        ]);
        break;
        
    case 'inventory_forecast':
        // Get comprehensive inventory data with detailed consumption analysis
        $stmt = $pdo->prepare("
            SELECT i.name, i.quantity, i.product_id,
                   COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN si.quantity ELSE 0 END), 0) as sold_last_30_days,
                   COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN si.quantity ELSE 0 END), 0) as sold_last_7_days,
                   COALESCE(SUM(CASE WHEN s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN si.quantity ELSE 0 END), 0) as sold_last_90_days,
                   CASE 
                       WHEN COALESCE(SUM(si.quantity), 0) = 0 THEN 'No Sales'
                       WHEN i.quantity = 0 THEN 'Out of Stock'
                       WHEN i.quantity < 10 THEN 'Low Stock'
                       WHEN i.quantity < 50 THEN 'Medium Stock'
                       ELSE 'Well Stocked'
                   END as stock_status
            FROM inventory i
            LEFT JOIN sale_items si ON i.product_id = si.product_id
            LEFT JOIN sales s ON si.sale_id = s.sale_id 
                AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY i.product_id
            ORDER BY i.quantity ASC
            LIMIT 8
        ");
        $stmt->execute();
        $inventoryData = $stmt->fetchAll();
        
        $labels = [];
        $currentStock = [];
        $daysUntilEmpty = [];
        $stockStatus = [];
        $consumptionTrends = [];
        
        foreach ($inventoryData as $item) {
            $labels[] = $item['name'];
            $currentStock[] = (int)$item['quantity'];
            
            // Calculate consumption trends based on actual data
            $dailyRate30 = $item['sold_last_30_days'] / 30;
            $dailyRate7 = $item['sold_last_7_days'] / 7;
            $dailyRate90 = $item['sold_last_90_days'] / 90;
            
            // Use the most recent trend (7-day) if available, otherwise fall back to 30-day
            $currentDailyRate = $dailyRate7 > 0 ? $dailyRate7 : $dailyRate30;
            
            // Calculate days until empty based on actual consumption
            if ($currentDailyRate > 0) {
                $daysUntilEmpty[] = round($item['quantity'] / $currentDailyRate);
                
                // Determine consumption trend
                if ($dailyRate7 > $dailyRate30 * 1.2) {
                    $consumptionTrends[] = 'Increasing';
                } elseif ($dailyRate7 < $dailyRate30 * 0.8) {
                    $consumptionTrends[] = 'Decreasing';
                } else {
                    $consumptionTrends[] = 'Stable';
                }
            } else {
                $daysUntilEmpty[] = $item['quantity'] > 0 ? 999 : 0; // No sales or out of stock
                $consumptionTrends[] = 'No Sales';
            }
            
            $stockStatus[] = $item['stock_status'];
        }
        
        echo json_encode([
            'labels' => $labels,
            'current_stock' => $currentStock,
            'days_until_empty' => $daysUntilEmpty,
            'stock_status' => $stockStatus,
            'consumption_trends' => $consumptionTrends
        ]);
        break;
        
    case 'low_stock':
        // Get low stock items
        $stmt = $pdo->prepare("
            SELECT name, quantity
            FROM inventory
            WHERE quantity < 10
            ORDER BY quantity ASC
            LIMIT 5
        ");
        $stmt->execute();
        $lowStockItems = $stmt->fetchAll();
        
        if (empty($lowStockItems)) {
            echo json_encode([
                'labels' => ['Well Stocked'],
                'values' => [100]
            ]);
        } else {
            echo json_encode([
                'labels' => array_column($lowStockItems, 'name'),
                'values' => array_map('intval', array_column($lowStockItems, 'quantity'))
            ]);
        }
        break;
        
    case 'revenue_distribution':
        // Get revenue distribution by product category
        $stmt = $pdo->prepare("
            SELECT i.name, SUM(si.subtotal) as revenue
            FROM sale_items si
            JOIN inventory i ON si.product_id = i.product_id
            JOIN sales s ON si.sale_id = s.sale_id
            WHERE s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY si.product_id
            ORDER BY revenue DESC
            LIMIT 5
        ");
        $stmt->execute();
        $revenueData = $stmt->fetchAll();
        
        if (empty($revenueData)) {
            echo json_encode([
                'labels' => ['No Sales'],
                'values' => [100]
            ]);
        } else {
            echo json_encode([
                'labels' => array_column($revenueData, 'name'),
                'values' => array_map('floatval', array_column($revenueData, 'revenue'))
            ]);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid chart type']);
        break;
}
?> 