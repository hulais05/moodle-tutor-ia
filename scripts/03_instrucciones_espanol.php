<?php
// Agrega "responder en español" al system instruction de cada acción de IA del provider Ollama.
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
global $DB;

$prefijo = "IMPORTANTE: Respondé SIEMPRE en español rioplatense (Argentina), claro y neutral.\n\n";

$provider = $DB->get_record('ai_providers', ['name' => 'Ollama local'], '*', MUST_EXIST);
$cfg = json_decode($provider->actionconfig, true);

foreach ($cfg as $action => &$data) {
    if (!isset($data['settings']['systeminstruction'])) {
        continue;
    }
    $actual = $data['settings']['systeminstruction'];
    if (strpos($actual, 'español rioplatense') !== false) {
        echo "  $action: ya estaba en español, se omite.\n";
        continue;
    }
    $data['settings']['systeminstruction'] = $prefijo . $actual;
    echo "  $action: instrucción de español agregada.\n";
}
unset($data);

$provider->actionconfig = json_encode($cfg);
$DB->update_record('ai_providers', $provider);

purge_all_caches();
echo "Listo. Las acciones ahora responden en español.\n";
