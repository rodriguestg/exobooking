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

$endpoint      = $base_url . '/wp-json/exobooking/v1/bookings';
$inventory_url = $base_url . '/wp-json/exobooking/v1/inventory';

// Aceita passeio_id como argumento: php script.php 7
$passeio_id     = isset($argv[1]) ? intval($argv[1]) : null;
$total_requests = 5;

echo "\n";
echo "  ExoBooking — Teste de Concorrência\n";
echo "  Endpoint : {$endpoint}\n";
echo "\n";

// ---------------------------------------------------------------
// Busca inventário completo e filtra pelo passeio_id informado
// Se não informar ID, lista os disponíveis e pede para escolher
// ---------------------------------------------------------------
$ch_inv = curl_init($inventory_url);
curl_setopt_array($ch_inv, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$inv_response = curl_exec($ch_inv);
$inv_error    = curl_error($ch_inv);
curl_close($ch_inv);

if ( $inv_error || ! $inv_response ) {
    echo "  ERRO: Não foi possível conectar ao endpoint de inventário.\n";
    echo "  Verifique se o WordPress está rodando em {$base_url}\n\n";
    exit(1);
}

$inventory = json_decode($inv_response, true);

if ( empty($inventory) ) {
    echo "  AVISO: Nenhum estoque cadastrado.\n";
    echo "  Crie um passeio com vagas no wp-admin antes de rodar o teste.\n\n";
    exit(1);
}

// Se não passou ID, lista os passeios disponíveis e encerra
if ( ! $passeio_id ) {
    echo "  Passeios disponíveis no inventário:\n\n";
    foreach ($inventory as $item) {
        $nome = $item['passeio_name'] ?? "Passeio #{$item['passeio_id']}";
        echo "  ID: {$item['passeio_id']}  |  {$nome}  |  Data: {$item['date']}  |  Vagas: {$item['available_slots']}/{$item['total_slots']}\n";
    }
    echo "\n  Uso: php /tmp/concurrency-test.php [passeio_id]\n";
    echo "  Exemplo: php /tmp/concurrency-test.php {$inventory[0]['passeio_id']}\n\n";
    exit(0);
}

// Filtra todas as datas disponíveis para o passeio informado
$slots_do_passeio = array_filter($inventory, function($item) use ($passeio_id) {
    return (int)$item['passeio_id'] === $passeio_id;
});

$slots_do_passeio = array_values($slots_do_passeio);

if ( empty($slots_do_passeio) ) {
    echo "  AVISO: Nenhum estoque encontrado para passeio_id={$passeio_id}\n\n";

    // Mostra os IDs disponíveis para ajudar
    echo "  Passeios disponíveis:\n";
    foreach ($inventory as $item) {
        $nome = $item['passeio_name'] ?? "Passeio #{$item['passeio_id']}";
        echo "  → ID {$item['passeio_id']}: {$nome} ({$item['date']})\n";
    }
    echo "\n";
    exit(1);
}

// Usa a primeira data com vagas disponíveis
$slot_escolhido = null;
foreach ($slots_do_passeio as $slot) {
    if ((int)$slot['available_slots'] > 0) {
        $slot_escolhido = $slot;
        break;
    }
}

// Se todas as datas estão esgotadas, pega a primeira mesmo assim
if ( ! $slot_escolhido ) {
    $slot_escolhido = $slots_do_passeio[0];
}

$booking_date = $slot_escolhido['date'];
$nome_passeio = $slot_escolhido['passeio_name'] ?? "Passeio #{$passeio_id}";

echo "  Passeio  : {$nome_passeio} (ID #{$passeio_id})\n";
echo "  Data     : {$booking_date}\n";
echo "  Estoque  : {$slot_escolhido['available_slots']}/{$slot_escolhido['total_slots']} vagas disponíveis\n";

// Alerta se vagas insuficientes para o teste
$vagas = (int)$slot_escolhido['available_slots'];
if ($vagas < 1) {
    echo "\n  AVISO: Sem vagas disponíveis para esta data.\n";
    echo "  Redefina o estoque antes de rodar o teste:\n";
    echo "  docker exec exobooking_db mysql -uroot -p\"root\" wordpress -e \\n";
    echo "  \"UPDATE wp_exobooking_inventory SET available_slots = 3 WHERE passeio_id = {$passeio_id};\"\n\n";
    exit(1);
}

$esperado_aprovadas = min($vagas, $total_requests);
$esperado_bloqueadas = $total_requests - $esperado_aprovadas;

echo "\n  Disparando {$total_requests} requisições simultâneas...\n";
echo "  Esperado : {$esperado_aprovadas} aprovadas / {$esperado_bloqueadas} bloqueadas\n\n";

// ---------------------------------------------------------------
// Gera payload dinâmico por requisição (nome e email únicos)
// ---------------------------------------------------------------
$mh      = curl_multi_init();
$handles = [];

for ($i = 0; $i < $total_requests; $i++) {
    $payload = json_encode([
        'passeio_id'     => $passeio_id,
        'customer_name'  => "Cliente Teste " . ($i + 1),
        'customer_email' => "teste" . ($i + 1) . "@exobooking.com",
        'booking_date'   => $booking_date,   // data dinâmica do inventário
        'quantity'       => 1,
    ]);

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

// Executa todas simultaneamente
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

// ---------------------------------------------------------------
// Coleta e exibe resultados
// ---------------------------------------------------------------
$approved = 0;
$rejected = 0;

for ($i = 0; $i < $total_requests; $i++) {
    $response   = curl_multi_getcontent($handles[$i]);
    $http_code  = curl_getinfo($handles[$i], CURLINFO_HTTP_CODE);
    $curl_error = curl_error($handles[$i]);
    $body       = json_decode($response, true);

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

// ---------------------------------------------------------------
// Resultado final
// ---------------------------------------------------------------
echo "\n";
echo "  RESULTADO FINAL\n\n";
echo "  Aprovadas : {$approved} (esperado: {$esperado_aprovadas})\n";
echo "  Bloqueadas: {$rejected} (esperado: {$esperado_bloqueadas})\n\n";

if ($approved === $esperado_aprovadas && $rejected === $esperado_bloqueadas) {
    echo "✅ PASSOU — Sistema anti-overbooking funcionando corretamente!\n\n";
} else {
    echo "❌ FALHOU — Verifique os detalhes acima.\n\n";
}