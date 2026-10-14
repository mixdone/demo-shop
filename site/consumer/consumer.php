<?php

/**
 * Kafka Consumer для обработки заказов.
 * 
 * Читает сообщения из топика `new-orders` и записывает их в таблицу `orders` MySQL.
 * Использует at-least-once delivery: offset коммитится только после успешной записи в БД.
 */

// --- Конфигурация ---
$kafkaBroker = 'kafka-broker:9092';
$kafkaTopic  = 'new-orders';
$kafkaGroup  = 'order-processors';

$mysqlHost = 'mysql-db';
$mysqlDb   = getenv('MYSQL_DATABASE');
$mysqlUser = getenv('MYSQL_USER');
$mysqlPass = getenv('MYSQL_PASSWORD');

// --- Подключение к MySQL с retry ---
$pdo = null;
$attempts = 0;
$maxAttempts = 30;

while ($pdo === null && $attempts < $maxAttempts) {
    try {
        $pdo = new PDO(
            "mysql:host=$mysqlHost;dbname=$mysqlDb;charset=utf8mb4",
            $mysqlUser,
            $mysqlPass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        echo "✅ Connected to MySQL\n";
    } catch (\PDOException $e) {
        $attempts++;
        echo "⏳ MySQL not ready (attempt $attempts/$maxAttempts): " . $e->getMessage() . "\n";
        sleep(2);
    }
}

if ($pdo === null) {
    die("❌ Failed to connect to MySQL after $maxAttempts attempts. Exiting.\n");
}

// --- Подключение к Kafka с retry ---
$consumer = null;
$attempts = 0;

while ($consumer === null && $attempts < $maxAttempts) {
    try {
        $conf = new RdKafka\Conf();
        $conf->set('group.id', $kafkaGroup);
        $conf->set('metadata.broker.list', $kafkaBroker);
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'false');

        $consumer = new RdKafka\KafkaConsumer($conf);
        $consumer->subscribe([$kafkaTopic]);
        echo "✅ Connected to Kafka, subscribed to '$kafkaTopic'\n";
    } catch (\Exception $e) {
        $attempts++;
        echo "⏳ Kafka not ready (attempt $attempts/$maxAttempts): " . $e->getMessage() . "\n";
        sleep(2);
    }
}

if ($consumer === null) {
    die("❌ Failed to connect to Kafka after $maxAttempts attempts. Exiting.\n");
}

// --- Graceful shutdown ---
$running = true;
pcntl_async_signals(true);

pcntl_signal(SIGTERM, function () use (&$running) {
    echo "🛑 SIGTERM received, shutting down...\n";
    $running = false;
});

pcntl_signal(SIGINT, function () use (&$running) {
    echo "🛑 SIGINT received, shutting down...\n";
    $running = false;
});

echo "🎧 Consumer started. Waiting for messages...\n";

// --- Основной цикл ---
while ($running) {
    $message = $consumer->consume(120 * 1000); // таймаут 120 секунд

    switch ($message->err) {
        case RD_KAFKA_RESP_ERR_NO_ERROR:
            $payload = json_decode($message->payload, true);

            if (!$payload || !isset($payload['user_id'], $payload['product_id'])) {
                echo "⚠️  Invalid payload: " . $message->payload . "\n";
                $consumer->commit($message);
                break;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$payload['user_id'], $payload['product_id']]);

                echo sprintf(
                    "✅ Order saved: user=%d, product=%d (partition=%d, offset=%d)\n",
                    $payload['user_id'],
                    $payload['product_id'],
                    $message->partition,
                    $message->offset
                );

                // Коммитим offset только после успешной записи в БД
                $consumer->commit($message);
            } catch (\PDOException $e) {
                echo "❌ DB insert failed: " . $e->getMessage() . "\n";
                // Не коммитим — сообщение будет обработано повторно
            }
            break;

        case RD_KAFKA_RESP_ERR__PARTITION_EOF:
            // Достигли конца партиции, ждём новых сообщений
            break;

        case RD_KAFKA_RESP_ERR__TIMED_OUT:
            // Таймаут — просто продолжаем цикл
            break;

        default:
            echo "❌ Kafka error: " . $message->errstr() . "\n";
            break;
    }
}

echo "👋 Consumer stopped.\n";