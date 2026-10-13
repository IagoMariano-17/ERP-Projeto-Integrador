<?php
// API interna de CEP - V03
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cep = preg_replace('/\D+/', '', $_GET['cep'] ?? '');
if (strlen($cep) !== 8) {
    http_response_code(400);
    echo json_encode(['erro'=>true,'mensagem'=>'CEP inválido. Informe 8 dígitos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function consultarJson(string $url): ?array {
    $ctx = stream_context_create(['http'=>[
        'method'=>'GET','timeout'=>5,
        'header'=>"Accept: application/json\r\nUser-Agent: ERP-GestaoLGPD-V03\r\n"
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

$data = consultarJson('https://viacep.com.br/ws/'.rawurlencode($cep).'/json/');
if (is_array($data) && empty($data['erro'])) {
    echo json_encode([
        'erro'=>false,'cep'=>$data['cep']??$cep,
        'logradouro'=>$data['logradouro']??'','bairro'=>$data['bairro']??'',
        'cidade'=>$data['localidade']??'','estado'=>$data['uf']??'','uf'=>$data['uf']??'',
        'complemento'=>$data['complemento']??''
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Fallback opcional para maior resiliência.
$fallback = consultarJson('https://brasilapi.com.br/api/cep/v1/'.rawurlencode($cep));
if (is_array($fallback) && !empty($fallback['street'])) {
    echo json_encode([
        'erro'=>false,'cep'=>$fallback['cep']??$cep,
        'logradouro'=>$fallback['street']??'','bairro'=>$fallback['neighborhood']??'',
        'cidade'=>$fallback['city']??'','estado'=>$fallback['state']??'','uf'=>$fallback['state']??'',
        'complemento'=>''
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(404);
echo json_encode(['erro'=>true,'mensagem'=>'CEP não encontrado ou serviço temporariamente indisponível.'], JSON_UNESCAPED_UNICODE);
