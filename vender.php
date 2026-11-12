
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

$mensagemErro = '';
$mensagemSucesso = '';
$carro = null;
$clientes = [];
$tiposPagamento = [];

$idCarro = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idCarro || $idCarro <= 0) {
    header('Location: index.php');
    exit;
}

// Processamento da venda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idCarroPost = filter_input(INPUT_POST, 'id_carro', FILTER_VALIDATE_INT);
    $idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
    $idTipoPagamento = filter_input(INPUT_POST, 'id_tipo_pagamento', FILTER_VALIDATE_INT);

    $valorInformado = str_replace(',', '.', trim($_POST['valor_venda'] ?? ''));

    if (!$idCarroPost || $idCarroPost !== $idCarro) {
        $mensagemErro = 'Identificação do veículo inválida.';
    } elseif (!$idCliente || !$idTipoPagamento) {
        $mensagemErro = 'Selecione um cliente e uma forma de pagamento.';
    } elseif (!is_numeric($valorInformado) || (float)$valorInformado <= 0) {
        $mensagemErro = 'Informe um valor de venda válido.';
    } else {
        try {
            $valorVenda = (float)$valorInformado;

            if (!is_finite($valorVenda)) {
                throw new Exception('Valor de venda inválido.');
            }

            $dataVenda = date('Y-m-d');

            // Inicia uma transação para proteger as operações.
            $conn->begin_transaction();

            // Bloqueia o veículo durante o processamento.
            $stmtCarro = $conn->prepare("
                SELECT
                    c.id_carro,
                    c.ano,
                    c.preco,
                    c.vendido,
                    mo.nome_modelo,
                    ma.nome_marca
                FROM carro c
                LEFT JOIN modelo mo ON c.id_modelo = mo.id_modelo
                LEFT JOIN marca ma ON mo.id_marca = ma.id_marca
                WHERE c.id_carro = ?
                FOR UPDATE
            ");

            $stmtCarro->bind_param('i', $idCarro);
            $stmtCarro->execute();

            $carroVenda = $stmtCarro->get_result()->fetch_assoc();
            $stmtCarro->close();

            if (!$carroVenda) {
                throw new Exception('Veículo não encontrado.');
            }

            if ((int)$carroVenda['vendido'] === 1) {
                throw new Exception('Este veículo já foi vendido.');
            }

            // Confirma se o cliente existe.
            $stmtCliente = $conn->prepare("
                SELECT id_cliente
                FROM cliente
                WHERE id_cliente = ?
            ");

            $stmtCliente->bind_param('i', $idCliente);
            $stmtCliente->execute();

            $clienteValido = $stmtCliente->get_result()->num_rows > 0;
            $stmtCliente->close();

            if (!$clienteValido) {
                throw new Exception('O cliente selecionado não foi encontrado.');
            }

            // Confirma se a forma de pagamento existe.
            $stmtPagamento = $conn->prepare("
                SELECT id_tipo_pagamento
                FROM tipopagamento
                WHERE id_tipo_pagamento = ?
            ");

            $stmtPagamento->bind_param('i', $idTipoPagamento);
            $stmtPagamento->execute();

            $pagamentoValido = $stmtPagamento->get_result()->num_rows > 0;
            $stmtPagamento->close();

            if (!$pagamentoValido) {
                throw new Exception('A forma de pagamento selecionada é inválida.');
            }

            // Atualiza o estoque sem excluir o veículo.
            $stmtUpdate = $conn->prepare("
                UPDATE carro
                SET vendido = 1
                WHERE id_carro = ?
                  AND COALESCE(vendido, 0) = 0
            ");

            $stmtUpdate->bind_param('i', $idCarro);
            $stmtUpdate->execute();

            if ($stmtUpdate->affected_rows !== 1) {
                $stmtUpdate->close();
                throw new Exception('Não foi possível atualizar o estoque. Verifique a situação do veículo.');
            }

            $stmtUpdate->close();

            // Preserva os dados do veículo no histórico da venda.
            $nomeModelo = $carroVenda['nome_modelo'] ?? 'Modelo não informado';
            $nomeMarca = $carroVenda['nome_marca'] ?? 'Marca não informada';

            // Registra a venda associada ao cliente e ao veículo.
            $stmtVenda = $conn->prepare("
                INSERT INTO venda (
                    id_cliente,
                    id_carro,
                    id_tipo_pagamento,
                    valor_total,
                    data_venda,
                    veiculo_nome,
                    veiculo_marca
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtVenda->bind_param(
                'iiidsss',
                $idCliente,
                $idCarro,
                $idTipoPagamento,
                $valorVenda,
                $dataVenda,
                $nomeModelo,
                $nomeMarca
            );

            $stmtVenda->execute();
            $stmtVenda->close();

            // Confirma as duas operações juntas.
            $conn->commit();

            header('Location: index.php?msg=vendido');
            exit;

        } catch (Throwable $exception) {
            $conn->rollback();

            $mensagemErro = $exception instanceof mysqli_sql_exception
                ? 'Não foi possível concluir a venda. Verifique os dados e tente novamente.'
                : $exception->getMessage();
        }
    }
}

// Busca os dados atuais do veículo.
$stmt = $conn->prepare("
    SELECT
        c.id_carro,
        c.ano,
        c.preco,
        c.foto,
        c.vendido,
        mo.nome_modelo,
        ma.nome_marca
    FROM carro c
    LEFT JOIN modelo mo ON c.id_modelo = mo.id_modelo
    LEFT JOIN marca ma ON mo.id_marca = ma.id_marca
    WHERE c.id_carro = ?
");

$stmt->bind_param('i', $idCarro);
$stmt->execute();

$carro = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Busca clientes disponíveis.
$resultadoClientes = $conn->query("
    SELECT id_cliente, nome
    FROM cliente
    ORDER BY nome ASC
");

while ($cliente = $resultadoClientes->fetch_assoc()) {
    $clientes[] = $cliente;
}

// Busca formas de pagamento cadastradas.
$resultadoPagamentos = $conn->query("
    SELECT id_tipo_pagamento, descricao
    FROM tipopagamento
    ORDER BY id_tipo_pagamento ASC
");

while ($pagamento = $resultadoPagamentos->fetch_assoc()) {
    $tiposPagamento[] = $pagamento;
}

$nomeVeiculo = '';

if ($carro) {
    $nomeVeiculo = trim(
        ($carro['nome_marca'] ?? '') . ' ' .
        ($carro['nome_modelo'] ?? '')
    );

    if ($nomeVeiculo === '') {
        $nomeVeiculo = 'Veículo sem identificação';
    }
}

$jaVendido = $carro && (int)$carro['vendido'] === 1;
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Registrar venda | AutoManagerPro</title>

    <meta
        name="description"
        content="Registro demonstrativo de vendas do AutoManagerPro."
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

        .field {
            width: 100%;
            background: #0b1120;
            border: 1px solid #334155;
            color: #e2e8f0;
            border-radius: 10px;
            padding: 13px 14px;
            outline: none;
            transition: .2s;
        }

        .field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
        }

        .field option {
            background: #111827;
            color: #e2e8f0;
        }
    </style>
</head>

<body class="min-h-screen">

<div class="max-w-3xl mx-auto px-4 py-8 md:py-12">

    <header class="flex items-center justify-between gap-4 mb-8">
<a href="index.php" class="flex items-center gap-3">

            <div class="w-11 h-11 rounded-xl bg-blue-600 flex items-center justify-center">
                <i class="fa-solid fa-car-side text-white text-xl"></i>
            </div>

            <div>
                <h1 class="text-lg font-extrabold text-white">
                    AutoManager<span class="text-blue-500">Pro</span>
                </h1>

                <p class="text-[10px] text-slate-500 uppercase tracking-widest">
                    Gestão automotiva
                </p>
            </div>

        </a>

        <a
            href="index.php"
            class="text-xs text-slate-400 hover:text-white transition"
        >
            <i class="fa-solid fa-arrow-left mr-1"></i>
            Voltar ao painel
        </a>

    </header>

    <div class="mb-7">
        <p class="text-xs text-blue-400 font-semibold mb-2">
            OPERAÇÃO DE ESTOQUE
        </p>

        <h2 class="text-2xl md:text-3xl font-extrabold text-white">
            Registrar venda
        </h2>

        <p class="text-sm text-slate-400 mt-2">
            Preencha os dados para registrar a venda do veículo.
        </p>
    </div>

    <?php if ($mensagemErro): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>
            <?= e($mensagemErro) ?>
        </div>
    <?php endif; ?>

    <?php if (!$carro): ?>

        <div class="panel p-8 text-center">
            <i class="fa-solid fa-car-burst text-4xl text-slate-600 mb-4"></i>

            <h3 class="text-lg font-bold text-white">
                Veículo não encontrado
            </h3>

            <p class="text-sm text-slate-400 mt-2">
                O veículo solicitado não existe ou não está disponível no cadastro.
            </p>

            <a
                href="index.php"
                class="inline-block mt-6 bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold px-5 py-3 rounded-lg transition"
            >
                Voltar ao dashboard
            </a>
        </div>

    <?php elseif ($jaVendido): ?>

        <div class="panel p-8 text-center">
            <div class="w-16 h-16 mx-auto rounded-full bg-rose-500/10 text-rose-400 flex items-center justify-center text-2xl mb-5">
                <i class="fa-solid fa-lock"></i>
            </div>

            <h3 class="text-lg font-bold text-white">
                Veículo já vendido
            </h3>

            <p class="text-sm text-slate-400 mt-2">
                Este veículo não pode receber uma nova venda.
            </p>

            <a
                href="index.php"
                class="inline-block mt-6 bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold px-5 py-3 rounded-lg transition"
            >
                Voltar ao dashboard
            </a>
        </div>

    <?php elseif (empty($clientes) || empty($tiposPagamento)): ?>

        <div class="panel p-8 text-center">
            <i class="fa-solid fa-circle-info text-3xl text-amber-400 mb-4"></i>

            <h3 class="text-lg font-bold text-white">
                Cadastros necessários
            </h3>

            <p class="text-sm text-slate-400 mt-2">
                Para registrar uma venda, é necessário ter pelo menos um cliente e uma forma de pagamento cadastrados.
            </p>

            <a
                href="index.php"
                class="inline-block mt-6 text-sm font-bold text-blue-400"
            >
                Voltar ao painel
            </a>
        </div>

    <?php else: ?>

        <div class="panel overflow-hidden">

            <!-- IDENTIFICAÇÃO DO VEÍCULO -->
            <div class="p-6 md:p-8 border-b border-slate-800">

                <div class="flex items-start gap-4">

                    <div class="w-12 h-12 shrink-0 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-car"></i>
                    </div>

                    <div class="min-w-0">
                        <p class="text-xs text-slate-500 mb-1">
                            Veículo selecionado
                        </p>

                        <h3 class="text-lg font-extrabold text-white">
                            <?= e($nomeVeiculo) ?>
                        </h3>

                        <p class="text-xs text-slate-400 mt-1">
                            Ano <?= e($carro['ano']) ?>
                            <span class="mx-2 text-slate-600">•</span>
                            Código #<?= (int)$carro['id_carro'] ?>
                        </p>
                    </div>

                </div>

                <div class="mt-5 p-4 bg-slate-900 rounded-xl flex items-center justify-between gap-3">
                    <span class="text-xs text-slate-400">
                        Preço cadastrado
                    </span>

                    <strong class="text-lg text-emerald-400">
                        <?= e(moeda($carro['preco'])) ?>
                    </strong>
                </div>

            </div>

            <!-- FORMULÁRIO -->
            <form
                method="POST"
                action="vender.php?id=<?= (int)$idCarro ?>"
                class="p-6 md:p-8 space-y-6"
            >

                <input
                    type="hidden"
                    name="id_carro"
                    value="<?= (int)$carro['id_carro'] ?>"
                >

                <div>
                    <label for="id_cliente" class="block text-xs font-bold text-slate-300 mb-2">
                        Cliente *
                    </label>

                    <select
                        id="id_cliente"
                        name="id_cliente"
                        class="field"
                        required
                    >
                        <option value="">Selecione o cliente</option>

                        <?php foreach ($clientes as $cliente): ?>
                            <option
                                value="<?= (int)$cliente['id_cliente'] ?>"
                                <?= (int)($_POST['id_cliente'] ?? 0) === (int)$cliente['id_cliente'] ? 'selected' : '' ?>
                            >
                                <?= e($cliente['nome']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label for="id_tipo_pagamento" class="block text-xs font-bold text-slate-300 mb-2">
                        Forma de pagamento *
                    </label>

                    <select
                        id="id_tipo_pagamento"
                        name="id_tipo_pagamento"
                        class="field"
                        required
                    >
                        <option value="">Selecione a forma de pagamento</option>

                        <?php foreach ($tiposPagamento as $pagamento): ?>
                            <option
                                value="<?= (int)$pagamento['id_tipo_pagamento'] ?>"
                                <?= (int)($_POST['id_tipo_pagamento'] ?? 0) === (int)$pagamento['id_tipo_pagamento'] ? 'selected' : '' ?>
                            >
                                <?= e($pagamento['descricao']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div>
                    <label for="valor_venda" class="block text-xs font-bold text-slate-300 mb-2">
                        Valor final da venda *
                    </label>

                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm font-bold">
                            R$
                        </span>

                        <input
                            type="number"
                            id="valor_venda"
                            name="valor_venda"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            class="field pl-12 text-lg font-bold text-emerald-400"
                            value="<?= e($_POST['valor_venda'] ?? $carro['preco']) ?>"
                            required
                        >
                    </div>

                    <p class="text-[11px] text-slate-500 mt-2">
                        Você pode ajustar o valor final negociado.
                    </p>
                </div>

                <div class="p-4 rounded-xl bg-blue-500/5 border border-blue-500/20">
                    <p class="text-xs text-blue-300 font-semibold">
                        <i class="fa-solid fa-circle-info mr-1"></i>
                        Atualização automática do estoque
                    </p>

                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                        Após a confirmação, o veículo permanecerá cadastrado com o status "Vendido", e a operação será registrada no histórico de vendas.
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-2">

                    <button
                        type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm rounded-xl py-4 transition"
                    >
                        <i class="fa-solid fa-check-circle mr-2"></i>
                        Confirmar venda
                    </button>

                    <a
                        href="index.php"
                        class="sm:w-36 text-center bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-sm rounded-xl py-4 transition"
                    >
                        Cancelar
                    </a>

                </div>

            </form>

        </div>

    <?php endif; ?>

    <footer class="text-center mt-8 text-[10px] text-slate-600">
        AutoManagerPro • Ambiente demonstrativo • AW TECHNOLOGY
    </footer>

</div>

</body>
</html>



