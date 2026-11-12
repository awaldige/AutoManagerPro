<?php
// Configurações para o seu ambiente específico
$host = "127.0.0.1"; // IP local para forçar o uso da porta
$user = "root";
$pass = ""; 
$db   = "vendascarros"; // Nome do banco em minúsculo
$port = 3308;          // Sua porta confirmada no my.ini

// Relatório de erros ativado
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Tentativa de conexão com a porta 3308
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    // Se falhar porque o banco é maiúsculo, tentamos VendasCarros
    try {
        $conn = new mysqli($host, $user, $pass, "VendasCarros", $port);
        $conn->set_charset("utf8mb4");
    } catch (Exception $e2) {
        die("Erro de conexão: " . $e2->getMessage());
    }
}
?>


