<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'administrador') {
    header("Location: login.php");
    exit;
}

$usuario_id = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 0;

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    die("Usuário não encontrado.");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Tarefas de <?= htmlspecialchars($usuario['nome']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"  rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>📋 Tarefas de <?= htmlspecialchars($usuario['nome']) ?></h2>
            <a href="admin_painel.php" class="btn btn-secondary">&larr; Voltar</a>
        </div>

        <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email']) ?></p>

        <?php
        $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $tarefas = $stmt->fetchAll();
        ?>

        <?php if (count($tarefas) > 0): ?>
            <div class="row g-3">
                <?php foreach ($tarefas as $tarefa): ?>
                    <div class="col-md-6">
                        <div class="card border-<?= $tarefa['status'] === 'pendente' ? 'danger' : 'success' ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($tarefa['titulo']) ?></h5>
                                <p class="card-text"><?= nl2br(htmlspecialchars($tarefa['descricao'])) ?></p>
                                <span class="badge bg-secondary"><?= htmlspecialchars($tarefa['categoria']) ?></span>
                                <span class="badge bg-<?= $tarefa['status'] === 'pendente' ? 'warning text-dark' : 'success' ?>">
                                    <?= $tarefa['status'] === 'pendente' ? 'Pendente' : 'Concluída' ?>
                                </span>
                                <div class="mt-2">
                                    <a href="../painel.php?alterar_status=<?= $tarefa['id'] ?>" class="btn btn-sm btn-secondary">
                                        <?= $tarefa['status'] === 'pendente' ? 'Marcar como concluída' : 'Marcar como pendente' ?>
                                    </a>
                                    <a href="../painel.php?excluir=<?= $tarefa['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir?')" class="btn btn-sm btn-danger">Excluir</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Nenhuma tarefa encontrada para este usuário.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> 
</body>
</html>