<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['perfil'], ['administrador', 'suporte'])) {
    header("Location: login.php");
    exit;
}

$tarefa_id = isset($_GET['excluir']) ? intval($_GET['excluir']) : 0;

$stmt = $pdo->prepare("DELETE FROM tarefas WHERE id = ?");
$stmt->execute([$tarefa_id]);

$_SESSION['mensagem'] = "Tarefa excluída com sucesso.";
header("Location: admin_painel.php");
exit;
?>