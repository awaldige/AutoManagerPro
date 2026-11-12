
<?php
require_once 'conexao.php';

/**
 * AutoManagerPro
 * Dashboard demonstrativo de gestão de concessionária.
 */

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moeda($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function dataFormatada($value): string
{
    if (empty($value)) {
        return '—';
    }

    try {
        return (new DateTime($value))->format('d/m/Y');
    } catch (Exception $exception) {
        return '—';
    }
}

function buscarValor(mysqli $conn, string $sql, string $campo): float
{
    $resultado = $conn->query($sql);
    $linha = $resultado->fetch_assoc();

    return (float)($linha[$campo] ?? 0);
}

// =====================================================
// INDICADORES GERAIS
// =====================================================

$totalVeiculos = buscarValor(
    $conn,
    "SELECT COUNT(*) AS total FROM carro",
    'total'
);

$veiculosDisponiveis = buscarValor(
    $conn,
    "SELECT COUNT(*) AS total FROM carro WHERE COALESCE(vendido, 0) = 0",
    'total'
);

$totalClientes = buscarValor(
    $conn,
    "SELECT COUNT(*) AS total FROM cliente",
    'total'
);

$totalLojas = buscarValor(
    $conn,
    "SELECT COUNT(*) AS total FROM loja",
    'total'
);

// Total de vendas registradas no histórico
$totalVendas = buscarValor(
    $conn,
    "SELECT COUNT(*) AS total FROM venda",
    'total'
);

// Faturamento baseado nas vendas registradas
$faturamento = buscarValor(
    $conn,
    "SELECT COALESCE(SUM(valor_total), 0) AS total FROM venda",
    'total'
);

// =====================================================
// VEÍCULOS RECENTES
// =====================================================

$sqlVeiculos = "
    SELECT
        c.id_carro,
        c.ano,
        c.preco,
        c.foto,
        c.vendido,
        mo.nome_modelo,
        ma.nome_marca,
        l.nome AS loja_nome
    FROM carro c
    LEFT JOIN modelo mo ON c.id_modelo = mo.id_modelo
    LEFT JOIN marca ma ON mo.id_marca = ma.id_marca
    LEFT JOIN loja l ON c.id_loja = l.id_loja
    ORDER BY c.id_carro DESC
    LIMIT 6
";

$resultadoVeiculos = $conn->query($sqlVeiculos);

// =====================================================
// VENDAS RECENTES
// =====================================================

$sqlVendas = "
    SELECT
        v.id_venda,
        v.data_venda,
        v.valor_total,
        v.veiculo_nome,
        v.veiculo_marca,
        cl.nome AS cliente_nome,
        ve.nome AS vendedor_nome
    FROM venda v
    LEFT JOIN cliente cl ON v.id_cliente = cl.id_cliente
    LEFT JOIN vendedor ve ON v.id_vendedor = ve.id_vendedor
    ORDER BY v.data_venda DESC, v.id_venda DESC
    LIMIT 5
";

$resultadoVendas = $conn->query($sqlVendas);

// =====================================================
// MENSAGENS DO SISTEMA
// =====================================================

$mensagem = '';

if (isset($_GET['msg'])) {
    $mensagens = [
        'sucesso' => 'Operação realizada com sucesso!',
        'vendido' => 'Venda registrada com sucesso!',
        'cadastrado' => 'Veículo cadastrado com sucesso!',
        'atualizado' => 'Informações atualizadas com sucesso!',
        'excluido' => 'Registro removido com sucesso!'
    ];

    if (isset($mensagens[$_GET['msg']])) {
        $mensagem = $mensagens[$_GET['msg']];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | AutoManagerPro</title>

    <meta
        name="description"
        content="Painel demonstrativo de gestão de veículos, estoque, lojas e vendas do AutoManagerPro."
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

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    },
                    colors: {
                        brand: {
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb'
                        }
                    }
                }
            }
        };
    </script>

    <style>
        body {
            background: #0b1120;
            color: #e2e8f0;
            font-family: 'Inter', sans-serif;
        }

        .sidebar {
            background: #111827;
            border-right: 1px solid #1e293b;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 600;
            transition: .2s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(59, 130, 246, .13);
            color: #60a5fa;
        }

        .panel {
            background: #111827;
            border: 1px solid #1e293b;
            border-radius: 16px;
        }

        .stat-card {
            background: linear-gradient(145deg, #151f31, #111827);
            border: 1px solid #253248;
            border-radius: 16px;
            transition: transform .2s, border-color .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #3b82f6;
        }

        .vehicle-image {
            background: #1e293b;
            height: 150px;
            object-fit: cover;
        }

        .table-row {
            border-bottom: 1px solid #1e293b;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }

        .scrollbar::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 10px;
        }

        @media (max-width: 767px) {
            .mobile-nav {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 8px;
            }

            .mobile-nav .nav-link {
                justify-content: center;
                padding: 10px 5px;
                font-size: 11px;
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>

</head>

<body class="min-h-screen">

<div class="min-h-screen flex">

    <!-- MENU LATERAL -->
    <aside class="sidebar w-64 shrink-0 hidden md:flex flex-col">

        <div class="px-6 py-7 border-b border-slate-800">

            <div class="flex items-center gap-3">

                <div class="w-11 h-11 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-900/30">
                    <i class="fa-solid fa-car-side text-white text-xl"></i>
                </div>

                <div>

                    <h1 class="text-lg font-extrabold text-white tracking-tight">
                        AutoManager<span class="text-blue-500">Pro</span>
                    </h1>

                    <p class="text-[10px] text-slate-500 uppercase tracking-[2px]">
                        Gestão automotiva
                    </p>

                </div>

            </div>

        </div>

        <nav class="flex-1 px-4 py-6 space-y-2">

            <p class="text-[10px] font-bold text-slate-600 uppercase tracking-widest px-3 mb-3">
                Principal
            </p>

            <a href="index.php" class="nav-link active">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                Dashboard
            </a>

            <a href="veiculos.php" class="nav-link">
                <i class="fa-solid fa-car w-5 text-center"></i>
                Todos os veículos
            </a>

            <a href="cadastrar.php" class="nav-link">
                <i class="fa-solid fa-plus-circle w-5 text-center"></i>
                Cadastrar veículo
            </a>

            <a href="relatorio_vendas.php" class="nav-link">
                <i class="fa-solid fa-chart-line w-5 text-center"></i>
                Relatório de vendas
            </a>

            <div class="pt-6">

                <p class="text-[10px] font-bold text-slate-600 uppercase tracking-widest px-3 mb-3">
                    Administração
                </p>

                <a href="configuracoes.php" class="nav-link">
                    <i class="fa-solid fa-tags w-5 text-center"></i>
                    Marcas e modelos
                </a>

                <a href="gerenciar_lojas.php" class="nav-link">
                    <i class="fa-solid fa-store w-5 text-center"></i>
                    Gerenciar lojas
                </a>

            </div>

        </nav>

        <div class="p-5 border-t border-slate-800">

            <div class="flex items-center gap-3">

                <div class="w-9 h-9 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center">
                    <i class="fa-solid fa-user-gear"></i>
                </div>

                <div>
                    <p class="text-xs font-bold text-white">Administrador</p>
                    <p class="text-[10px] text-slate-500">Ambiente demonstrativo</p>
                </div>

            </div>

        </div>

    </aside>

    <!-- CONTEÚDO -->
    <main class="flex-1 min-w-0">

        <!-- CABEÇALHO -->
        <header class="bg-slate-900/70 border-b border-slate-800 px-5 md:px-8 py-5">
<div class="flex flex-wrap items-center justify-between gap-4">

                <div>

                    <p class="text-xs text-slate-500 mb-1">
                        Painel de controle
                    </p>

                    <h2 class="text-xl md:text-2xl font-extrabold text-white">
                        Visão geral
                    </h2>

                </div>

                <div class="flex items-center gap-3">

                    <span class="hidden sm:flex items-center gap-2 text-xs text-slate-400 bg-slate-800 px-3 py-2 rounded-lg">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Sistema demonstrativo
                    </span>

                    <a
                        href="cadastrar.php"
                        class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold px-4 py-3 rounded-lg transition flex items-center gap-2"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Novo veículo
                    </a>

                </div>

            </div>

        </header>

        <div class="p-5 md:p-8 max-w-[1600px] mx-auto">

            <!-- MENU MOBILE -->
            <nav class="mobile-nav md:hidden mb-6">

                <a href="index.php" class="nav-link active">
                    <i class="fa-solid fa-chart-pie"></i>
                    Dashboard
                </a>

                <a href="veiculos.php" class="nav-link">
                    <i class="fa-solid fa-car"></i>
                    Veículos
                </a>

                <a href="cadastrar.php" class="nav-link">
                    <i class="fa-solid fa-plus-circle"></i>
                    Cadastrar
                </a>

                <a href="relatorio_vendas.php" class="nav-link">
                    <i class="fa-solid fa-chart-line"></i>
                    Vendas
                </a>

                <a href="configuracoes.php" class="nav-link">
                    <i class="fa-solid fa-tags"></i>
                    Marcas
                </a>

                <a href="gerenciar_lojas.php" class="nav-link">
                    <i class="fa-solid fa-store"></i>
                    Lojas
                </a>

            </nav>

            <?php if ($mensagem): ?>

                <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-xl px-5 py-4 text-sm">

                    <i class="fa-solid fa-circle-check mr-2"></i>

                    <?= e($mensagem) ?>

                </div>

            <?php endif; ?>

            <!-- AVISO -->
            <div class="mb-7 rounded-xl border border-blue-500/20 bg-blue-500/5 px-5 py-4 flex gap-3 items-start">

                <i class="fa-solid fa-circle-info text-blue-400 mt-0.5"></i>

                <div>

                    <p class="text-sm font-bold text-blue-300">
                        Ambiente demonstrativo
                    </p>

                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Este painel apresenta dados de demonstração para visualizar os recursos de gestão de uma concessionária.
                    </p>

                </div>

            </div>

            <!-- INDICADORES -->
            <section class="mb-9">

                <div class="flex items-center justify-between mb-5">

                    <div>

                        <h3 class="text-sm font-bold text-white">
                            Indicadores gerais
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Resumo dos dados cadastrados no sistema
                        </p>

                    </div>

                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">

                    <!-- TOTAL DE VEÍCULOS -->
                    <div class="stat-card p-5">

                        <div class="flex justify-between items-start">

                            <div>

                                <p class="text-xs font-medium text-slate-400">
                                    Total de veículos
                                </p>

                                <h3 class="text-3xl font-extrabold text-white mt-3">
                                    <?= number_format($totalVeiculos, 0, ',', '.') ?>
                                </h3>

                                <p class="text-[11px] text-slate-500 mt-2">
                                    Veículos cadastrados
                                </p>

                            </div>

                            <div class="w-11 h-11 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                                <i class="fa-solid fa-car text-lg"></i>
                            </div>

                        </div>

                    </div>

                    <!-- DISPONÍVEIS -->
                    <div class="stat-card p-5">

                        <div class="flex justify-between items-start">

                            <div>

                                <p class="text-xs font-medium text-slate-400">
                                    Disponíveis em estoque
                                </p>

                                <h3 class="text-3xl font-extrabold text-white mt-3">
                                    <?= number_format($veiculosDisponiveis, 0, ',', '.') ?>
                                </h3>

                                <p class="text-[11px] text-emerald-400 mt-2">
                                    <i class="fa-solid fa-circle-check mr-1"></i>
                                    Disponíveis para venda
                                </p>

                            </div>

                            <div class="w-11 h-11 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                                <i class="fa-solid fa-warehouse text-lg"></i>
                            </div>

                        </div>

                    </div>

                    <!-- VENDAS REGISTRADAS -->
                    <div class="stat-card p-5">

                        <div class="flex justify-between items-start">

                            <div>

                                <p class="text-xs font-medium text-slate-400">
                                    Vendas registradas
                                </p>

                                <h3 class="text-3xl font-extrabold text-white mt-3">
                                    <?= number_format($totalVendas, 0, ',', '.') ?>
                                </h3>

                                <p class="text-[11px] text-slate-500 mt-2">
                                    Vendas existentes no histórico
                                </p>

                            </div>

                            <div class="w-11 h-11 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center">
                                <i class="fa-solid fa-receipt text-lg"></i>
                            </div>

                        </div>

                    </div>

                    <!-- CLIENTES -->
                    <div class="stat-card p-5">

                        <div class="flex justify-between items-start">

                            <div>

                                <p class="text-xs font-medium text-slate-400">
                                    Clientes cadastrados
                                </p>

                                <h3 class="text-3xl font-extrabold text-white mt-3">
                                    <?= number_format($totalClientes, 0, ',', '.') ?>
                                </h3>

                                <p class="text-[11px] text-slate-500 mt-2">
                                    Base de clientes
                                </p>

                            </div>

                            <div class="w-11 h-11 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                                <i class="fa-solid fa-users text-lg"></i>
                            </div>

                        </div>

                    </div>

                    <!-- LOJAS -->
                    <div class="stat-card p-5">

                        <div class="flex justify-between items-start">

                            <div>

                                <p class="text-xs font-medium text-slate-400">
                                    Lojas cadastradas
                                </p>

                                <h3 class="text-3xl font-extrabold text-white mt-3">
                                    <?= number_format($totalLojas, 0, ',', '.') ?>
                                </h3>

                                <p class="text-[11px] text-slate-500 mt-2">
                                    Unidades cadastradas
                                </p>

                            </div>

                            <div class="w-11 h-11 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center">
                                <i class="fa-solid fa-building text-lg"></i>
                            </div>

                        </div>

                    </div>

                    <!-- FATURAMENTO NÃO É UM CONTADOR -->
                    <div class="stat-card p-5">

                        <div class="flex justify-between items-start">

                            <div>

                                <p class="text-xs font-medium text-slate-400">
                                    Faturamento acumulado
                                </p>

                                <h3 class="text-2xl font-extrabold text-white mt-3">
                                    <?= e(moeda($faturamento)) ?>
                                </h3>

                                <p class="text-[11px] text-slate-500 mt-2">
                                    Histórico de vendas
                                </p>

                            </div>

                            <div class="w-11 h-11 rounded-xl bg-rose-500/10 text-rose-400 flex items-center justify-center">
                                <i class="fa-solid fa-sack-dollar text-lg"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </section>

            <!-- FATURAMENTO -->
            <section class="mb-9">

                <div class="panel p-6 relative overflow-hidden">

                    <div class="absolute -right-10 -top-10 w-48 h-48 rounded-full bg-blue-600/10 blur-3xl"></div>

                    <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-5">

                        <div>

                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                Faturamento acumulado
                            </p>

                            <h3 class="text-3xl md:text-4xl font-extrabold text-white mt-3">
                                <?= e(moeda($faturamento)) ?>
                            </h3>

                            <p class="text-xs text-slate-500 mt-2">
                                Soma dos valores registrados no histórico de vendas
                            </p>

                        </div>

                        <div class="w-14 h-14 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                            <i class="fa-solid fa-sack-dollar text-2xl"></i>
                        </div>

                    </div>

                </div>

            </section>

            <!-- ESTOQUE -->
            <section class="mb-9">

                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">

                    <div>

                        <h3 class="text-sm font-bold text-white">
                            Veículos recentes
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Últimos veículos cadastrados no estoque
                        </p>

                    </div>

                    <div class="flex items-center gap-4">

                        <a
                            href="veiculos.php"
                            class="text-xs font-bold text-blue-400 hover:text-blue-300 transition"
                        >
                            <i class="fa-solid fa-car mr-1"></i>
                            Ver todos
                        </a>

                        <a
                            href="cadastrar.php"
                            class="text-xs font-bold text-emerald-400 hover:text-emerald-300 transition"
                        >
                            <i class="fa-solid fa-plus mr-1"></i>
                            Cadastrar veículo
                        </a>

                    </div>

                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

                    <?php if ($resultadoVeiculos->num_rows > 0): ?>

                        <?php while ($carro = $resultadoVeiculos->fetch_assoc()): ?>

                            <?php

                                $nomeVeiculo = trim(
                                    ($carro['nome_marca'] ?? '') . ' ' .
                                    ($carro['nome_modelo'] ?? '')
                                );

                                if ($nomeVeiculo === '') {
                                    $nomeVeiculo = 'Veículo sem identificação';
                                }

                                $foto = !empty($carro['foto'])
                                    ? 'uploads/' . rawurlencode($carro['foto'])
                                    : 'uploads/sem-foto.jpg';

                                $vendido = (int)$carro['vendido'] === 1;

                            ?>

                            <article class="panel overflow-hidden group">

                                <div class="relative overflow-hidden">

                                    <img
                                        src="<?= e($foto) ?>"
                                        alt="<?= e($nomeVeiculo) ?>"
                                        class="vehicle-image w-full transition duration-500 group-hover:scale-105"
                                        onerror="this.onerror=null;this.src='uploads/sem-foto.jpg';"
                                    >

                                    <span class="absolute top-3 right-3 text-[10px] font-bold px-3 py-1.5 rounded-full <?= $vendido ? 'bg-rose-500/90 text-white' : 'bg-emerald-500/90 text-white' ?>">
                                        <?= $vendido ? 'Vendido' : 'Disponível' ?>
                                    </span>

                                </div>

                                <div class="p-5">

                                    <div class="flex items-start justify-between gap-3">

                                        <div class="min-w-0">

                                            <h4 class="text-sm font-bold text-white truncate">
                                                <?= e($nomeVeiculo) ?>
                                            </h4>

                                            <p class="text-xs text-slate-500 mt-1">
                                                Ano <?= e($carro['ano']) ?>
                                            </p>

                                        </div>

                                        <span class="text-sm font-extrabold text-blue-400 whitespace-nowrap">
                                            <?= e(moeda($carro['preco'])) ?>
                                        </span>

                                    </div>

                                    <div class="flex items-center justify-between gap-3 mt-5 pt-4 border-t border-slate-800">

                                        <span class="text-[11px] text-slate-500 truncate">
                                            <i class="fa-solid fa-location-dot mr-1"></i>
                                            <?= e($carro['loja_nome'] ?: 'Loja não informada') ?>
                                        </span>

                                        <a
                                            href="editar.php?id=<?= (int)$carro['id_carro'] ?>"
                                            class="text-xs font-bold text-slate-300 hover:text-blue-400 transition whitespace-nowrap"
                                        >
                                            Editar
                                            <i class="fa-solid fa-arrow-right ml-1"></i>
                                        </a>

                                    </div>

                                    <?php if (!$vendido): ?>

                                        <a
                                            href="vender.php?id=<?= (int)$carro['id_carro'] ?>"
                                            class="mt-4 w-full flex items-center justify-center gap-2 bg-blue-600/10 hover:bg-blue-600 text-blue-400 hover:text-white border border-blue-500/20 hover:border-blue-600 rounded-lg py-2.5 text-xs font-bold transition"
                                        >
                                            <i class="fa-solid fa-handshake"></i>
                                            Registrar venda
                                        </a>

                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <div class="panel p-8 text-center col-span-full">

                            <i class="fa-solid fa-car text-3xl text-slate-600 mb-3"></i>

                            <p class="text-sm text-slate-400">
                                Nenhum veículo cadastrado.
                            </p>

                            <a
                                href="cadastrar.php"
                                class="inline-block mt-4 text-xs font-bold text-blue-400"
                            >
                                Cadastrar primeiro veículo
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </section>

            <!-- VENDAS RECENTES -->
            <section class="mb-9">

                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">

                    <div>

                        <h3 class="text-sm font-bold text-white">
                            Vendas recentes
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Últimas movimentações registradas
                        </p>

                    </div>

                    <a
                        href="relatorio_vendas.php"
                        class="text-xs font-bold text-blue-400 hover:text-blue-300 transition"
                    >
                        Ver relatório completo
                        <i class="fa-solid fa-arrow-right ml-1"></i>
                    </a>

                </div>

                <div class="panel overflow-hidden">

                    <div class="overflow-x-auto scrollbar">

                        <table class="w-full text-left min-w-[650px]">

                            <thead class="bg-slate-800/60">

                                <tr>

                                    <th class="px-5 py-4 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                                        Código
                                    </th>

                                    <th class="px-5 py-4 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                                        Veículo
                                    </th>

                                    <th class="px-5 py-4 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                                        Cliente
                                    </th>

                                    <th class="px-5 py-4 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                                        Data
                                    </th>

                                    <th class="px-5 py-4 text-[10px] uppercase tracking-wider text-slate-500 font-bold">
                                        Valor
                                    </th>

                                    <th class="px-5 py-4 text-[10px] uppercase tracking-wider text-slate-500 font-bold text-right">
                                        Ação
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if ($resultadoVendas->num_rows > 0): ?>

                                    <?php while ($venda = $resultadoVendas->fetch_assoc()): ?>

                                        <?php

                                            $nomeVenda = trim(
                                                ($venda['veiculo_marca'] ?? '') . ' ' .
                                                ($venda['veiculo_nome'] ?? '')
                                            );

                                            if ($nomeVenda === '') {
                                                $nomeVenda = 'Veículo não identificado';
                                            }

                                        ?>

                                        <tr class="table-row hover:bg-slate-800/30 transition">

                                            <td class="px-5 py-4 text-xs font-bold text-blue-400">
                                                #<?= (int)$venda['id_venda'] ?>
                                            </td>

                                            <td class="px-5 py-4 text-xs font-semibold text-white">
                                                <?= e($nomeVenda) ?>
                                            </td>

                                            <td class="px-5 py-4 text-xs text-slate-400">
                                                <?= e($venda['cliente_nome'] ?: 'Não informado') ?>
                                            </td>

                                            <td class="px-5 py-4 text-xs text-slate-400">
                                                <?= e(dataFormatada($venda['data_venda'])) ?>
                                            </td>

                                            <td class="px-5 py-4 text-xs font-bold text-emerald-400">
                                                <?= e(moeda($venda['valor_total'])) ?>
                                            </td>

                                            <td class="px-5 py-4 text-right">

                                                <a
                                                    href="imprimir_recibo.php?id=<?= (int)$venda['id_venda'] ?>"
                                                    class="inline-flex items-center gap-2 text-xs text-slate-400 hover:text-blue-400 transition"
                                                    title="Visualizar recibo"
                                                >
                                                    <i class="fa-solid fa-file-invoice"></i>
                                                    Recibo
                                                </a>

                                            </td>

                                        </tr>

                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <tr>

                                        <td colspan="6" class="px-5 py-10 text-center text-xs text-slate-500">
                                            Nenhuma venda registrada até o momento.
                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </section>

            <!-- RODAPÉ -->
            <footer class="border-t border-slate-800 pt-6 pb-3">

                <div class="flex flex-col sm:flex-row justify-between items-center gap-3">

                    <p class="text-[11px] text-slate-500 text-center">
                        © <?= date('Y') ?> AutoManagerPro. Projeto demonstrativo desenvolvido por AW TECHNOLOGY.
                    </p>

                    <span class="text-[10px] text-slate-600 uppercase tracking-widest">
                        Gestão automotiva
                    </span>

                </div>

            </footer>

        </div>

    </main>

</div>

</body>
</html>



