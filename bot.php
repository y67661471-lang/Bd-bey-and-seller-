<?php

declare(strict_types=1);

if (!defined('BOT_TOKEN') || !defined('BOT_USERNAME')) {
    http_response_code(503);
    exit('Runtime credentials unavailable');
}

// ------------------------------------------------------------------
// CONFIGURATION
// ------------------------------------------------------------------
const ADMIN_ID = 123456789; // আপনার টেলিগ্রাম আইডি দিন

const BUY_RATE  = 130.00; // ক্রয় রেট
const SELL_RATE = 122.00; // বিক্রয় রেট
const REFERRAL_COMMISSION_PERCENT = 10;

// Admin Addresses & Payment Numbers
const ADMIN_BEP20_ADDRESS  = '0xbe93322399a0dd0420cc2503218c4faedf37f0a5';
const ADMIN_BKASH_NUMBER  = '01879010845';
const ADMIN_NAGAD_NUMBER  = '01784678672';

const OFFICIAL_CHANNEL_URL = 'https://t.me/INCOME_BAZAAR';
const SUPPORT_ADMIN_URL    = 'https://t.me/INCOME_BAZAAR';

// Work / Account Sell Links
const FB_SELL_URL          = 'https://t.me/Nexora_Up_bot?start=7137870877';
const INSTA_SELL_URL       = 'https://t.me/Nexora_Up_bot?start=7137870877';
const WA_SELL_URL          = 'https://t.me/ALL_WS_Sell_BOT?start=ref_7137870877';
const FREE_NUMBER_BOT_URL  = 'https://t.me/Nexora_Up_Official_bot?start=7137870877';

const STORAGE_FILE = __DIR__ . '/bot_data.json';

// ------------------------------------------------------------------
// HELPER FUNCTIONS
// ------------------------------------------------------------------

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function loadData(): array
{
    if (!file_exists(STORAGE_FILE)) {
        return ['users' => [], 'orders' => [], 'next_order_id' => 1001];
    }
    $fp = fopen(STORAGE_FILE, 'r');
    if (!$fp) {
        return ['users' => [], 'orders' => [], 'next_order_id' => 1001];
    }
    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $decoded = json_decode($content ?: '', true);
    return is_array($decoded) ? $decoded : ['users' => [], 'orders' => [], 'next_order_id' => 1001];
}

function saveData(array $data): bool
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $tmpFile = STORAGE_FILE . '.tmp';
    $fp = fopen($tmpFile, 'w');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return rename($tmpFile, STORAGE_FILE);
}

function telegramApi(string $method, array $data = []): array
{
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $raw = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $payload = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $payload,
                'timeout' => 20,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
    }

    $decoded = json_decode(is_string($raw) ? $raw : '{}', true);
    return is_array($decoded) ? $decoded : ['ok' => false];
}

function getMainKeyboard(int $userId): array
{
    $keyboard = [
        [['text' => '💼 কাজ করে ইনকাম'], ['text' => '⚡ ডলার বিক্রয় (SELL) 🔴']],
        [['text' => '📊 লাইভ রেট (RATES) 📈'], ['text' => '💎 ডলার ক্রয় (BUY) 🟢']],
        [['text' => '👥 রেফারেল ইনকাম (Referral) 🎁'], ['text' => '👨‍💻 লাইভ সাপোর্ট (SUPPORT) 💬']]
    ];

    if ($userId === ADMIN_ID) {
        $keyboard[] = [['text' => '👑 অ্যাডমিন প্যানেল (ADMIN) ⚙️']];
    }

    return [
        'keyboard' => $keyboard,
        'resize_keyboard' => true,
        'persistent' => true
    ];
}

function getUsdAmountButtonsKeyboard(): array
{
    return [
        'keyboard' => [
            [['text' => '💵 0.50 USD'], ['text' => '💵 1.00 USD'], ['text' => '💵 2.00 USD']],
            [['text' => '💵 5.00 USD'], ['text' => '💵 10.00 USD'], ['text' => '💵 20.00 USD']],
            [['text' => '💵 50.00 USD'], ['text' => '💵 100.00 USD'], ['text' => '💵 500.00 USD']],
            [['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
}

function getBuyWalletsKeyboard(): array
{
    return [
        'keyboard' => [
            [['text' => '🟡 Binance BEP-20'], ['text' => '🛡️ Trust Wallet']],
            [['text' => '👛 Token Pocket'], ['text' => '📈 Bitget']],
            [['text' => '💎 TON Wallet'], ['text' => '🚀 Bybit Wallet']],
            [['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
}

function getSellPaymentMethodKeyboard(): array
{
    return [
        'keyboard' => [
            [['text' => '🟡 Binance BEP-20']],
            [['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
}

function getReceiveGatewayKeyboard(): array
{
    return [
        'keyboard' => [
            [['text' => '💗 bKash (বিকাশ)'], ['text' => '🟠 Nagad (নগদ)']],
            [['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
}

function getAccountTypeKeyboard(): array
{
    return [
        'keyboard' => [
            [['text' => '👤 Personal (পার্সোনাল)'], ['text' => '🏪 Agent (এজেন্ট)']],
            [['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
}

function clearUserState(int $userId): void
{
    $db = loadData();
    if (isset($db['users'][(string)$userId])) {
        unset($db['users'][(string)$userId]['state'], $db['users'][(string)$userId]['temp']);
        saveData($db);
    }
}

function parseAmount(string $input): float
{
    $clean = trim(str_replace(['USD', '$', 'usd', ' ', 'ডলার', '💵'], '', $input));
    return is_numeric($clean) ? floatval($clean) : 0.0;
}

// ------------------------------------------------------------------
// WEBHOOK HANDLING
// ------------------------------------------------------------------

$input = file_get_contents('php://input');
$update = json_decode($input ?: '{}', true);

if (!is_array($update)) {
    http_response_code(200);
    exit('OK');
}

$message = $update['message'] ?? null;
$callbackQuery = $update['callback_query'] ?? null;

// Callback Query (ইনলাইন বাটন)
if (is_array($callbackQuery) && isset($callbackQuery['id'], $callbackQuery['data'], $callbackQuery['from']['id'])) {
    $callbackId = (string)$callbackQuery['id'];
    $data = (string)$callbackQuery['data'];
    $chatId = (string)$callbackQuery['message']['chat']['id'];
    $fromId = (int)$callbackQuery['from']['id'];

    // BUY Proof Method Selection
    if ($data === 'buy_proof_screenshot' || $data === 'buy_proof_digits') {
        telegramApi('answerCallbackQuery', ['callback_query_id' => $callbackId]);
        $sUserId = (string)$fromId;
        $db = loadData();

        if ($data === 'buy_proof_screenshot') {
            $db['users'][$sUserId]['state'] = 'BUY_STEP_SCREENSHOT';
            saveData($db);

            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "📸 <b>টাকা পাঠানোর স্ক্রিনশটটি (Photo) এখানে পাঠান:</b>",
                'parse_mode' => 'HTML'
            ]);
        } else {
            $db['users'][$sUserId]['state'] = 'BUY_STEP_DIGITS';
            saveData($db);

            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "🔢 <b>আপনার বিকাশ/নগদ নাম্বারের লাস্ট ৪ ডিজিট (Last 4 Digits) লিখে পাঠান:</b>",
                'parse_mode' => 'HTML'
            ]);
        }
        http_response_code(200);
        exit('OK');
    }

    // Admin Callback Handling
    if ($fromId === ADMIN_ID && preg_match('/^(app|rej)_(\d+)$/', $data, $matches)) {
        $action = $matches[1];
        $orderId = (int)$matches[2];

        $db = loadData();
        $foundIndex = -1;

        foreach ($db['orders'] as $idx => $ord) {
            if ($ord['id'] === $orderId) {
                $foundIndex = $idx;
                break;
            }
        }

        if ($foundIndex !== -1 && $db['orders'][$foundIndex]['status'] === 'Pending') {
            if ($action === 'app') {
                $db['orders'][$foundIndex]['status'] = 'Completed';

                $orderUser = $db['users'][(string)$db['orders'][$foundIndex]['user_id']] ?? null;
                if ($orderUser && !empty($orderUser['referred_by'])) {
                    $referrerId = (string)$orderUser['referred_by'];
                    $commissionUsd = ($db['orders'][$foundIndex]['usd'] * REFERRAL_COMMISSION_PERCENT) / 100;

                    if (isset($db['users'][$referrerId])) {
                        $db['users'][$referrerId]['referral_earnings'] = ($db['users'][$referrerId]['referral_earnings'] ?? 0.0) + $commissionUsd;

                        $refEarnMsg = "🎉 <b>অভিনন্দন! ১০% রেফারেল কমিশন পেয়েছেন!</b>\n\n"
                            . "আপনার রেফার করা মেম্বার লেনদেন সম্পন্ন করেছেন।\n\n"
                            . "💵 <b>প্রাপ্ত কমিশন:</b> $" . number_format($commissionUsd, 2) . " USD";

                        telegramApi('sendMessage', [
                            'chat_id' => $referrerId,
                            'text' => $refEarnMsg,
                            'parse_mode' => 'HTML'
                        ]);
                    }
                }

                saveData($db);
                telegramApi('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => '✅ এপ্রুভ হয়েছে!']);

                telegramApi('sendMessage', [
                    'chat_id' => (string)$db['orders'][$foundIndex]['user_id'],
                    'text' => "🎉 <b>আপনার অর্ডারটি সম্পন্ন হয়েছে! ID: #" . $orderId . "</b>",
                    'parse_mode' => 'HTML',
                ]);
            } else {
                $db['orders'][$foundIndex]['status'] = 'Rejected';
                saveData($db);
                telegramApi('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => '❌ রিজেক্ট করা হয়েছে।']);
            }
        }
    }

    http_response_code(200);
    exit('OK');
}

// Message Processing
if (is_array($message) && isset($message['chat']['id'], $message['from']['id'])) {
    $chatId = (string)$message['chat']['id'];
    $userId = (int)$message['from']['id'];
    $text = trim((string)($message['text'] ?? ''));
    $photo = $message['photo'] ?? null;
    $sUserId = (string)$userId;

    $db = loadData();
    $isNewUser = !isset($db['users'][$sUserId]);

    if ($isNewUser) {
        $db['users'][$sUserId] = [
            'id' => $userId,
            'first_name' => $message['from']['first_name'] ?? 'User',
            'username' => $message['from']['username'] ?? '',
            'state' => null,
            'temp' => [],
            'referred_by' => null,
            'referral_earnings' => 0.0,
            'last_active' => time()
        ];
    } else {
        $db['users'][$sUserId]['last_active'] = time();
    }
    saveData($db);

    $userState = $db['users'][$sUserId]['state'] ?? null;

    if ($text === '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)') {
        clearUserState($userId);
        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "❌ <b>চলতি প্রসেসটি বাতিল করা হয়েছে।</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getMainKeyboard($userId))
        ]);
        http_response_code(200);
        exit('OK');
    }

    // /start Command Processing
    if (preg_match('~^/start(?:@' . preg_quote(BOT_USERNAME, '~') . ')?(?:\s+(.+))?$~i', $text, $startMatches)) {
        clearUserState($userId);

        $refPayload = trim($startMatches[1] ?? '');
        if ($isNewUser && $refPayload !== '' && is_numeric($refPayload)) {
            $referrerId = (int)$refPayload;
            if ($referrerId !== $userId && isset($db['users'][(string)$referrerId])) {
                $db['users'][$sUserId]['referred_by'] = $referrerId;
                saveData($db);

                $newUserMsg = "🔔 <b>নতুন রেফারেল জয়েন নোটিফিকেশন!</b>\n\n"
                    . "আপনার রেফারেল লিংক থেকে একজন ইউজার জয়েন করেছেন!";

                telegramApi('sendMessage', [
                    'chat_id' => (string)$referrerId,
                    'text' => $newUserMsg,
                    'parse_mode' => 'HTML'
                ]);
            }
        }

        $welcomeText = "🌟 <b>স্বাগতম Official_Admin_💰!</b>\n\n"
            . "👑 <b>অফিসিয়াল প্রিমিয়াম ডলার এক্সচেঞ্জ বোট</b>\n\n"
            . "👇 <i>নিচের অপশন থেকে আপনার সার্ভিস নির্বাচন করুন:</i>";

        $channelInlineButton = [
            'inline_keyboard' => [
                [['text' => '📢 আমাদের অফিসিয়াল চ্যানেল', 'url' => OFFICIAL_CHANNEL_URL]]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $welcomeText,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($channelInlineButton)
        ]);

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "স্মার্ট মেন্যু সচল করা হয়েছে:",
            'reply_markup' => json_encode(getMainKeyboard($userId))
        ]);

        http_response_code(200);
        exit('OK');
    }

    // 💼 কাজ করে ইনকাম (১০০% রিয়েল ইনকাম অপশন)
    if ($text === '💼 কাজ করে ইনকাম') {
        $workInlineButtons = [
            'inline_keyboard' => [
                [['text' => '📘 Facebook Sell (৭.৫০ ৳)', 'url' => FB_SELL_URL]],
                [['text' => '📸 Instagram Sell (৪.৮০ ৳)', 'url' => INSTA_SELL_URL]],
                [['text' => '💬 WhatsApp Sell (৯.০০ ৳)', 'url' => WA_SELL_URL]],
                [['text' => '🤖 ফ্রি নাম্বার বট (Free Bot)', 'url' => FREE_NUMBER_BOT_URL]]
            ]
        ];

        $workText = "💼 <b>কাজ করে ইনকাম ১০০%</b> ⚡\n\n"
            . "🔥 <b>লাইভ কাজের রেট তালিকা:</b>\n"
            . "------------------------------------\n"
            . "📘 <b>ফেসবুক সেল:</b> ৭.৫০ ৳ / অ্যাকাউন্ট\n"
            . "📸 <b>Instagram সেল:</b> ৪.৮০ ৳ / অ্যাকাউন্ট\n"
            . "💬 <b>WhatsApp সেল:</b> ৯.০০ ৳ / অ্যাকাউন্ট\n"
            . "------------------------------------\n\n"
            . "👇 <i>কাজ শুরু করতে নিচের পছন্দমতো বাটনে ক্লিক করুন:</i>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $workText,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($workInlineButtons)
        ]);
        http_response_code(200);
        exit('OK');
    }

    // 👨‍💻 লাইভ সাপোর্ট (SUPPORT) 💬
    if ($text === '👨‍💻 লাইভ সাপোর্ট (SUPPORT) 💬') {
        $supportInlineButtons = [
            'inline_keyboard' => [
                [['text' => '📢 অফিসিয়াল চ্যানেল', 'url' => OFFICIAL_CHANNEL_URL]],
                [['text' => '👨‍💻 সাপোর্ট এডমিন', 'url' => SUPPORT_ADMIN_URL]]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "👨‍💻 <b>লাইভ সাপোর্ট (SUPPORT) 💬</b>\n\n"
                . "যেকোনো সাহায্য বা যোগাযোগের জন্য নিচের বাটন সমূহে ক্লিক করুন:",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($supportInlineButtons)
        ]);
        http_response_code(200);
        exit('OK');
    }

    // 📊 লাইভ রেট (RATES) 📈
    if ($text === '📊 লাইভ রেট (RATES) 📈') {
        $rateMsg = "📊 <b>লাইভ রেট (RATES) 📈</b>\n\n"
            . "🟢 <b>ডলার ক্রয় (BUY):</b> " . number_format(BUY_RATE, 2) . " টাকা\n"
            . "🔴 <b>ডলার বিক্রয় (SELL):</b> " . number_format(SELL_RATE, 2) . " টাকা\n\n"
            . "⚡ <i>সর্বনিম্ন লেনদেন $0.50 USD।</i>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $rateMsg,
            'parse_mode' => 'HTML'
        ]);
        http_response_code(200);
        exit('OK');
    }

    // 💎 ডলার ক্রয় (BUY) - STEP 1
    if ($text === '💎 ডলার ক্রয় (BUY) 🟢') {
        $db['users'][$sUserId]['state'] = 'BUY_STEP_USD';
        $db['users'][$sUserId]['temp'] = ['type' => 'BUY'];
        saveData($db);

        $msg = "💎 <b>ডলার ক্রয় (BUY) 🟢</b>\n\n"
            . "বর্তমান ক্রয় রেট: <b>১ USD = " . BUY_RATE . " BDT</b>\n"
            . "কত ডলার কিনতে চান? (নিচের বাটন থেকে বেছে নিন অথবা টাইপ করুন):\n\n"
            . "<i>* সর্বনিম্ন পরিমাণ: $0.50 USD</i>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getUsdAmountButtonsKeyboard())
        ]);
        http_response_code(200);
        exit('OK');
    }

    // BUY STEP 2: USD Input & Wallet Selection
    if ($userState === 'BUY_STEP_USD') {
        $usd = parseAmount($text);
        if ($usd < 0.50) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> সর্বনিম্ন ক্রয়ের পরিমাণ $0.50 USD। সঠিক বাটন বেছে নিন।",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(getUsdAmountButtonsKeyboard())
            ]);
            http_response_code(200);
            exit('OK');
        }

        $totalBdt = $usd * BUY_RATE;
        $db['users'][$sUserId]['temp']['usd'] = $usd;
        $db['users'][$sUserId]['temp']['bdt'] = $totalBdt;
        $db['users'][$sUserId]['state'] = 'BUY_STEP_WALLET';
        saveData($db);

        $msg = "✅ আপনি নির্বাচন করেছেন: <b>$" . number_format($usd, 2) . " USD</b>\n"
            . "💵 আপনাকে পরিশোধ করতে হবে: <b>" . number_format($totalBdt, 2) . " BDT</b>\n\n"
            . "🏦 <b>কোন ওয়ালেটে ডলার রিসিভ করতে চান? সিলেক্ট করুন:</b>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getBuyWalletsKeyboard())
        ]);
        http_response_code(200);
        exit('OK');
    }

    // BUY STEP 3: Wallet Choice & Enter Address
    if ($userState === 'BUY_STEP_WALLET') {
        $validWallets = ['🟡 Binance BEP-20', '🛡️ Trust Wallet', '👛 Token Pocket', '📈 Bitget', '💎 TON Wallet', '🚀 Bybit Wallet'];

        if (!in_array($text, $validWallets, true)) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> নিচের বাটন থেকে একটি সঠিক ওয়ালেট সিলেক্ট করুন।",
                'reply_markup' => json_encode(getBuyWalletsKeyboard())
            ]);
            http_response_code(200);
            exit('OK');
        }

        $db['users'][$sUserId]['temp']['wallet'] = $text;
        $db['users'][$sUserId]['state'] = 'BUY_STEP_ADDRESS';
        saveData($db);

        $msg = "📥 <b>আপনার (" . e($text) . ") ওয়ালেট অ্যাড্রেস বা আইডি টাইপ করে পাঠান:</b>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['keyboard' => [[['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]], 'resize_keyboard' => true])
        ]);
        http_response_code(200);
        exit('OK');
    }

    // BUY STEP 4: Receive Address & Show Payment Notice
    if ($userState === 'BUY_STEP_ADDRESS') {
        if (strlen($text) < 5) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> একটি সঠিক ওয়ালেট অ্যাড্রেস লিখুন।"
            ]);
            http_response_code(200);
            exit('OK');
        }

        $db['users'][$sUserId]['temp']['receive_address'] = $text;
        $db['users'][$sUserId]['state'] = 'BUY_STEP_PROOF_CHOICE';
        saveData($db);

        $temp = $db['users'][$sUserId]['temp'];

        $noticeMsg = "📌 <b>পেমেন্ট করার বিস্তারিত নোটিশ:</b>\n\n"
            . "💵 <b>মোট ডলার:</b> $" . number_format($temp['usd'], 2) . " USD\n"
            . "💰 <b>প্রদেয় BDT:</b> " . number_format($temp['bdt'], 2) . " BDT\n"
            . "🏦 <b>ওয়ালেট:</b> " . e($temp['wallet']) . "\n"
            . "📍 <b>রিসিভ অ্যাড্রেস:</b> <code>" . e($text) . "</code>\n\n"
            . "------------------------------------\n"
            . "📱 <b>বিকাশ (Personal):</b> <code>" . ADMIN_BKASH_NUMBER . "</code>\n"
            . "📱 <b>নগদ (Personal):</b> <code>" . ADMIN_NAGAD_NUMBER . "</code>\n"
            . "------------------------------------\n\n"
            . "⚠️ <b>নির্দেশনা:</b>\n"
            . "উপরে দেওয়া নাম্বার সমূহে নিরাপদে <b>" . number_format($temp['bdt'], 2) . " BDT</b> সেন্ড মানি করুন। টাকা পাঠানোর পর নিচের বাটন থেকে যেকোনো একটি বেছে নিয়ে প্রুফ জমা দিন:";

        $proofChoiceButtons = [
            'inline_keyboard' => [
                [['text' => '📸 স্ক্রিনশট জমা দিন', 'callback_data' => 'buy_proof_screenshot']],
                [['text' => '🔢 বিকাশ/নগদ লাস্ট ৪ ডিজিট পাঠান', 'callback_data' => 'buy_proof_digits']]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $noticeMsg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($proofChoiceButtons)
        ]);
        http_response_code(200);
        exit('OK');
    }

    // BUY STEP 5A: Process Screenshot
    if ($userState === 'BUY_STEP_SCREENSHOT') {
        if (!is_array($photo)) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> টাকা পাঠানোর স্ক্রিনশট (Photo) হিসেবে জমা দিন।",
                'parse_mode' => 'HTML'
            ]);
            http_response_code(200);
            exit('OK');
        }

        $largestPhoto = end($photo);
        $temp = $db['users'][$sUserId]['temp'];
        $orderId = $db['next_order_id']++;

        $db['orders'][] = [
            'id' => $orderId,
            'user_id' => $userId,
            'type' => 'BUY',
            'usd' => $temp['usd'],
            'bdt' => $temp['bdt'],
            'wallet' => $temp['wallet'],
            'address' => $temp['receive_address'],
            'proof_type' => 'Screenshot',
            'status' => 'Pending',
            'date' => date('Y-m-d H:i:s')
        ];
        clearUserState($userId);

        $supportKb = [
            'inline_keyboard' => [
                [['text' => '👨‍💻 সাপোর্ট এডমিন', 'url' => SUPPORT_ADMIN_URL]]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "⏳ <b>আপনার বায় (BUY) অর্ডারটি রিভিউতে পাঠানো হয়েছে!</b>\n\n"
                . "<b>অর্ডার ID:</b> #" . $orderId . "\n"
                . "<b>ক্রয় ডলার:</b> $" . number_format($temp['usd'], 2) . " USD\n"
                . "অ্যাডমিন দ্রুত ভেরিফাই করে আপনার ওয়ালেটে ডলার পাঠিয়ে দেবেন। ধন্যবাদ!",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($supportKb)
        ]);

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "স্মার্ট মেনু:",
            'reply_markup' => json_encode(getMainKeyboard($userId))
        ]);

        $adminMsg = "🔔 <b>নতুন ক্রয় (BUY) অর্ডার প্রাপ্তি!</b>\n\n"
            . "🆔 <b>Order ID:</b> #" . $orderId . "\n"
            . "👤 <b>ইউজার:</b> " . e($message['from']['first_name'] ?? '') . " (@" . e($message['from']['username'] ?? 'N/A') . ")\n"
            . "💵 <b>ডলার:</b> $" . number_format($temp['usd'], 2) . " USD\n"
            . "💰 <b>প্রাপ্ত BDT:</b> " . number_format($temp['bdt'], 2) . " BDT\n"
            . "🏦 <b>ওয়ালেট:</b> " . e($temp['wallet']) . "\n"
            . "📍 <b>ইউজার অ্যাড্রেস:</b> <code>" . e($temp['receive_address']) . "</code>\n"
            . "📸 <b>প্রুফ টাইপ:</b> স্ক্রিনশট";

        $adminKb = ['inline_keyboard' => [[['text' => '✅ Approve', 'callback_data' => 'app_' . $orderId], ['text' => '❌ Reject', 'callback_data' => 'rej_' . $orderId]]]];

        telegramApi('sendPhoto', [
            'chat_id' => (string)ADMIN_ID,
            'photo' => $largestPhoto['file_id'],
            'caption' => $adminMsg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($adminKb)
        ]);

        http_response_code(200);
        exit('OK');
    }

    // BUY STEP 5B: Process 4 Digits
    if ($userState === 'BUY_STEP_DIGITS') {
        if (strlen($text) < 4) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> কমপক্ষে ৪ ডিজিট লিখুন।"
            ]);
            http_response_code(200);
            exit('OK');
        }

        $temp = $db['users'][$sUserId]['temp'];
        $orderId = $db['next_order_id']++;

        $db['orders'][] = [
            'id' => $orderId,
            'user_id' => $userId,
            'type' => 'BUY',
            'usd' => $temp['usd'],
            'bdt' => $temp['bdt'],
            'wallet' => $temp['wallet'],
            'address' => $temp['receive_address'],
            'proof_type' => 'Digits: ' . $text,
            'status' => 'Pending',
            'date' => date('Y-m-d H:i:s')
        ];
        clearUserState($userId);

        $supportKb = [
            'inline_keyboard' => [
                [['text' => '👨‍💻 সাপোর্ট এডমিন', 'url' => SUPPORT_ADMIN_URL]]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "⏳ <b>আপনার বায় (BUY) অর্ডারটি রিভিউতে পাঠানো হয়েছে!</b>\n\n"
                . "<b>অর্ডার ID:</b> #" . $orderId . "\n"
                . "<b>ক্রয় ডলার:</b> $" . number_format($temp['usd'], 2) . " USD\n"
                . "অ্যাডমিন দ্রুত ভেরিফাই করে আপনার ওয়ালেটে ডলার পাঠিয়ে দেবেন। ধন্যবাদ!",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($supportKb)
        ]);

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "স্মার্ট মেনু:",
            'reply_markup' => json_encode(getMainKeyboard($userId))
        ]);

        $adminMsg = "🔔 <b>নতুন ক্রয় (BUY) অর্ডার প্রাপ্তি!</b>\n\n"
            . "🆔 <b>Order ID:</b> #" . $orderId . "\n"
            . "👤 <b>ইউজার:</b> " . e($message['from']['first_name'] ?? '') . " (@" . e($message['from']['username'] ?? 'N/A') . ")\n"
            . "💵 <b>ডলার:</b> $" . number_format($temp['usd'], 2) . " USD\n"
            . "💰 <b>প্রাপ্ত BDT:</b> " . number_format($temp['bdt'], 2) . " BDT\n"
            . "🏦 <b>ওয়ালেট:</b> " . e($temp['wallet']) . "\n"
            . "📍 <b>ইউজার অ্যাড্রেস:</b> <code>" . e($temp['receive_address']) . "</code>\n"
            . "🔢 <b>পেমেন্ট ডিজিট:</b> <code>" . e($text) . "</code>";

        $adminKb = ['inline_keyboard' => [[['text' => '✅ Approve', 'callback_data' => 'app_' . $orderId], ['text' => '❌ Reject', 'callback_data' => 'rej_' . $orderId]]]];

        telegramApi('sendMessage', [
            'chat_id' => (string)ADMIN_ID,
            'text' => $adminMsg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($adminKb)
        ]);

        http_response_code(200);
        exit('OK');
    }

    // ⚡ ডলার বিক্রয় (SELL) - STEP 1
    if ($text === '⚡ ডলার বিক্রয় (SELL) 🔴') {
        $db['users'][$sUserId]['state'] = 'SELL_STEP_USD';
        $db['users'][$sUserId]['temp'] = ['type' => 'SELL'];
        saveData($db);

        $msg = "⚡ <b>ডলার বিক্রয় (SELL) 🔴</b>\n\n"
            . "বর্তমান বিক্রয় রেট: <b>১ USD = " . SELL_RATE . " BDT</b>\n"
            . "কত ডলার বিক্রি করতে চান? (নিচের বাটন থেকে বেছে নিন অথবা টাইপ করুন):\n\n"
            . "<i>* সর্বনিম্ন পরিমাণ: $0.50 USD</i>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getUsdAmountButtonsKeyboard())
        ]);
        http_response_code(200);
        exit('OK');
    }

    // SELL STEP 2: Amount & Method Selection
    if ($userState === 'SELL_STEP_USD') {
        $usd = parseAmount($text);
        if ($usd < 0.50) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> সর্বনিম্ন বিক্রয় পরিমাণ $0.50 USD।",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(getUsdAmountButtonsKeyboard())
            ]);
            http_response_code(200);
            exit('OK');
        }

        $totalBdt = $usd * SELL_RATE;
        $db['users'][$sUserId]['temp']['usd'] = $usd;
        $db['users'][$sUserId]['temp']['bdt'] = $totalBdt;
        $db['users'][$sUserId]['state'] = 'SELL_STEP_METHOD';
        saveData($db);

        $msg = "✅ আপনি নির্বাচন করেছেন: <b>$" . number_format($usd, 2) . " USD</b>\n"
            . "💰 আপনি পাবেন: <b>" . number_format($totalBdt, 2) . " BDT</b>\n\n"
            . "💳 <b>কোন মাধ্যমে ডলার পাঠাবেন? সিলেক্ট করুন:</b>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getSellPaymentMethodKeyboard())
        ]);
        http_response_code(200);
        exit('OK');
    }

    // SELL STEP 3: Show Address & Ask Screenshot
    if ($userState === 'SELL_STEP_METHOD') {
        if ($text !== '🟡 Binance BEP-20') {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ সঠিক ওয়ালেট বাটন সিলেক্ট করুন।",
                'reply_markup' => json_encode(getSellPaymentMethodKeyboard())
            ]);
            http_response_code(200);
            exit('OK');
        }

        $db['users'][$sUserId]['temp']['sell_method'] = $text;
        $db['users'][$sUserId]['state'] = 'SELL_STEP_SCREENSHOT';
        saveData($db);

        $msg = "📢 <b>Binance [BEP-20] পেমেন্ট নির্দেশিকা:</b>\n\n"
            . "আমাদের ওয়ালেটে <b>$" . number_format($db['users'][$sUserId]['temp']['usd'], 2) . " USD</b> পাঠাবেন:\n\n"
            . "📌 <b>BEP-20 Address:</b>\n<code>" . ADMIN_BEP20_ADDRESS . "</code>\n\n"
            . "⚠️ <b>বিশেষ নোটিশ:</b>\nডলার পাঠানোর পর ট্রানজেকশনের <b>স্ক্রিনশট (Photo) জমা দিন</b>।";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['keyboard' => [[['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]], 'resize_keyboard' => true])
        ]);
        http_response_code(200);
        exit('OK');
    }

    // SELL STEP 4: Process Screenshot -> Ask Receive Gateway
    if ($userState === 'SELL_STEP_SCREENSHOT') {
        if (!is_array($photo)) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> ডলার পাঠানোর ট্রানজেকশন স্ক্রিনশট (Photo) জমা দিন।",
                'parse_mode' => 'HTML'
            ]);
            http_response_code(200);
            exit('OK');
        }

        $largestPhoto = end($photo);
        $db['users'][$sUserId]['temp']['photo_file_id'] = $largestPhoto['file_id'];
        $db['users'][$sUserId]['state'] = 'SELL_STEP_GATEWAY';
        saveData($db);

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "✅ <b>স্ক্রিনশট জমা হয়েছে!</b>\n\n📱 <b>কিসের মাধ্যমে টাকা রিসিভ করতে চান?</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getReceiveGatewayKeyboard())
        ]);
        http_response_code(200);
        exit('OK');
    }

    // SELL STEP 5: Receive Gateway Choice -> Ask Account Type
    if ($userState === 'SELL_STEP_GATEWAY') {
        if ($text !== '💗 bKash (বিকাশ)' && $text !== '🟠 Nagad (নগদ)') {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ সঠিক বাটন থেকে সিলেক্ট করুন (bKash নাকি Nagad):",
                'reply_markup' => json_encode(getReceiveGatewayKeyboard())
            ]);
            http_response_code(200);
            exit('OK');
        }

        $db['users'][$sUserId]['temp']['receive_gateway'] = $text;
        $db['users'][$sUserId]['state'] = 'SELL_STEP_ACCT_TYPE';
        saveData($db);

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "⚙️ <b>আপনার অ্যাকাউন্টের টাইপ নির্বাচন করুন:</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getAccountTypeKeyboard())
        ]);
        http_response_code(200);
        exit('OK');
    }

    // SELL STEP 6: Account Type Choice -> Ask Phone Number
    if ($userState === 'SELL_STEP_ACCT_TYPE') {
        if ($text !== '👤 Personal (পার্সোনাল)' && $text !== '🏪 Agent (এজেন্ট)') {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ সঠিক বাটন নির্বাচন করুন (Personal নাকি Agent):",
                'reply_markup' => json_encode(getAccountTypeKeyboard())
            ]);
            http_response_code(200);
            exit('OK');
        }

        $db['users'][$sUserId]['temp']['account_type'] = $text;
        $db['users'][$sUserId]['state'] = 'SELL_STEP_PHONE';
        saveData($db);

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "📱 টাকা নেওয়ার জন্য আপনার <b>" . e($db['users'][$sUserId]['temp']['receive_gateway']) . " (" . e($text) . ") নম্বরটি লিখুন:</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['keyboard' => [[['text' => '🔙 প্রধান মেনুতে ফিরে যান (অর্ডার বাতিল)']]], 'resize_keyboard' => true])
        ]);
        http_response_code(200);
        exit('OK');
    }

    // SELL STEP 7: Save Order & Send Confirmation
    if ($userState === 'SELL_STEP_PHONE') {
        if (strlen($text) < 11) {
            telegramApi('sendMessage', [
                'chat_id' => $chatId,
                'text' => "⚠️ <b>দুঃখিত!</b> একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর লিখুন।",
                'parse_mode' => 'HTML'
            ]);
            http_response_code(200);
            exit('OK');
        }

        $temp = $db['users'][$sUserId]['temp'];
        $orderId = $db['next_order_id']++;

        $fullAccountInfo = $temp['receive_gateway'] . " (" . $temp['account_type'] . "): " . $text;

        $db['orders'][] = [
            'id' => $orderId,
            'user_id' => $userId,
            'type' => 'SELL',
            'usd' => $temp['usd'],
            'bdt' => $temp['bdt'],
            'method' => $temp['sell_method'] ?? '',
            'account' => $fullAccountInfo,
            'status' => 'Pending',
            'date' => date('Y-m-d H:i:s')
        ];
        clearUserState($userId);

        $supportKb = [
            'inline_keyboard' => [
                [['text' => '👨‍💻 সাপোর্ট এডমিন', 'url' => SUPPORT_ADMIN_URL]]
            ]
        ];

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "⏳ <b>আপনার সেল অর্ডারটি রিভিউতে জমা হয়েছে!</b>\n\n"
                . "<b>অর্ডার ID:</b> #" . $orderId . "\n"
                . "<b>বিক্রি ডলার:</b> $" . number_format($temp['usd'], 2) . " USD\n"
                . "<b>প্রাপ্য BDT:</b> " . number_format($temp['bdt'], 2) . " BDT\n"
                . "<b>অ্যাকাউন্ট:</b> " . e($fullAccountInfo) . "\n\n"
                . "অ্যাডমিন দ্রুত ভেরিফাই করে টাকা পাঠিয়ে দেবেন। ধন্যবাদ!",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($supportKb)
        ]);

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => "স্মার্ট মেনু:",
            'reply_markup' => json_encode(getMainKeyboard($userId))
        ]);

        $adminMsg = "🔔 <b>নতুন সেল (SELL) অর্ডার প্রাপ্তি!</b>\n\n"
            . "🆔 <b>Order ID:</b> #" . $orderId . "\n"
            . "👤 <b>ইউজার:</b> " . e($message['from']['first_name'] ?? '') . " (@" . e($message['from']['username'] ?? 'N/A') . ")\n"
            . "💵 <b>বিক্রি ডলার:</b> $" . number_format($temp['usd'], 2) . " USD\n"
            . "💰 <b>প্রদেয় BDT:</b> " . number_format($temp['bdt'], 2) . " BDT\n"
            . "📱 <b>অ্যাকাউন্ট:</b> <code>" . e($fullAccountInfo) . "</code>";

        $adminKb = ['inline_keyboard' => [[['text' => '✅ Approve', 'callback_data' => 'app_' . $orderId], ['text' => '❌ Reject', 'callback_data' => 'rej_' . $orderId]]]];

        telegramApi('sendPhoto', [
            'chat_id' => (string)ADMIN_ID,
            'photo' => $temp['photo_file_id'],
            'caption' => $adminMsg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($adminKb)
        ]);

        http_response_code(200);
        exit('OK');
    }

    // 👥 রেফারেল ইনকাম
    if ($text === '👥 রেফারেল ইনকাম (Referral) 🎁' || $text === '/referral') {
        $refLink = "https://t.me/" . BOT_USERNAME . "?start=" . $userId;

        $totalRefers = 0;
        foreach ($db['users'] as $u) {
            if (($u['referred_by'] ?? null) === $userId) {
                $totalRefers++;
            }
        }

        $myEarnings = $db['users'][$sUserId]['referral_earnings'] ?? 0.0;

        $refMsg = "👥 <b>রেফারেল ইনকাম (Referral) 🎁</b>\n\n"
            . "📊 <b>আপনার লাইভ পরিসংখ্যান:</b>\n"
            . "------------------------------------\n"
            . "👤 <b>বর্তমান মোট রেফারেল:</b> <b>" . $totalRefers . " জন</b>\n"
            . "💰 <b>মোট অর্জিত কমিশন:</b> <b>$" . number_format($myEarnings, 2) . " USD</b>\n"
            . "------------------------------------\n\n"
            . "🔗 <b>আপনার ইউনিক রেফারেল লিংক:</b>\n<code>" . $refLink . "</code>";

        telegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $refMsg,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(getMainKeyboard($userId))
        ]);
        http_response_code(200);
        exit('OK');
    }
}

http_response_code(200);
echo 'OK';