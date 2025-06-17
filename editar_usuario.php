<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'administrador') {
    header("Location: login.php");
    exit;
}

$usuario_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Buscar dados do usuário a ser editado
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    $_SESSION['erro'] = "Usuário não encontrado.";
    header("Location: dashboard.php");
    exit;
}

// Impedir edição de outro administrador
if ($usuario['perfil'] === 'administrador' && $usuario['id'] != $_SESSION['usuario_id']) {
    $_SESSION['erro'] = "Você não pode editar outro administrador.";
    header("Location: dashboard.php");
    exit;
}

// Processar envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'] ?? null;
    $cargo = $_POST['cargo'] ?? null;
    $novo_perfil = $_POST['perfil'] ?? 'funcionario';
    $nova_senha = !empty($_POST['senha']) ? password_hash($_POST['senha'], PASSWORD_DEFAULT) : null;

    // Validar nome
    if (strlen($nome) < 3) {
        $_SESSION['erro'] = "O nome deve ter pelo menos 3 caracteres.";
        header("Location: editar_usuario.php?id=$usuario_id");
        exit;
    }

    // Atualizar senha, se fornecida
    if ($nova_senha) {
        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ?, cargo = ?, perfil = ?, senha = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $telefone, $cargo, $novo_perfil, $nova_senha, $usuario_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ?, cargo = ?, perfil = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $telefone, $cargo, $novo_perfil, $usuario_id]);
    }

    // Salvar no histórico
    $admin_id = $_SESSION['usuario_id'];
    $acao = "Perfil atualizado - Nome: '$nome', Email: '$email'" . ($nova_senha ? ", Senha alterada" : "");

    $pdo->prepare("INSERT INTO historico_edicoes (admin_id, usuario_id, acao) VALUES (?, ?, ?)")
        ->execute([$admin_id, $usuario_id, $acao]);

    $_SESSION['mensagem'] = "Usuário atualizado com sucesso!";
    header("Location: editar_usuario.php?id=$usuario_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Editar Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .foto-perfil {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
        }
    </style>
</head>

<body class="bg-light">

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>✏️ Editar Usuário</h2>
            <a href="dashboard.php" class="btn btn-secondary">⬅️ Voltar ao Dashboard</a>
        </div>

        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-success"><?= $_SESSION['mensagem'];
                                                unset($_SESSION['mensagem']); ?></div>
        <?php elseif (isset($_SESSION['erro'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['erro'];
                                            unset($_SESSION['erro']); ?></div>
        <?php endif; ?>

        <!-- Formulário -->
        <form method="POST" class="bg-white p-4 rounded shadow-sm" onsubmit="return validarFormulario(this)">
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Nome:</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                    <label>Telefone:</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>" class="form-control">
                </div>
                <div class="col-md-6">
                    <label>Cargo:</label>
                    <input type="text" name="cargo" value="<?= htmlspecialchars($usuario['cargo'] ?? '') ?>" class="form-control">
                </div>
                <div class="col-md-6">
                    <label>Perfil:</label>
                    <select name="perfil" class="form-select">
                        <option value="funcionario" <?= $usuario['perfil'] === 'funcionario' ? 'selected' : '' ?>>Funcionário</option>
                        <option value="suporte" <?= $usuario['perfil'] === 'suporte' ? 'selected' : '' ?>>Suporte</option>
                        <option value="administrador" <?= $usuario['perfil'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Nova Senha (opcional):</label>
                    <input type="password" name="senha" class="form-control" placeholder="Deixe em branco para manter a mesma senha">
                </div>
            </div>

            <hr>

            <!-- Botão de salvar -->
            <button type="submit" class="btn btn-success mt-3">💾 Salvar Alterações</button>
        </form>

        <!-- Upload de Foto -->
        <div class="mt-4 bg-white p-4 rounded shadow-sm">
            <h5>🖼️ Foto de Perfil</h5>
            <form action="upload_foto.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="usuario_id" value="<?= $usuario_id ?>">
                <input type="file" name="foto" class="form-control mb-2" accept="image/*">
                <button type="submit" class="btn btn-primary">Atualizar Foto</button>
            </form>

            <!-- Mostrar foto atual -->
            <?php
            $foto_atual = file_exists("fotos/{$usuario_id}.jpg") ? "fotos/{$usuario_id}.jpg" : "fotos/default.jpg";
            ?>
            <div class="mt-3">
                <img src="<?= $foto_atual ?>?t=<?= time() ?>" alt="Foto do usuário" class="foto-perfil shadow-sm">
            </div>
        </div>
    </div>

    <!-- Validação com JS -->
    <script>
        function validarFormulario(form) {
            const nome = form.nome.value.trim();
            const email = form.email.value.trim();

            form.nome.classList.remove('is-invalid');
            form.email.classList.remove('is-invalid');

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

            return true;
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>