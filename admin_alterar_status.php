<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'administrador') {
    header("Location: login.php");
    exit;
}

$tarefa_id = isset($_GET['alterar_status']) ? intval($_GET['alterar_status']) : 0;

$stmt = $pdo->prepare("SELECT status FROM tarefas WHERE id = ?");
$stmt->execute([$tarefa_id]);
$tarefa = $stmt->fetch();

if ($tarefa) {
    $novo_status = $tarefa['status'] === 'pendente' ? 'concluida' : 'pendente';
    $pdo->prepare("UPDATE tarefas SET status = ? WHERE id = ?")->execute([$novo_status, $tarefa_id]);
    $_SESSION['mensagem'] = "Status da tarefa atualizado.";
} else {
    $_SESSION['erro'] = "Tarefa não encontrada.";
}

header("Location: admin_painel.php");
exit;
?>