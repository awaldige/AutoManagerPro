
<?php
require_once 'conexao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * AutoManagerPro
 * EdiÃ§Ã£o demonstrativa de veÃ­culos.
 */

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['csrf_editar'])) {
    $_SESSION['csrf_editar'] = bin2hex(random_bytes(32));
}

$erros = [];

$dados = [
    'id_loja' => '',
    'id_modelo' => '',
    'ano' => '',
    'preco' => ''
];

$lojas = [];
$modelos = [];
$carro = null;

$anoMaximo = (int) date('Y') + 1;
$fotoNova = null;
$fotoAntiga = null;

/*
|--------------------------------------------------------------------------
| Identificar veÃ­culo
|--------------------------------------------------------------------------
*/

$idCarro = filter_var(
    $_POST['id_carro'] ?? $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idCarro === false || $idCarro === null) {
    http_response_code(400);
    exit('Identificador de veÃ­culo invÃ¡lido.');
}

/*
|--------------------------------------------------------------------------
| Carregar lojas, modelos e veÃ­culo
|--------------------------------------------------------------------------
*/

try {
    $resultadoLojas = $conn->query(
        "SELECT id_loja, nome
         FROM Loja
         ORDER BY nome ASC"
    );

    while ($loja = $resultadoLojas->fetch_assoc()) {
        $lojas[] = $loja;
    }

    $resultadoModelos = $conn->query(
        "SELECT
            mo.id_modelo,
            mo.nome_modelo,
            ma.nome_marca
         FROM Modelo mo
         LEFT JOIN Marca ma
            ON ma.id_marca = mo.id_marca
         ORDER BY ma.nome_marca ASC, mo.nome_modelo ASC"
    );

    while ($modelo = $resultadoModelos->fetch_assoc()) {
        $modelos[] = $modelo;
    }

    $stmtCarro = $conn->prepare(
        "SELECT
            id_carro,
            ano,
            preco,
            foto,
            id_loja,
            id_modelo,
            vendido
         FROM Carro
         WHERE id_carro = ?"
    );

    $stmtCarro->bind_param('i', $idCarro);
    $stmtCarro->execute();

    $carro = $stmtCarro->get_result()->fetch_assoc();

    $stmtCarro->close();

    if (!$carro) {
        http_response_code(404);
        exit('VeÃ­culo nÃ£o encontrado.');
    }

    $dados = [
        'id_loja' => (string) ($carro['id_loja'] ?? ''),
        'id_modelo' => (string) ($carro['id_modelo'] ?? ''),
        'ano' => (string) $carro['ano'],
        'preco' => (string) $carro['preco']
    ];

    $fotoAntiga = $carro['foto'] ?: 'sem-foto.jpg';

} catch (Throwable $erro) {
    error_log('Erro ao carregar veÃ­culo para ediÃ§Ã£o: ' . $erro->getMessage());

    http_response_code(500);
    exit('NÃ£o foi possÃ­vel carregar os dados do veÃ­culo.');
}

/*
|--------------------------------------------------------------------------
| Processar atualizaÃ§Ã£o
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $dados['id_loja'] = trim($_POST['id_loja'] ?? '');
    $dados['id_modelo'] = trim($_POST['id_modelo'] ?? '');
    $dados['ano'] = trim($_POST['ano'] ?? '');
    $dados['preco'] = trim($_POST['preco'] ?? '');

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_editar'], $token)
    ) {
        $erros[] = 'Sua sessÃ£o expirou ou a solicitaÃ§Ã£o Ã© invÃ¡lida. Atualize a pÃ¡gina.';
    }

    /*
    |----------------------------------------------------------------------
    | Validar loja
    |----------------------------------------------------------------------
    */

    $idLoja = filter_var(
        $dados['id_loja'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idLoja === false) {
        $erros[] = 'Selecione uma loja vÃ¡lida.';
    }

    /*
    |----------------------------------------------------------------------
    | Validar modelo
    |----------------------------------------------------------------------
    */

    $idModelo = filter_var(
        $dados['id_modelo'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idModelo === false) {
        $erros[] = 'Selecione um modelo vÃ¡lido.';
    }

    /*
    |----------------------------------------------------------------------
    | Validar ano
    |----------------------------------------------------------------------
    */

    $ano = filter_var(
        $dados['ano'],
        FILTER_VALIDATE_INT
    );

    if (
        $ano === false ||
        $ano < 1900 ||
        $ano > $anoMaximo
    ) {
        $erros[] = 'Informe um ano entre 1900 e ' . $anoMaximo . '.';
    }

    /*
    |----------------------------------------------------------------------
    | Validar preÃ§o
    |----------------------------------------------------------------------
    */

    $precoInformado = str_replace(',', '.', $dados['preco']);

    if (
        !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $precoInformado) ||
        (float) $precoInformado <= 0
    ) {
        $erros[] = 'Informe um preÃ§o vÃ¡lido, maior que zero.';
    } else {
        $precoBanco = number_format(
            (float) $precoInformado,
            2,
            '.',
            ''
        );
    }

    /*
    |----------------------------------------------------------------------
    | Confirmar existÃªncia da loja e do modelo
    |----------------------------------------------------------------------
    */

    if (empty($erros)) {
        try {
            $stmtLoja = $conn->prepare(
                "SELECT id_loja FROM Loja WHERE id_loja = ?"
            );

            $stmtLoja->bind_param('i', $idLoja);
            $stmtLoja->execute();

            if (!$stmtLoja->get_result()->fetch_assoc()) {
                $erros[] = 'A loja selecionada nÃ£o foi encontrada.';
            }

            $stmtLoja->close();

            $stmtModelo = $conn->prepare(
                "SELECT id_modelo FROM Modelo WHERE id_modelo = ?"
            );

            $stmtModelo->bind_param('i', $idModelo);
            $stmtModelo->execute();

            if (!$stmtModelo->get_result()->fetch_assoc()) {
                $erros[] = 'O modelo selecionado nÃ£o foi encontrado.';
            }

            $stmtModelo->close();

        } catch (Throwable $erro) {
            error_log('Erro ao validar ediÃ§Ã£o: ' . $erro->getMessage());
            $erros[] = 'NÃ£o foi possÃ­vel validar os dados informados.';
        }
    }

    /*
    |----------------------------------------------------------------------
    | Processar nova imagem
    |----------------------------------------------------------------------
    */

    if (
        empty($erros) &&
        isset($_FILES['foto']) &&
        $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        $arquivo = $_FILES['foto'];

        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            $erros[] = 'Ocorreu um erro durante o envio da imagem.';

        } elseif ($arquivo['size'] > 5 * 1024 * 1024) {
            $erros[] = 'A imagem deve ter no mÃ¡ximo 5 MB.';

        } elseif (!is_uploaded_file($arquivo['tmp_name'])) {
            $erros[] = 'O arquivo enviado nÃ£o Ã© vÃ¡lido.';

        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($arquivo['tmp_name']);

            $formatosPermitidos = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $informacoesImagem = @getimagesize($arquivo['tmp_name']);

            if (
                !isset($formatosPermitidos[$mime]) ||
                $informacoesImagem === false
            ) {
                $erros[] = 'Formato de imagem invÃ¡lido. Utilize JPG, PNG ou WebP.';

            } else {
                $pastaUploads = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

                if (
                    !is_dir($pastaUploads) ||
                    !is_writable($pastaUploads)
                ) {
                    $erros[] = 'A pasta de imagens nÃ£o estÃ¡ disponÃ­vel para gravaÃ§Ã£o.';

                } else {
                    $extensao = $formatosPermitidos[$mime];

                    $nomeArquivo = bin2hex(random_bytes(16))
                        . '.'
                        . $extensao;

                    $destino = $pastaUploads
                        . DIRECTORY_SEPARATOR
                        . $nomeArquivo;

                    if (move_uploaded_file(
                        $arquivo['tmp_name'],
                        $destino
                    )) {
                        $fotoNova = $nomeArquivo;
                    } else {
                        $erros[] = 'NÃ£o foi possÃ­vel salvar a nova imagem.';
                    }
                }
            }
        }
    }

    /*
    |----------------------------------------------------------------------
    | Atualizar veÃ­culo
    |----------------------------------------------------------------------
    */

    if (empty($erros)) {
        try {
            $fotoFinal = $fotoNova ?? $fotoAntiga;

            $stmt = $conn->prepare(
                "UPDATE Carro
                 SET
                    id_modelo = ?,
                    ano = ?,
                    preco = ?,
                    id_loja = ?,
                    foto = ?
                 WHERE id_carro = ?"
            );

            $stmt->bind_param(
                'iisisi',
                $idModelo,
                $ano,
                $precoBanco,
                $idLoja,
                $fotoFinal,
                $idCarro
            );

            $stmt->execute();
            $stmt->close();

            /*
             * SÃ³ remover a imagem antiga apÃ³s a atualizaÃ§Ã£o.
             * Nunca remover a imagem padrÃ£o.
             */

            if (
                $fotoNova !== null &&
                $fotoAntiga !== 'sem-foto.jpg' &&
                $fotoAntiga !== $fotoNova &&
                basename($fotoAntiga) === $fotoAntiga
            ) {
                $arquivoAntigo = __DIR__
                    . DIRECTORY_SEPARATOR
                    . 'uploads'
                    . DIRECTORY_SEPARATOR
                    . $fotoAntiga;

                if (is_file($arquivoAntigo)) {
                    @unlink($arquivoAntigo);
                }
            }

            $_SESSION['csrf_editar'] = bin2hex(random_bytes(32));

            header('Location: veiculos.php?msg=editado');
            exit;

        } catch (Throwable $erro) {
            error_log('Erro ao atualizar veÃ­culo: ' . $erro->getMessage());

            if ($fotoNova !== null) {
                $arquivoNovo = __DIR__
                    . DIRECTORY_SEPARATOR
                    . 'uploads'
                    . DIRECTORY_SEPARATOR
                    . $fotoNova;

                if (is_file($arquivoNovo)) {
                    @unlink($arquivoNovo);
                }
            }

            $erros[] = 'NÃ£o foi possÃ­vel atualizar o veÃ­culo. Tente novamente.';
        }
    }
}

$fotoExibida = $fotoNova ?? $fotoAntiga;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar VeÃ­culo | AutoManagerPro</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        fundo: '#0b1121',
                        painel: '#111c32',
                        destaque: '#10b981'
                    }
                }
            }
        };
    </script>

    <style>
        body {
            background: #0b1121;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .campo {
            width: 100%;
            background: #0b1121;
            border: 1px solid #334155;
            border-radius: 10px;
            padding: 12px 14px;
            color: #f8fafc;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        .campo:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, .12);
        }

        .campo::placeholder {
            color: #64748b;
        }

        select.campo option {
            background: #111c32;
            color: #f8fafc;
        }

        .botao-principal {
            background: #10b981;
            color: #052e24;
            font-weight: 700;
            transition: .2s;
        }

        .botao-principal:hover {
            background: #34d399;
            transform: translateY(-1px);
        }

        .botao-secundario {
            background: #1e293b;
            color: #cbd5e1;
            transition: .2s;
        }

        .botao-secundario:hover {
            background: #334155;
        }
    </style>
</head>

<body>

    <main class="max-w-4xl mx-auto px-4 py-8 md:py-12">

        <div class="mb-8">
            <a href="veiculos.php"
               class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-emerald-400 transition">

                <span aria-hidden="true">â†</span>
                Voltar para veÃ­culos
            </a>
        </div>

        <header class="mb-8">
<div class="flex items-center gap-3 mb-3">

                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-2xl">
                    âœï¸
                </div>

                <div>
                    <p class="text-xs uppercase tracking-widest text-emerald-400 font-bold">
                        AutoManagerPro
                    </p>

                    <h1 class="text-2xl md:text-3xl font-bold text-white">
                        Editar veÃ­culo #<?= (int) $carro['id_carro'] ?>
                    </h1>
                </div>

            </div>

            <p class="text-slate-400 text-sm">
                Atualize as informaÃ§Ãµes e caracterÃ­sticas do veÃ­culo.
            </p>

        </header>

        <?php if (!empty($erros)): ?>

            <div class="mb-6 p-4 rounded-xl border border-red-500/30 bg-red-500/10">

                <p class="font-semibold text-red-400 mb-2">
                    NÃ£o foi possÃ­vel concluir a atualizaÃ§Ã£o:
                </p>

                <ul class="list-disc list-inside space-y-1 text-sm text-red-300">

                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>

        <form method="POST"
              action="editar.php?id=<?= (int) $carro['id_carro'] ?>"
              enctype="multipart/form-data"
              class="bg-painel border border-slate-700/70 rounded-2xl p-5 md:p-8 shadow-xl">

            <input type="hidden"
                   name="id_carro"
                   value="<?= (int) $carro['id_carro'] ?>">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_editar']) ?>">

            <div class="mb-8">

                <h2 class="text-lg font-bold text-white mb-1">
                    InformaÃ§Ãµes do veÃ­culo
                </h2>

                <p class="text-xs text-slate-400">
                    IdentificaÃ§Ã£o e caracterÃ­sticas principais.
                </p>

            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Loja -->

                <div>
                    <label for="id_loja"
                           class="block text-sm font-semibold text-slate-300 mb-2">
                        Loja responsÃ¡vel
                    </label>

                    <select name="id_loja"
                            id="id_loja"
                            class="campo"
                            required>

                        <option value="">Selecione uma loja</option>

                        <?php foreach ($lojas as $loja): ?>

                            <option value="<?= (int) $loja['id_loja'] ?>"
                                <?= (string) $dados['id_loja'] === (string) $loja['id_loja'] ? 'selected' : '' ?>>

                                <?= e($loja['nome']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- Modelo -->

                <div>
                    <label for="id_modelo"
                           class="block text-sm font-semibold text-slate-300 mb-2">
                        Modelo do veÃ­culo
                    </label>

                    <select name="id_modelo"
                            id="id_modelo"
                            class="campo"
                            required>

                        <option value="">Selecione um modelo</option>

                        <?php foreach ($modelos as $modelo): ?>

                            <option value="<?= (int) $modelo['id_modelo'] ?>"
                                <?= (string) $dados['id_modelo'] === (string) $modelo['id_modelo'] ? 'selected' : '' ?>>

                                <?= e(
                                    ($modelo['nome_marca'] ?: 'Marca nÃ£o informada')
                                    . ' â€” '
                                    . $modelo['nome_modelo']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- Ano -->

                <div>
                    <label for="ano"
                           class="block text-sm font-semibold text-slate-300 mb-2">
                        Ano do veÃ­culo
                    </label>

                    <input type="number"
                           name="ano"
                           id="ano"
                           class="campo"
                           min="1900"
                           max="<?= $anoMaximo ?>"
                           step="1"
                           value="<?= e($dados['ano']) ?>"
                           required>
                </div>

                <!-- PreÃ§o -->

                <div>
                    <label for="preco"
                           class="block text-sm font-semibold text-slate-300 mb-2">
                        PreÃ§o de venda (R$)
                    </label>

                    <input type="text"
                           name="preco"
                           id="preco"
                           class="campo"
                           inputmode="decimal"
                           value="<?= e($dados['preco']) ?>"
                           required>
                </div>

            </div>

            <!-- Imagem -->

            <div class="mt-8 pt-7 border-t border-slate-700/70">

                <h2 class="text-lg font-bold text-white mb-1">
                    Foto do veÃ­culo
                </h2>

                <p class="text-xs text-slate-400 mb-5">
                    VocÃª pode manter a imagem atual ou selecionar outra.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">

                    <div>

                        <label for="foto"
                               class="block text-sm font-semibold text-slate-300 mb-2">
                            Alterar imagem
                        </label>

                        <input type="file"
                               name="foto"
                               id="foto"
                               class="campo"
                               accept="image/jpeg,image/png,image/webp">

                        <p class="text-xs text-slate-500 mt-2">
                            JPG, PNG ou WebP. Tamanho mÃ¡ximo: 5 MB.
                        </p>

                    </div>

                    <div class="flex justify-center">

                        <div class="w-full max-w-xs h-44 rounded-xl border border-slate-700 bg-slate-900/60 flex items-center justify-center overflow-hidden">

                            <img
                                id="preview"
                                src="<?= e('uploads/' . basename($fotoExibida)) ?>"
                                alt="Foto do veÃ­culo"
                                class="w-full h-full object-contain"
                                onerror="this.style.display='none'; document.getElementById('fotoIndisponivel').classList.remove('hidden');">

                            <span id="fotoIndisponivel"
                                  class="hidden text-slate-500 text-sm text-center px-4">
                                Imagem nÃ£o disponÃ­vel
                            </span>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Status -->

            <div class="mt-6 p-4 rounded-xl border border-slate-700 bg-slate-900/40">

                <p class="text-sm text-slate-300 font-semibold">
                    Status do veÃ­culo
                </p>

                <p class="text-sm mt-1 <?= (int) $carro['vendido'] === 1 ? 'text-amber-400' : 'text-emerald-400' ?>">

                    <?= (int) $carro['vendido'] === 1
                        ? 'Vendido'
                        : 'DisponÃ­vel em estoque' ?>

                </p>

                <p class="text-xs text-slate-500 mt-1">
                    O status de venda Ã© preservado durante a ediÃ§Ã£o.
                </p>

            </div>

            <!-- BotÃµes -->

            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 mt-10 pt-6 border-t border-slate-700/70">

                <a href="veiculos.php"
                   class="botao-secundario text-center px-6 py-3 rounded-xl font-semibold">
                    Cancelar
                </a>

                <button type="submit"
                        class="botao-principal px-6 py-3 rounded-xl">
                    Salvar alteraÃ§Ãµes
                </button>

            </div>

        </form>

        <footer class="text-center text-xs text-slate-600 mt-8">
            AutoManagerPro â€” Sistema demonstrativo de gestÃ£o de veÃ­culos.
        </footer>

    </main>

    <script>
        const campoFoto = document.getElementById('foto');
        const preview = document.getElementById('preview');
        const fotoIndisponivel = document.getElementById('fotoIndisponivel');

        let imagemAnterior = null;

        campoFoto.addEventListener('change', function () {

            if (imagemAnterior) {
                URL.revokeObjectURL(imagemAnterior);
                imagemAnterior = null;
            }

            const arquivo = this.files[0];

            if (!arquivo) {
                return;
            }

            const formatos = [
                'image/jpeg',
                'image/png',
                'image/webp'
            ];

            if (!formatos.includes(arquivo.type)) {
                alert('Selecione uma imagem JPG, PNG ou WebP.');
                this.value = '';
                return;
            }

            if (arquivo.size > 5 * 1024 * 1024) {
                alert('A imagem deve ter no mÃ¡ximo 5 MB.');
                this.value = '';
                return;
            }

            imagemAnterior = URL.createObjectURL(arquivo);

            preview.src = imagemAnterior;
            preview.style.display = 'block';
            fotoIndisponivel.classList.add('hidden');
        });

        window.addEventListener('beforeunload', function () {
            if (imagemAnterior) {
                URL.revokeObjectURL(imagemAnterior);
            }
        });
    </script>

</body>
</html>





