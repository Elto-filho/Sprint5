<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'suporte') {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = intval($_POST['usuario_id']);
    $novo_nome = $_POST['novo_nome'];
    $nova_senha = !empty($_POST['nova_senha']) ? password_hash($_POST['nova_senha'], PASSWORD_DEFAULT) : null;

    // Verificar se o usuário é funcionário
    $stmt = $pdo->prepare("SELECT perfil FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    if (!$usuario || $usuario['perfil'] !== 'funcionario') {
        $_SESSION['erro'] = "Você só pode editar usuários do tipo funcionário.";
        header("Location: registro_suporte.php");
        exit;
    }

    // Atualizar nome e/ou senha
    $dados_atualizados = [];

    if ($novo_nome && strlen($novo_nome) >= 3) {
        $dados_atualizados[] = "nome = '$novo_nome'";
    }

    if ($nova_senha) {
        $dados_atualizados[] = "senha = '$nova_senha'";
    }

    if (!empty($dados_atualizados)) {
        $sql = "UPDATE usuarios SET " . implode(', ', $dados_atualizados) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$usuario_id]);

        // Registrar no histórico
        $acao = "Perfil editado - Nome: '$novo_nome'" . ($nova_senha ? ", Senha alterada" : "");
        $pdo->prepare("INSERT INTO historico_edicoes (admin_id, usuario_id, acao) VALUES (?, ?, ?)")
           ->execute([$_SESSION['usuario_id'], $usuario_id, $acao]);

        $_SESSION['mensagem'] = "Perfil atualizado com sucesso!";
    } else {
        $_SESSION['erro'] = "Nenhum dado válido foi alterado.";
    }

    header("Location: registro_suporte.php");
    exit;
}
?>