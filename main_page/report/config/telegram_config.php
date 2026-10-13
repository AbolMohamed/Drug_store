<?php
/**
 * Telegram Bot Configuration
 * 
 * Instructions:
 * 1. Create a Telegram bot by messaging @BotFather
 * 2. Get your bot token from BotFather
 * 3. Send a message to your bot and get the chat ID from:
 *    https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
 * 4. Configure your bot through the web interface
 */

/**
 * Load user-configured Telegram settings
 */
function loadTelegramConfig() {
    $configFile = __DIR__ . '/user_telegram_config.json';
    
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return [
            'bot_token' => $config['bot_token'] ?? '',
            'chat_id' => $config['chat_id'] ?? ''
        ];
    }
    
    return [
        'bot_token' => '',
        'chat_id' => ''
    ];
}

/**
 * Save user-configured Telegram settings
 */
function saveTelegramConfig($botToken, $chatId) {
    $configFile = __DIR__ . '/user_telegram_config.json';
    
    $config = [
        'bot_token' => $botToken,
        'chat_id' => $chatId,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT)) !== false;
}

// Load configuration
$telegramConfig = loadTelegramConfig();

// Telegram Bot Token (user configured)
define('TELEGRAM_BOT_TOKEN', $telegramConfig['bot_token']);

// Telegram Chat ID (user configured)
define('TELEGRAM_CHAT_ID', $telegramConfig['chat_id']);

// Telegram API Base URL
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN);

/**
 * Send message to Telegram
 */
function sendTelegramMessage($message, $chatId = null) {
    if (!$chatId) {
        $chatId = TELEGRAM_CHAT_ID;
    }
    
    if (!TELEGRAM_BOT_TOKEN || TELEGRAM_BOT_TOKEN === 'your_bot_token_here') {
        return ['success' => false, 'error' => 'Bot token not configured'];
    }
    
    if (!$chatId || $chatId === 'your_chat_id_here') {
        return ['success' => false, 'error' => 'Chat ID not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/sendMessage';
    
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'cURL error: ' . $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['ok']) && $responseData['ok']) {
        return ['success' => true, 'data' => $responseData];
    } else {
        $errorMsg = isset($responseData['description']) ? $responseData['description'] : 'Unknown error';
        return ['success' => false, 'error' => 'Telegram API error: ' . $errorMsg];
    }
}

/**
 * Test Telegram connection
 */
function testTelegramConnection() {
    if (!TELEGRAM_BOT_TOKEN || TELEGRAM_BOT_TOKEN === 'your_bot_token_here') {
        return ['success' => false, 'error' => 'Bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/getMe';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => 'cURL error: ' . $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['ok']) && $responseData['ok']) {
        return [
            'success' => true, 
            'bot_name' => $responseData['result']['first_name'],
            'bot_username' => $responseData['result']['username']
        ];
    } else {
        $errorMsg = isset($responseData['description']) ? $responseData['description'] : 'Unknown error';
        return ['success' => false, 'error' => 'Telegram API error: ' . $errorMsg];
    }
}

/**
 * Get chat information
 */
function getTelegramChatInfo($chatId = null) {
    if (!$chatId) {
        $chatId = TELEGRAM_CHAT_ID;
    }
    
    if (!TELEGRAM_BOT_TOKEN || TELEGRAM_BOT_TOKEN === 'your_bot_token_here') {
        return ['success' => false, 'error' => 'Bot token not configured'];
    }
    
    $url = TELEGRAM_API_URL . '/getChat?chat_id=' . $chatId;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['ok']) && $responseData['ok']) {
        return ['success' => true, 'data' => $responseData['result']];
    } else {
        $errorMsg = isset($responseData['description']) ? $responseData['description'] : 'Unknown error';
        return ['success' => false, 'error' => 'Telegram API error: ' . $errorMsg];
    }
}
?>