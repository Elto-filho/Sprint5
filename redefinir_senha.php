<?php
require_once 'conexao.php';

$token = $_GET['token'] ?? null;

if (!$token) {
    die("<div class='alert alert-danger'>Token inválido.</div>");
}

// Buscar token válido e não usado
$stmt = $pdo->prepare("
    SELECT * FROM recuperacao_senha 
    WHERE token = ? AND usado = 0 AND expira_em > NOW()
");
$stmt->execute([$token]);
$registro = $stmt->fetch();

if (!$registro) {
    die("<div class='alert alert-danger'>Token inválido ou expirado.</div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    $usuario_id = $registro['usuario_id'];

    // Atualizar senha do usuário
    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
    $stmt->execute([$nova_senha, $usuario_id]);

    // Marcar token como usado
    $pdo->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE token = ?")->execute([$token]);

    echo "<div class='alert alert-success'>Senha alterada com sucesso! <a href='login.php'>Faça login novamente</a></div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Redefinir Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"  rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="card mx-auto" style="max-width: 400px;">
            <div class="card-body text-center">
                <h2>🔐 Redefinir Senha</h2>
                <form method="POST" class="mt-4">
                    <div class="mb-3">
                        <label>Nova Senha:</label>
                        <input type="password" name="senha" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100">Salvar Nova Senha</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>