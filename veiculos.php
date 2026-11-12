
<?php
require_once 'conexao.php';

/**
 * AutoManagerPro
 * Listagem demonstrativa de veículos com paginação.
 */

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moeda($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

// Paginação
$porPagina = 6;
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT);

if (!$pagina || $pagina < 1) {
    $pagina = 1;
}

// Total de veículos
$resultadoTotal = $conn->query("SELECT COUNT(*) AS total FROM carro");
$totalVeiculos = (int)$resultadoTotal->fetch_assoc()['total'];

$totalPaginas = max(1, (int)ceil($totalVeiculos / $porPagina));

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$offset = ($pagina - 1) * $porPagina;

// Consulta dos veículos
$sql = "
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
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $porPagina, $offset);
$stmt->execute();

$resultadoVeiculos = $stmt->get_result();

// Indicadores
$resultadoDisponiveis = $conn->query(
    "SELECT COUNT(*) AS total FROM carro WHERE COALESCE(vendido, 0) = 0"
);
$disponiveis = (int)$resultadoDisponiveis->fetch_assoc()['total'];

$resultadoVendidos = $conn->query(
    "SELECT COUNT(*) AS total FROM carro WHERE vendido = 1"
);
$vendidos = (int)$resultadoVendidos->fetch_assoc()['total'];

$inicio = $totalVeiculos > 0 ? $offset + 1 : 0;
$fim = min($offset + $porPagina, $totalVeiculos);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Veículos | AutoManagerPro</title>

    <meta
        name="description"
        content="Consulte os veículos cadastrados no estoque do AutoManagerPro."
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

        .vehicle-card {
            transition: transform .2s, border-color .2s;
        }

        .vehicle-card:hover {
            transform: translateY(-3px);
            border-color: #3b82f6;
        }

        .vehicle-image {
            background: #1e293b;
            height: 180px;
            object-fit: cover;
        }

        .stat-card {
            background: linear-gradient(145deg, #151f31, #111827);
            border: 1px solid #253248;
            border-radius: 14px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            border: 1px solid #334155;
            border-radius: 9px;
            color: #cbd5e1;
            font-size: 12px;
            font-weight: 700;
            transition: .2s;
        }

        .page-link:hover {
            border-color: #3b82f6;
            color: #60a5fa;
        }

        .page-link.active {
            background: #2563eb;
            border-color: #2563eb;
            color: white;
        }

        .page-link.disabled {
            opacity: .35;
            pointer-events: none;
        }

        .action-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-width: 0;
            border-radius: 9px;
            padding: 11px 7px;
            font-size: 11px;
            font-weight: 700;
            transition: .2s;
            white-space: nowrap;
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

            <a href="index.php" class="nav-link">
                <i class="fa-solid fa-chart-pie w-5 text-center"></i>
                Dashboard
            </a>

            <a href="veiculos.php" class="nav-link active">
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

        <header class="bg-slate-900/70 border-b border-slate-800 px-5 md:px-8 py-5">
<div class="flex flex-wrap items-center justify-between gap-4">

                <div>
                    <p class="text-xs text-slate-500 mb-1">
                        Gestão de estoque
                    </p>

                    <h2 class="text-xl md:text-2xl font-extrabold text-white">
                        Todos os veículos
                    </h2>
                </div>

                <a
                    href="cadastrar.php"
                    class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold px-4 py-3 rounded-lg transition flex items-center gap-2"
                >
                    <i class="fa-solid fa-plus"></i>
                    Novo veículo
                </a>

            </div>

        </header>

        <div class="p-5 md:p-8 max-w-[1600px] mx-auto">

            <!-- MENU MOBILE -->
            <nav class="mobile-nav md:hidden mb-6">

                <a href="index.php" class="nav-link">
                    <i class="fa-solid fa-chart-pie"></i>
                    Dashboard
                </a>

                <a href="veiculos.php" class="nav-link active">
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

            <!-- AVISO -->
            <div class="mb-7 rounded-xl border border-blue-500/20 bg-blue-500/5 px-5 py-4 flex gap-3 items-start">
                <i class="fa-solid fa-circle-info text-blue-400 mt-0.5"></i>

                <div>
                    <p class="text-sm font-bold text-blue-300">
                        Estoque demonstrativo
                    </p>

                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Consulte os veículos cadastrados, seus valores e a situação atual no estoque.
                    </p>
                </div>
            </div>

            <!-- INDICADORES -->
            <section class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">

                <div class="stat-card p-5">
                    <p class="text-xs text-slate-400">Total de veículos</p>
                    <h3 class="text-3xl font-extrabold text-white mt-2">
                        <?= number_format($totalVeiculos, 0, ',', '.') ?>
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-1">Cadastrados no sistema</p>
                </div>

                <div class="stat-card p-5">
                    <p class="text-xs text-slate-400">Disponíveis</p>
                    <h3 class="text-3xl font-extrabold text-emerald-400 mt-2">
                        <?= number_format($disponiveis, 0, ',', '.') ?>
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-1">Disponíveis para venda</p>
                </div>

                <div class="stat-card p-5">
                    <p class="text-xs text-slate-400">Vendidos</p>
                    <h3 class="text-3xl font-extrabold text-rose-400 mt-2">
                        <?= number_format($vendidos, 0, ',', '.') ?>
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-1">Conforme cadastro do estoque</p>
                </div>

            </section>

            <!-- TÍTULO DA LISTAGEM -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-5">

                <div>
                    <h3 class="text-sm font-bold text-white">
                        Veículos cadastrados
                    </h3>

                    <p class="text-xs text-slate-500 mt-1">
                        Exibindo <?= $inicio ?> a <?= $fim ?> de <?= $totalVeiculos ?> veículos
                    </p>
                </div>

                <a
                    href="index.php"
                    class="text-xs font-bold text-slate-400 hover:text-blue-400 transition"
                >
                    <i class="fa-solid fa-arrow-left mr-1"></i>
                    Voltar ao dashboard
                </a>

            </div>

            <!-- CARDS -->
            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

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
                            $idCarro = (int)$carro['id_carro'];
                        ?>

                        <article class="panel vehicle-card overflow-hidden">

                            <div class="relative overflow-hidden">

                                <img
                                    src="<?= e($foto) ?>"
                                    alt="<?= e($nomeVeiculo) ?>"
                                    class="vehicle-image w-full"
                                    onerror="this.onerror=null;this.src='uploads/sem-foto.jpg';"
                                >

                                <span class="absolute top-3 right-3 text-[10px] font-bold px-3 py-1.5 rounded-full <?= $vendido ? 'bg-rose-500/90 text-white' : 'bg-emerald-500/90 text-white' ?>">
                                    <?= $vendido ? 'Vendido' : 'Disponível' ?>
                                </span>

                            </div>

                            <div class="p-5">

                                <div class="flex items-start justify-between gap-3">

                                    <div class="min-w-0">
                                        <h4 class="text-sm font-bold text-white">
                                            <?= e($nomeVeiculo) ?>
                                        </h4>

                                        <p class="text-xs text-slate-500 mt-1">
                                            Código #<?= $idCarro ?>
                                            <span class="mx-1">•</span>
                                            Ano <?= e($carro['ano']) ?>
                                        </p>
                                    </div>

                                    <span class="text-sm font-extrabold text-blue-400 whitespace-nowrap">
                                        <?= e(moeda($carro['preco'])) ?>
                                    </span>

                                </div>

                                <div class="mt-4 pt-4 border-t border-slate-800">

                                    <p class="text-[11px] text-slate-500">
                                        <i class="fa-solid fa-location-dot mr-1"></i>
                                        <?= e($carro['loja_nome'] ?: 'Loja não informada') ?>
                                    </p>

                                </div>

                                <!-- AÇÕES DO VEÍCULO -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-4">

                                    <!-- EDITAR -->
                                    <a
                                        href="editar.php?id=<?= $idCarro ?>"
                                        class="action-button border border-slate-700 hover:border-blue-500 text-slate-300 hover:text-blue-400"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                        Editar
                                    </a>

                                    <!-- VENDER -->
                                    <?php if (!$vendido): ?>

                                        <a
                                            href="vender.php?id=<?= $idCarro ?>"
                                            class="action-button bg-blue-600/10 hover:bg-blue-600 border border-blue-500/20 text-blue-400 hover:text-white"
                                        >
                                            <i class="fa-solid fa-handshake"></i>
                                            Vender
                                        </a>

                                    <?php else: ?>

                                        <span class="action-button bg-rose-500/10 border border-rose-500/20 text-rose-400">
                                            <i class="fa-solid fa-check"></i>
                                            Vendido
                                        </span>

                                    <?php endif; ?>

                                    <!-- EXCLUIR -->
                                    <a
                                        href="excluir.php?id=<?= $idCarro ?>"
                                        class="action-button bg-red-500/10 hover:bg-red-600 border border-red-500/30 text-red-400 hover:text-white"
                                        title="Solicitar exclusão do veículo"
                                        aria-label="Excluir <?= e($nomeVeiculo) ?>"
                                    >
                                        <i class="fa-solid fa-trash-can"></i>
                                        Excluir
                                    </a>

                                </div>

                            </div>

                        </article>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="panel p-10 text-center col-span-full">
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

            </section>

            <!-- PAGINAÇÃO -->
            <?php if ($totalPaginas > 1): ?>

                <nav class="flex flex-wrap items-center justify-center gap-2 mt-9" aria-label="Paginação">

                    <a
                        href="?pagina=<?= max(1, $pagina - 1) ?>"
                        class="page-link <?= $pagina <= 1 ? 'disabled' : '' ?>"
                        aria-label="Página anterior"
                    >
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>

                    <?php for ($numero = 1; $numero <= $totalPaginas; $numero++): ?>

                        <a
                            href="?pagina=<?= $numero ?>"
                            class="page-link <?= $numero === $pagina ? 'active' : '' ?>"
                            <?= $numero === $pagina ? 'aria-current="page"' : '' ?>
                        >
                            <?= $numero ?>
                        </a>

                    <?php endfor; ?>

                    <a
                        href="?pagina=<?= min($totalPaginas, $pagina + 1) ?>"
                        class="page-link <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"
                        aria-label="Próxima página"
                    >
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>

                </nav>

            <?php endif; ?>

            <!-- RODAPÉ -->
            <footer class="border-t border-slate-800 mt-10 pt-6 pb-3">

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

<?php
$stmt->close();
?>



