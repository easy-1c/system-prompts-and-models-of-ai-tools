<?php
/**
 * Обработчик заявок с лендинга.
 *
 * Что делает:
 *  1. Принимает POST из формы (fetch/FormData).
 *  2. Валидирует поля и проверяет согласие на обработку ПДн (152-ФЗ).
 *  3. Отсекает спам (honeypot + простая rate-limit по IP).
 *  4. Отправляет письмо на почту домена.
 *  5. Пишет заявку в CSV на этом же (российском) хостинге.
 *  6. Возвращает JSON { ok: true } / { ok:false, error }.
 *
 * Данные НЕ покидают ваш сервер — это ключ к соответствию 152-ФЗ.
 * Настройки — в config.php (создайте из config.example.php).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// --- Только POST ------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Метод не разрешён']);
    exit;
}

// --- Конфигурация -----------------------------------------------------------
$configFile = __DIR__ . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
$mailTo    = $config['mail_to']    ?? 'info@easy-1c.ru';
$mailFrom  = $config['mail_from']  ?? 'noreply@easy-1c.ru';
$subjectPrefix = $config['subject_prefix'] ?? 'Заявка с лендинга';
$dataDir   = $config['data_dir']   ?? __DIR__ . '/data';

// --- Утилиты ----------------------------------------------------------------
function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function clean(string $v): string {
    return trim(preg_replace('/[\r\n\t]+/', ' ', $v));
}

// --- Антиспам: honeypot -----------------------------------------------------
// Скрытое поле "website" людям не видно; если заполнено — это бот.
if (!empty($_POST['website'])) {
    echo json_encode(['ok' => true]); // тихо «принимаем», но игнорируем
    exit;
}

// --- Антиспам: rate-limit по IP (не чаще 1 заявки в 20 сек) ------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rlFile = sys_get_temp_dir() . '/lead_rl_' . md5($ip);
if (is_file($rlFile) && (time() - filemtime($rlFile)) < 20) {
    fail('Слишком часто. Подождите немного и попробуйте снова.', 429);
}
@touch($rlFile);

// --- Сбор и валидация полей -------------------------------------------------
$name    = clean((string)($_POST['name']    ?? ''));
$phone   = clean((string)($_POST['phone']   ?? ''));
$email   = clean((string)($_POST['email']   ?? ''));
$message = clean((string)($_POST['message'] ?? ''));
$source  = clean((string)($_POST['source']  ?? ($_SERVER['HTTP_REFERER'] ?? '')));
$consent = !empty($_POST['consent']);

if ($name === '') {
    fail('Укажите имя.');
}
$phoneDigits = preg_replace('/\D/', '', $phone);
$phoneOk = strlen($phoneDigits) >= 10 && strlen($phoneDigits) <= 15;
$emailOk = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$phoneOk && !$emailOk) {
    fail('Нужен корректный телефон или e-mail.');
}
if (!$consent) {
    fail('Требуется согласие на обработку персональных данных.');
}

// --- Формирование записи ----------------------------------------------------
$now = date('Y-m-d H:i:s');
$row = [
    'datetime' => $now,
    'name'     => $name,
    'phone'    => $phone,
    'email'    => $email,
    'message'  => $message,
    'source'   => $source,
    'ip'       => $ip,
    'ua'       => clean((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
];

// --- Сохранение в CSV (с блокировкой файла) ---------------------------------
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0750, true);
}
$csvFile = $dataDir . '/leads-' . date('Y-m') . '.csv';
$isNew = !is_file($csvFile);
$fp = @fopen($csvFile, 'a');
if ($fp) {
    if (flock($fp, LOCK_EX)) {
        if ($isNew) {
            fputcsv($fp, array_keys($row), ';');
        }
        fputcsv($fp, array_values($row), ';');
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// --- Письмо на почту домена -------------------------------------------------
$subject = $subjectPrefix . ': ' . $name;
$bodyLines = [
    "Новая заявка с лендинга",
    "-----------------------------------",
    "Дата:      {$now}",
    "Имя:       {$name}",
    "Телефон:   {$phone}",
    "E-mail:    {$email}",
    "Сообщение: {$message}",
    "Источник:  {$source}",
    "IP:        {$ip}",
];
$body = implode("\r\n", $bodyLines);

$headers = [
    'From: ' . $mailFrom,
    'Reply-To: ' . ($emailOk ? $email : $mailFrom),
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: landing-kit',
];
$mailOk = @mail($mailTo, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));

// --- Опционально: уведомление в Telegram ------------------------------------
// Раскомментируйте и заполните config.php (telegram_token / telegram_chat_id),
// если захотите дублировать заявки в Telegram.
/*
if (!empty($config['telegram_token']) && !empty($config['telegram_chat_id'])) {
    $tgText = "🆕 Заявка: {$name}\n📞 {$phone}\n✉️ {$email}\n💬 {$message}\n🔗 {$source}";
    @file_get_contents(
        'https://api.telegram.org/bot' . $config['telegram_token'] . '/sendMessage?' .
        http_build_query(['chat_id' => $config['telegram_chat_id'], 'text' => $tgText])
    );
}
*/

// Заявка сохранена локально в любом случае; письмо — «best effort».
echo json_encode(['ok' => true, 'mail' => (bool)$mailOk], JSON_UNESCAPED_UNICODE);
