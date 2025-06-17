<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['perfil'], ['administrador', 'suporte'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;

// Buscar perfil do usuário a ser excluído
$stmt = $pdo->prepare("SELECT perfil FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if ($usuario && $usuario['perfil'] === 'funcionario') {
    // Excluir usuário
    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$usuario_id]);

    // Salvar no histórico
    $acao = "Usuário excluído - ID: $usuario_id";
    $pdo->prepare("INSERT INTO historico_edicoes (admin_id, usuario_id, acao) VALUES (?, ?, ?)")
       ->execute([$_SESSION['usuario_id'], $usuario_id, $acao]);

    $_SESSION['mensagem'] = "Funcionário excluído com sucesso.";
} else {
    $_SESSION['erro'] = "Você não pode excluir este usuário.";
}

header("Location: registro_suporte.php");
exit;
?>