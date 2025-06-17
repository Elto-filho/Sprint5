<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'administrador') {
    header("Location: login.php");
    exit;
}

// ========== ATUALIZAR PERFIL DO USUÁRIO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usuario_id'], $_POST['novo_perfil'])) {
    $usuario_id = intval($_POST['usuario_id']);
    $novo_perfil = $_POST['novo_perfil'];

    if ($usuario_id == $_SESSION['usuario_id']) {
        $_SESSION['erro'] = "Você não pode alterar seu próprio perfil aqui.";
        header("Location: admin_painel.php");
        exit;
    }

    $stmt = $pdo->prepare("UPDATE usuarios SET perfil = ? WHERE id = ?");
    $stmt->execute([$novo_perfil, $usuario_id]);

    header("Location: admin_painel.php");
    exit;
}

// ========== ADICIONAR TAREFA ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_tarefa'])) {
    $usuario_id = intval($_POST['usuario_id']);
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'] ?? '';
    $categoria = $_POST['categoria'] ?? 'Outros';
    $data_vencimento = $_POST['data_vencimento'] ?: null;

    $stmt = $pdo->prepare("INSERT INTO tarefas (titulo, descricao, categoria, usuario_id, data_vencimento) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$titulo, $descricao, $categoria, $usuario_id, $data_vencimento]);

    header("Location: admin_painel.php");
    exit;
}

// ========== EDITAR TAREFA ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_tarefa'])) {
    $id = intval($_POST['tarefa_id']);
    $titulo = $_POST['editar_titulo'];
    $descricao = $_POST['editar_descricao'];
    $categoria = $_POST['editar_categoria'];
    $status = $_POST['editar_status'];
    $data_vencimento = $_POST['editar_data_vencimento'] ?: null;

    $stmt = $pdo->prepare("UPDATE tarefas SET titulo = ?, descricao = ?, categoria = ?, status = ?, data_vencimento = ? WHERE id = ?");
    $stmt->execute([$titulo, $descricao, $categoria, $status, $data_vencimento, $id]);

    header("Location: admin_painel.php");
    exit;
}

// ========== EXCLUIR TAREFA ==========
if (isset($_GET['excluir_tarefa'])) {
    $id = intval($_GET['excluir_tarefa']);
    $stmt = $pdo->prepare("DELETE FROM tarefas WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_painel.php");
    exit;
}

// ========== ALTERAR STATUS DA TAREFA ==========
if (isset($_GET['alterar_status_admin'])) {
    $id = intval($_GET['alterar_status_admin']);

    $stmt = $pdo->prepare("SELECT status FROM tarefas WHERE id = ?");
    $stmt->execute([$id]);
    $tarefa = $stmt->fetch();

    if ($tarefa) {
        $novo_status = $tarefa['status'] === 'pendente' ? 'concluida' : 'pendente';
        $stmt = $pdo->prepare("UPDATE tarefas SET status = ? WHERE id = ?");
        $stmt->execute([$novo_status, $id]);
    }

    header("Location: admin_painel.php");
    exit;
}

// Buscar todos os usuários
$stmt = $pdo->query("SELECT id, nome, email, perfil, telefone, cargo FROM usuarios ORDER BY perfil DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aplicar filtros nas tarefas
$usuario_id_filtro = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : null;
$categoria_filtro = isset($_GET['categoria']) ? $_GET['categoria'] : null;

$where_clausula = [];
if ($usuario_id_filtro) {
    $where_clausula[] = "t.usuario_id = $usuario_id_filtro";
}
if ($categoria_filtro) {
    $where_clausula[] = "t.categoria = '$categoria_filtro'";
}

$where_sql = "";
if (!empty($where_clausula)) {
    $where_sql = "WHERE " . implode(" AND ", $where_clausula);
}

// Buscar tarefas com base nos filtros
$stmt_tarefas = $pdo->query("
    SELECT t.*, u.nome AS usuario_nome 
    FROM tarefas t
    JOIN usuarios u ON t.usuario_id = u.id
    $where_sql
    ORDER BY u.id
");
$tarefas = $stmt_tarefas->fetchAll(PDO::FETCH_ASSOC);

// Tarefas por Categoria
$stmt_categoria = $pdo->query("
    SELECT categoria, COUNT(*) AS total 
    FROM tarefas 
    GROUP BY categoria
");
$tarefas_por_categoria = $stmt_categoria->fetchAll(PDO::FETCH_ASSOC);

// Tarefas por Usuário
$stmt_usuario = $pdo->query("
    SELECT u.nome AS usuario_nome, COUNT(*) AS total 
    FROM tarefas t
    JOIN usuarios u ON t.usuario_id = u.id
    GROUP BY u.id
");
$tarefas_por_usuario = $stmt_usuario->fetchAll(PDO::FETCH_ASSOC);

// Alerta de tarefas próximas ao vencimento
$data_limite = date('Y-m-d', strtotime('+3 days'));

$stmt_alerta_admin = $pdo->query("
    SELECT t.*, u.nome AS usuario_nome 
    FROM tarefas t
    JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.data_vencimento IS NOT NULL 
      AND t.data_vencimento <= '$data_limite'
      AND t.status = 'pendente'
    ORDER BY t.data_vencimento ASC
");
$tarefas_proximas_admin = $stmt_alerta_admin->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Painel do Administrador</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }

        .card-counter {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .foto-perfil {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🔐 Painel do Administrador</h2>
            <a href="logout.php" class="btn btn-danger">Sair</a>
        </div>

        <!-- Navegação rápida -->
        <div class="mb-3 d-flex flex-wrap gap-2">
            <a href="dashboard.php" class="btn btn-info text-white">📊 Dashboard</a>
            <a href="registro.php" class="btn btn-success">➕ Novo Usuário</a>
            <a href="admin_painel.php" class="btn btn-secondary">📋 Todas as Tarefas</a>
            <a href="exportar_csv.php" class="btn btn-outline-primary">📥 Exportar CSV</a>
            <a href="exportar_pdf.php" class="btn btn-outline-danger">📄 Exportar PDF</a>
        </div>

        <!-- Alerta de tarefas próximas ao vencimento -->
        <?php if (count($tarefas_proximas_admin) > 0): ?>
            <div class="alert alert-danger">
                🔥 Há <strong><?= count($tarefas_proximas_admin) ?></strong> tarefa(s) próxima(s) ao vencimento:
                <ul>
                    <?php foreach ($tarefas_proximas_admin as $t): ?>
                        <li><strong><?= htmlspecialchars($t['titulo']) ?></strong> - <?= htmlspecialchars($t['usuario_nome']) ?> - Vence em <?= date('d/m/Y', strtotime($t['data_vencimento'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Formulário para adicionar tarefa -->
        <h4 class="mt-5">➕ Adicionar Nova Tarefa</h4>
        <form method="POST" class="mb-4 p-3 bg-white rounded shadow-sm">
            <div class="mb-3">
                <label>Selecionar Usuário:</label>
                <select name="usuario_id" class="form-select" required>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nome']) ?> (<?= ucfirst($u['perfil']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Título:</label>
                <input type="text" name="titulo" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Descrição (opcional):</label>
                <textarea name="descricao" class="form-control"></textarea>
            </div>

            <div class="mb-3">
                <label>Categoria:</label>
                <select name="categoria" class="form-select">
                    <option value="Trabalho">Trabalho</option>
                    <option value="Pessoal">Pessoal</option>
                    <option value="Urgente">Urgente</option>
                    <option value="Estudo">Estudo</option>
                    <option value="Outros">Outros</option>
                </select>
            </div>

            <div class="mb-3">
                <label>Data de Vencimento (opcional):</label>
                <input type="date" name="data_vencimento" class="form-control">
            </div>

            <button type="submit" name="adicionar_tarefa" class="btn btn-success">Adicionar Tarefa</button>
        </form>

        <!-- Lista de Usuários -->
        <h3 class="mb-3">👥 Usuários Registrados</h3>
        <?php if (count($usuarios) > 0): ?>
            <table class="table table-bordered table-striped bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Foto</th>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Perfil</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td class="text-center">
                                <?php
                                $foto_atual = file_exists("fotos/" . $u['id'] . ".jpg") ? "fotos/" . $u['id'] . ".jpg" : "fotos/default.jpg";
                                ?>
                                <img src="<?= $foto_atual ?>?t=<?= time() ?>" class="foto-perfil" alt="Foto">
                            </td>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['nome']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= ucfirst($u['perfil']) ?></td>
                            <td class="text-center">
                                <a href="admin_tarefas.php?usuario_id=<?= $u['id'] ?>" class="btn btn-sm btn-info text-white">Ver Tarefas</a>
                                <?php if ($u['perfil'] !== 'administrador'): ?>
                                    <a href="#" onclick="confirmExclusao(<?= $u['id'] ?>)" class="btn btn-sm btn-danger">Excluir</a>
                                    <a href="editar_usuario.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-warning text-white">Editar</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nenhum usuário encontrado.</p>
        <?php endif; ?>

        <hr>

        <!-- Lista de Tarefas -->
        <h3 class="mb-3">📋 Todas as Tarefas do Sistema</h3>
        <?php if (count($tarefas) > 0): ?>
            <div class="row g-3">
                <?php foreach ($tarefas as $t): ?>
                    <div class="col-md-6">
                        <div class="card border-<?= $t['status'] === 'pendente' ? 'danger' : 'success' ?>">
                            <div class="card-body">
                                <small class="text-muted">Usuário: <?= htmlspecialchars($t['usuario_nome']) ?></small><br>
                                <h5 class="card-title"><?= htmlspecialchars($t['titulo']) ?></h5>
                                <p class="card-text"><?= nl2br(htmlspecialchars($t['descricao'])) ?></p>
                                <span class="badge bg-secondary"><?= htmlspecialchars($t['categoria']) ?></span>
                                <span class="badge bg-<?= $t['status'] === 'pendente' ? 'warning text-dark' : 'success' ?>">
                                    <?= $t['status'] === 'pendente' ? 'Pendente' : 'Concluída' ?>
                                </span>
                                <div class="mt-2">
                                    <a href="admin_alterar_status.php?alterar_status=<?= $t['id'] ?>" class="btn btn-sm btn-secondary">
                                        <?= $t['status'] === 'pendente' ? 'Marcar como concluída' : 'Marcar como pendente' ?>
                                    </a>
                                    <a href="admin_excluir_tarefa.php?excluir=<?= $t['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir?')" class="btn btn-sm btn-danger">Excluir</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">Nenhuma tarefa encontrada.</p>
        <?php endif; ?>
    </div>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="excluirModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Tem certeza que deseja excluir este usuário e todas as suas tarefas?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a id="confirmDeleteBtn" href="#" class="btn btn-danger">Excluir</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modais de Edição de Perfil -->
    <?php foreach ($usuarios as $u): ?>
        <div class="modal fade" id="editarPerfilModal<?= $u['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Editar Perfil de <?= htmlspecialchars($u['nome']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <div class="mb-3">
                                <label>Perfil:</label>
                                <select name="novo_perfil" class="form-select" required>
                                    <option value="funcionario" <?= $u['perfil'] === 'funcionario' ? 'selected' : '' ?>>Funcionário</option>
                                    <option value="suporte" <?= $u['perfil'] === 'suporte' ? 'selected' : '' ?>>Suporte</option>
                                    <option value="administrador" <?= $u['perfil'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                                </select>
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

    <!-- Modais de Edição de Tarefa -->
    <?php foreach ($tarefas as $t): ?>
        <div class="modal fade" id="editarTarefaModal<?= $t['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">Editar Tarefa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="editar_tarefa" value="1">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmExclusao(usuarioId) {
            document.getElementById('confirmDeleteBtn').href = 'admin_excluir_usuario.php?usuario_id=' + usuarioId;
            new bootstrap.Modal(document.getElementById('excluirModal')).show();
        }
    </script>

    <!-- Gráfico de Categorias -->
    <script>
        const ctxCategoria = document.getElementById('graficoCategoria').getContext('2d');
        new Chart(ctxCategoria, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($tarefas_por_categoria as $cat): ?> "<?= $cat['categoria'] ?>", <?php endforeach; ?>],
                datasets: [{
                    label: 'Tarefas por Categoria',
                    data: [<?php foreach ($tarefas_por_categoria as $cat): ?> "<?= $cat['total'] ?>", <?php endforeach; ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.6)'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + " tarefa(s)";
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>

    <!-- Gráfico de Usuários -->
    <script>
        const ctxUsuario = document.getElementById('graficoUsuario').getContext('2d');
        new Chart(ctxUsuario, {
            type: 'pie',
            data: {
                labels: [<?php foreach ($tarefas_por_usuario as $user): ?> "<?= htmlspecialchars($user['usuario_nome']) ?>", <?php endforeach; ?>],
                datasets: [{
                    data: [<?php foreach ($tarefas_por_usuario as $user): ?> "<?= $user['total'] ?>", <?php endforeach; ?>],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)',
                        'rgba(255, 159, 64, 0.6)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + " tarefa(s)";
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>