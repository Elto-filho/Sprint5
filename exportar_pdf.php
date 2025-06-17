<?php
require_once 'conexao.php';
require_once __DIR__ . '/vendor/autoload.php'; // Caminho do autoload do Composer
if (!is_writable(sys_get_temp_dir())) {
    die("A pasta temporária não tem permissão de escrita.");
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
use Mpdf\Mpdf;

// Configurar o MPDF
$mpdf = new Mpdf();

$stmt = $pdo->query("
    SELECT t.*, u.nome AS usuario_nome 
    FROM tarefas t
    JOIN usuarios u ON t.usuario_id = u.id
");

$tarefas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '
<h1>📋 Relatório de Tarefas</h1>
<style>
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 8px; }
    th { background-color: #f0f0f0; }
</style>
<table>
    <tr>
        <th>ID</th>
        <th>Título</th>
        <th>Descrição</th>
        <th>Categoria</th>
        <th>Status</th>
        <th>Usuário</th>
        <th>Vencimento</th>
    </tr>
';

foreach ($tarefas as $t) {
    $html .= '
    <tr>
        <td>'.$t['id'].'</td>
        <td>'.htmlspecialchars($t['titulo']).'</td>
        <td>'.htmlspecialchars($t['descricao']).'</td>
        <td>'.$t['categoria'].'</td>
        <td>'.$t['status'].'</td>
        <td>'.$t['usuario_nome'].'</td>
        <td>'.($t['data_vencimento'] ?: 'N/A').'</td>
    </tr>';
}

$html .= '</table>';

// Configurar o cabeçalho HTTP para forçar o download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="relatorio_tarefas.pdf"');

// Gerar e enviar o PDF
$mpdf->WriteHTML($html);
$mpdf->Output('relatorio_tarefas.pdf', 'D'); // D = Download
exit;