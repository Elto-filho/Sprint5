<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
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
    header("Location: painel.php");
    exit;
}

// Editar uma tarefa existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_titulo'])) {
    $id = $_POST['tarefa_id'];
    $titulo = $_POST['editar_titulo'];
    $descricao = $_POST['editar_descricao'];
    $categoria = $_POST['editar_categoria'];
    $data_vencimento = $_POST['editar_data_vencimento'] ?: null;

    $stmt = $pdo->prepare("UPDATE tarefas SET titulo = ?, descricao = ?, categoria = ?, data_vencimento = ? WHERE id = ?");
    $stmt->execute([$titulo, $descricao, $categoria, $data_vencimento, $id]);
    header("Location: painel.php");
    exit;
}

// Alterar status da tarefa
if (isset($_GET['alterar_status'])) {
    $id = $_GET['alterar_status'];

    $stmt = $pdo->prepare("SELECT status, usuario_id FROM tarefas WHERE id = ?");
    $stmt->execute([$id]);
    $tarefa = $stmt->fetch();

    if ($tarefa && ($tarefa['usuario_id'] === $usuario_id || $perfil === 'administrador')) {
        $novo_status = $tarefa['status'] === 'pendente' ? 'concluida' : 'pendente';
        $stmt = $pdo->prepare("UPDATE tarefas SET status = ? WHERE id = ?");
        $stmt->execute([$novo_status, $id]);
    }
    header("Location: painel.php");
    exit;
}

// Excluir tarefa
if (isset($_GET['excluir'])) {
    $id = $_GET['excluir'];

    $stmt = $pdo->prepare("SELECT usuario_id FROM tarefas WHERE id = ?");
    $stmt->execute([$id]);
    $tarefa = $stmt->fetch();

    if ($tarefa && ($tarefa['usuario_id'] === $usuario_id || $perfil === 'administrador')) {
        $stmt = $pdo->prepare("DELETE FROM tarefas WHERE id = ?");
        $stmt->execute([$id]);
    }
    header("Location: painel.php");
    exit;
}

// Buscar tarefas - se for administrador, verá todas
$data_limite = date('Y-m-d', strtotime('+3 days'));

if ($perfil === 'administrador') {
    $stmt = $pdo->query("SELECT * FROM tarefas ORDER BY usuario_id");

    // Tarefas próximas ao vencimento no sistema todo
    $stmt_alerta_admin = $pdo->query("
        SELECT t.*, u.nome AS usuario_nome 
        FROM tarefas t
        JOIN usuarios u ON t.usuario_id = u.id
        WHERE t.data_vencimento IS NOT NULL AND t.data_vencimento <= '$data_limite' AND t.status = 'pendente'
    ");
    $tarefas_proximas_admin = $stmt_alerta_admin->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT * FROM tarefas WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);

    // Tarefas próximas ao vencimento do usuário
    $stmt_alerta_user = $pdo->prepare("
        SELECT * FROM tarefas 
        WHERE usuario_id = ?
          AND data_vencimento IS NOT NULL
          AND data_vencimento <= ?
          AND status = 'pendente'
    ");
    $stmt_alerta_user->execute([$usuario_id, $data_limite]);
    $tarefas_proximas_user = $stmt_alerta_user->fetchAll();
}

$tarefas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Painel de Tarefas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Bem-vindo ao seu Painel</h2>
            <a href="logout.php" class="btn btn-danger">Sair</a>
        </div>

        <p><strong>Perfil:</strong> <?= ucfirst($perfil) ?></p>

        <?php
        $foto_atual = file_exists("fotos/{$usuario_id}.jpg") ? "fotos/{$usuario_id}.jpg" : "fotos/default.jpg";
        ?>
        <div class="mb-3 text-center">
            <img src="<?= $foto_atual ?>?t=<?= time() ?>" alt="Foto de perfil" class="rounded-circle shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
        </div>

        <!-- Apenas usuários administradores veem esse link -->
        <?php if ($perfil === 'administrador'): ?>
            <div class="alert alert-info">
                Você está logado como <strong>Administrador</strong>. Pode visualizar e gerenciar todas as tarefas.
                <br><br>
                <a href="admin_painel.php" class="btn btn-primary btn-sm">Ir para Painel do Administrador</a>
            </div>
        <?php endif; ?>

        <!-- Notificação de tarefas próximas ao vencimento -->
        <?php if ($perfil === 'administrador' && isset($tarefas_proximas_admin) && count($tarefas_proximas_admin) > 0): ?>
            <div class="alert alert-danger">
                🔥 Há <strong><?= count($tarefas_proximas_admin) ?></strong> tarefa(s) próxima(s) ao vencimento:
                <ul>
                    <?php foreach ($tarefas_proximas_admin as $t): ?>
                        <li><strong><?= htmlspecialchars($t['titulo']) ?></strong> - Usuário: <?= htmlspecialchars($t['usuario_nome']) ?> - Vence em <?= date('d/m/Y', strtotime($t['data_vencimento'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($perfil !== 'administrador' && isset($tarefas_proximas_user) && count($tarefas_proximas_user) > 0): ?>
            <div class="alert alert-warning">
                ⚠️ Você tem <strong><?= count($tarefas_proximas_user) ?></strong> tarefa(s) próxima(s) ao vencimento:
                <ul>
                    <?php foreach ($tarefas_proximas_user as $t): ?>
                        <li><strong><?= htmlspecialchars($t['titulo']) ?></strong> - Vence em <?= date('d/m/Y', strtotime($t['data_vencimento'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

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
        <h4><?= $perfil === 'administrador' ? 'Todas as Tarefas do Sistema' : 'Suas Tarefas' ?></h4>
        <?php if (count($tarefas) > 0): ?>
            <div class="row g-3">
                <?php foreach ($tarefas as $tarefa): ?>
                    <div class="col-md-6">
                        <div class="card border-<?= $tarefa['status'] === 'pendente' ? 'danger' : 'success' ?>">
                            <div class="card-body">
                                <?php if ($perfil === 'administrador'): ?>
                                    <small class="text-muted">Usuário ID: <?= $tarefa['usuario_id'] ?></small><br>
                                <?php endif; ?>
                                <h5 class="card-title"><?= htmlspecialchars($tarefa['titulo']) ?></h5>
                                <p class="card-text"><?= nl2br(htmlspecialchars($tarefa['descricao'])) ?></p>
                                <span class="badge bg-secondary"><?= htmlspecialchars($tarefa['categoria']) ?></span>
                                <span class="badge bg-<?= $tarefa['status'] === 'pendente' ? 'warning text-dark' : 'success' ?>">
                                    <?= $tarefa['status'] === 'pendente' ? 'Pendente' : 'Concluída' ?>
                                </span>
                                <div class="mt-2 d-flex justify-content-between">
                                    <div>
                                        <a href="?alterar_status=<?= $tarefa['id'] ?>" class="btn btn-sm btn-secondary">
                                            <?= $tarefa['status'] === 'pendente' ? 'Marcar como concluída' : 'Marcar como pendente' ?>
                                        </a>
                                        <a href="#" class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#editarModal<?= $tarefa['id'] ?>">Editar</a>
                                        <a href="?excluir=<?= $tarefa['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir?')" class="btn btn-sm btn-danger">Excluir</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal de Edição -->
                    <div class="modal fade" id="editarModal<?= $tarefa['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar Tarefa</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="tarefa_id" value="<?= $tarefa['id'] ?>">
                                        <div class="mb-3">
                                            <label>Título:</label>
                                            <input type="text" name="editar_titulo" value="<?= htmlspecialchars($tarefa['titulo']) ?>" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label>Descrição:</label>
                                            <textarea name="editar_descricao" class="form-control"><?= htmlspecialchars($tarefa['descricao']) ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label>Categoria:</label>
                                            <select name="editar_categoria" class="form-select">
                                                <option value="Trabalho" <?= $tarefa['categoria'] === 'Trabalho' ? 'selected' : '' ?>>Trabalho</option>
                                                <option value="Pessoal" <?= $tarefa['categoria'] === 'Pessoal' ? 'selected' : '' ?>>Pessoal</option>
                                                <option value="Urgente" <?= $tarefa['categoria'] === 'Urgente' ? 'selected' : '' ?>>Urgente</option>
                                                <option value="Estudo" <?= $tarefa['categoria'] === 'Estudo' ? 'selected' : '' ?>>Estudo</option>
                                                <option value="Outros" <?= $tarefa['categoria'] === 'Outros' ? 'selected' : '' ?>>Outros</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label>Data de Vencimento:</label>
                                            <input type="date" name="editar_data_vencimento" class="form-control" value="<?= $tarefa['data_vencimento'] ?>">
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
            <p class="text-muted">Você não tem tarefas ainda.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>