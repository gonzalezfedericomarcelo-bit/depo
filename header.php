<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
$nombre_usuario_global = $_SESSION['user_name'] ?? 'Usuario';
$roles_global = $_SESSION['user_roles'] ?? [];
$rol_principal_global = !empty($roles_global) ? $roles_global[0] : 'Sin Rol';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policlínica ACTIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar { min-width: 260px; max-width: 260px; min-height: 100vh; background: linear-gradient(180deg, #1b263b 0%, #0d1b2a 100%); color: #fff; transition: all 0.3s; }
        .sidebar .brand { padding: 20px; text-align: center; font-weight: 700; font-size: 1.2rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar ul li a { padding: 12px 20px; display: block; color: rgba(255,255,255,0.7); text-decoration: none; border-left: 4px solid transparent; }
        .sidebar ul li a:hover, .sidebar ul li a.active { color: #fff; background: rgba(255,255,255,0.1); border-left-color: #3282b8; }
        .sidebar .section-title { padding: 15px 20px 5px; font-size: 0.7rem; text-transform: uppercase; color: rgba(255,255,255,0.4); font-weight: 700; }

        /* Navbar & Menu Fix */
        .content-wrapper { width: 100%; display: flex; flex-direction: column; min-height: 100vh; overflow: visible !important; }
        .top-navbar { background: #fff; height: 70px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); padding: 0 30px; z-index: 1050 !important; overflow: visible !important; }
        .dropdown-menu { z-index: 99999 !important; position: absolute !important; }

        @media (max-width: 768px) {
            .sidebar { margin-left: -260px; }
            .sidebar.active { margin-left: 0; }
            .top-navbar { padding: 0 15px; }
        }
    </style>
</head>
<body>
    <div class="d-flex">