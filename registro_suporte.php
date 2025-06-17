<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'suporte') {
    header("Location: login.php");
    exit;
}

// ========== REGISTRO DE NOVO USUÁRIO ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    // O suporte só pode cadastrar 'funcionario' ou 'administrador'
    $perfil = in_array($_POST['perfil'], ['funcionario', 'administrador']) ? $_POST['perfil'] : 'funcionario';

    // Verificar se o email já existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['erro'] = "Este email já está cadastrado.";
        header("Location: registro_suporte.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$nome, $email, $senha, $perfil])) {
            // Salvar no histórico
            $admin_id = $_SESSION['usuario_id'];
            $acao = "Registro de novo usuário - Nome: '$nome', Email: '$email', Perfil: '$perfil'";
            $pdo->prepare("INSERT INTO historico_edicoes (admin_id, usuario_id, acao) VALUES (?, ?, ?)")
               ->execute([$admin_id, $pdo->lastInsertId(), $acao]);

            $_SESSION['mensagem'] = "Usuário registrado com sucesso!";
            header("Location: registro_suporte.php");
            exit;
        }
    } catch (PDOException $e) {
        die("Erro ao registrar usuário: " . $e->getMessage());
    }
}

// ========== EXCLUIR USUÁRIO ==========
if (isset($_GET['excluir'])) {
    $id = intval($_GET['excluir']);

    // O suporte só pode excluir usuários com perfil 'funcionario'
    $stmt = $pdo->prepare("SELECT id, perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();

    if ($usuario && $usuario['perfil'] === 'funcionario') {
        $stmt_nome = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
        $stmt_nome->execute([$id]);
        $nome_usuario = $stmt_nome->fetchColumn();

        // Excluir usuário
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        // Salvar no histórico
        $admin_id = $_SESSION['usuario_id'];
        $acao = "Funcionário excluído - ID: $id, Nome: '$nome_usuario'";
        $pdo->prepare("INSERT INTO historico_edicoes (admin_id, usuario_id, acao) VALUES (?, ?, ?)")
           ->execute([$admin_id, $id, $acao]);

        $_SESSION['mensagem'] = "Funcionário excluído com sucesso.";
    } else {
        $_SESSION['erro'] = "Você não pode excluir este usuário.";
    }

    header("Location: registro_suporte.php");
    exit;
}

// Buscar todos os funcionários
$stmt_usuarios = $pdo->query("SELECT id, nome, email, perfil FROM usuarios WHERE perfil IN ('funcionario', 'administrador')");
$usuarios = $stmt_usuarios->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>🛠️ Painel do Suporte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"  rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .foto-perfil {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
        }
        input.is-invalid {
            border-color: red;
            background-color: #ffe6e6 !important;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🛠️ Painel do Suporte</h2>
        <a href="logout.php" class="btn btn-danger">🚪 Sair</a>
    </div>

    <p><strong>Você está logado como:</strong> <?= ucfirst($_SESSION['perfil']) ?></p>

    <!-- Formulário de Registro -->
    <h4 class="mb-4">➕ Registrar Novo Usuário</h4>
    <?php if (isset($_SESSION['mensagem'])): ?>
        <div class="alert alert-success"><?= $_SESSION['mensagem']; unset($_SESSION['mensagem']); ?></div>
    <?php elseif (isset($_SESSION['erro'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['erro']; unset($_SESSION['erro']); ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-3 rounded shadow-sm mb-5" onsubmit="return validarFormulario(this)">
        <div class="mb-3">
            <label>Nome:</label>
            <input type="text" name="nome" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Email:</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Senha:</label>
            <input type="password" name="senha" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Perfil:</label>
            <select name="perfil" class="form-select">
                <option value="funcionario">Funcionário</option>
                <option value="administrador">Administrador</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary w-100">Registrar Usuário</button>
    </form>

    <!-- Lista de Funcionários -->
    <h4 class="mb-3">👥 Funcionários Registrados</h4>
    <?php if (count($usuarios) > 0): ?>
        <table class="table table-bordered table-striped bg-white">
            <thead class="table-dark">
                <tr>
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
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['nome'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($u['email'] ?? 'N/A') ?></td>
                        <td><?= ucfirst($u['perfil'] ?? 'Não definido') ?></td>
                        <td class="text-center">
                            <?php if (isset($u['perfil']) && $u['perfil'] === 'funcionario'): ?>
                                <a href="#" class="btn btn-sm btn-warning text-white" data-bs-toggle="modal" data-bs-target="#editarPerfilModal<?= $u['id'] ?>">Editar</a>
                                <a href="?excluir=<?= $u['id'] ?>" onclick="return confirm('Tem certeza que deseja excluir?')" class="btn btn-sm btn-danger">Excluir</a>
                            <?php elseif (isset($u['perfil']) && $u['perfil'] === 'administrador'): ?>
                                <span class="badge bg-secondary">Não pode ser editado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nenhum funcionário encontrado.</p>
    <?php endif; ?>

    <!-- Histórico de Edições -->
    <h4 class="mt-5">📋 Histórico de Registros e Edições</h4>
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
        <table class="table table-bordered bg-white">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Editado por</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historico as $log): ?>
                    <tr>
                        <td><?= $log['id'] ?></td>
                        <td><?= htmlspecialchars($log['nome_admin'] ?? 'Desconhecido') ?></td>
                        <td><?= htmlspecialchars($log['nome_editado'] ?? 'Desconhecido') ?> (<?= ucfirst($log['perfil'] ?? 'Não definido') ?>)</td>
                        <td><?= htmlspecialchars($log['acao']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($log['data_edicao'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-muted">Nenhuma edição registrada ainda.</p>
    <?php endif; ?>
</div>

<!-- Modal de Edição -->
<?php foreach ($usuarios as $u): ?>
    <?php if ($u['perfil'] === 'funcionario'): ?>
        <div class="modal fade" id="editarPerfilModal<?= $u['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="editar_perfil_suporte.php" onsubmit="return validarEdicao(this)">
                        <div class="modal-header">
                            <h5 class="modal-title">Editar Perfil de <?= htmlspecialchars($u['nome']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <div class="mb-3">
                                <label>Novo Nome:</label>
                                <input type="text" name="novo_nome" class="form-control" placeholder="Deixe vazio para manter o mesmo nome">
                            </div>
                            <div class="mb-3">
                                <label>Nova Senha (opcional):</label>
                                <input type="password" name="nova_senha" class="form-control" placeholder="Deixe em branco para manter a mesma senha">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- Validação JS -->
<script>
function validarFormulario(form) {
    const nome = form.nome.value.trim();
    const email = form.email.value.trim();
    const senha = form.senha.value;

    form.nome.classList.remove('is-invalid');
    form.email.classList.remove('is-invalid');
    form.senha.classList.remove('is-invalid');

    let valido = true;

    if (nome.length < 3) {
        alert("O nome deve ter pelo menos 3 caracteres.");
        form.nome.classList.add('is-invalid');
        return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert("Por favor, insira um email válido.");
        form.email.classList.add('is-invalid');
        return false;
    }

    if (senha.length < 6) {
        alert("A senha deve ter pelo menos 6 caracteres.");
        form.senha.classList.add('is-invalid');
        return false;
    }

    return true;
}

function validarEdicao(form) {
    const novoNome = form.novo_nome.value.trim();
    const novaSenha = form.nova_senha.value;

    form.novo_nome.classList.remove('is-invalid');
    form.nova_senha.classList.remove('is-invalid');

    if (novoNome && novoNome.length < 3) {
        alert("O novo nome deve ter pelo menos 3 caracteres.");
        form.novo_nome.classList.add('is-invalid');
        return false;
    }

    if (novaSenha && novaSenha.length < 6) {
        alert("A nova senha deve ter pelo menos 6 caracteres.");
        form.nova_senha.classList.add('is-invalid');
        return false;
    }

    return true;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> 
</body>
</html>