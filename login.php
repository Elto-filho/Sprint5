<?php
session_start();
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    // Buscar usuário pelo email
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    // Verificar se o usuário existe e a senha está correta
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        // Salvar dados na sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['perfil'] = $usuario['perfil'];

        // Redirecionar com base no perfil
        if ($usuario['perfil'] === 'administrador') {
            header("Location: dashboard.php");
            exit;
        } elseif ($usuario['perfil'] === 'suporte') {
            header("Location: registro_suporte.php");
            exit;
        } else {
            header("Location: painel.php");
            exit;
        }
    } else {
        // Senha ou email inválidos
        $_SESSION['erro'] = "Email ou senha inválidos";
        header("Location: login.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card mx-auto" style="max-width: 400px;">
            <div class="card-body">
                <h2 class="text-center mb-4">Login</h2>

                <?php if (isset($_SESSION['erro'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['erro'];
                                                    unset($_SESSION['erro']); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label>Senha:</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
                <p class="mt-3 text-center">
                    <a href="recuperar_senha.php">Esqueceu a senha?</a>
                </p>
                <p class="mt-3 text-center">Apenas administradores podem registrar novos usuários.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>