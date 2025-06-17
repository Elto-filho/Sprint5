<?php
require_once 'conexao.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=tarefas_exportadas.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Título', 'Descrição', 'Categoria', 'Status', 'Usuário', 'Data Vencimento']);

$stmt = $pdo->query("
    SELECT t.*, u.nome AS usuario_nome 
    FROM tarefas t
    JOIN usuarios u ON t.usuario_id = u.id
");

while ($tarefa = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $tarefa['id'],
        $tarefa['titulo'],
        $tarefa['descricao'],
        $tarefa['categoria'],
        $tarefa['status'],
        $tarefa['usuario_nome'],
        $tarefa['data_vencimento']
    ]);
}

fclose($output);
exit;