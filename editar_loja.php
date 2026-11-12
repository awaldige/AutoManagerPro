<?php 
include('conexao.php'); 

$id = $_GET['id'];
$res = $conn->query("SELECT * FROM Loja WHERE id_loja = $id");
$loja = $res->fetch_assoc();

if (isset($_POST['update_loja'])) {
    $nome = $conn->real_escape_string($_POST['nome']);
    $conn->query("UPDATE Loja SET nome = '$nome' WHERE id_loja = $id");
    header("Location: gerenciar_lojas.php?msg=editada");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow border-0 mx-auto" style="max-width: 400px;">
            <div class="card-header bg-dark text-white">Editar Loja</div>
            <div class="card-body">
                <form method="POST">
                    <input type="text" name="nome" class="form-control mb-3" value="<?= $loja['nome'] ?>" required>
                    <button type="submit" name="update_loja" class="btn btn-primary w-100">Atualizar Nome</button>
                    <a href="gerenciar_lojas.php" class="btn btn-link w-100 mt-2 text-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

