<?php
require_once 'conexao.php';
require 'vendor/autoload.php'; // Se estiver usando Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];

    // Buscar usuário pelo email
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        echo "<div class='alert alert-danger'>Se este email estiver cadastrado, você receberá as instruções.</div>";
        exit;
    }

    // Gerar token único
    $token = bin2hex(random_bytes(50));
    $usuario_id = $usuario['id'];
    $expira_em = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Salvar token no banco
    $stmt = $pdo->prepare("INSERT INTO recuperacao_senha (usuario_id, token, expira_em) VALUES (?, ?, ?)");
    $stmt->execute([$usuario_id, $token, $expira_em]);

    // Montar link de redefinição
    $link = "http://seusite.com/redefinir_senha.php?token=$token";

    // Enviar email com PHPMailer
    try {
        $mail = new PHPMailer(true);

        // Configurar o servidor SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Exemplo: Gmail
        $mail->SMTPAuth   = true;
        $mail->Username   = 'seu_email@gmail.com'; // Seu email
        $mail->Password   = 'sua_senha'; // Sua senha (ou app password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Configurar remetente e destinatário
        $mail->setFrom('no-reply@seusite.com', 'Seu Site');
        $mail->addAddress($email);

        // Assunto e corpo do email
        $mail->Subject = 'Redefinição de Senha';
        $mail->Body    = "Clique no link abaixo para redefinir sua senha:\n\n$link\n\nEste link expira em 1 hora.";

        // Enviar email
        $mail->send();
        echo "<div class='alert alert-success'>Se este email estiver cadastrado, você receberá as instruções.</div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Erro ao enviar email: {$mail->ErrorInfo}</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Senha</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"  rel="stylesheet">
</head>
<body class="bg-light py-5">
    <div class="container">
        <div class="card mx-auto" style="max-width: 400px;">
            <div class="card-body text-center">
                <h2>🔑 Recuperar Senha</h2>
                <form method="POST" class="mt-4">
                    <div class="mb-3">
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Enviar Instruções</button>
                </form>
                <p class="mt-3"><a href="login.php">Voltar ao login</a></p>
            </div>
        </div>
    </div>
</body>
</html>