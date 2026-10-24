<?php
// Variables de Entorno obligatorias (Requisito PaaS)
$db_url = getenv('DATABASE_URL');

$pdo = null;
$error_msg = null;

try {
    if (!empty($db_url)) {
        // Conexión mediante cadena de conexión PostgreSQL de Render
        $db_parts = parse_url($db_url);
        $db_host = $db_parts['host'] ?? 'localhost';
        $db_port = $db_parts['port'] ?? '5432';
        $db_user = $db_parts['user'] ?? '';
        $db_pass = $db_parts['pass'] ?? '';
        $db_name = ltrim($db_parts['path'] ?? '', '/');

        $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name};sslmode=require";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } else {
        // Conexión por variables individuales (MySQL/PostgreSQL)
        $db_host = getenv('DB_HOST') ?: 'localhost';
        $db_port = getenv('DB_PORT') ?: '3306';
        $db_name = getenv('DB_NAME') ?: 'paas_db';
        $db_user = getenv('DB_USER') ?: 'root';
        $db_pass = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    // Creación automática de la tabla si no existe
    $pdo->exec("CREATE TABLE IF NOT EXISTS productos (
        id SERIAL PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        precio DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Crear Registro (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $precio = floatval($_POST['precio'] ?? 0);
        if (!empty($nombre) && $precio > 0) {
            $stmt = $pdo->prepare("INSERT INTO productos (nombre, precio) VALUES (?, ?)");
            $stmt->execute([$nombre, $precio]);
        }
        header("Location: index.php");
        exit;
    }

    // Eliminar Registro (GET)
    if (isset($_GET['eliminar'])) {
        $id = intval($_GET['eliminar']);
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php");
        exit;
    }

    // Listar Registros (SELECT)
    $productos = $pdo->query("SELECT * FROM productos ORDER BY id DESC")->fetchAll();

} catch (PDOException $e) {
    $error_msg = $e->getMessage();
    $productos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PaaS App 1 - Stack PHP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body bg-primary text-white rounded-top p-4">
                        <h2 class="h4 mb-1">Módulo CRUD: Gestión de Productos</h2>
                        <small>Stack: PHP Nativo + Base de Datos Administrada | Entorno PaaS</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error_msg): ?>
                            <div class="alert alert-warning border-0 shadow-sm" role="alert">
                                <h6 class="alert-heading fw-bold">Esperando conexión a Base de Datos PaaS</h6>
                                <p class="small mb-0">Define la variable de entorno <code>DATABASE_URL</code> en el panel del PaaS.</p>
                                <hr>
                                <p class="small text-muted mb-0">Detalle técnico: <?= htmlspecialchars($error_msg) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success border-0 py-2 small d-flex align-items-center">
                                <span class="badge bg-success me-2">Conectado</span>
                                Base de datos relacional conectada exitosamente vía variables de entorno.
                            </div>

                            <form method="POST" action="index.php" class="row g-3 mb-4">
                                <input type="hidden" name="accion" value="crear">
                                <div class="col-md-6">
                                    <input type="text" name="nombre" class="form-control" placeholder="Nombre del producto" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="number" step="0.01" name="precio" class="form-control" placeholder="Precio ($)" required>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Agregar</button>
                                </div>
                            </form>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Producto</th>
                                            <th>Precio</th>
                                            <th>Fecha Registro</th>
                                            <th class="text-end">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($productos)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">No hay registros almacenados. ¡Agrega el primero!</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($productos as $p): ?>
                                                <tr>
                                                    <td><?= $p['id'] ?></td>
                                                    <td class="fw-semibold"><?= htmlspecialchars($p['nombre']) ?></td>
                                                    <td>$<?= number_format($p['precio'], 2) ?></td>
                                                    <td class="small text-muted"><?= $p['created_at'] ?></td>
                                                    <td class="text-end">
                                                        <a href="index.php?eliminar=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este registro?')">Eliminar</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
