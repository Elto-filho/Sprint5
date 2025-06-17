<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'administrador') {
    header("Location: login.php");
    exit;
}

// ========== ESTATÍSTICAS ==========
$stmt_usuarios = $pdo->query("SELECT COUNT(*) FROM usuarios");
$total_usuarios = $stmt_usuarios->fetchColumn();

$stmt_pendentes = $pdo->query("SELECT COUNT(*) FROM tarefas WHERE status = 'pendente'");
$tarefas_pendentes = $stmt_pendentes->fetchColumn();

$stmt_concluidas = $pdo->query("SELECT COUNT(*) FROM tarefas WHERE status = 'concluida'");
$tarefas_concluidas = $stmt_concluidas->fetchColumn();

$data_limite = date('Y-m-d', strtotime('+3 days'));
$stmt_vencimento = $pdo->query("
    SELECT COUNT(*) 
    FROM tarefas 
    WHERE data_vencimento IS NOT NULL AND data_vencimento <= '$data_limite' AND status = 'pendente'
");
$tarefas_proximas = $stmt_vencimento->fetchColumn();

$stmt_urgentes = $pdo->query("SELECT COUNT(*) FROM tarefas WHERE categoria = 'Urgente' AND status = 'pendente'");
$tarefas_urgentes = $stmt_urgentes->fetchColumn();

$stmt_atrasadas = $pdo->query("
    SELECT t.*, u.nome AS usuario_nome 
    FROM tarefas t
    JOIN usuarios u ON t.usuario_id = u.id
    WHERE t.data_vencimento IS NOT NULL AND t.data_vencimento < CURDATE() AND t.status = 'pendente'
");
$tarefas_atrasadas = $stmt_atrasadas->fetchAll(PDO::FETCH_ASSOC);

// ========== GRÁFICOS ==========
$stmt_categoria = $pdo->query("SELECT categoria, COUNT(*) AS total FROM tarefas GROUP BY categoria");
$tarefas_por_categoria = $stmt_categoria->fetchAll(PDO::FETCH_ASSOC);

$stmt_usuario = $pdo->query("
    SELECT u.nome AS usuario_nome, COUNT(*) AS total 
    FROM tarefas t
    JOIN usuarios u ON t.usuario_id = u.id
    GROUP BY u.id
");
$tarefas_por_usuario = $stmt_usuario->fetchAll(PDO::FETCH_ASSOC);

// ========== USUÁRIOS (somente listagem) ==========
$stmt_usuarios_lista = $pdo->query("SELECT id, nome, email, perfil FROM usuarios ORDER BY perfil DESC");
$usuarios = $stmt_usuarios_lista->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>📊 Dashboard Administrativo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"  rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-counter {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        <a href="logout.php" class="btn btn-danger">🚪 Sair</a>
    </div>

    <!-- Navegação Rápida -->
    <div class="mb-4 d-flex flex-wrap gap-2">
        <a href="dashboard.php" class="btn btn-info text-white">📊 Dashboard</a>
        <a href="registro.php" class="btn btn-success">➕ Novo Usuário</a>
        <a href="admin_painel.php" class="btn btn-secondary">📋 Todas as Tarefas</a>
        <a href="exportar_csv.php" class="btn btn-outline-primary">📥 Exportar CSV</a>
        <a href="exportar_pdf.php" class="btn btn-outline-danger">📄 Exportar PDF</a>
    </div>

    <!-- Foto do Admin -->
    <div class="row g-3 mb-4">
        <div class="col-md-12 d-flex align-items-center">
            <?php
            $foto_atual = file_exists("fotos/".$_SESSION['usuario_id'].".jpg") ? "fotos/".$_SESSION['usuario_id'].".jpg" : "fotos/default.jpg";
            ?>
            <img src="<?= $foto_atual ?>?t=<?= time() ?>" alt="Foto do Admin" class="rounded-circle me-3" style="width: 60px; height: 60px;">
            <h3 class="m-0"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Admin') ?> 👋</h3>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card card-counter bg-white text-center p-3 shadow-sm">
                <h5>Total de Usuários</h5>
                <h2><?= $total_usuarios ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-counter bg-white text-center p-3 shadow-sm">
                <h5>Tarefas Pendentes</h5>
                <h2><?= $tarefas_pendentes ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-counter bg-white text-center p-3 shadow-sm">
                <h5>Tarefas Concluídas</h5>
                <h2><?= $tarefas_concluidas ?></h2>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-counter bg-white text-center p-3 shadow-sm text-danger">
                <h5>Vencem nos Próximos 3 Dias</h5>
                <h2><?= $tarefas_proximas ?></h2>
            </div>
        </div>
    </div>

    <!-- Alertas -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-white">
                    ⚠️ Alertas Importantes
                </div>
                <div class="card-body">
                    <?php if ($tarefas_urgentes > 0): ?>
                        <p class="alert alert-danger">
                            🔥 Há <strong><?= $tarefas_urgentes ?></strong> tarefa(s) urgente(s) não concluída(s)!
                        </p>
                    <?php else: ?>
                        <p class="text-muted">Nenhuma tarefa urgente no momento.</p>
                    <?php endif; ?>

                    <?php if (count($tarefas_atrasadas) > 0): ?>
                        <p class="alert alert-danger">
                            ⏳ Há <strong><?= count($tarefas_atrasadas) ?></strong> tarefa(s) atrasada(s):
                            <ul>
                                <?php foreach ($tarefas_atrasadas as $t): ?>
                                    <li><strong><?= htmlspecialchars($t['titulo']) ?></strong> - <?= htmlspecialchars($t['usuario_nome']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </p>
                    <?php else: ?>
                        <p class="text-muted">Nenhuma tarefa atrasada até o momento.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="col-md-6">
            <div class="card mb-4 shadow-sm bg-white">
                <div class="card-body">
                    <h5>📊 Tarefas por Categoria</h5>
                    <canvas id="graficoCategoria" height="100"></canvas>
                </div>
            </div>

            <div class="card shadow-sm bg-white">
                <div class="card-body">
                    <h5>👥 Tarefas por Usuário</h5>
                    <canvas id="graficoUsuario" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Usuários -->
    <div class="card mb-4 shadow-sm bg-white">
        <div class="card-body">
            <h4>👥 Usuários Registrados</h4>
            <?php if (count($usuarios) > 0): ?>
                <table class="table table-bordered table-striped mt-3">
                    <thead class="table-dark">
                        <tr>
                            <th>Foto</th>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Perfil</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="text-center">
                                    <?php
                                    $foto = file_exists("fotos/".$u['id'].".jpg") ? "fotos/".$u['id'].".jpg" : "fotos/default.jpg";
                                    ?>
                                    <img src="<?= $foto ?>?t=<?= time() ?>" class="foto-perfil" alt="Foto">
                                </td>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['nome']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= ucfirst($u['perfil']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhum usuário encontrado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Histórico -->
    <div class="card mb-4 shadow-sm bg-white">
        <div class="card-body">
            <h5>📋 Histórico de Registros e Edições</h5>
            <?php
            $stmt_historico = $pdo->query("
                SELECT h.*, u.nome AS nome_editado, a.nome AS nome_admin 
                FROM historico_edicoes h
                JOIN usuarios u ON h.usuario_id = u.id
                JOIN usuarios a ON h.admin_id = a.id
                ORDER BY h.data_edicao DESC
            ");
            $historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (count($historico) > 0): ?>
                <table class="table table-bordered mt-3">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Ação</th>
                            <th>Realizada por</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $log): ?>
                            <tr>
                                <td><?= $log['id'] ?></td>
                                <td><?= htmlspecialchars($log['acao']) ?></td>
                                <td><?= htmlspecialchars($log['nome_admin']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($log['data_edicao'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">Nenhuma atividade registrada ainda.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rodapé -->
    <div class="mt-5 text-center text-muted pb-4">
        &copy; <?= date('Y') ?> - Sistema Corporativo de Tarefas
    </div>
</div>

<!-- Gráfico de Categorias -->
<script>
const ctxCategoria = document.getElementById('graficoCategoria').getContext('2d');
new Chart(ctxCategoria, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($tarefas_por_categoria as $cat): ?>"<?= $cat['categoria'] ?>", <?php endforeach; ?>],
        datasets: [{
            label: 'Tarefas por Categoria',
            data: [<?php foreach ($tarefas_por_categoria as $cat): ?>"<?= $cat['total'] ?>", <?php endforeach; ?>],
            backgroundColor: '#007bff'
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
            legend: { display: false }
        }
    }
});
</script>

<!-- Gráfico de Tarefas por Usuário -->
<script>
const ctxUsuario = document.getElementById('graficoUsuario').getContext('2d');
new Chart(ctxUsuario, {
    type: 'pie',
    data: {
        labels: [<?php foreach ($tarefas_por_usuario as $user): ?>"<?= htmlspecialchars($user['usuario_nome']) ?>", <?php endforeach; ?>],
        datasets: [{
            data: [<?php foreach ($tarefas_por_usuario as $user): ?>"<?= $user['total'] ?>", <?php endforeach; ?>],
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