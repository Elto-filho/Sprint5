<?php
session_start();
require_once 'conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Adicionar nova tarefa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'] ?? '';

    $stmt = $pdo->prepare("INSERT INTO tarefas (titulo, descricao, usuario_id) VALUES (?, ?, ?)");
    $stmt->execute([$titulo, $descricao, $usuario_id]);
}

// Buscar tarefas do usuário
$stmt = $pdo->prepare("SELECT * FROM tarefas WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$tarefas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel de Tarefas</title>
</head>
<body>
    <h2>Bem-vindo ao seu Painel</h2>
    <p><strong>Perfil:</strong> <?= $_SESSION['perfil'] ?> | 
       <a href="logout.php">Sair</a></p>

    <h3>Suas Tarefas</h3>

    <!-- Formulário para adicionar nova tarefa -->
    <form method="POST">
        <input type="text" name="titulo" placeholder="Título da tarefa" required>
        <br><br>
        <textarea name="descricao" placeholder="Descrição (opcional)"></textarea>
        <br><br>
        <button type="submit">Adicionar Tarefa</button>
    </form>

    <hr>

    <!-- Lista de tarefas -->
    <?php if (count($tarefas) > 0): ?>
        <ul>
            <?php foreach ($tarefas as $tarefa): ?>
                <li>
                    <strong><?= htmlspecialchars($tarefa['titulo']) ?></strong><br>
                    <?= nl2br(htmlspecialchars($tarefa['descricao'])) ?><br>
                    <small>Status: <?= $tarefa['status'] ?></small>
                </li>
                <hr>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Você não tem tarefas ainda.</p>
    <?php endif; ?>
</body>
</html>