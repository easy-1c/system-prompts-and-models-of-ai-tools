<?php
/**
 * Скопируйте этот файл в config.php и заполните своими значениями.
 * config.php НЕ коммитится в git (см. .gitignore).
 */
return [
    // Куда слать письма о заявках (почта вашего домена):
    'mail_to'   => 'info@easy-1c.ru',

    // От кого письмо. Должен быть ящик НА вашем домене — иначе письма
    // попадут в спам (SPF/DKIM). Например noreply@easy-1c.ru.
    'mail_from' => 'noreply@easy-1c.ru',

    // Префикс темы письма:
    'subject_prefix' => 'Заявка ОКК',

    // Папка для хранения CSV с заявками (по умолчанию api/data):
    'data_dir'  => __DIR__ . '/data',

    // --- Telegram (опционально) ---
    // Создайте бота через @BotFather, узнайте chat_id через @userinfobot.
    // 'telegram_token'   => '123456:ABC-DEF...',
    // 'telegram_chat_id' => '123456789',
];
