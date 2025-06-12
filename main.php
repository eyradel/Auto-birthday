<?php
// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
// Uncomment and use this if you're storing data in a database
// include("includes/conn.php");

// Configuration for Mnotify SMS API


// Get current date and time
$today = date("d/m");
$current_month = date("n");
$current_day = date("j");
$current_date = date("Y-m-d");
$current_hour = (int)date("G"); // 24-hour format (0-23)

// Path to CSV file
$csv_file = "Birthdays_up.csv";

// Path to the flag file indicating messages were sent today
$flag_file = "sms_sent_" . date("Y-m-d") . ".flag";

// Variables to store data for displaying in dashboard
$total_birthdays_today = 0;
$successful_messages = 0;
$failed_messages = 0;
$birthdays_today = [];
$upcoming_birthdays = [];
$all_people = [];

// Function to send SMS
function sendBirthdaySMS($phone, $name, $key, $sender_id) {
    // Format the message
    $msg = "Happy Birthday $name! 🎂 Wishing you a fantastic day filled with joy and celebration. From all of us at 4th-IR.";
    
    // URL encode the message
    $msg = urlencode($msg);
    
    // Format the phone number if needed
    // Remove any leading zeros and ensure proper format
    $phone = ltrim($phone, '0');
    
    // Construct the API URL
    $url = "https://apps.mnotify.net/smsapi?key=$key&to=$phone&msg=$msg&sender_id=$sender_id";
    
    // Send the request
    $response = file_get_contents($url);
    
    return $response;
}

// Function to calculate days until birthday
function daysUntilBirthday($month, $day) {
    $today = new DateTime();
    $birthday = new DateTime();
    $birthday->setDate($today->format('Y'), $month, $day);
    
    // If birthday has passed this year, use next year's date
    if ($birthday < $today) {
        $birthday->setDate($today->format('Y') + 1, $month, $day);
    }
    
    $diff = $today->diff($birthday);
    return $diff->days;
}

// Check if form was submitted to send manual messages
$manual_sent = false;
$manual_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_manual'])) {
    if (isset($_POST['selected_people']) && !empty($_POST['selected_people'])) {
        $selected_ids = $_POST['selected_people'];
        $custom_message = isset($_POST['custom_message']) ? $_POST['custom_message'] : "Happy Birthday from 4th-IR! 🎂";
        
        $manual_success = 0;
        $manual_failed = 0;
        
        // Process the CSV to find the selected people
        $file = fopen($csv_file, "r");
        fgetcsv($file); // Skip header
        
        $index = 0;
        while (($data = fgetcsv($file)) !== FALSE) {
            if (in_array($index, $selected_ids)) {
                $phone = $data[4];
                $name = $data[0];
                
                // Send the custom message
                $msg = str_replace("{name}", $name, $custom_message);
                $phone = ltrim($phone, '0');
                $msg_encoded = urlencode($msg);
                
                $url = "https://apps.mnotify.net/smsapi?key=$key&to=$phone&msg=$msg_encoded&sender_id=$sender_id";
                $response = file_get_contents($url);
                
                if (strpos($response, '"code":"1000"') !== false) {
                    $manual_success++;
                } else {
                    $manual_failed++;
                }
            }
            $index++;
        }
        fclose($file);
        
        $manual_sent = true;
        $manual_status = "Sent $manual_success messages successfully. Failed: $manual_failed";
    } else {
        $manual_status = "No recipients selected!";
    }
}

// Process birthday data loading for display
// Check if file exists
if (!file_exists($csv_file)) {
    die("Error: CSV file not found!");
}

// Open and read the CSV file
$file = fopen($csv_file, "r");
if (!$file) {
    die("Error: Unable to open the CSV file!");
}

// Skip the header row
fgetcsv($file);

// Log file
$log_file = "birthday_sms_log_" . date("Y-m-d") . ".txt";
$log = fopen($log_file, "a");
fwrite($log, "=== Birthday SMS Log " . date("Y-m-d H:i:s") . " ===\n");

// Process each row in the CSV
$index = 0;
while (($data = fgetcsv($file)) !== FALSE) {
    // Extract data
    $first_name = $data[0];
    $last_name = $data[1];
    $birth_date = $data[2];
    $email = $data[3];
    $mobile = $data[4];
    $month = intval($data[5]);
    $day = intval($data[6]);
    
    $full_name = trim($first_name . " " . $last_name);
    
    // Store all people for the directory
    $all_people[] = [
        'id' => $index,
        'name' => $full_name,
        'email' => $email,
        'phone' => $mobile,
        'birth_date' => $birth_date,
        'month' => $month,
        'day' => $day
    ];
    
    // Check if today is the person's birthday
    if ($month == $current_month && $day == $current_day) {
        $total_birthdays_today++;
        
        // Add to birthdays today array for display
        $birthdays_today[] = [
            'name' => $full_name,
            'email' => $email,
            'phone' => $mobile,
            'birth_date' => $birth_date
        ];
        
        // Log the birthday
        fwrite($log, "Found birthday: $full_name, Phone: $mobile\n");
        
        // Determine if SMS should be sent
        $should_send_sms = false;
        
        // Check if it's 6am (hour is 6) and we haven't sent messages today yet
        if ($current_hour == 6 && !file_exists($flag_file) && !isset($_GET['manual'])) {
            $should_send_sms = true;
        }
        
        // Send SMS if conditions are met
        if ($should_send_sms) {
            $response = sendBirthdaySMS($mobile, $first_name, $key, $sender_id);
            
            // Log the response
            if (strpos($response, '"code":"1000"') !== false) {
                fwrite($log, "SUCCESS: SMS sent to $full_name ($mobile)\n");
                $successful_messages++;
            } else {
                fwrite($log, "FAILED: SMS to $full_name ($mobile). Response: $response\n");
                $failed_messages++;
            }
        }
    }
    
    // Check for upcoming birthdays (next 30 days)
    $days_until = daysUntilBirthday($month, $day);
    if ($days_until > 0 && $days_until <= 30) {
        $upcoming_birthdays[] = [
            'name' => $full_name,
            'email' => $email,
            'phone' => $mobile, 
            'birth_date' => $birth_date,
            'days_until' => $days_until
        ];
    }
    
    $index++;
}

// Sort upcoming birthdays by days until
usort($upcoming_birthdays, function($a, $b) {
    return $a['days_until'] - $b['days_until'];
});

// Close the CSV file
fclose($file);

// Create flag file if messages were sent at 6am
if ($current_hour == 6 && $successful_messages > 0 && !file_exists($flag_file)) {
    file_put_contents($flag_file, date('Y-m-d H:i:s'));
    fwrite($log, "Created flag file to prevent duplicate sending today\n");
}

// Log summary
fwrite($log, "\nSummary:\n");
fwrite($log, "Total birthdays today: $total_birthdays_today\n");
fwrite($log, "Successful messages: $successful_messages\n");
fwrite($log, "Failed messages: $failed_messages\n");
fwrite($log, "Current hour: $current_hour\n");
fwrite($log, "Messages sent: " . ($successful_messages > 0 ? "Yes" : "No") . "\n");
fwrite($log, "====================\n\n");
fclose($log);

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birthday SMS Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .birthday-cake {
            animation: bounce 2s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header - Improved for mobile responsiveness -->
        <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg">
            <div class="container mx-auto py-4 px-4 sm:py-6">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="flex items-center space-x-4 mb-2 sm:mb-0">
                        <i class="fas fa-birthday-cake text-2xl sm:text-3xl birthday-cake"></i>
                        <h1 class="text-xl sm:text-2xl font-bold">4th-IR Birthday SMS System</h1>
                    </div>
                    <div class="text-sm">
                        <p>Today: <?php echo date("F j, Y"); ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Navigation Tabs - Scrollable on mobile -->
        <nav class="bg-white shadow">
            <div class="container mx-auto px-2 sm:px-4">
                <div class="overflow-x-auto no-scrollbar">
                    <ul class="flex whitespace-nowrap">
                        <li>
                            <a href="?tab=dashboard" class="inline-block py-3 sm:py-4 px-3 sm:px-4 border-b-2 <?php echo $active_tab == 'dashboard' ? 'border-blue-500 text-blue-500' : 'border-transparent hover:text-blue-500'; ?>">
                                <i class="fas fa-chart-pie mr-1 sm:mr-2"></i><span class="hidden sm:inline">Dashboard</span>
                                <span class="inline sm:hidden">Home</span>
                            </a>
                        </li>
                        <li>
                            <a href="?tab=today" class="inline-block py-3 sm:py-4 px-3 sm:px-4 border-b-2 <?php echo $active_tab == 'today' ? 'border-blue-500 text-blue-500' : 'border-transparent hover:text-blue-500'; ?>">
                                <i class="fas fa-cake-candles mr-1 sm:mr-2"></i><span>Today</span>
                            </a>
                        </li>
                        <li>
                            <a href="?tab=upcoming" class="inline-block py-3 sm:py-4 px-3 sm:px-4 border-b-2 <?php echo $active_tab == 'upcoming' ? 'border-blue-500 text-blue-500' : 'border-transparent hover:text-blue-500'; ?>">
                                <i class="fas fa-calendar-alt mr-1 sm:mr-2"></i><span>Upcoming</span>
                            </a>
                        </li>
                        <li>
                            <a href="?tab=directory" class="inline-block py-3 sm:py-4 px-3 sm:px-4 border-b-2 <?php echo $active_tab == 'directory' ? 'border-blue-500 text-blue-500' : 'border-transparent hover:text-blue-500'; ?>">
                                <i class="fas fa-address-book mr-1 sm:mr-2"></i><span>Directory</span>
                            </a>
                        </li>
                        <li>
                            <a href="?tab=manual&manual=1" class="inline-block py-3 sm:py-4 px-3 sm:px-4 border-b-2 <?php echo $active_tab == 'manual' ? 'border-blue-500 text-blue-500' : 'border-transparent hover:text-blue-500'; ?>">
                                <i class="fas fa-paper-plane mr-1 sm:mr-2"></i><span>Send SMS</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-4 sm:py-6">
            <?php if ($active_tab == 'dashboard'): ?>
                <!-- Dashboard Tab - Improved for mobile -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
                        <div class="flex items-center">
                            <div class="p-2 sm:p-3 rounded-full bg-blue-100 text-blue-500">
                                <i class="fas fa-cake-candles text-xl sm:text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-xs sm:text-sm text-gray-500">Today's Birthdays</p>
                                <h3 class="text-2xl sm:text-3xl font-bold"><?php echo $total_birthdays_today; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
                        <div class="flex items-center">
                            <div class="p-2 sm:p-3 rounded-full bg-green-100 text-green-500">
                                <i class="fas fa-check-circle text-xl sm:text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-xs sm:text-sm text-gray-500">Successful Messages</p>
                                <h3 class="text-2xl sm:text-3xl font-bold"><?php echo $successful_messages; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
                        <div class="flex items-center">
                            <div class="p-2 sm:p-3 rounded-full bg-red-100 text-red-500">
                                <i class="fas fa-times-circle text-xl sm:text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-xs sm:text-sm text-gray-500">Failed Messages</p>
                                <h3 class="text-2xl sm:text-3xl font-bold"><?php echo $failed_messages; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Birthday Lists - Improved for mobile -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                    <!-- Today's Birthdays -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="border-b px-4 sm:px-6 py-3 sm:py-4">
                            <h3 class="text-base sm:text-lg font-medium">Today's Birthdays</h3>
                        </div>
                        <div class="p-4 sm:p-6">
                            <?php if (count($birthdays_today) > 0): ?>
                                <div class="space-y-3 sm:space-y-4">
                                    <?php foreach ($birthdays_today as $person): ?>
                                        <div class="flex items-center p-2 sm:p-3 border rounded-lg">
                                            <div class="p-2 rounded-full bg-blue-100 text-blue-500">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="ml-3 overflow-hidden">
                                                <h4 class="font-medium truncate"><?php echo $person['name']; ?></h4>
                                                <p class="text-xs sm:text-sm text-gray-500 truncate"><?php echo $person['email']; ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-500">No birthdays today!</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Upcoming Birthdays -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="border-b px-4 sm:px-6 py-3 sm:py-4">
                            <h3 class="text-base sm:text-lg font-medium">Upcoming Birthdays</h3>
                        </div>
                        <div class="p-4 sm:p-6">
                            <?php if (count($upcoming_birthdays) > 0): ?>
                                <div class="space-y-3 sm:space-y-4">
                                    <?php 
                                    // Display only first 5 upcoming
                                    $display_birthdays = array_slice($upcoming_birthdays, 0, 5);
                                    foreach ($display_birthdays as $person): 
                                    ?>
                                        <div class="flex items-center justify-between p-2 sm:p-3 border rounded-lg">
                                            <div class="flex items-center overflow-hidden">
                                                <div class="p-2 rounded-full bg-purple-100 text-purple-500 flex-shrink-0">
                                                    <i class="fas fa-calendar-day"></i>
                                                </div>
                                                <div class="ml-3 min-w-0">
                                                    <h4 class="font-medium truncate"><?php echo $person['name']; ?></h4>
                                                    <p class="text-xs sm:text-sm text-gray-500 truncate"><?php echo $person['birth_date']; ?></p>
                                                </div>
                                            </div>
                                            <div class="bg-yellow-100 text-yellow-800 py-1 px-2 sm:px-3 rounded-full text-xs whitespace-nowrap ml-2 flex-shrink-0">
                                                <?php echo $person['days_until'] == 1 ? 'Tomorrow' : 'In ' . $person['days_until'] . ' days'; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($upcoming_birthdays) > 5): ?>
                                    <div class="mt-4 text-center">
                                        <a href="?tab=upcoming" class="text-blue-500 hover:underline">View all upcoming</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-gray-500">No upcoming birthdays in the next 30 days!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- System Log - Improved for mobile -->
                <div class="mt-4 sm:mt-6 bg-white rounded-lg shadow">
                    <div class="border-b px-4 sm:px-6 py-3 sm:py-4">
                        <h3 class="text-base sm:text-lg font-medium">System Log</h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <p class="mb-2 text-sm"><strong>Log File:</strong> <?php echo $log_file; ?></p>
                        <pre class="bg-gray-100 p-3 sm:p-4 rounded-lg text-xs sm:text-sm overflow-x-auto max-h-40 sm:max-h-60"><?php 
                            if (file_exists($log_file)) {
                                echo htmlspecialchars(file_get_contents($log_file));
                            } else {
                                echo "No log file found.";
                            }
                        ?></pre>
                    </div>
                </div>
            <?php elseif ($active_tab == 'today'): ?>
                <!-- Today's Birthdays Tab - Improved for mobile -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-4 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row justify-between sm:items-center">
                        <h3 class="text-base sm:text-lg font-medium mb-2 sm:mb-0">Today's Birthdays (<?php echo $total_birthdays_today; ?>)</h3>
                        <button onclick="window.location.href='?tab=manual&manual=1'" class="bg-blue-500 hover:bg-blue-600 text-white py-1 sm:py-2 px-3 sm:px-4 rounded-lg text-sm">
                            <i class="fas fa-paper-plane mr-1 sm:mr-2"></i>Send Messages
                        </button>
                    </div>
                    <div class="p-4 sm:p-6">
                        <?php if (count($birthdays_today) > 0): ?>
                            <div class="overflow-x-auto -mx-4 sm:mx-0">
                                <div class="inline-block min-w-full sm:px-0 align-middle">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Date</th>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Email</th>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach($birthdays_today as $person): ?>
                                                <tr>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="p-1 sm:p-2 rounded-full bg-blue-100 text-blue-500">
                                                                <i class="fas fa-user text-xs sm:text-sm"></i>
                                                            </div>
                                                            <div class="ml-2 sm:ml-3">
                                                                <div class="text-xs sm:text-sm font-medium text-gray-900"><?php echo $person['name']; ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                        <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['birth_date']; ?></div>
                                                    </td>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                                        <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['email']; ?></div>
                                                    </td>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                        <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['phone']; ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 sm:py-10">
                                <div class="text-gray-400 mb-4">
                                    <i class="fas fa-calendar-times fa-3x sm:fa-4x"></i>
                                </div>
                                <h3 class="text-base sm:text-lg font-medium text-gray-900">No Birthdays Today</h3>
                                <p class="text-sm text-gray-500 mt-1">Check back tomorrow or visit upcoming birthdays!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($active_tab == 'upcoming'): ?>
                <!-- Upcoming Birthdays Tab - Improved for mobile -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-4 sm:px-6 py-3 sm:py-4">
                        <h3 class="text-base sm:text-lg font-medium">Upcoming Birthdays (Next 30 Days)</h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <?php if (count($upcoming_birthdays) > 0): ?>
                            <div class="overflow-x-auto -mx-4 sm:mx-0">
                                <div class="inline-block min-w-full sm:px-0 align-middle">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead>
                                            <tr>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Date</th>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Email</th>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Phone</th>
                                                <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach($upcoming_birthdays as $person): ?>
                                                <tr>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="p-1 sm:p-2 rounded-full bg-purple-100 text-purple-500">
                                                                <i class="fas fa-user text-xs sm:text-sm"></i>
                                                            </div>
                                                            <div class="ml-2 sm:ml-3">
                                                                <div class="text-xs sm:text-sm font-medium text-gray-900"><?php echo $person['name']; ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                        <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['birth_date']; ?></div>
                                                    </td>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                                        <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['email']; ?></div>
                                                    </td>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                                        <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['phone']; ?></div>
                                                    </td>
                                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $person['days_until'] <= 7 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?>">
                                                            <?php echo $person['days_until'] == 1 ? 'Tomorrow' : $person['days_until'] . ' days'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8 sm:py-10">
                                <div class="text-gray-400 mb-4">
                                    <i class="fas fa-calendar-times fa-3x sm:fa-4x"></i>
                                </div>
                                <h3 class="text-base sm:text-lg font-medium text-gray-900">No Upcoming Birthdays</h3>
                                <p class="text-sm text-gray-500 mt-1">No birthdays in the next 30 days!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($active_tab == 'directory'): ?>
                <!-- Directory Tab - Improved for mobile -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-4 sm:px-6 py-3 sm:py-4 flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-2 sm:space-y-0">
                        <h3 class="text-base sm:text-lg font-medium">Full Directory (<?php echo count($all_people); ?> people)</h3>
                        <div class="relative w-full sm:w-auto">
                            <input type="text" id="directorySearch" placeholder="Search..." class="w-full sm:w-auto pl-8 pr-3 py-1 sm:py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <div class="absolute left-2 top-1.5 sm:top-2.5 text-gray-400">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="overflow-x-auto -mx-4 sm:mx-0">
                            <div class="inline-block min-w-full sm:px-0 align-middle">
                                <table id="directoryTable" class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Date</th>
                                            <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Email</th>
                                            <th class="px-3 sm:px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($all_people as $person): ?>
                                            <tr class="search-item">
                                                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="p-1 sm:p-2 rounded-full bg-gray-100 text-gray-500">
                                                            <i class="fas fa-user text-xs sm:text-sm"></i>
                                                        </div>
                                                        <div class="ml-2 sm:ml-3">
                                                            <div class="text-xs sm:text-sm font-medium text-gray-900"><?php echo $person['name']; ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                    <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['birth_date']; ?></div>
                                                </td>
                                                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                                    <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['email']; ?></div>
                                                </td>
                                                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                    <div class="text-xs sm:text-sm text-gray-900"><?php echo $person['phone']; ?></div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($active_tab == 'manual'): ?>
                <!-- Manual SMS Tab - Improved for mobile -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b px-4 sm:px-6 py-3 sm:py-4">
                        <h3 class="text-base sm:text-lg font-medium">Send Manual Birthday Messages</h3>
                    </div>
                    <div class="p-4 sm:p-6">
                        <?php if ($manual_sent): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 sm:p-4 mb-4 sm:mb-6 text-sm">
                                <p><?php echo $manual_status; ?></p>
                            </div>
                        <?php elseif (!empty($manual_status)): ?>
                            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-3 sm:p-4 mb-4 sm:mb-6 text-sm">
                                <p><?php echo $manual_status; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="?tab=manual&manual=1">
                            <div class="mb-4 sm:mb-6">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="custom_message">
                                    Custom Message (use {name} to include recipient's name)
                                </label>
                                <textarea id="custom_message" name="custom_message" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 text-sm leading-tight focus:outline-none focus:shadow-outline">Happy Birthday {name}! 🎂 Wishing you a fantastic day filled with joy and celebration. From all of us at 4th-IR.</textarea>
                            </div>
                            
                            <div class="mb-4 sm:mb-6">
                                <label class="block text-gray-700 text-sm font-bold mb-2">
                                    Select Recipients
                                </label>
                                
                                <div class="mt-2 flex flex-wrap gap-2 mb-4">
                                    <button type="button" id="selectAllBtn" class="bg-blue-100 text-blue-700 py-1 px-2 sm:px-3 rounded-full text-xs">Select All</button>
                                    <button type="button" id="selectNoneBtn" class="bg-gray-100 text-gray-700 py-1 px-2 sm:px-3 rounded-full text-xs">Select None</button>
                                    <button type="button" id="selectTodayBtn" class="bg-green-100 text-green-700 py-1 px-2 sm:px-3 rounded-full text-xs">Today's Birthdays</button>
                                </div>
                                
                                <div class="bg-gray-50 rounded-lg p-3 sm:p-4 max-h-60 sm:max-h-96 overflow-y-auto">
                                    <div class="overflow-x-auto -mx-2 sm:mx-0">
                                        <table class="min-w-full">
                                            <thead>
                                                <tr>
                                                    <th class="w-6 sm:w-8"></th>
                                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Birth Date</th>
                                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">Phone</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_people as $index => $person): ?>
                                                    <?php 
                                                        $is_today = ($person['month'] == $current_month && $person['day'] == $current_day);
                                                    ?>
                                                    <tr class="<?php echo $is_today ? 'bg-green-50' : ''; ?>">
                                                        <td class="py-2">
                                                            <input type="checkbox" name="selected_people[]" value="<?php echo $index; ?>" class="person-checkbox" <?php echo $is_today ? 'checked' : ''; ?>>
                                                        </td>
                                                        <td class="px-2 py-2 text-xs sm:text-sm"><?php echo $person['name']; ?></td>
                                                        <td class="px-2 py-2 text-xs sm:text-sm"><?php echo $person['birth_date']; ?></td>
                                                        <td class="px-2 py-2 text-xs sm:text-sm hidden sm:table-cell"><?php echo $person['phone']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="send_manual" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1.5 sm:py-2 px-3 sm:px-4 rounded text-sm focus:outline-none focus:shadow-outline">
                                    <i class="fas fa-paper-plane mr-1 sm:mr-2"></i>Send Messages
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <!-- Footer - Improved for mobile -->
        <footer class="bg-white border-t py-3 sm:py-4 mt-auto">
            <div class="container mx-auto px-4">
                <div class="flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
                    <p class="text-xs sm:text-sm text-gray-600">© 2025 4th-IR Birthday SMS System</p>
                    <p class="text-xs sm:text-sm text-gray-600">Today: <?php echo date("F j, Y"); ?></p>
                </div>
            </div>
        </footer>
    </div>

    <!-- JavaScript - Improved for mobile responsiveness -->
    <script>
        // Directory search functionality - optimized
        document.getElementById('directorySearch')?.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#directoryTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Manual SMS selection buttons
        document.getElementById('selectAllBtn')?.addEventListener('click', function() {
            document.querySelectorAll('.person-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        });
        
        document.getElementById('selectNoneBtn')?.addEventListener('click', function() {
            document.querySelectorAll('.person-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
        
        document.getElementById('selectTodayBtn')?.addEventListener('click', function() {
            document.querySelectorAll('.person-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Today's birthdays are already marked with bg-green-50
            document.querySelectorAll('tr.bg-green-50 .person-checkbox').forEach(checkbox => {
                checkbox.checked = true;
            });
        });

        // Add a small delay after page load for any animations
        document.addEventListener('DOMContentLoaded', function() {
            // Check if on a mobile device
            const isMobile = window.matchMedia("(max-width: 640px)").matches;
            
            // Only show what fits in the viewport initially on mobile
            if (isMobile) {
                // Ensure tables don't break layout on small screens
                const tables = document.querySelectorAll('table');
                tables.forEach(table => {
                    table.classList.add('table-fixed');
                });
            }
        });
    </script>
</body>
</html>