<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'suporte') {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$perfil = $_SESSION['perfil'];

// Adicionar nova tarefa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titulo'])) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'] ?? '';
    $categoria = $_POST['categoria'] ?? 'Outros';
    $data_vencimento = $_POST['data_vencimento'] ?: null;

    $stmt = $pdo->prepare("INSERT INTO tarefas (titulo, descricao, usuario_id, categoria, data_vencimento) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$titulo, $descricao, $usuario_id, $categoria, $data_vencimento]);
    header("Location: painel_suporte.php");
    exit;
}

// Editar uma tarefa existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_titulo'])) {
    $id = $_POST['tarefa_id'];
    $titulo = $_POST['editar_titulo'];
    $descricao = $_POST['editar_descricao'];
    $categoria = $_POST['editar_categoria'];
    $status = $_POST['editar_status'];
    $data_vencimento = $_POST['editar_data_vencimento'] ?: null;

    // Usuário pode editar qualquer tarefa no painel do suporte
    $stmt = $pdo->prepare("UPDATE tarefas SET titulo = ?, descricao = ?, categoria = ?, status = ?, data_vencimento = ? WHERE id = ?");
    $stmt->execute([$titulo, $descricao, $categoria, $status, $data_vencimento, $id]);
    header("Location: painel_suporte.php");
    exit;
}

// Alterar status da tarefa
if (isset($_GET['alterar_status'])) {
    $id = $_GET['alterar_status'];
    
    $stmt = $pdo->prepare("SELECT status FROM tarefas WHERE id = ?");
    $stmt->execute([$id]);
    $tarefa = $stmt->fetch();

    if ($tarefa) {
        $novo_status = $tarefa['status'] === 'pendente' ? 'concluida' : 'pendente';
        $stmt = $pdo->prepare("UPDATE tarefas SET status = ? WHERE id = ?");
        $stmt->execute([$novo_status, $id]);
    }
    header("Location: painel_suporte.php");
    exit;
}

// Excluir tarefa (somente admin ou suporte pode excluir)
if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];
    
    $stmt = $pdo->prepare("DELETE FROM tarefas WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: painel_suporte.php");
    exit;
}

// Buscar todas as tarefas (suporte vê todas)
$stmt = $pdo->query("SELECT * FROM tarefas ORDER BY usuario_id");
$tarefas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel do Suporte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"  rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🛠️ Painel do Suporte</h2>
            <a href="logout.php" class="btn btn-danger">Sair</a>
        </div>

        <p><strong>Perfil:</strong> <?= ucfirst($perfil) ?></p>

        <!-- Formulário de Nova Tarefa -->
        <form method="POST" class="mb-4 p-3 bg-white rounded shadow-sm">
            <h4>Adicionar Nova Tarefa</h4>
            <input type="text" name="titulo" placeholder="Título da tarefa" class="form-control mb-2" required>
            <textarea name="descricao" placeholder="Descrição (opcional)" class="form-control mb-2"></textarea>

            <label for="categoria" class="form-label">Categoria:</label>
            <select name="categoria" id="categoria" class="form-select mb-2">
                <option value="Trabalho">Trabalho</option>
                <option value="Pessoal">Pessoal</option>
                <option value="Urgente">Urgente</option>
                <option value="Estudo">Estudo</option>
                <option value="Outros">Outros</option>
            </select>

            <label for="data_vencimento" class="form-label">Data de Vencimento (opcional):</label>
            <input type="date" name="data_vencimento" id="data_vencimento" class="form-control mb-2">

            <button type="submit" class="btn btn-primary">Adicionar</button>
        </form>

        <hr>

        <!-- Lista de Tarefas -->
        <h4 class="mb-3">📋 Todas as Tarefas do Sistema</h4>
        <?php if (count($tarefas) > 0): ?>
            <div class="row g-3">
                <?php foreach ($tarefas as $t): ?>
                    <div class="col-md-6">
                        <div class="card border-<?= $t['status'] === 'pendente' ? 'danger' : 'success' ?>">
                            <div class="card-body">
                                <small class="text-muted">Usuário ID: <?= $t['usuario_id'] ?></small><br>
                                <h5 class="card-title"><?= htmlspecialchars($t['titulo']) ?></h5>
                                <p class="card-text"><?= nl2br(htmlspecialchars($t['descricao'])) ?></p>
                                <span class="badge bg-secondary"><?= htmlspecialchars($t['categoria']) ?></span>
                                <span class="badge bg-<?= $t['status'] === 'pendente' ? 'warning text-dark' : 'success' ?>">
                                    <?= $t['status'] === 'pendente' ? 'Pendente' : 'Concluída' ?>
                                </span>
                                <div class="mt-2">
                                    <a href="?alterar_status=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">
                                        <?= $t['status'] === 'pendente' ? 'Marcar como concluída' : 'Marcar como pendente' ?>
                                    </a>
                                    <a href="#" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#editarTarefaModal<?= $t['id'] ?>">Editar</a>
                                    <a href="?excluir=<?= $t['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir?')" class="btn btn-sm btn-danger">Excluir</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal de Edição -->
                    <div class="modal fade" id="editarTarefaModal<?= $t['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar Tarefa</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="tarefa_id" value="<?= $t['id'] ?>">
                                        <div class="mb-3">
                                            <label>Título:</label>
                                            <input type="text" name="editar_titulo" value="<?= htmlspecialchars($t['titulo']) ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Descrição:</label>
                                            <textarea name="editar_descricao" class="form-control"><?= htmlspecialchars($t['descricao']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label>Categoria:</label>
                                            <select name="editar_categoria" class="form-select">
                                                <option value="Trabalho" <?= $t['categoria'] === 'Trabalho' ? 'selected' : '' ?>>Trabalho</option>
                                                <option value="Pessoal" <?= $t['categoria'] === 'Pessoal' ? 'selected' : '' ?>>Pessoal</option>
                                                <option value="Urgente" <?= $t['categoria'] === 'Urgente' ? 'selected' : '' ?>>Urgente</option>
                                                <option value="Estudo" <?= $t['categoria'] === 'Estudo' ? 'selected' : '' ?>>Estudo</option>
                                                <option value="Outros" <?= $t['categoria'] === 'Outros' ? 'selected' : '' ?>>Outros</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label>Status:</label>
                                            <select name="editar_status" class="form-select">
                                                <option value="pendente" <?= $t['status'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                                                <option value="concluida" <?= $t['status'] === 'concluida' ? 'selected' : '' ?>>Concluída</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label>Data de Vencimento:</label>
                                            <input type="date" name="editar_data_vencimento" class="form-control" value="<?= $t['data_vencimento'] ?>">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-success">Salvar Alterações</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Nenhuma tarefa encontrada.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> 
</body>
</html>