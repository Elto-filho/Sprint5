<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['perfil'] !== 'administrador') {
    header("Location: login.php");
    exit;
}

$usuario_id = intval($_POST['usuario_id']);

// Verificar se o arquivo foi enviado
if (isset($_FILES['foto']) && is_uploaded_file($_FILES['foto']['tmp_name'])) {
    // Nome do arquivo original
    $nome_arquivo = basename($_FILES['foto']['name']);
    $extensao = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));

    // Pasta onde as fotos serão salvas
    $pasta_fotos = "fotos/";

    // Criar a pasta se não existir
    if (!is_dir($pasta_fotos)) {
        mkdir($pasta_fotos, 0755, true);
    }

    // Caminho completo para salvar a foto
    $caminho = $pasta_fotos . $usuario_id . "." . $extensao;

    // Mover o arquivo temporário para a pasta de destino
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $caminho)) {
        $_SESSION['mensagem'] = "Foto atualizada com sucesso!";
    } else {
        $_SESSION['erro'] = "Erro ao mover o arquivo.";
    }
} else {
    $_SESSION['erro'] = "Nenhum arquivo foi enviado.";
}

header("Location: editar_usuario.php?id=$usuario_id");
exit;
?>