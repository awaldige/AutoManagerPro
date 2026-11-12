
<?php
require_once 'conexao.php';

session_start();

/**
 * AutoManagerPro
 * Gerenciamento demonstrativo de marcas e modelos.
 */

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirecionar(string $status): void
{
    header('Location: configuracoes.php?status=' . urlencode($status));
    exit;
}

function nomeValido(string $nome): bool
{
    $nome = trim($nome);

    if ($nome === '') {
        return false;
    }

    $tamanho = function_exists('mb_strlen')
        ? mb_strlen($nome, 'UTF-8')
        : strlen($nome);

    return $tamanho <= 50;
}

function nomeExiste(
    mysqli $conn,
    string $tabela,
    string $coluna,
    string $nome,
    int $idMarca = 0,
    int $idIgnorar = 0
): bool {
    // Identificadores são definidos internamente, nunca recebidos do usuário.
    if ($tabela === 'Marca') {
        $sql = "SELECT id_marca FROM Marca
                WHERE LOWER(TRIM(nome_marca)) = LOWER(TRIM(?))
                AND id_marca <> ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $nome, $idIgnorar);
    } else {
        $sql = "SELECT id_modelo FROM Modelo
                WHERE LOWER(TRIM(nome_modelo)) = LOWER(TRIM(?))
                AND id_marca = ?
                AND id_modelo <> ?
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sii', $nome, $idMarca, $idIgnorar);
    }

    $stmt->execute();
    $stmt->store_result();

    $existe = $stmt->num_rows > 0;

    $stmt->close();

    return $existe;
}

// --------------------------------------------------
// PROTEÇÃO CSRF
// --------------------------------------------------

if (empty($_SESSION['csrf_configuracoes'])) {
    $_SESSION['csrf_configuracoes'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_configuracoes'];

// --------------------------------------------------
// PROCESSAMENTO DAS OPERAÇÕES
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($csrf, $token)) {
        redirecionar('csrf');
    }

    try {

        // CADASTRAR MARCA
        if (isset($_POST['add_marca'])) {

            $nome = trim($_POST['nome_marca'] ?? '');

            if (!nomeValido($nome)) {
                redirecionar('nome_invalido');
            }

            if (nomeExiste($conn, 'Marca', 'nome_marca', $nome)) {
                redirecionar('marca_duplicada');
            }

            $stmt = $conn->prepare(
                "INSERT INTO Marca (nome_marca) VALUES (?)"
            );

            $stmt->bind_param('s', $nome);
            $stmt->execute();
            $stmt->close();

            redirecionar('marca_ok');
        }

        // CADASTRAR MODELO
        if (isset($_POST['add_modelo'])) {

            $nome = trim($_POST['nome_modelo'] ?? '');
            $idMarca = filter_var(
                $_POST['id_marca'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!nomeValido($nome) || !$idMarca || $idMarca < 1) {
                redirecionar('nome_invalido');
            }

            $stmt = $conn->prepare(
                "SELECT id_marca FROM Marca WHERE id_marca = ?"
            );

            $stmt->bind_param('i', $idMarca);
            $stmt->execute();
            $stmt->store_result();

            $marcaExiste = $stmt->num_rows > 0;
            $stmt->close();

            if (!$marcaExiste) {
                redirecionar('marca_invalida');
            }

            if (nomeExiste($conn, 'Modelo', 'nome_modelo', $nome, $idMarca)) {
                redirecionar('modelo_duplicado');
            }

            $stmt = $conn->prepare(
                "INSERT INTO Modelo (nome_modelo, id_marca)
                 VALUES (?, ?)"
            );

            $stmt->bind_param('si', $nome, $idMarca);
            $stmt->execute();
            $stmt->close();

            redirecionar('modelo_ok');
        }

        // EDITAR MARCA OU MODELO
        if (isset($_POST['salvar_edicao'])) {

            $id = filter_var(
                $_POST['id_registro'] ?? null,
                FILTER_VALIDATE_INT
            );

            $tipo = $_POST['tipo_edicao'] ?? '';
            $nome = trim($_POST['nome_novo'] ?? '');

            if (!$id || $id < 1 || !nomeValido($nome)) {
                redirecionar('nome_invalido');
            }

            if ($tipo === 'marca') {

                if (nomeExiste($conn, 'Marca', 'nome_marca', $nome, 0, $id)) {
                    redirecionar('marca_duplicada');
                }

                $stmt = $conn->prepare(
                    "UPDATE Marca SET nome_marca = ? WHERE id_marca = ?"
                );

                $stmt->bind_param('si', $nome, $id);
                $stmt->execute();

                $encontrado = $stmt->affected_rows >= 0;
                $stmt->close();

            } elseif ($tipo === 'modelo') {

                if (nomeExiste($conn, 'Marca', 'nome_marca', '', 0, -1)) {
                    // Não utilizado: a validação de duplicidade de modelo
                    // considera a marca atual e será feita na consulta abaixo.
                }

                $stmt = $conn->prepare(
                    "SELECT id_marca FROM Modelo WHERE id_modelo = ?"
                );

                $stmt->bind_param('i', $id);
                $stmt->execute();

                $resultado = $stmt->get_result();
                $modeloAtual = $resultado->fetch_assoc();

                $stmt->close();

                if (!$modeloAtual) {
                    redirecionar('nao_encontrado');
                }

                $idMarcaAtual = (int) $modeloAtual['id_marca'];

                if (nomeExiste(
                    $conn,
                    'Modelo',
                    'nome_modelo',
                    $nome,
                    $idMarcaAtual,
                    $id
                )) {
                    redirecionar('modelo_duplicado');
                }

                $stmt = $conn->prepare(
                    "UPDATE Modelo SET nome_modelo = ? WHERE id_modelo = ?"
                );

                $stmt->bind_param('si', $nome, $id);
                $stmt->execute();
                $stmt->close();

            } else {
                redirecionar('erro');
            }

            redirecionar('editado');
        }

        // EXCLUIR MARCA OU MODELO
        if (isset($_POST['excluir_registro'])) {

            $id = filter_var(
                $_POST['id_registro'] ?? null,
                FILTER_VALIDATE_INT
            );

            $tipo = $_POST['tipo_exclusao'] ?? '';

            if (!$id || $id < 1) {
                redirecionar('nao_encontrado');
            }

            if ($tipo === 'marca') {

                // Uma marca não pode ser removida se possuir modelos.
                $stmt = $conn->prepare(
                    "SELECT COUNT(*) FROM Modelo WHERE id_marca = ?"
                );

                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->bind_result($totalModelos);
                $stmt->fetch();
                $stmt->close();

                if ($totalModelos > 0) {
                    redirecionar('marca_em_uso');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM Marca WHERE id_marca = ?"
                );

                $stmt->bind_param('i', $id);
                $stmt->execute();

                $excluido = $stmt->affected_rows > 0;
                $stmt->close();

                redirecionar($excluido ? 'excluido' : 'nao_encontrado');
            }

            if ($tipo === 'modelo') {

                // Impede a exclusão de modelos utilizados por veículos.
                $stmt = $conn->prepare(
                    "SELECT COUNT(*) FROM carro WHERE id_modelo = ?"
                );

                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->bind_result($totalVeiculos);
                $stmt->fetch();
                $stmt->close();

                if ($totalVeiculos > 0) {
                    redirecionar('modelo_em_uso');
                }

                $stmt = $conn->prepare(
                    "DELETE FROM Modelo WHERE id_modelo = ?"
                );

                $stmt->bind_param('i', $id);
                $stmt->execute();

                $excluido = $stmt->affected_rows > 0;
                $stmt->close();

                redirecionar($excluido ? 'excluido' : 'nao_encontrado');
            }

            redirecionar('erro');
        }

    } catch (mysqli_sql_exception $erro) {
        redirecionar('erro');
    }

    redirecionar('erro');
}

// --------------------------------------------------
// MENSAGENS
// --------------------------------------------------

$mensagens = [
    'marca_ok' => ['Marca cadastrada com sucesso.', 'success'],
    'modelo_ok' => ['Modelo cadastrado com sucesso.', 'success'],
    'editado' => ['Alterações salvas com sucesso.', 'success'],
    'excluido' => ['Registro excluído com sucesso.', 'success'],
    'marca_em_uso' => ['Esta marca possui modelos vinculados e não pode ser excluída.', 'warning'],
    'modelo_em_uso' => ['Este modelo está associado a veículos e não pode ser excluído.', 'warning'],
    'marca_duplicada' => ['Já existe uma marca com esse nome.', 'warning'],
    'modelo_duplicado' => ['Já existe esse modelo para a marca selecionada.', 'warning'],
    'marca_invalida' => ['A marca selecionada não foi encontrada.', 'danger'],
    'nome_invalido' => ['Informe um nome válido com até 50 caracteres.', 'warning'],
    'nao_encontrado' => ['O registro solicitado não foi encontrado.', 'warning'],
    'csrf' => ['Sua sessão de segurança expirou. Atualize a página e tente novamente.', 'danger'],
    'erro_fk' => ['Não foi possível excluir o registro devido a vínculos existentes.', 'warning'],
    'erro' => ['Não foi possível concluir a operação. Tente novamente.', 'danger']
];

$status = $_GET['status'] ?? '';
$mensagem = $mensagens[$status] ?? null;

// --------------------------------------------------
// CONSULTAS
// --------------------------------------------------

$marcas = [];
$modelos = [];

$resultado = $conn->query(
    "SELECT id_marca, nome_marca
     FROM Marca
     ORDER BY nome_marca"
);

while ($row = $resultado->fetch_assoc()) {
    $marcas[] = $row;
}

$resultado = $conn->query(
    "SELECT
        mo.id_modelo,
        mo.nome_modelo,
        mo.id_marca,
        ma.nome_marca,
        COUNT(c.id_carro) AS total_veiculos
     FROM Modelo mo
     LEFT JOIN Marca ma ON ma.id_marca = mo.id_marca
     LEFT JOIN carro c ON c.id_modelo = mo.id_modelo
     GROUP BY
        mo.id_modelo,
        mo.nome_modelo,
        mo.id_marca,
        ma.nome_marca
     ORDER BY ma.nome_marca, mo.nome_modelo"
);

while ($row = $resultado->fetch_assoc()) {
    $modelos[] = $row;
}

$totalMarcas = count($marcas);
$totalModelos = count($modelos);

$totalVeiculos = (int) $conn->query(
    "SELECT COUNT(*) AS total FROM carro"
)->fetch_assoc()['total'];

$modelosComVeiculos = 0;

foreach ($modelos as $modelo) {
    if ((int) $modelo['total_veiculos'] > 0) {
        $modelosComVeiculos++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Marcas e Modelos | AutoManagerPro</title>

    <meta name="description"
          content="Gerenciamento de marcas e modelos de veículos no AutoManagerPro.">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            background: #0b1121;
            color: #e2e8f0;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
        }

        .painel {
            background: #111b2e;
            border: 1px solid #26354c;
        }

        .campo {
            background: #0b1425;
            border: 1px solid #334155;
            color: #f8fafc;
        }

        .campo:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
        }

        .acao {
            transition: .2s ease;
        }

        .acao:hover {
            transform: translateY(-1px);
        }

        .lista {
            max-height: 480px;
            overflow-y: auto;
        }

        .lista::-webkit-scrollbar {
            width: 6px;
        }

        .lista::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 10px;
        }

        .modal-fundo {
            background: rgba(2, 6, 23, .82);
            backdrop-filter: blur(5px);
        }

        [hidden] {
            display: none !important;
        }
    </style>
</head>

<body>

<div class="max-w-7xl mx-auto px-4 py-8 md:px-8">

    <!-- CABEÇALHO -->

    <header class="flex flex-col sm:flex-row sm:items-center
                   sm:justify-between gap-4 mb-8">
<div>
            <div class="flex items-center gap-3 mb-2">
                <div class="w-11 h-11 rounded-xl bg-blue-600/15
                            flex items-center justify-center text-blue-400">
                    <i class="fa-solid fa-gears text-xl"></i>
                </div>

                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white">
                        Marcas e Modelos
                    </h1>

                    <p class="text-slate-400 text-sm">
                        Configurações do AutoManagerPro
                    </p>
                </div>
            </div>
        </div>

        <a href="index.php"
           class="acao inline-flex items-center justify-center gap-2
                  bg-slate-700 hover:bg-slate-600 text-white
                  px-5 py-3 rounded-xl font-semibold text-sm">

            <i class="fa-solid fa-arrow-left"></i>
            Voltar ao painel
        </a>

    </header>

    <!-- IDENTIFICAÇÃO DEMONSTRATIVA -->

    <div class="flex items-center gap-2 text-xs text-blue-300
                bg-blue-500/10 border border-blue-500/20
                rounded-xl px-4 py-3 mb-6">

        <i class="fa-solid fa-circle-info"></i>

        Ambiente demonstrativo de gerenciamento de veículos.
    </div>

    <!-- MENSAGEM -->

    <?php if ($mensagem): ?>

        <?php
        $cores = [
            'success' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300',
            'warning' => 'bg-amber-500/10 border-amber-500/30 text-amber-300',
            'danger' => 'bg-red-500/10 border-red-500/30 text-red-300'
        ];

        $classeMensagem = $cores[$mensagem[1]] ?? $cores['danger'];
        ?>

        <div class="border rounded-xl px-4 py-4 mb-6
                    flex items-start gap-3 <?= $classeMensagem ?>">

            <i class="fa-solid fa-circle-info mt-1"></i>

            <p class="text-sm font-medium">
                <?= e($mensagem[0]) ?>
            </p>

        </div>

    <?php endif; ?>

    <!-- INDICADORES -->

    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        <div class="painel rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-slate-400">Marcas cadastradas</span>
                <i class="fa-solid fa-tags text-blue-400"></i>
            </div>

            <p class="text-3xl font-bold text-white">
                <?= $totalMarcas ?>
            </p>
        </div>

        <div class="painel rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-slate-400">Modelos cadastrados</span>
                <i class="fa-solid fa-car-side text-indigo-400"></i>
            </div>

            <p class="text-3xl font-bold text-white">
                <?= $totalModelos ?>
            </p>
        </div>

        <div class="painel rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-slate-400">Modelos utilizados</span>
                <i class="fa-solid fa-link text-emerald-400"></i>
            </div>

            <p class="text-3xl font-bold text-white">
                <?= $modelosComVeiculos ?>
            </p>
        </div>

        <div class="painel rounded-2xl p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-slate-400">Veículos cadastrados</span>
                <i class="fa-solid fa-warehouse text-amber-400"></i>
            </div>

            <p class="text-3xl font-bold text-white">
                <?= $totalVeiculos ?>
            </p>
        </div>

    </section>

    <!-- CADASTROS -->

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">

        <!-- NOVA MARCA -->

        <div class="painel rounded-2xl p-6">

            <div class="flex items-center gap-3 mb-5">

                <div class="w-10 h-10 rounded-xl bg-blue-500/15
                            flex items-center justify-center text-blue-400">

                    <i class="fa-solid fa-tag"></i>

                </div>

                <div>
                    <h2 class="font-bold text-white">Nova marca</h2>
                    <p class="text-xs text-slate-400">
                        Cadastre uma fabricante
                    </p>
                </div>

            </div>

            <form method="POST" class="flex flex-col sm:flex-row gap-3">

                <input type="hidden" name="csrf_token"
                       value="<?= e($csrf) ?>">

                <input type="text"
                       name="nome_marca"
                       maxlength="50"
                       required
                       placeholder="Ex.: Toyota"
                       class="campo rounded-xl px-4 py-3 flex-1 min-w-0">

                <button type="submit"
                        name="add_marca"
                        class="acao bg-blue-600 hover:bg-blue-500
                               text-white font-semibold px-5 py-3 rounded-xl">

                    <i class="fa-solid fa-plus mr-2"></i>
                    Cadastrar

                </button>

            </form>

        </div>

        <!-- NOVO MODELO -->

        <div class="painel rounded-2xl p-6">

            <div class="flex items-center gap-3 mb-5">

                <div class="w-10 h-10 rounded-xl bg-indigo-500/15
                            flex items-center justify-center text-indigo-400">

                    <i class="fa-solid fa-car"></i>

                </div>

                <div>
                    <h2 class="font-bold text-white">Novo modelo</h2>
                    <p class="text-xs text-slate-400">
                        Vincule o modelo a uma marca
                    </p>
                </div>

            </div>

            <form method="POST"
                  class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                <input type="hidden" name="csrf_token"
                       value="<?= e($csrf) ?>">

                <select name="id_marca"
                        required
                        class="campo rounded-xl px-4 py-3 min-w-0">

                    <option value="">Selecione a marca</option>

                    <?php foreach ($marcas as $marca): ?>

                        <option value="<?= (int) $marca['id_marca'] ?>">
                            <?= e($marca['nome_marca']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <div class="flex gap-2">

                    <input type="text"
                           name="nome_modelo"
                           maxlength="50"
                           required
                           placeholder="Ex.: Corolla"
                           class="campo rounded-xl px-4 py-3 flex-1 min-w-0">

                    <button type="submit"
                            name="add_modelo"
                            class="acao bg-indigo-600 hover:bg-indigo-500
                                   text-white font-semibold px-4 rounded-xl">

                        <i class="fa-solid fa-plus"></i>

                    </button>

                </div>

            </form>

        </div>

    </section>

    <!-- LISTAGENS -->

    <section class="grid grid-cols-1 lg:grid-cols-5 gap-5">

        <!-- MARCAS -->

        <div class="painel rounded-2xl overflow-hidden lg:col-span-2">

            <div class="px-5 py-4 border-b border-slate-700
                        flex items-center justify-between">

                <div>
                    <h2 class="font-bold text-white">Marcas registradas</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Fabricantes cadastradas
                    </p>
                </div>

                <span class="bg-blue-500/15 text-blue-300
                             text-xs font-bold rounded-full px-3 py-1">
                    <?= $totalMarcas ?>
                </span>

            </div>

            <div class="lista">

                <?php if (empty($marcas)): ?>

                    <p class="text-center text-slate-500 text-sm py-10">
                        Nenhuma marca cadastrada.
                    </p>

                <?php else: ?>

                    <?php foreach ($marcas as $marca): ?>

                        <div class="px-5 py-4 border-b border-slate-800
                                    last:border-0 flex items-center
                                    justify-between gap-3">

                            <div class="min-w-0">

                                <p class="font-semibold text-slate-200
                                          break-words">

                                    <?= e($marca['nome_marca']) ?>

                                </p>

                                <p class="text-xs text-slate-500 mt-1">
                                    ID #<?= (int) $marca['id_marca'] ?>
                                </p>

                            </div>

                            <div class="flex items-center gap-2 shrink-0">

                                <button type="button"
                                        class="acao w-9 h-9 rounded-lg
                                               bg-blue-500/10 text-blue-400
                                               hover:bg-blue-500/20"
                                        data-editar
                                        data-tipo="marca"
                                        data-id="<?= (int) $marca['id_marca'] ?>"
                                        data-nome="<?= e($marca['nome_marca']) ?>"
                                        title="Editar marca">

                                    <i class="fa-solid fa-pen"></i>

                                </button>

                                <form method="POST"
                                      onsubmit="return confirm('Deseja realmente excluir esta marca?')">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e($csrf) ?>">

                                    <input type="hidden" name="id_registro"
                                           value="<?= (int) $marca['id_marca'] ?>">

                                    <input type="hidden" name="tipo_exclusao"
                                           value="marca">

                                    <button type="submit"
                                            name="excluir_registro"
                                            class="acao w-9 h-9 rounded-lg
                                                   bg-red-500/10 text-red-400
                                                   hover:bg-red-500/20"
                                            title="Excluir marca">

                                        <i class="fa-solid fa-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

        <!-- MODELOS -->

        <div class="painel rounded-2xl overflow-hidden lg:col-span-3">

            <div class="px-5 py-4 border-b border-slate-700
                        flex items-center justify-between">

                <div>
                    <h2 class="font-bold text-white">Modelos registrados</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Modelos e seus vínculos com veículos
                    </p>
                </div>

                <span class="bg-indigo-500/15 text-indigo-300
                             text-xs font-bold rounded-full px-3 py-1">
                    <?= $totalModelos ?>
                </span>

            </div>

            <div class="lista">

                <?php if (empty($modelos)): ?>

                    <p class="text-center text-slate-500 text-sm py-10">
                        Nenhum modelo cadastrado.
                    </p>

                <?php else: ?>

                    <?php foreach ($modelos as $modelo): ?>

                        <div class="px-5 py-4 border-b border-slate-800
                                    last:border-0 flex items-center
                                    justify-between gap-3">

                            <div class="min-w-0">

                                <p class="text-xs text-blue-400
                                          uppercase tracking-wide mb-1">

                                    <?= e($modelo['nome_marca'] ?? 'Sem marca associada') ?>

                                </p>

                                <p class="font-semibold text-slate-200 break-words">

                                    <?= e($modelo['nome_modelo']) ?>

                                </p>

                                <p class="text-xs text-slate-500 mt-1">

                                    <?= (int) $modelo['total_veiculos'] ?>
                                    veículo(s) vinculado(s)

                                </p>

                            </div>

                            <div class="flex items-center gap-2 shrink-0">

                                <button type="button"
                                        class="acao w-9 h-9 rounded-lg
                                               bg-blue-500/10 text-blue-400
                                               hover:bg-blue-500/20"
                                        data-editar
                                        data-tipo="modelo"
                                        data-id="<?= (int) $modelo['id_modelo'] ?>"
                                        data-nome="<?= e($modelo['nome_modelo']) ?>"
                                        title="Editar modelo">

                                    <i class="fa-solid fa-pen"></i>

                                </button>

                                <form method="POST"
                                      onsubmit="return confirm('Deseja realmente excluir este modelo?')">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e($csrf) ?>">

                                    <input type="hidden" name="id_registro"
                                           value="<?= (int) $modelo['id_modelo'] ?>">

                                    <input type="hidden" name="tipo_exclusao"
                                           value="modelo">

                                    <button type="submit"
                                            name="excluir_registro"
                                            class="acao w-9 h-9 rounded-lg
                                                   bg-red-500/10 text-red-400
                                                   hover:bg-red-500/20"
                                            title="Excluir modelo">

                                        <i class="fa-solid fa-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </section>

    <footer class="text-center text-xs text-slate-600 mt-10">
        AutoManagerPro · Ambiente demonstrativo de gestão
    </footer>

</div>

<!-- MODAL DE EDIÇÃO -->

<div id="modalEdicao"
     class="modal-fundo fixed inset-0 z-50 flex items-center
            justify-center p-4"
     hidden>

    <div class="painel w-full max-w-md rounded-2xl overflow-hidden shadow-2xl">

        <div class="px-6 py-5 border-b border-slate-700
                    flex items-center justify-between">

            <h2 id="tituloModal" class="text-lg font-bold text-white">
                Editar registro
            </h2>

            <button type="button"
                    id="fecharModal"
                    class="text-slate-400 hover:text-white">

                <i class="fa-solid fa-xmark text-xl"></i>

            </button>

        </div>

        <form method="POST">

            <input type="hidden" name="csrf_token"
                   value="<?= e($csrf) ?>">

            <input type="hidden" name="id_registro" id="edit_id">

            <input type="hidden" name="tipo_edicao" id="edit_tipo">

            <div class="p-6">

                <label id="labelNome"
                       for="edit_nome"
                       class="block text-sm font-semibold text-slate-300 mb-2">

                    Nome

                </label>

                <input type="text"
                       name="nome_novo"
                       id="edit_nome"
                       maxlength="50"
                       required
                       class="campo w-full rounded-xl px-4 py-3">

            </div>

            <div class="px-6 py-4 border-t border-slate-700
                        flex justify-end gap-3">

                <button type="button"
                        id="cancelarModal"
                        class="acao bg-slate-700 hover:bg-slate-600
                               text-white px-5 py-3 rounded-xl text-sm font-semibold">

                    Cancelar

                </button>

                <button type="submit"
                        name="salvar_edicao"
                        class="acao bg-blue-600 hover:bg-blue-500
                               text-white px-5 py-3 rounded-xl text-sm font-semibold">

                    <i class="fa-solid fa-check mr-2"></i>
                    Salvar alterações

                </button>

            </div>

        </form>

    </div>

</div>

<script>
    const modal = document.getElementById('modalEdicao');
    const campoId = document.getElementById('edit_id');
    const campoTipo = document.getElementById('edit_tipo');
    const campoNome = document.getElementById('edit_nome');

    function fecharModal() {
        modal.hidden = true;
    }

    document.querySelectorAll('[data-editar]').forEach(botao => {

        botao.addEventListener('click', () => {

            const tipo = botao.dataset.tipo;

            campoId.value = botao.dataset.id;
            campoTipo.value = tipo;
            campoNome.value = botao.dataset.nome;

            document.getElementById('tituloModal').textContent =
                tipo === 'marca' ? 'Editar marca' : 'Editar modelo';

            document.getElementById('labelNome').textContent =
                tipo === 'marca' ? 'Nome da marca' : 'Nome do modelo';

            modal.hidden = false;

            campoNome.focus();
        });

    });

    document.getElementById('fecharModal')
        .addEventListener('click', fecharModal);

    document.getElementById('cancelarModal')
        .addEventListener('click', fecharModal);

    modal.addEventListener('click', event => {
        if (event.target === modal) {
            fecharModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden) {
            fecharModal();
        }
    });
</script>

</body>
</html>



