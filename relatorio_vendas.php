
<?php
require_once 'conexao.php';

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moeda($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function dataFormatada($data): string
{
    if (empty($data)) {
        return 'Data não informada';
    }

    $timestamp = strtotime($data);

    return $timestamp ? date('d/m/Y', $timestamp) : 'Data inválida';
}

// Indicadores baseados no histórico completo de vendas.
$sqlResumo = "
    SELECT
        COUNT(*) AS total_vendas,
        COALESCE(SUM(valor_total), 0) AS faturamento_total,
        COALESCE(AVG(valor_total), 0) AS ticket_medio
    FROM venda
";

$resumo = $conn->query($sqlResumo)->fetch_assoc();

// Consulta que preserva vendas mesmo quando o veículo
// não está mais cadastrado no estoque.
$sqlVendas = "
    SELECT
        v.id_venda,
        v.id_carro,
        v.id_cliente,
        v.id_tipo_pagamento,
        v.data_venda,
        v.valor_total,
        v.veiculo_nome,
        v.veiculo_marca,

        c.id_loja,
        cl.nome AS cliente_nome,
        tp.descricao AS pagamento_descricao,
        l.nome AS loja_nome

    FROM venda v

    LEFT JOIN cliente cl
        ON v.id_cliente = cl.id_cliente

    LEFT JOIN tipopagamento tp
        ON v.id_tipo_pagamento = tp.id_tipo_pagamento

    LEFT JOIN carro c
        ON v.id_carro = c.id_carro

    LEFT JOIN loja l
        ON c.id_loja = l.id_loja

    ORDER BY v.data_venda DESC, v.id_venda DESC
";

$vendas = $conn->query($sqlVendas);

$totalVendas = (int)($resumo['total_vendas'] ?? 0);
$faturamentoTotal = (float)($resumo['faturamento_total'] ?? 0);
$ticketMedio = (float)($resumo['ticket_medio'] ?? 0);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Relatório de Vendas | AutoManagerPro</title>

    <meta
        name="description"
        content="Histórico e relatório de vendas do AutoManagerPro."
    >

    <script src="https://cdn.tailwindcss.com"></script>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        body {
            background: #0b1120;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
        }

        .panel {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 18px;
        }

        .stat-card {
            transition: transform .2s, border-color .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #334155;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            min-width: 850px;
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #0f172a;
        }

        th {
            color: #94a3b8;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 700;
            padding: 17px 15px;
            text-align: left;
            white-space: nowrap;
        }

        td {
            padding: 17px 15px;
            border-top: 1px solid #1e293b;
            font-size: 12px;
            color: #cbd5e1;
            vertical-align: middle;
        }

        tbody tr {
            transition: background .2s;
        }

        tbody tr:hover {
            background: rgba(30, 41, 59, .45);
        }

        .tag {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }
    </style>
</head>

<body class="min-h-screen">

<div class="max-w-7xl mx-auto px-4 py-8 md:px-6">

    <!-- CABEÇALHO -->
    <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-5 mb-9">
<div class="flex items-center gap-3">

            <div class="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center">
                <i class="fa-solid fa-chart-line text-white text-xl"></i>
            </div>

            <div>
                <h1 class="text-xl font-extrabold text-white">
                    AutoManager<span class="text-blue-500">Pro</span>
                </h1>

                <p class="text-[10px] text-slate-500 uppercase tracking-widest">
                    Gestão automotiva
                </p>
            </div>

        </div>

        <a
            href="index.php"
            class="inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 px-5 py-3 rounded-xl text-xs font-bold transition"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Voltar ao dashboard
        </a>

    </header>

    <!-- TÍTULO -->
    <section class="mb-8">

        <p class="text-xs text-blue-400 font-bold uppercase tracking-wider mb-2">
            Análise comercial
        </p>

        <h2 class="text-2xl md:text-3xl font-extrabold text-white">
            Histórico de vendas
        </h2>

        <p class="text-sm text-slate-400 mt-2">
            Consulte as vendas registradas e acompanhe o faturamento acumulado.
        </p>

    </section>

    <!-- INDICADORES -->
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">

        <div class="panel stat-card p-5">

            <div class="flex items-center justify-between mb-5">

                <div class="w-11 h-11 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                    <i class="fa-solid fa-cart-shopping text-lg"></i>
                </div>

                <span class="tag bg-blue-500/10 text-blue-400">
                    Histórico
                </span>

            </div>

            <p class="text-xs text-slate-500 font-semibold">
                TOTAL DE VENDAS REALIZADAS
            </p>

            <h3 class="text-3xl font-extrabold text-white mt-2">
                <?= number_format($totalVendas, 0, ',', '.') ?>
            </h3>

            <p class="text-[10px] text-slate-500 mt-2">
                Registros existentes no histórico
            </p>

        </div>

        <div class="panel stat-card p-5">

            <div class="flex items-center justify-between mb-5">

                <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-sack-dollar text-lg"></i>
                </div>

                <span class="tag bg-emerald-500/10 text-emerald-400">
                    Acumulado
                </span>

            </div>

            <p class="text-xs text-slate-500 font-semibold">
                FATURAMENTO TOTAL
            </p>

            <h3 class="text-2xl md:text-3xl font-extrabold text-emerald-400 mt-2">
                <?= e(moeda($faturamentoTotal)) ?>
            </h3>

            <p class="text-[10px] text-slate-500 mt-2">
                Soma de todas as vendas registradas
            </p>

        </div>

        <div class="panel stat-card p-5">

            <div class="flex items-center justify-between mb-5">

                <div class="w-11 h-11 rounded-xl bg-violet-500/10 text-violet-400 flex items-center justify-center">
                    <i class="fa-solid fa-chart-simple text-lg"></i>
                </div>

                <span class="tag bg-violet-500/10 text-violet-400">
                    Média
                </span>

            </div>

            <p class="text-xs text-slate-500 font-semibold">
                TICKET MÉDIO
            </p>

            <h3 class="text-2xl md:text-3xl font-extrabold text-violet-400 mt-2">
                <?= e(moeda($ticketMedio)) ?>
            </h3>

            <p class="text-[10px] text-slate-500 mt-2">
                Valor médio por venda registrada
            </p>

        </div>

    </section>

    <!-- HISTÓRICO -->
    <section class="panel overflow-hidden mb-8">

        <div class="p-5 md:p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

            <div>
                <h3 class="text-base font-bold text-white">
                    Vendas registradas
                </h3>

                <p class="text-xs text-slate-500 mt-1">
                    Histórico completo, incluindo vendas antigas.
                </p>
            </div>

            <span class="tag bg-slate-800 text-slate-300">
                <?= number_format($totalVendas, 0, ',', '.') ?> registro(s)
            </span>

        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th class="pl-6">Venda / Data</th>
                        <th>Veículo</th>
                        <th>Cliente</th>
                        <th>Pagamento</th>
                        <th>Loja</th>
                        <th class="text-right">Valor</th>
                        <th class="text-center pr-6">Documento</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($vendas && $vendas->num_rows > 0): ?>

                    <?php while ($v = $vendas->fetch_assoc()): ?>

                        <?php
                            $marca = trim((string)($v['veiculo_marca'] ?? ''));
                            $modelo = trim((string)($v['veiculo_nome'] ?? ''));

                            if ($marca === '' && $modelo === '') {
                                $nomeVeiculo = 'Veículo não identificado';
                            } else {
                                $nomeVeiculo = trim($marca . ' ' . $modelo);
                            }

                            $clienteNome = !empty($v['cliente_nome'])
                                ? $v['cliente_nome']
                                : 'Não informado';

                            $pagamentoNome = !empty($v['pagamento_descricao'])
                                ? $v['pagamento_descricao']
                                : 'Não informado';

                            $lojaNome = !empty($v['loja_nome'])
                                ? $v['loja_nome']
                                : 'Não informada';
                        ?>

                        <tr>

                            <td class="pl-6">
                                <p class="font-bold text-white">
                                    #<?= (int)$v['id_venda'] ?>
                                </p>

                                <p class="text-[11px] text-slate-500 mt-1">
                                    <?= e(dataFormatada($v['data_venda'])) ?>
                                </p>
                            </td>

                            <td>
                                <p class="font-semibold text-slate-200">
                                    <?= e($nomeVeiculo) ?>
                                </p>

                                <p class="text-[10px] text-slate-500 mt-1">
                                    Referência do veículo: #<?= (int)$v['id_carro'] ?>
                                </p>
                            </td>

                            <td>
                                <span class="<?= $clienteNome === 'Não informado' ? 'text-slate-500 italic' : 'text-slate-300' ?>">
                                    <?= e($clienteNome) ?>
                                </span>
                            </td>

                            <td>
                                <span class="tag bg-slate-800 text-slate-300">
                                    <?= e($pagamentoNome) ?>
                                </span>
                            </td>

                            <td>
                                <span class="text-slate-400">
                                    <?= e($lojaNome) ?>
                                </span>
                            </td>

                            <td class="text-right">
                                <span class="font-bold text-emerald-400 whitespace-nowrap">
                                    <?= e(moeda($v['valor_total'])) ?>
                                </span>
                            </td>

                            <td class="text-center pr-6">
                                <a
                                    href="imprimir_recibo.php?id=<?= (int)$v['id_venda'] ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-2 bg-blue-600/10 hover:bg-blue-600 text-blue-400 hover:text-white border border-blue-500/20 px-3 py-2 rounded-lg text-[10px] font-bold transition"
                                >
                                    <i class="fa-solid fa-file-lines"></i>
                                    Recibo
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="text-center py-16">

                            <i class="fa-solid fa-inbox text-4xl text-slate-700 mb-4"></i>

                            <p class="text-sm text-slate-400">
                                Nenhuma venda encontrada no histórico.
                            </p>

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <!-- INFORMAÇÃO -->
    <div class="panel p-5 flex items-start gap-4">

        <div class="w-10 h-10 shrink-0 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
            <i class="fa-solid fa-circle-info"></i>
        </div>

        <div>
            <h4 class="text-xs font-bold text-slate-200">
                Sobre este relatório
            </h4>

            <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                Este relatório apresenta todas as vendas registradas no sistema,
                independentemente da situação atual dos veículos no estoque.
                Informações não disponíveis nos registros antigos são identificadas
                como "Não informado".
            </p>
        </div>

    </div>

    <footer class="text-center mt-8 text-[10px] text-slate-600">
        AutoManagerPro • Ambiente demonstrativo • AW TECHNOLOGY
    </footer>

</div>

</body>
</html>



