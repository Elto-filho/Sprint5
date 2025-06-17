<?php
session_start();
require_once 'conexao.php';

// Verificar se o usuário tem permissão
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['perfil'], ['administrador', 'suporte'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    // O suporte só pode cadastrar 'funcionario' ou 'administrador'
    $perfil = in_array($_POST['perfil'], ['funcionario', 'administrador']) ? $_POST['perfil'] : 'funcionario';

    // Impedir que suporte crie outro suporte
    if ($_SESSION['perfil'] === 'suporte' && $perfil === 'administrador') {
        $_SESSION['erro'] = "Você não pode criar um novo administrador.";
        header("Location: registro.php");
        exit;
    }

    // Verificar se o email já existe
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['erro'] = "Este email já está cadastrado.";
        header("Location: registro.php");
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
            header("Location: dashboard.php");
            exit;
        }
    } catch (PDOException $e) {
        die("Erro ao registrar usuário: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>➕ Registrar Novo Usuário</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🔐 Registrar Novo Usuário</h2>
            <a href="dashboard.php" class="btn btn-secondary">⬅️ Voltar ao Dashboard</a>
        </div>

        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="alert alert-success"><?= $_SESSION['mensagem'];
                                                unset($_SESSION['mensagem']); ?></div>
        <?php elseif (isset($_SESSION['erro'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['erro'];
                                            unset($_SESSION['erro']); ?></div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-4 rounded shadow-sm">
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
                <input type="password" name="senha" class="form-control" required minlength="6">
            </div>

            <div class="mb-3">
                <label>Perfil:</label>
                <select name="perfil" class="form-select" <?= $_SESSION['perfil'] === 'suporte' ? 'disabled' : '' ?>>
                    <option value="funcionario">Funcionário</option>
                    <option value="administrador">Administrador</option>
                </select>
                <?php if ($_SESSION['perfil'] === 'suporte'): ?>
                    <small class="text-muted">Você só pode criar usuários do tipo 'Funcionário'</small>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-success w-100">Salvar Usuário</button>
        </form>

        <p class="mt-3 text-center">Não tem conta? <a href="login.php">Faça login</a></p>
    </div>

    <script>
        // Validação simples via JavaScript
        document.querySelector('form').addEventListener('submit', function(e) {
            const senha = document.querySelector("[name='senha']").value;
            if (senha.length < 6) {
                alert("A senha deve ter pelo menos 6 caracteres.");
                e.preventDefault();
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>