<?php
// Configurações de conexão com o banco de dados
$host = 'localhost';       // Servidor do banco
$db   = 'sistema_login';   // Nome do banco criado anteriormente
$user = 'root';            // Usuário do MySQL
$senha = 'Senai@118';      // Senha do usuário do MySQL
$charset = 'utf8mb4';      // Recomendado para suportar emojis e caracteres especiais

// DSN (Data Source Name)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Configurações de atributos (opcional)
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Conectando ao banco
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Caso a conexão falhe, exibe uma mensagem de erro
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>