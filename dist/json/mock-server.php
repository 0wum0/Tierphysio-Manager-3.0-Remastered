<?php
// mock-server.php

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration constants
const MAX_PER_PAGE = 100;
const CACHE_FILE = __DIR__ . '/mock_data_cache.json';  // Use absolute path
const CACHE_TTL = 86400; // 24 hours in seconds (increased from 1 hour)
const FORCE_CACHE = true; // Always use cached data if available
const CACHE_VERSION = 5; // Increment this when the data generation logic changes

// Generate or retrieve mock data
function getMockData() {
    static $data = null;
    
    if ($data !== null) {
        return $data;
    }
    
    // Get current version from cache if it exists
    $currentCacheVersion = 0;
    if (file_exists(CACHE_FILE)) {
        try {
            $cachedData = file_get_contents(CACHE_FILE);
            if (!empty($cachedData)) {
                $decodedData = json_decode($cachedData, true);
                if (is_array($decodedData) && isset($decodedData['version'])) {
                    $currentCacheVersion = $decodedData['version'];
                }
            }
        } catch (Exception $e) {
            error_log("Cache read error: " . $e->getMessage());
        }
    }
    
    // Check for cached data with version check
    if (FORCE_CACHE && file_exists(CACHE_FILE) && $currentCacheVersion == CACHE_VERSION) {
        try {
            $cachedData = file_get_contents(CACHE_FILE);
            if (!empty($cachedData)) {
                $decodedData = json_decode($cachedData, true);
                if (is_array($decodedData) && isset($decodedData['data']) && is_array($decodedData['data']) && !empty($decodedData['data'])) {
                    $data = $decodedData['data'];
                    return $data;
                }
            }
        } catch (Exception $e) {
            error_log("Cache read error: " . $e->getMessage());
            // Continue to generate new data if cache read fails
        }
    } else if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_TTL && $currentCacheVersion == CACHE_VERSION) {
        try {
            $cachedData = file_get_contents(CACHE_FILE);
            if (!empty($cachedData)) {
                $decodedData = json_decode($cachedData, true);
                if (is_array($decodedData) && isset($decodedData['data']) && is_array($decodedData['data']) && !empty($decodedData['data'])) {
                    $data = $decodedData['data'];
                    return $data;
                }
            }
        } catch (Exception $e) {
            error_log("Cache read error: " . $e->getMessage());
            // Continue to generate new data if cache read fails
        }
    }
    
    // Set a fixed seed for consistent random data
    mt_srand(12345);
    
    // Arrays of first and last names
    $firstNames = [
        "James", "Emma", "Liam", "Olivia", "Noah", "Ava", "William", "Sophia",
        "Michael", "Isabella", "Alexander", "Mia", "Daniel", "Charlotte", "Henry",
        "Benjamin", "Amelia", "Lucas", "Harper", "Mason", "Evelyn", "Ethan", "Abigail",
        "Samuel", "Emily", "Joseph", "Elizabeth", "David", "Sofia", "Carter", "Madison",
        "Owen", "Scarlett", "Wyatt", "Victoria", "John", "Camila", "Jack", "Aria",
        "Luke", "Grace", "Jayden", "Chloe", "Dylan", "Penelope", "Gabriel", "Layla",
        "Anthony", "Riley", "Isaac", "Zoey", "Grayson", "Nora", "Julian", "Lily",
        "Levi", "Eleanor", "Christopher", "Hannah", "Joshua", "Lillian", "Andrew", "Addison"
    ];
    $lastNames = [
        "Smith", "Johnson", "Brown", "Taylor", "Wilson", "Davis", "Clark", "Lewis",
        "Walker", "Hall", "Allen", "Young", "King", "Wright", "Scott", "Green",
        "Baker", "Adams", "Nelson", "Hill", "Richards", "Turner", "Morgan", "Cooper",
        "Peterson", "Bailey", "Reed", "Kelly", "Howard", "Ramos", "Kim", "Cox",
        "Ward", "Richardson", "Watson", "Brooks", "Chavez", "Wood", "James", "Bennett",
        "Gray", "Hughes", "Price", "Sanders", "Patel", "Myers", "Long", "Ross",
        "Foster", "Powell", "Jenkins", "Perry", "Russell", "Sullivan", "Bell", "Coleman",
        "Butler", "Henderson", "Barnes", "Gonzales", "Fisher", "Vasquez", "Simmons", "Romero"
    ];
    
    // Generate new data
    $data = [];
    $departments = ["HR", "Finance", "InfoTech", "Marketing", "Sales", "Operations", "Support", "Research"];
    
    for ($i = 0; $i < 100; $i++) {
        // Use prime number based deterministic selection for more varied but consistent naming
        $seed = $i * 47 + 13;
        $firstNameIndex = $seed % count($firstNames);
        $lastNameIndex = ($seed * 31) % count($lastNames);
        $departmentIndex = ($seed * 17) % count($departments);
        
        $firstName = $firstNames[$firstNameIndex];
        $lastName = $lastNames[$lastNameIndex];
        $fullName = trim("$firstName $lastName"); // Ensure no extra spaces
        
        // Use consistent seed for each record based on its index
        $seedForRecord = 12345 + $i;
        mt_srand($seedForRecord);
        
        // Generate deterministic random values for all fields using better prime number based hashing
        $randomNum = 100 + ($seed % 900); // Email number suffix
        $age = 20 + ($seed % 50);
        $salary = 40000 + (($seed * 937) % 80001);
        $bonus = 1000 + (($seed * 673) % 9001);
        $performance = 1 + (($seed * 13 + $i * 7) % 100); // More variation
        $overtimeHours = ($seed * 3 + $i * 5) % 51; // More variation
        $projectsCompleted = ($seed * 7 + $i * 11) % 21; // More variation
        
        // Use a completely different approach for satisfaction_score for maximum variation
        // Combine multiple factors to create a distribution across all pages
        $scoreFactor1 = $i % 10;
        $scoreFactor2 = ($seed * 29) % 10;
        $scoreFactor3 = (($i * $seed) % 97) % 10;
        // Pick one of the factors based on another calculation
        $pickFactor = ($i * 3 + $seed) % 3;
        $satisfactionFactors = [$scoreFactor1, $scoreFactor2, $scoreFactor3];
        $satisfactionScore = 1 + $satisfactionFactors[$pickFactor];
        
        $remoteWorkDays = ($seed * 11 + $i * 17) % 366; // More variation
        $trainingHours = ($seed * 5 + $i * 13) % 101; // More variation
        
        // Generate a deterministic hire date with more variation
        $yearOffset = 1 + ($seed % 10); // 1-10 years ago
        $monthOffset = $seed % 12; // 0-11 months
        $dayOffset = $seed % 28; // 0-27 days
        $hireTimestamp = strtotime("-$yearOffset years -$monthOffset months -$dayOffset days");
        $hireDate = date("Y-m-d", $hireTimestamp);
        
        // Ensure email is randomized but consistent
        $emailName = strtolower(str_replace(' ', '.', $firstName));
        $emailSurname = strtolower(str_replace(' ', '', $lastName));
        $email = $emailName . "." . $emailSurname . $randomNum . "@example.com";
        
        $data[] = [
            'id' => 1001 + ($i * 10),
            'name' => $fullName,
            'email' => $email,
            'age' => $age,
            'salary' => $salary,
            'bonus' => $bonus,
            'performance' => $performance,
            'department' => $departments[$departmentIndex],
            'hire_date' => $hireDate,
            'overtime_hours' => $overtimeHours,
            'projects_completed' => $projectsCompleted,
            'satisfaction_score' => $satisfactionScore,
            'remote_work_days' => $remoteWorkDays,
            'training_hours' => $trainingHours
        ];
    }
    
    // Cache the data with proper directory permissions
    $cacheDir = dirname(CACHE_FILE);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0777, true);
    }
    
    try {
        $jsonData = json_encode(['version' => CACHE_VERSION, 'data' => $data]);
        if ($jsonData === false) {
            throw new Exception("JSON encoding failed");
        }
        
        $writeResult = file_put_contents(CACHE_FILE, $jsonData, LOCK_EX);
        if ($writeResult === false) {
            throw new Exception("Failed to write cache file");
        }
    } catch (Exception $e) {
        error_log("Cache write error: " . $e->getMessage());
        // Continue even if cache write fails
    }
    
    // Reset the random seed to a system state to avoid affecting other PHP random functions
    mt_srand((double) microtime() * 1000000);
    
    return $data;
}

// Process data with filters, sorting, and pagination
function processData($data, $page, $perPage, $search = '', $sortColumn = null, $sortDirection = null) {
    // Start with the full dataset
    $filteredData = $data;

    // Define numeric fields for comparison and sorting
    $numericFields = ['id', 'age', 'salary', 'bonus', 'performance', 'overtime_hours', 'projects_completed', 'satisfaction_score', 'remote_work_days', 'training_hours'];

    // Define date fields
    $dateFields = ['hire_date'];

    // Apply search filter
    if (!empty($search)) {
        $search = trim(strtolower($search));
        $filteredData = array_filter($data, function($item) use ($search) {
            foreach ($item as $key => $value) {
                if (is_array($value) || is_object($value)) continue;
                if (strpos(strtolower((string)$value), $search) !== false) {
                    return true;
                }
            }
            return false;
        });
        $filteredData = array_values($filteredData); // Reindex the array
    }

    // Calculate total after filtering
    $total = count($filteredData);

    // Calculate max page based on filtered data
    $maxPage = $total > 0 ? ceil($total / $perPage) : 1;
    
    // Ensure requested page is within bounds
    $page = max(1, min($page, $maxPage));

    // Apply sorting to the filtered data
    if ($sortColumn !== null && in_array($sortDirection, ['asc', 'desc'])) {
        $columnMap = [
            0 => 'id',
            1 => 'name',
            2 => 'age',
            3 => 'salary',
            4 => 'bonus',
            5 => 'performance',
            6 => 'department',
            7 => 'hire_date',
            8 => 'overtime_hours',
            9 => 'projects_completed',
            10 => 'satisfaction_score',
            11 => 'remote_work_days',
            12 => 'training_hours'
        ];
        
        $sortKey = isset($columnMap[$sortColumn]) ? $columnMap[$sortColumn] : 'id';
        
        error_log("Sorting by column index: {$sortColumn}, key: {$sortKey}, direction: {$sortDirection}");

        usort($filteredData, function($a, $b) use ($sortKey, $sortDirection, $numericFields, $dateFields) {
            $valA = $a[$sortKey];
            $valB = $b[$sortKey];

            // Handle date sorting
            if (in_array($sortKey, $dateFields)) {
                $dateA = strtotime($valA);
                $dateB = strtotime($valB);
                if ($dateA === false) $dateA = 0;
                if ($dateB === false) $dateB = 0;
                return $sortDirection === 'asc' ? $dateA - $dateB : $dateB - $dateA;
            }

            // Handle numeric sorting
            if (in_array($sortKey, $numericFields)) {
                $numA = (float)$valA;
                $numB = (float)$valB;
                return $sortDirection === 'asc' ? $numA - $numB : $numB - $numA;
            }

            // Handle string sorting (case insensitive)
            $strA = strtolower(trim((string)$valA));
            $strB = strtolower(trim((string)$valB));
            error_log("Comparing: '$strA' vs '$strB' for sortKey '$sortKey'");
            return $sortDirection === 'asc' ? strcmp($strA, $strB) : strcmp($strB, $strA);
        });
    }
    
    // Apply pagination with strict bounds
    $start = ($page - 1) * $perPage;
    $paginatedData = array_slice($filteredData, $start, $perPage);
    
    // Debug info
    error_log("Search: '$search', Total: $total, Page: $page, PerPage: $perPage, Start: $start, Returned: " . count($paginatedData));

    return [
        'data' => $paginatedData,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage
    ];
}

// Get and sanitize query parameters
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = min(MAX_PER_PAGE, max(1, filter_input(INPUT_GET, 'perPage', FILTER_VALIDATE_INT) ?: 10));
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: '';
$sortColumn = filter_input(INPUT_GET, 'sortColumn', FILTER_VALIDATE_INT);
$sortDirection = filter_input(INPUT_GET, 'sortDirection', FILTER_SANITIZE_STRING);
$scenario = filter_input(INPUT_GET, 'scenario', FILTER_SANITIZE_STRING) ?: 'success';

// Process request
try {
    $mockData = getMockData();
    
    switch (strtolower($scenario)) {
        case 'error':
            sleep(1);
            http_response_code(500);
            echo json_encode(['data' => [], 'total' => 0, 'error' => 'Server error occurred']);
            break;

        case 'timeout':
            sleep(15);
            $response = processData($mockData, $page, $perPage, $search, $sortColumn, $sortDirection);
            http_response_code(200);
            echo json_encode($response);
            break;

        case 'success':
        default:
            $response = processData($mockData, $page, $perPage, $search, $sortColumn, $sortDirection);
            http_response_code(200);
            echo json_encode($response);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['data' => [], 'total' => 0, 'error' => 'Internal server error']);
}

// Clean up memory
unset($mockData);
?>