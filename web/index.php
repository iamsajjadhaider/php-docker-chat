<?php
// Start session for storing the callsign
session_start();

// =========================================================================
// Configuration and Database Connection
// =========================================================================
$host = 'db';
$db_name = getenv('MYSQL_DATABASE');
$username = getenv('MYSQL_USER');
$password = getenv('MYSQL_PASSWORD');

// --- 1. Handle Callsign State ---

// Handle callsign submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['callsign'])) {
    $callsign_input = trim($_POST['callsign']);
    // Simple validation
    if (!empty($callsign_input) && strlen($callsign_input) <= 50) {
        $_SESSION['callsign'] = $callsign_input;
    }
    // Redirect to clear POST data
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$callsign = $_SESSION['callsign'] ?? null;

// If callsign is not set, we skip database interaction and jump to the HTML rendering.
if ($callsign) {
    
    // --- Database Connection (Only runs if $callsign is set) ---
    $max_retries = 10;
    $retry_count = 0;
    $db_conn = null;

    while ($retry_count < $max_retries) {
        try {
            $db_conn = new mysqli($host, $username, $password, $db_name);

            if ($db_conn->connect_error) {
                throw new Exception("Connection failed: " . $db_conn->connect_error);
            }
            break;

        } catch (Exception $e) {
            error_log("Attempt " . ($retry_count + 1) . " to connect to DB failed. Retrying in 2 seconds...");
            sleep(2);
            $retry_count++;
        }
    }

    if ($db_conn === null || $db_conn->connect_error) {
        die("<h1>Database Connection Failed</h1><p>Could not connect to the MySQL service after multiple retries. Check Docker logs.</p>");
    }

    // =========================================================================
    // Table Setup (Updated to include 'callsign')
    // =========================================================================
    $create_table_sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        callsign VARCHAR(50) NOT NULL,
        user_message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if (!$db_conn->query($create_table_sql)) {
        error_log("Error creating table: " . $db_conn->error);
    }
    
    // --- SCHEMA MIGRATION: Self-healing for missing 'callsign' column ---
    // The previous deployment might have created the table without 'callsign'.
    // We attempt to add the column. Use try/catch to safely ignore the exception
    // if the column already exists (MySQL Error Code 1060).
    try {
        $db_conn->query("ALTER TABLE messages ADD COLUMN callsign VARCHAR(50) NOT NULL DEFAULT 'unknown' AFTER id");
    } catch (mysqli_sql_exception $e) {
        // Only log errors if they are NOT "Duplicate column name" (1060)
        if ($e->getCode() !== 1060) {
            error_log("Migration error: " . $e->getMessage());
        }
    }


    // =========================================================================
    // Handle New Message Submission (Updated to save 'callsign')
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
        $user_message = trim($_POST['message']);

        if (!empty($user_message)) {
            // Prepare statement to prevent SQL injection and insert callsign
            $stmt = $db_conn->prepare("INSERT INTO messages (callsign, user_message) VALUES (?, ?)");
            $stmt->bind_param("ss", $callsign, $user_message);
            
            if ($stmt->execute()) {
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                error_log("Error inserting message: " . $stmt->error);
            }
            $stmt->close();
        }
    }


    // =========================================================================
    // Retrieve and Display Messages (Updated to retrieve 'callsign')
    // =========================================================================
    $messages = [];
    $select_sql = "SELECT callsign, user_message, created_at FROM messages ORDER BY created_at DESC";
    // This query is now safe because the migration block handles existing columns
    $result = $db_conn->query($select_sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $result->free();
    } else {
        error_log("Error retrieving messages: " . $db_conn->error);
    }

    $db_conn->close();
} // End of if ($callsign) block

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Two-Tier Chat App</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --secondary: #6366f1;
            --text: #1f2937;
            --background: #f3f4f6;
            --card-bg: #ffffff;
            --self-bg: #dbeafe; /* Blue-50 */
            --self-text: #1e40af; /* Blue-800 */
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            display: flex;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .container {
            width: 100%;
            max-width: 600px;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 90vh;
            min-height: 400px;
        }
        .header {
            background-color: var(--primary);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.25rem;
            text-align: center;
        }
        .message-area {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column-reverse;
        }
        .message {
            margin-bottom: 10px;
            padding: 10px 15px;
            border-radius: 18px;
            max-width: 85%;
            word-wrap: break-word;
            align-self: flex-start;
            background-color: #e5e7eb;
            color: var(--text);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            font-size: 0.95rem;
        }
        /* Style for the user's own messages */
        .message.self-message {
            background-color: var(--self-bg);
            align-self: flex-end; /* Align to the right */
        }
        .message-callsign {
            display: block;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--primary);
        }
        .message.self-message .message-callsign {
            color: var(--secondary);
        }
        .message-timestamp {
            display: block;
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 5px;
            text-align: right;
        }
        .input-area {
            padding: 20px;
            border-top: 1px solid #e5e7eb;
            background-color: #fafafa;
        }
        .input-form {
            display: flex;
            gap: 10px;
        }
        .input-form textarea {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            resize: vertical; /* Allow vertical resize */
            min-height: 40px; 
            max-height: 100px;
            font-size: 1rem;
            line-height: 1.5;
            transition: border-color 0.2s;
        }
        .input-form textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        .input-form button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s, transform 0.1s;
        }
        /* Match button height to textarea height when it is single line */
        .input-form button {
            align-self: flex-start;
            height: 40px;
        }
        .input-form button:hover {
            background-color: var(--secondary);
        }
        .input-form button:active {
            transform: scale(0.98);
        }
        /* Callsign specific styles */
        .callsign-container {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            text-align: center;
        }
        .callsign-form input[type="text"] {
            padding: 12px;
            border: 2px solid #ccc;
            border-radius: 10px;
            width: 80%;
            max-width: 300px;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .callsign-form button {
            width: 80%;
            max-width: 300px;
            padding: 12px;
            height: auto;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .container {
                border-radius: 0;
                height: 100vh;
                max-width: 100%;
            }
            .input-form {
                gap: 5px;
            }
            .input-form button {
                padding: 10px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <?php if (!$callsign): ?>
        <!-- CALLSIGN INPUT SCREEN -->
        <div class="header">
            Welcome, Pilot!
        </div>
        <div class="callsign-container">
            <h2 style="color: var(--primary); margin-bottom: 5px;">Identify Yourself</h2>
            <p style="color: #6b7280; margin-bottom: 30px;">Please enter your unique callsign to begin chatting.</p>
            <form method="POST" action="" class="callsign-form">
                <input type="text" name="callsign" placeholder="e.g., EchoDelta7" required minlength="3" maxlength="50">
                <button type="submit">Start Chatting</button>
            </form>
        </div>

    <?php else: ?>
        <!-- FULL CHAT INTERFACE -->
        <div class="header">
            Two-Tier Chat (Pilot: <?= htmlspecialchars($callsign) ?>)
        </div>

        <!-- Message Display Area -->
        <div class="message-area">
            <?php if (empty($messages)): ?>
                <p style="text-align: center; color: #9ca3af; margin-top: 10px;">No messages yet. Start transmission!</p>
            <?php else: ?>
                <?php foreach ($messages as $message): 
                    // Format the timestamp to be more readable
                    $timestamp = date("M j, Y, g:i A", strtotime($message['created_at']));
                    // Check if the message belongs to the current user for styling
                    $is_self = $message['callsign'] === $callsign;
                ?>
                    <div class="message <?= $is_self ? 'self-message' : '' ?>">
                        <span class="message-callsign"><?= htmlspecialchars($message['callsign']) ?>:</span> 
                        <?= htmlspecialchars($message['user_message']) ?>
                        <span class="message-timestamp"><?= $timestamp ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Input Form -->
        <div class="input-area">
            <form id="chatForm" class="input-form" method="POST" action="">
                <textarea id="messageInput" name="message" placeholder="Type your message and press Enter to send..." required></textarea>
                <button type="submit">Send</button>
            </form>
            <p style="font-size: 0.75rem; color: #6b7280; margin-top: 10px; text-align: center;">Press Enter to send (Shift+Enter for a new line).</p>
        </div>

        <script>
            // JavaScript for Enter Key Submission
            document.addEventListener('DOMContentLoaded', () => {
                const messageInput = document.getElementById('messageInput');
                const chatForm = document.getElementById('chatForm');

                messageInput.addEventListener('keydown', (event) => {
                    // Check for Enter key press (key code 13 or key name 'Enter') AND ensure Shift key is NOT pressed
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault(); // Prevent default newline insertion
                        
                        // Submit the form only if there is content
                        if (messageInput.value.trim().length > 0) {
                            chatForm.submit();
                        }
                    }
                });
            });
        </script>
    <?php endif; ?>
</div>

</body>
</html>
