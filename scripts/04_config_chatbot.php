<?php
// Configura el chatbot (block_ollama_chat) como tutor del curso, en español,
// usando el material del curso como "fuente de verdad".
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$m1 = file_get_contents('/var/www/html/_provision/01-introduccion-monitoreo-ambiental.md');
$m2 = file_get_contents('/var/www/html/_provision/02-parametros-y-metodos.md');

$prompt = "Sos el tutor virtual del curso 'Monitoreo ambiental en minería'. "
    . "Respondé SIEMPRE en español rioplatense (Argentina), de forma clara, breve y didáctica. "
    . "Basate en el material del curso que se te proporciona. Si la pregunta no está cubierta por "
    . "el material, decilo con honestidad y orientá de forma general. "
    . "Presentate como facilitador neutral: no afirmes causas de fenómenos ambientales que no estén "
    . "respaldadas por datos. Tu objetivo es ayudar al alumno a entender los conceptos.";

$sourceoftruth = "MATERIAL DEL CURSO 'Monitoreo ambiental en minería':\n\n"
    . "=== MÓDULO 1 ===\n" . $m1 . "\n\n"
    . "=== MÓDULO 2 ===\n" . $m2;

set_config('prompt', $prompt, 'block_ollama_chat');
set_config('sourceoftruth', $sourceoftruth, 'block_ollama_chat');
set_config('assistantname', 'Tutor IA', 'block_ollama_chat');
set_config('username', 'Vos', 'block_ollama_chat');

echo "Prompt configurado (" . strlen($prompt) . " chars).\n";
echo "Fuente de verdad configurada (" . strlen($sourceoftruth) . " chars).\n";
echo "Listo. El chatbot ahora responde como tutor del curso, en español.\n";
