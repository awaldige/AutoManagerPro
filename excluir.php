
<?php
session_start();

require_once 'conexao.php';

/**
 * AutoManagerPro
 * Exclusão protegida de veículos.
 * Projeto demonstrativo de portfólio.
 */

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function contarRelacionados(mysqli $conn, string $sql, int $id): int
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    return (int) $total;
}

if (empty($_SESSION['csrf_excluir'])) {
    $_SESSION['csrf_excluir'] = bin2hex(random_bytes(32));
}

$erro = '';
$carro = null;

$id = filter_input(
    $_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    header('Location: index.php?msg=id_invalido');
    exit;
}

/**
 * Processamento da exclusão.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_excluir'], $token)
    ) {
        $erro = 'Solicitação inválida ou expirada. Atualize a página e tente novamente.';
    } else {

        try {
            $conn->begin_transaction();

            // Bloqueia o registro durante a operação.
            $stmt = $conn->prepare(
                "SELECT id_carro, ano, preco, foto, vendido
                 FROM carro
                 WHERE id_carro = ?
                 FOR UPDATE"
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();

            $resultado = $stmt->get_result();
            $carro = $resultado->fetch_assoc();
            $stmt->close();

            if (!$carro) {
                throw new RuntimeException('Veículo não encontrado.');
            }

            // Não permite excluir veículos vendidos.
            if ((int) $carro['vendido'] === 1) {
                throw new RuntimeException(
                    'Este veículo está marcado como vendido e não pode ser excluído.'
                );
            }

            // Preserva o histórico de vendas.
            $vendas = contarRelacionados(
                $conn,
                "SELECT COUNT(*) FROM venda WHERE id_carro = ?",
                $id
            );

            if ($vendas > 0) {
                throw new RuntimeException(
                    'Este veículo possui registros de vendas vinculados e não pode ser excluído.'
                );
            }

            // Preserva os acessórios associados.
            $acessorios = contarRelacionados(
                $conn,
                "SELECT COUNT(*) FROM carro_acessorio WHERE id_carro = ?",
                $id
            );

            if ($acessorios > 0) {
                throw new RuntimeException(
                    'Este veículo possui acessórios vinculados e não pode ser excluído.'
                );
            }

            // Preserva os registros de manutenção e garantia.
            $manutencoes = contarRelacionados(
                $conn,
                "SELECT COUNT(*) FROM manutencao_garantia WHERE id_carro = ?",
                $id
            );

            if ($manutencoes > 0) {
                throw new RuntimeException(
                    'Este veículo possui histórico de manutenção ou garantia e não pode ser excluído.'
                );
            }

            // Exclusão protegida por prepared statement.
            $stmt = $conn->prepare(
                "DELETE FROM carro WHERE id_carro = ?"
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();

            if ($stmt->affected_rows !== 1) {
                $stmt->close();

                throw new RuntimeException(
                    'Não foi possível confirmar a exclusão do veículo.'
                );
            }

            $stmt->close();

            $conn->commit();

            // Não remove a imagem física.
            header('Location: index.php?msg=excluido');
            exit;

        } catch (Throwable $ex) {

            $conn->rollback();

            $erro = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'Ocorreu um erro ao tentar excluir o veículo. Nenhuma alteração foi confirmada.';
        }
    }
}

/**
 * Carrega os dados para a tela de confirmação.
 */
$stmt = $conn->prepare(
    "SELECT
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
     WHERE c.id_carro = ?"
);

$stmt->bind_param('i', $id);
$stmt->execute();

$resultado = $stmt->get_result();
$carro = $resultado->fetch_assoc();

$stmt->close();

if (!$carro && !$erro) {
    $erro = 'Veículo não encontrado.';
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Confirmar Exclusão | AutoManagerPro</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            background: #0b1121;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-5">

    <main class="w-full max-w-xl bg-slate-900 border border-slate-700 rounded-2xl p-7 shadow-2xl">

        <div class="mb-6">
            <span class="text-xs font-bold uppercase tracking-widest text-red-400">
                AutoManagerPro
            </span>

            <h1 class="text-2xl font-bold mt-2">
                Confirmar exclusão
            </h1>

            <p class="text-slate-400 text-sm mt-2">
                Verifique as informações antes de continuar.
            </p>
        </div>

        <?php if ($erro): ?>

            <div class="bg-red-950/50 border border-red-800 text-red-300 rounded-xl p-4 mb-5">
                <?= e($erro) ?>
            </div>

        <?php endif; ?>

        <?php if ($carro): ?>

            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 mb-6">

                <p class="text-sm text-slate-400">
                    Veículo #<?= e($carro['id_carro']) ?>
                </p>

                <h2 class="text-xl font-bold mt-1">
                    <?= e($carro['nome_marca'] ?? 'Marca não informada') ?>
                    <?= e($carro['nome_modelo'] ?? 'Modelo não informado') ?>
                </h2>

                <div class="grid grid-cols-2 gap-4 mt-5">

                    <div>
                        <p class="text-xs text-slate-400">Ano</p>
                        <p class="font-semibold"><?= e($carro['ano']) ?></p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-400">Preço</p>
                        <p class="font-semibold">
                            R$ <?= number_format((float) $carro['preco'], 2, ',', '.') ?>
                        </p>
                    </div>

                </div>

            </div>

            <div class="bg-amber-950/40 border border-amber-800 text-amber-300 rounded-xl p-4 mb-6 text-sm">
                A exclusão somente será permitida se o veículo não possuir vendas,
                acessórios, manutenção ou garantia vinculados.
                A imagem armazenada será preservada.
            </div>

            <?php if ((int) $carro['vendido'] !== 1): ?>

                <form method="POST" action="excluir.php" class="space-y-3">

                    <input
                        type="hidden"
                        name="id"
                        value="<?= e($carro['id_carro']) ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_excluir']) ?>"
                    >

                    <button
                        type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl transition"
                    >
                        Confirmar exclusão
                    </button>

                </form>

            <?php else: ?>

                <p class="text-red-400 text-sm mb-4">
                    Este veículo está marcado como vendido. A exclusão está bloqueada.
                </p>

            <?php endif; ?>

        <?php endif; ?>

        <a
            href="veiculos.php"
            class="block text-center border border-slate-600 hover:bg-slate-800 text-slate-300 font-semibold py-3 rounded-xl mt-3 transition"
        >
            Voltar aos veículos
        </a>

        <p class="text-xs text-slate-500 text-center mt-6">
            Ambiente demonstrativo — AutoManagerPro
        </p>

    </main>

</body>
</html>



