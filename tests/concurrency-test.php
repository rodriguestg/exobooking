<?php
/**
 * Teste de Concorrência — ExoBooking Core
 *
 * Uso dentro do container:
 *   php /tmp/concurrency-test.php [passeio_id]
 *
 * Uso no host (se PHP instalado):
 *   WP_URL=http://localhost:8000 php tests/concurrency-test.php [passeio_id]
 */

// URL adaptável: container usa porta 80, host usa porta 8000
$base_url = getenv('WP_URL')
    ? rtrim(getenv('WP_URL'), '/')
    : 'http://localhost:80';

$endpoint       = $base_url . '/wp-json/exobooking/v1/bookings';
$inventory_url  = $base_url . '/wp-json/exobooking/v1/inventory';
$total_requests = 5;

// Aceita passeio_id como argumento: php script.php 5
$passeio_id = isset($argv[1]) ? intval($argv[1]) : 1;

echo "\n";
echo "  ExoBooking — Teste de Concorrência\n";
echo "  Endpoint : {$endpoint}\n";
echo "  Passeio  : #{$passeio_id}\n";
echo "\n";

// Mostra estoque antes do teste
$ch_inv = curl_init($inventory_url);
curl_setopt_array($ch_inv, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$inv_response = curl_exec($ch_inv);
curl_close($ch_inv);

$inventory = json_decode($inv_response, true);
$current   = null;

if ($inventory) {
    foreach ($inventory as $item) {
        if ((int)$item['passeio_id'] === $passeio_id) {
            $current = $item;
            break;
        }
    }
}

if ($current) {
    echo "  Estoque antes: {$current['available_slots']}/{$current['total_slots']} vagas disponíveis\n";
} else {
    echo "  AVISO: Nenhum estoque encontrado para passeio_id={$passeio_id}\n";
    echo "  Verifique o ID e tente novamente.\n\n";
    exit(1);
}

echo "\nDisparando {$total_requests} requisições simultâneas...\n\n";

$payload = json_encode([
    'passeio_id'     => $passeio_id,
    'customer_name'  => 'Cliente Teste',
    'customer_email' => 'teste@exobooking.com',
    'booking_date'   => '2026-03-20',
    'quantity'       => 1,
]);

// Multi-curl: todas as requisições simultaneamente
$mh      = curl_multi_init();
$handles = [];

for ($i = 0; $i < $total_requests; $i++) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$i] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

// Coleta resultados
$approved = 0;
$rejected = 0;

for ($i = 0; $i < $total_requests; $i++) {
    $response  = curl_multi_getcontent($handles[$i]);
    $http_code = curl_getinfo($handles[$i], CURLINFO_HTTP_CODE);
    $curl_error = curl_error($handles[$i]);
    $body      = json_decode($response, true);

    if ($http_code === 0) {
        echo "Requisição " . ($i+1) . ": [ERR] ❌ Falha de conexão → {$curl_error}\n";
        $rejected++;
    } elseif ($http_code === 200) {
        $approved++;
        $detail = "booking_id={$body['booking_id']}, vagas restantes={$body['remaining']}";
        echo "Requisição " . ($i+1) . ": [200] ✅ APROVADA  → {$detail}\n";
    } else {
        $rejected++;
        $detail = $body['message'] ?? 'Sem detalhes';
        echo "Requisição " . ($i+1) . ": [{$http_code}] ❌ BLOQUEADA → {$detail}\n";
    }

    curl_multi_remove_handle($mh, $handles[$i]);
    curl_close($handles[$i]);
}

curl_multi_close($mh);

// Resultado final
echo "\n\n";
echo "  RESULTADO FINAL\n\n";
echo "  Aprovadas : {$approved} (esperado: 3)\n";
echo "  Bloqueadas: {$rejected} (esperado: 2)\n";
echo "\n";

if ($approved === 3 && $rejected === 2) {
    echo "✅ PASSOU — Sistema anti-overbooking funcionando corretamente!\n\n";
} else {
    echo "❌ FALHOU — Verifique os detalhes acima.\n\n";
}