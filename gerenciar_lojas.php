
<?php
require_once 'conexao.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirecionar(string $status): void
{
    header('Location: gerenciar_lojas.php?status=' . urlencode($status));
    exit;
}

$mensagens = [
    'cadastrada' => ['success', 'Loja cadastrada com sucesso!'],
    'editada' => ['success', 'Loja atualizada com sucesso!'],
    'excluida' => ['success', 'Loja excluída com sucesso!'],
    'erro_fk' => ['warning', 'Não é possível excluir esta loja porque existem veículos vinculados a ela.'],
    'nao_encontrada' => ['warning', 'A loja informada não foi encontrada.'],
    'nome_invalido' => ['warning', 'Informe um nome de loja válido.'],
    'erro' => ['danger', 'Não foi possível concluir a operação. Tente novamente.'],
    'csrf' => ['danger', 'Sua sessão de formulário expirou. Atualize a página e tente novamente.'],
];

$mensagem = '';
$tipoAlerta = 'success';

$status = $_GET['status'] ?? '';

if (isset($mensagens[$status])) {
    [$tipoAlerta, $mensagem] = $mensagens[$status];
}

// Token de proteção dos formulários.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_lojas'])) {
    $_SESSION['csrf_lojas'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_lojas'];

// Processamento dos formulários.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (!is_string($tokenRecebido) ||
        !hash_equals($csrfToken, $tokenRecebido)) {
        redirecionar('csrf');
    }

    try {
        // Cadastro.
        if (isset($_POST['add_loja'])) {
            $nome = trim((string)($_POST['nome_loja'] ?? ''));
            $endereco = trim((string)($_POST['endereco'] ?? ''));

            if ($nome === '' || mb_strlen($nome) > 100 ||
                mb_strlen($endereco) > 255) {
                redirecionar('nome_invalido');
            }

            $stmt = $conn->prepare(
                "INSERT INTO loja (nome, endereco) VALUES (?, ?)"
            );

            $stmt->bind_param('ss', $nome, $endereco);
            $stmt->execute();
            $stmt->close();

            redirecionar('cadastrada');
        }

        // Edição.
        if (isset($_POST['salvar_edicao'])) {
            $id = filter_var(
                $_POST['id_loja'] ?? null,
                FILTER_VALIDATE_INT
            );

            $nome = trim((string)($_POST['nome_loja'] ?? ''));
            $endereco = trim((string)($_POST['endereco'] ?? ''));

            if (!$id || $id <= 0 || $nome === '' ||
                mb_strlen($nome) > 100 ||
                mb_strlen($endereco) > 255) {
                redirecionar('nome_invalido');
            }

            $stmt = $conn->prepare(
                "UPDATE loja SET nome = ?, endereco = ? WHERE id_loja = ?"
            );

            $stmt->bind_param('ssi', $nome, $endereco, $id);
            $stmt->execute();

            $afetadas = $stmt->affected_rows;
            $stmt->close();

            if ($afetadas === 0) {
                $verifica = $conn->prepare(
                    "SELECT id_loja FROM loja WHERE id_loja = ?"
                );
                $verifica->bind_param('i', $id);
                $verifica->execute();
                $existe = $verifica->get_result()->num_rows > 0;
                $verifica->close();

                if (!$existe) {
                    redirecionar('nao_encontrada');
                }
            }

            redirecionar('editada');
        }

        // Exclusão.
        if (isset($_POST['excluir_loja'])) {
            $id = filter_var(
                $_POST['id_loja'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {
                redirecionar('nao_encontrada');
            }

            $conn->begin_transaction();

            try {
                // Bloqueia a loja durante a verificação.
                $stmt = $conn->prepare(
                    "SELECT id_loja FROM loja WHERE id_loja = ? FOR UPDATE"
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();

                $lojaExiste = $stmt->get_result()->num_rows > 0;
                $stmt->close();

                if (!$lojaExiste) {
                    $conn->rollback();
                    redirecionar('nao_encontrada');
                }

                // Verifica veículos vinculados.
                $stmt = $conn->prepare(
                    "SELECT COUNT(*) AS total FROM carro WHERE id_loja = ?"
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();

                $totalVeiculos = (int)$stmt->get_result()->fetch_assoc()['total'];
                $stmt->close();

                if ($totalVeiculos > 0) {
                    $conn->rollback();
                    redirecionar('erro_fk');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM loja WHERE id_loja = ?"
                );
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                redirecionar('excluida');

            } catch (Throwable $exception) {
                $conn->rollback();

                // Uma restrição de chave estrangeira também impede
                // a exclusão de registros relacionados.
                redirecionar('erro_fk');
            }
        }

    } catch (Throwable $exception) {
        redirecionar('erro');
    }
}

// Consulta das lojas.
$lojas = [];
$totalLojas = 0;
$totalVeiculos = 0;

$sql = "
    SELECT
        l.id_loja,
        l.nome,
        l.endereco,
        COUNT(c.id_carro) AS total_veiculos
    FROM loja l
    LEFT JOIN carro c ON c.id_loja = l.id_loja
    GROUP BY l.id_loja, l.nome, l.endereco
    ORDER BY l.nome ASC
";

$resultado = $conn->query($sql);

while ($linha = $resultado->fetch_assoc()) {
    $lojas[] = $linha;
    $totalVeiculos += (int)$linha['total_veiculos'];
}

$totalLojas = count($lojas);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gerenciar Lojas | AutoManagerPro</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0b1121;
        }

        .panel {
            background: #111c31;
            border: 1px solid #24324a;
        }

        .field {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #334155;
            border-radius: 9px;
            background: #0b1121;
            color: #e2e8f0;
            outline: none;
        }

        .field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,.15);
        }

        .field::placeholder {
            color: #64748b;
        }

        .table-row:hover {
            background: rgba(51,65,85,.2);
        }

        .action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            transition: .2s;
        }
    </style>
</head>

<body class="min-h-screen text-slate-200">

<div class="max-w-7xl mx-auto px-4 py-8 md:px-8">

    <header class="flex flex-col sm:flex-row sm:items-center
                   sm:justify-between gap-5 mb-8">
<div>
            <div class="flex items-center gap-3 mb-2">
                <div class="w-11 h-11 rounded-xl bg-blue-500/10
                            text-blue-400 flex items-center justify-center">
                    <i class="fa-solid fa-building text-xl"></i>
                </div>

                <div>
                    <h1 class="text-2xl font-extrabold text-white">
                        Gerenciamento de Lojas
                    </h1>

                    <p class="text-sm text-slate-400">
                        Administração de unidades e filiais
                    </p>
                </div>
            </div>
        </div>

        <a href="index.php"
           class="inline-flex items-center justify-center gap-2
                  px-5 py-3 rounded-xl bg-slate-700 hover:bg-slate-600
                  text-white text-sm font-bold transition">
            <i class="fa-solid fa-arrow-left"></i>
            Voltar ao painel
        </a>

    </header>

    <?php if ($mensagem): ?>
        <div class="mb-6 p-4 rounded-xl border
            <?= $tipoAlerta === 'success'
                ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                : ($tipoAlerta === 'warning'
                    ? 'bg-amber-500/10 border-amber-500/30 text-amber-300'
                    : 'bg-red-500/10 border-red-500/30 text-red-300') ?>">

            <div class="flex items-center gap-3">
                <i class="fa-solid
                    <?= $tipoAlerta === 'success'
                        ? 'fa-circle-check'
                        : ($tipoAlerta === 'warning'
                            ? 'fa-triangle-exclamation'
                            : 'fa-circle-exclamation') ?>"></i>

                <span class="text-sm font-semibold"><?= e($mensagem) ?></span>
            </div>

        </div>
    <?php endif; ?>

    <section class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">

        <div class="panel rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Total de lojas
                    </p>

                    <p class="text-3xl font-extrabold text-white mt-2">
                        <?= $totalLojas ?>
                    </p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-blue-500/10
                            text-blue-400 flex items-center justify-center">
                    <i class="fa-solid fa-building text-xl"></i>
                </div>
            </div>
        </div>

        <div class="panel rounded-2xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Veículos vinculados
                    </p>

                    <p class="text-3xl font-extrabold text-white mt-2">
                        <?= $totalVeiculos ?>
                    </p>
                </div>

                <div class="w-12 h-12 rounded-xl bg-emerald-500/10
                            text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-car text-xl"></i>
                </div>
            </div>
        </div>

    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <section class="panel rounded-2xl p-6 h-fit">

            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10
                            text-emerald-400 flex items-center justify-center">
                    <i class="fa-solid fa-plus"></i>
                </div>

                <div>
                    <h2 class="font-bold text-white">Nova loja</h2>
                    <p class="text-xs text-slate-400">Cadastrar unidade</p>
                </div>
            </div>

            <form method="POST" class="space-y-4">

                <input type="hidden" name="csrf_token"
                       value="<?= e($csrfToken) ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2">
                        NOME DA LOJA *
                    </label>

                    <input type="text"
                           name="nome_loja"
                           maxlength="100"
                           class="field"
                           placeholder="Ex.: Filial Centro"
                           required>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-2">
                        ENDEREÇO
                    </label>

                    <textarea name="endereco"
                              maxlength="255"
                              rows="3"
                              class="field resize-none"
                              placeholder="Endereço da unidade (opcional)"></textarea>
                </div>

                <button type="submit"
                        name="add_loja"
                        class="w-full py-3 rounded-xl bg-emerald-500
                               hover:bg-emerald-400 text-slate-950
                               font-extrabold text-sm transition">

                    <i class="fa-solid fa-plus mr-2"></i>
                    Cadastrar loja

                </button>

            </form>

        </section>

        <section class="panel rounded-2xl overflow-hidden lg:col-span-2">

            <div class="p-5 border-b border-slate-700/70">
                <h2 class="font-bold text-white">Lojas cadastradas</h2>

                <p class="text-xs text-slate-400 mt-1">
                    Consulte e gerencie as unidades do sistema.
                </p>
            </div>

            <?php if (count($lojas) > 0): ?>

                <div class="overflow-x-auto">

                    <table class="w-full text-sm text-left">

                        <thead class="bg-slate-800/70 text-slate-400
                                      text-xs uppercase tracking-wider">

                            <tr>
                                <th class="px-5 py-4">Loja</th>
                                <th class="px-5 py-4">Veículos</th>
                                <th class="px-5 py-4 text-right">Ações</th>
                            </tr>

                        </thead>

                        <tbody class="divide-y divide-slate-700/60">

                            <?php foreach ($lojas as $loja): ?>

                                <tr class="table-row transition">

                                    <td class="px-5 py-4">

                                        <div class="font-bold text-white">
                                            <?= e($loja['nome']) ?>
                                        </div>

                                        <div class="text-xs text-slate-400 mt-1">
                                            <?= e($loja['endereco'] ?: 'Endereço não informado') ?>
                                        </div>

                                        <div class="text-[10px] text-slate-500 mt-1">
                                            ID #<?= (int)$loja['id_loja'] ?>
                                        </div>

                                    </td>

                                    <td class="px-5 py-4">

                                        <span class="inline-flex items-center gap-2
                                            px-3 py-1.5 rounded-lg
                                            bg-blue-500/10 text-blue-300
                                            text-xs font-bold">

                                            <i class="fa-solid fa-car"></i>
                                            <?= (int)$loja['total_veiculos'] ?>

                                        </span>

                                    </td>

                                    <td class="px-5 py-4">

                                        <div class="flex justify-end gap-2">

                                            <button type="button"
                                                    class="action bg-blue-500/10
                                                           text-blue-400 hover:bg-blue-500/20"
                                                    title="Editar loja"
                                                    onclick='abrirModal(
                                                        <?= (int)$loja['id_loja'] ?>,
                                                        <?= json_encode($loja['nome'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                                                        <?= json_encode($loja['endereco'] ?? '', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
                                                    )'>

                                                <i class="fa-solid fa-pen"></i>

                                            </button>

                                            <form method="POST"
                                                  onsubmit="return confirm('Deseja realmente excluir esta loja? Esta ação não poderá ser desfeita.');">

                                                <input type="hidden"
                                                       name="csrf_token"
                                                       value="<?= e($csrfToken) ?>">

                                                <input type="hidden"
                                                       name="id_loja"
                                                       value="<?= (int)$loja['id_loja'] ?>">

                                                <button type="submit"
                                                        name="excluir_loja"
                                                        class="action bg-red-500/10
                                                               text-red-400 hover:bg-red-500/20"
                                                        title="Excluir loja">

                                                    <i class="fa-solid fa-trash"></i>

                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="py-16 text-center">
                    <i class="fa-solid fa-building text-4xl text-slate-600"></i>

                    <p class="text-slate-300 font-semibold mt-4">
                        Nenhuma loja cadastrada.
                    </p>

                    <p class="text-slate-500 text-xs mt-1">
                        Utilize o formulário para cadastrar a primeira unidade.
                    </p>
                </div>

            <?php endif; ?>

        </section>

    </div>

    <footer class="text-center text-xs text-slate-500 mt-10">
        AutoManagerPro · Ambiente demonstrativo
    </footer>

</div>

<!-- Modal de edição -->

<div id="modalEditar"
     class="fixed inset-0 z-50 hidden items-center justify-center
            bg-black/70 p-4">

    <div class="panel w-full max-w-lg rounded-2xl overflow-hidden shadow-2xl">

        <div class="flex items-center justify-between p-5
                    border-b border-slate-700">

            <div>
                <h2 class="font-bold text-white">Editar loja</h2>
                <p class="text-xs text-slate-400 mt-1">
                    Atualize os dados da unidade.
                </p>
            </div>

            <button type="button"
                    onclick="fecharModal()"
                    class="text-slate-400 hover:text-white text-xl"
                    aria-label="Fechar">

                <i class="fa-solid fa-xmark"></i>

            </button>

        </div>

        <form method="POST" class="p-5 space-y-4">

            <input type="hidden" name="csrf_token"
                   value="<?= e($csrfToken) ?>">

            <input type="hidden" name="id_loja" id="edit_id">

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-2">
                    NOME DA LOJA *
                </label>

                <input type="text"
                       name="nome_loja"
                       id="edit_nome"
                       maxlength="100"
                       class="field"
                       required>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 mb-2">
                    ENDEREÇO
                </label>

                <textarea name="endereco"
                          id="edit_endereco"
                          maxlength="255"
                          rows="3"
                          class="field resize-none"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-2">

                <button type="button"
                        onclick="fecharModal()"
                        class="px-4 py-3 rounded-xl bg-slate-700
                               hover:bg-slate-600 text-white text-sm font-bold">

                    Cancelar

                </button>

                <button type="submit"
                        name="salvar_edicao"
                        class="px-5 py-3 rounded-xl bg-blue-600
                               hover:bg-blue-500 text-white text-sm font-bold">

                    <i class="fa-solid fa-check mr-2"></i>
                    Salvar alterações

                </button>

            </div>

        </form>

    </div>

</div>

<script>
    const modal = document.getElementById('modalEditar');

    function abrirModal(id, nome, endereco) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nome').value = nome || '';
        document.getElementById('edit_endereco').value = endereco || '';

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function fecharModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            fecharModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            fecharModal();
        }
    });
</script>

</body>
</html>



