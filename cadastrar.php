<?php
require_once 'conexao.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * AutoManagerPro
 * Cadastro demonstrativo de veículos.
 */

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if (!isset($_SESSION['csrf_cadastrar'])) {
    $_SESSION['csrf_cadastrar'] = bin2hex(random_bytes(32));
}

$erros = [];
$sucesso = false;

$dados = [
    'id_loja' => '',
    'id_modelo' => '',
    'ano' => '',
    'preco' => ''
];

$lojas = [];
$modelos = [];

$anoMaximo = (int) date('Y') + 1;
$fotoSalva = null;

/*
|--------------------------------------------------------------------------
| Carregar lojas e modelos
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

} catch (Throwable $erro) {
    error_log('Erro ao carregar cadastro de veículos: ' . $erro->getMessage());
    $erros[] = 'Não foi possível carregar as lojas e os modelos.';
}

/*
|--------------------------------------------------------------------------
| Processar cadastro
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
        !hash_equals($_SESSION['csrf_cadastrar'], $token)
    ) {
        $erros[] = 'Sua sessão expirou ou a solicitação é inválida. Atualize a página.';
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
        $erros[] = 'Selecione uma loja válida.';
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
        $erros[] = 'Selecione um modelo válido.';
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
    | Validar preço
    |----------------------------------------------------------------------
    */

    $precoInformado = str_replace(',', '.', $dados['preco']);

    if (
        !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $precoInformado) ||
        (float) $precoInformado <= 0
    ) {
        $erros[] = 'Informe um preço válido, maior que zero.';
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
    | Confirmar existência da loja e do modelo
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
                $erros[] = 'A loja selecionada não foi encontrada.';
            }

            $stmtLoja->close();

            $stmtModelo = $conn->prepare(
                "SELECT id_modelo FROM Modelo WHERE id_modelo = ?"
            );

            $stmtModelo->bind_param('i', $idModelo);
            $stmtModelo->execute();

            if (!$stmtModelo->get_result()->fetch_assoc()) {
                $erros[] = 'O modelo selecionado não foi encontrado.';
            }

            $stmtModelo->close();

        } catch (Throwable $erro) {
            error_log('Erro ao validar cadastro: ' . $erro->getMessage());
            $erros[] = 'Não foi possível validar os dados informados.';
        }
    }

    /*
    |----------------------------------------------------------------------
    | Processar imagem
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
            $erros[] = 'A imagem deve ter no máximo 5 MB.';

        } elseif (!is_uploaded_file($arquivo['tmp_name'])) {
            $erros[] = 'O arquivo enviado não é válido.';

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
                $erros[] = 'Formato de imagem inválido. Utilize JPG, PNG ou WebP.';
            } else {
                $pastaUploads = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

                if (
                    !is_dir($pastaUploads) ||
                    !is_writable($pastaUploads)
                ) {
                    $erros[] = 'A pasta de imagens não está disponível para gravação.';
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
                        $fotoSalva = $nomeArquivo;
                    } else {
                        $erros[] = 'Não foi possível salvar a imagem enviada.';
                    }
                }
            }
        }
    }

    /*
    |----------------------------------------------------------------------
    | Inserir veículo
    |----------------------------------------------------------------------
    */

    if (empty($erros)) {
        try {
            $foto = $fotoSalva ?? 'sem-foto.jpg';

            $stmt = $conn->prepare(
                "INSERT INTO Carro
                    (ano, preco, foto, id_loja, id_modelo, vendido)
                 VALUES (?, ?, ?, ?, ?, 0)"
            );

            $stmt->bind_param(
                'issii',
                $ano,
                $precoBanco,
                $foto,
                $idLoja,
                $idModelo
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['csrf_cadastrar'] = bin2hex(random_bytes(32));

            header('Location: veiculos.php?msg=cadastrado');
            exit;

        } catch (Throwable $erro) {
            error_log('Erro ao cadastrar veículo: ' . $erro->getMessage());

            if ($fotoSalva !== null) {
                $arquivoNovo = __DIR__
                    . DIRECTORY_SEPARATOR
                    . 'uploads'
                    . DIRECTORY_SEPARATOR
                    . $fotoSalva;

                if (is_file($arquivoNovo)) {
                    @unlink($arquivoNovo);
                }
            }

            $erros[] = 'Não foi possível cadastrar o veículo. Tente novamente.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cadastrar Veículo | AutoManagerPro</title>

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

                <span aria-hidden="true">←</span>
                Voltar para veículos
            </a>
        </div>

        <header class="mb-8">
<div class="flex items-center gap-3 mb-3">

                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-2xl">
                    🚗
                </div>

                <div>
                    <p class="text-xs uppercase tracking-widest text-emerald-400 font-bold">
                        AutoManagerPro
                    </p>

                    <h1 class="text-2xl md:text-3xl font-bold text-white">
                        Cadastrar veículo
                    </h1>
                </div>

            </div>

            <p class="text-slate-400 text-sm">
                Preencha as informações abaixo para adicionar um veículo ao estoque.
            </p>
        </header>

        <?php if (!empty($erros)): ?>

            <div class="mb-6 p-4 rounded-xl border border-red-500/30 bg-red-500/10">

                <p class="font-semibold text-red-400 mb-2">
                    Não foi possível concluir o cadastro:
                </p>

                <ul class="list-disc list-inside space-y-1 text-sm text-red-300">
                    <?php foreach ($erros as $erro): ?>
                        <li><?= e($erro) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>

        <form method="POST"
              action="cadastrar.php"
              enctype="multipart/form-data"
              class="bg-painel border border-slate-700/70 rounded-2xl p-5 md:p-8 shadow-xl">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION['csrf_cadastrar']) ?>">

            <div class="mb-8">
                <h2 class="text-lg font-bold text-white mb-1">
                    Informações do veículo
                </h2>

                <p class="text-xs text-slate-400">
                    Identificação e características principais.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Loja -->

                <div>
                    <label for="id_loja"
                           class="block text-sm font-semibold text-slate-300 mb-2">
                        Loja responsável
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
                        Modelo do veículo
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
                                    ($modelo['nome_marca'] ?: 'Marca não informada')
                                    . ' — '
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
                        Ano do veículo
                    </label>

                    <input type="number"
                           name="ano"
                           id="ano"
                           class="campo"
                           min="1900"
                           max="<?= $anoMaximo ?>"
                           step="1"
                           placeholder="Ex.: 2024"
                           value="<?= e($dados['ano']) ?>"
                           required>
                </div>

                <!-- Preço -->

                <div>
                    <label for="preco"
                           class="block text-sm font-semibold text-slate-300 mb-2">
                        Preço de venda (R$)
                    </label>

                    <input type="text"
                           name="preco"
                           id="preco"
                           class="campo"
                           inputmode="decimal"
                           placeholder="Ex.: 85000,00"
                           value="<?= e($dados['preco']) ?>"
                           required>
                </div>

            </div>

            <!-- Foto -->

            <div class="mt-8 pt-7 border-t border-slate-700/70">

                <h2 class="text-lg font-bold text-white mb-1">
                    Foto do veículo
                </h2>

                <p class="text-xs text-slate-400 mb-5">
                    A imagem é opcional. Formatos aceitos: JPG, PNG e WebP. Limite de 5 MB.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">

                    <div>
                        <label for="foto"
                               class="block text-sm font-semibold text-slate-300 mb-2">
                            Selecionar imagem
                        </label>

                        <input type="file"
                               name="foto"
                               id="foto"
                               class="campo"
                               accept="image/jpeg,image/png,image/webp">

                        <p class="text-xs text-slate-500 mt-2">
                            Se nenhuma foto for enviada, será utilizada a imagem padrão.
                        </p>
                    </div>

                    <div class="flex justify-center">

                        <div class="w-full max-w-xs h-44 rounded-xl border border-dashed border-slate-600 bg-slate-900/60 flex items-center justify-center overflow-hidden">

                            <img id="preview"
                                 src=""
                                 alt="Pré-visualização da foto"
                                 class="hidden w-full h-full object-contain">

                            <span id="previewTexto"
                                  class="text-slate-500 text-sm text-center px-4">
                                A pré-visualização aparecerá aqui
                            </span>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Botões -->

            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 mt-10 pt-6 border-t border-slate-700/70">

                <a href="veiculos.php"
                   class="botao-secundario text-center px-6 py-3 rounded-xl font-semibold">
                    Cancelar
                </a>

                <button type="submit"
                        class="botao-principal px-6 py-3 rounded-xl">
                    Cadastrar veículo
                </button>

            </div>

        </form>

        <footer class="text-center text-xs text-slate-600 mt-8">
            AutoManagerPro — Sistema demonstrativo de gestão de veículos.
        </footer>

    </main>

    <script>
        const campoFoto = document.getElementById('foto');
        const preview = document.getElementById('preview');
        const previewTexto = document.getElementById('previewTexto');

        let imagemAnterior = null;

        campoFoto.addEventListener('change', function () {

            if (imagemAnterior) {
                URL.revokeObjectURL(imagemAnterior);
                imagemAnterior = null;
            }

            const arquivo = this.files[0];

            if (!arquivo) {
                preview.src = '';
                preview.classList.add('hidden');
                previewTexto.classList.remove('hidden');
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
                preview.classList.add('hidden');
                previewTexto.classList.remove('hidden');
                return;
            }

            if (arquivo.size > 5 * 1024 * 1024) {
                alert('A imagem deve ter no máximo 5 MB.');
                this.value = '';
                preview.classList.add('hidden');
                previewTexto.classList.remove('hidden');
                return;
            }

            imagemAnterior = URL.createObjectURL(arquivo);

            preview.src = imagemAnterior;
            preview.classList.remove('hidden');
            previewTexto.classList.add('hidden');
        });

        window.addEventListener('beforeunload', function () {
            if (imagemAnterior) {
                URL.revokeObjectURL(imagemAnterior);
            }
        });
    </script>

</body>
</html>



