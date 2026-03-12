<?php
session_start();
require '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent') {
    header('Location: ../login.php');
    exit;
}

$error = null;
$success = null;

try {
    $stmt = $pdo->prepare("SELECT agency_id FROM agency_agents WHERE agent_id = :agent_id LIMIT 1");
    $stmt->execute(['agent_id' => $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Erreur: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $logo = 'default-agency.png'; 

    // Validation
    if (!$name) {
        $error = "Le nom de l'agence est requis";
    } elseif (!$city) {
        $error = "La ville est requise";
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide";
    } elseif ($phone && !preg_match('/^[0-9\s\-\+\(\)]{7,}$/', $phone)) {
        $error = "Le numéro de téléphone n'est pas valide";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO agencies (name, city, address, phone, email, website, logo, description, is_active)
                 VALUES (:name, :city, :address, :phone, :email, :website, :logo, :description, 1)"
            );
            
            $stmt->execute([
                'name' => $name,
                'city' => $city,
                'address' => $address ?: null,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'website' => $website ?: null,
                'logo' => $logo,
                'description' => $description ?: null
            ]);

            $agency_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO agency_agents (agency_id, agent_id) VALUES (:agency_id, :agent_id)"
            );
            
            $stmt->execute([
                'agency_id' => $agency_id,
                'agent_id' => $_SESSION['user_id']
            ]);

            $success = "Agence créée avec succès!";
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $error = "Erreur lors de la création: " . $e->getMessage();
        }
    }
}

require '../includes/header.php';
?>

<div class="container">
    <h1>Créer une nouvelle agence</h1>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" class="form-section">
        <div class="form-group">
            <label for="name">Nom de l'agence *</label>
            <input type="text" id="name" name="name" placeholder="Nom de l'agence" required>
        </div>

        <div class="form-group">
            <label for="city">Ville *</label>
            <input type="text" id="city" name="city" placeholder="Ville" required>
        </div>

        <div class="form-group">
            <label for="address">Adresse</label>
            <input type="text" id="address" name="address" placeholder="Adresse complète">
        </div>

        <div class="form-group">
            <label for="phone">Téléphone</label>
            <input type="tel" id="phone" name="phone" placeholder="Numéro de téléphone">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Email de l'agence">
        </div>

        <div class="form-group">
            <label for="website">Site web</label>
            <input type="url" id="website" name="website" placeholder="https://www.example.com">
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Description de l'agence" rows="5"></textarea>
        </div>

        <button type="submit" class="btn">Créer l'agence</button>
    </form>

    <a href="index.php" class="btn btn-secondary">Retour</a>
</div>

<?php require '../includes/footer.php'; ?>
