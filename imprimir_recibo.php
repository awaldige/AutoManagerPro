
<?php
require_once 'conexao.php';

/**
 * AutoManagerPro
 * Recibo demonstrativo de venda.
 * Documento não fiscal.
 */

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function moeda($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

// Validação do identificador da venda.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Identificador de venda inválido.');
}

// Busca os dados sem depender de o veículo ainda existir no estoque.
$sql = "
    SELECT
        v.id_venda,
        v.data_venda,
        v.valor_total,
        v.veiculo_nome,
        v.veiculo_marca,

        cl.nome AS cliente_nome,
        cl.telefone AS cliente_telefone,
        cl.email AS cliente_email,

        tp.descricao AS pagamento_descricao,

        vd.nome AS vendedor_nome,
        vd.telefone AS vendedor_telefone,

        l.nome AS loja_nome,
        l.endereco AS loja_endereco

    FROM venda v

    LEFT JOIN cliente cl
        ON v.id_cliente = cl.id_cliente

    LEFT JOIN tipopagamento tp
        ON v.id_tipo_pagamento = tp.id_tipo_pagamento

    LEFT JOIN vendedor vd
        ON v.id_vendedor = vd.id_vendedor

    LEFT JOIN carro c
        ON v.id_carro = c.id_carro

    LEFT JOIN loja l
        ON c.id_loja = l.id_loja

    WHERE v.id_venda = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();

$resultado = $stmt->get_result();
$venda = $resultado->fetch_assoc();

$stmt->close();

if (!$venda) {
    http_response_code(404);
    exit('Venda não encontrada.');
}

// Dados apresentados no documento.
$numeroVenda = str_pad((string)$venda['id_venda'], 5, '0', STR_PAD_LEFT);

$dataVenda = !empty($venda['data_venda'])
    ? date('d/m/Y', strtotime($venda['data_venda']))
    : 'Não informada';

$veiculo = trim(
    (string)($venda['veiculo_marca'] ?? '') . ' ' .
    (string)($venda['veiculo_nome'] ?? '')
);

if ($veiculo === '') {
    $veiculo = 'Veículo não identificado';
}

$cliente = $venda['cliente_nome'] ?: 'Não informado';
$telefoneCliente = $venda['cliente_telefone'] ?: 'Não informado';
$emailCliente = $venda['cliente_email'] ?: 'Não informado';

$pagamento = $venda['pagamento_descricao'] ?: 'Não informado';
$vendedor = $venda['vendedor_nome'] ?: 'Não informado';

$loja = $venda['loja_nome'] ?: 'Não informada';
$enderecoLoja = $venda['loja_endereco'] ?: '';

$valorTotal = (float)$venda['valor_total'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Recibo #<?= e($numeroVenda) ?> | AutoManagerPro</title>

    <style>
        :root {
            --primary: #0f766e;
            --primary-dark: #115e59;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --background: #f1f5f9;
            --surface: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 35px 18px;
            background: var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        .actions {
            max-width: 850px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 12px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: .2s;
        }

        .btn-print {
            background: var(--primary);
            color: white;
        }

        .btn-print:hover {
            background: var(--primary-dark);
        }

        .btn-back {
            background: #e2e8f0;
            color: #334155;
        }

        .receipt {
            width: 100%;
            max-width: 850px;
            margin: auto;
            padding: 42px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, .07);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 3px solid var(--primary);
        }

        .brand {
            color: var(--primary);
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -.7px;
        }

        .brand-subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .document-title {
            text-align: right;
        }

        .document-title h1 {
            margin: 0;
            font-size: 19px;
            letter-spacing: 1px;
        }

        .document-number {
            margin-top: 7px;
            color: var(--primary);
            font-size: 14px;
            font-weight: 700;
        }

        .section {
            margin-top: 30px;
        }

        .section-title {
            margin: 0 0 14px;
            padding-bottom: 9px;
            border-bottom: 1px solid var(--border);
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .field {
            min-width: 0;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 8px;
        }

        .field-label {
            display: block;
            margin-bottom: 7px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .6px;
            text-transform: uppercase;
        }

        .field-value {
            display: block;
            overflow-wrap: anywhere;
            color: var(--text);
            font-size: 14px;
            font-weight: 700;
        }

        .vehicle {
            padding: 19px;
            background: #f0fdfa;
            border: 1px solid #ccfbf1;
            border-radius: 9px;
        }

        .vehicle .field-value {
            color: var(--primary-dark);
            font-size: 19px;
        }

        .total-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
            padding: 23px;
            background: var(--primary);
            border-radius: 10px;
            color: white;
        }

        .total-label {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .total-value {
            font-size: 27px;
            font-weight: 800;
            white-space: nowrap;
        }

        .notice {
            margin-top: 25px;
            padding: 13px 15px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            color: #92400e;
            font-size: 12px;
            line-height: 1.6;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 65px;
        }

        .signature {
            padding-top: 10px;
            border-top: 1px solid #94a3b8;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }

        .footer {
            margin-top: 45px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px 10px;
            }

            .receipt {
                padding: 23px 17px;
            }

            .header {
                flex-direction: column;
            }

            .document-title {
                text-align: left;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .total-box {
                align-items: flex-start;
                flex-direction: column;
            }

            .total-value {
                font-size: 24px;
            }

            .signatures {
                gap: 20px;
            }

            .actions {
                justify-content: stretch;
            }

            .actions .btn {
                flex: 1;
                text-align: center;
            }
        }

        @page {
            size: A4;
            margin: 15mm;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }

            .no-print {
                display: none !important;
            }

            .receipt {
                max-width: none;
                padding: 10px;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }

            .field,
            .vehicle,
            .total-box,
            .notice {
                break-inside: avoid;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <div class="actions no-print">
        <a href="relatorio_vendas.php" class="btn btn-back">
            ← Voltar ao relatório
        </a>

        <button type="button" class="btn btn-print" onclick="window.print()">
            Imprimir / Salvar PDF
        </button>
    </div>

    <main class="receipt">

        <header class="header">
<div>
                <div class="brand">🚗 AutoManagerPro</div>

                <div class="brand-subtitle">
                    Sistema demonstrativo de gestão de veículos
                </div>
            </div>

            <div class="document-title">
                <h1>RECIBO DE VENDA</h1>

                <div class="document-number">
                    Nº <?= e($numeroVenda) ?>
                </div>
            </div>

        </header>

        <section class="section">

            <h2 class="section-title">Identificação da venda</h2>

            <div class="grid">

                <div class="field">
                    <span class="field-label">Data da venda</span>
                    <span class="field-value"><?= e($dataVenda) ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Número do registro</span>
                    <span class="field-value">#<?= e($numeroVenda) ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Loja</span>
                    <span class="field-value"><?= e($loja) ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Endereço da loja</span>
                    <span class="field-value">
                        <?= e($enderecoLoja ?: 'Não informado') ?>
                    </span>
                </div>

            </div>

        </section>

        <section class="section">

            <h2 class="section-title">Dados do veículo</h2>

            <div class="vehicle">
                <span class="field-label">Veículo comercializado</span>
                <span class="field-value"><?= e($veiculo) ?></span>
            </div>

        </section>

        <section class="section">

            <h2 class="section-title">Dados do cliente</h2>

            <div class="grid">

                <div class="field">
                    <span class="field-label">Nome do cliente</span>
                    <span class="field-value"><?= e($cliente) ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Telefone</span>
                    <span class="field-value"><?= e($telefoneCliente) ?></span>
                </div>

                <div class="field">
                    <span class="field-label">E-mail</span>
                    <span class="field-value"><?= e($emailCliente) ?></span>
                </div>

                <div class="field">
                    <span class="field-label">Forma de pagamento</span>
                    <span class="field-value"><?= e($pagamento) ?></span>
                </div>

            </div>

        </section>

        <section class="section">

            <h2 class="section-title">Responsável pela venda</h2>

            <div class="field">
                <span class="field-label">Vendedor</span>
                <span class="field-value"><?= e($vendedor) ?></span>
            </div>

        </section>

        <div class="total-box">

            <div class="total-label">Valor registrado da venda</div>

            <div class="total-value">
                <?= e(moeda($valorTotal)) ?>
            </div>

        </div>

        <div class="notice">
            <strong>Documento demonstrativo:</strong>
            este recibo apresenta os dados registrados no AutoManagerPro
            para fins de demonstração do sistema. Não possui validade fiscal
            e não substitui documento fiscal ou comprovante oficial de pagamento.
            Campos sem informação cadastrada são identificados como
            "Não informado".
        </div>

        <div class="signatures">

            <div class="signature">
                Assinatura do responsável pela venda
            </div>

            <div class="signature">
                Assinatura do cliente
            </div>

        </div>

        <footer class="footer">
            <strong>AutoManagerPro</strong><br>
            Sistema demonstrativo de gestão de concessionária.<br>
            Documento gerado em <?= date('d/m/Y H:i') ?>.
        </footer>

    </main>

</body>
</html>



