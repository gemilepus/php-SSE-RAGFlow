<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

if (ob_get_level() == 0) ob_start();

ini_set('max_execution_time', 0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

$apiKey = 'ragflow-E4MzRjNWVlMmM4NjExZjBiNzA1NzZlMm'; // RAGFlow API KEY

$client = new Client([
    'base_uri' => 'http://192.168.255.253', // address
    'headers'  => [
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type'  => 'application/json',
    ],
]);
$payload = '{
    "model": "model",
    "messages": [{"role": "user", "content": "' . str_replace(array("\r", "\n","\""), '', $_GET['text']) . '"}],
    "stream": true
  }';

// /api/v1/agents_openai/{agent_id}/chat/completions
$response = $client->request('POST', '/api/v1/agents_openai/b5afd902282b11f1b10c1ef36ef08cf2/chat/completions', [
    'stream' => true,
    'body'   => $payload,
]);


if ($response->getStatusCode() !== 200) {
    echo 'Error: ' . $response->getStatusCode() . "\n";
    return;
}

$stream = $response->getBody();
$buffer = '';
while (!$stream->eof()) {

    $chunk = $stream->read(1024);
    if ($chunk === '') {
        continue;
    }

    $buffer .= $chunk;
    $lines   = explode("\n", $buffer);
    $buffer  = array_pop($lines);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if ($line === 'data: [DONE]') {
            echo "id: 0\ndata: " . json_encode("[DONE]");
            echo "\n\n";
            ob_flush();
            flush();
            continue;
        }

        if (!str_starts_with($line, 'data: ')) {
            continue;
        }

        $jsonStr = substr($line, 6);
        $jsonStr = trim($jsonStr);

        if ($jsonStr === '') {
            continue;
        }

        $data = json_decode($jsonStr, true);
        if (!$data || !isset($data['choices'][0]['delta']['content'])) {
            continue;
        }

        $message_id = $data['id'];
        $content = $data['choices'][0]['delta']['content'];

        echo "id: $message_id\ndata: " . json_encode($content);
        echo "\n\n";
        ob_flush();
        flush();
    }
}

