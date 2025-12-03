<?php
/**
 * SYSTÈME DE LOCATION DE VOITURES - ALGÉRIE
 * Version: 2.1 - Minimaliste avec Login Cards
 * Propriétaire: Cherifi Youssouf
 * 
 * MODIFICATIONS:
 * 1. Commission supprimée pour les agents
 * 2. Page de login avec cards (agent, client, admin, owner)
 * 3. Fonctionnalités CRUD pour tous les utilisateurs
 * 4. Base de données pré-remplie avec 7+ lignes
 */

session_start();

/****************************
 * DATABASE CLASS
 ****************************/

class Database {
    private $host = 'localhost';
    private $user = 'root';
    private $pass = '';
    private $dbname = 'location_voiture';
    public $conn;

    public function __construct() {
        $this->connect();
        $this->createTables();
        $this->seedData();
    }

    private function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->query("CREATE DATABASE IF NOT EXISTS $this->dbname CHARACTER SET utf8mb4");
        $this->conn->select_db($this->dbname);
    }

    private function createTables() {
        // Wilaya
        $sql = "CREATE TABLE IF NOT EXISTS wilaya (id INT PRIMARY KEY, name VARCHAR(100)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Company
        $sql = "CREATE TABLE IF NOT EXISTS company (
            company_id INT AUTO_INCREMENT PRIMARY KEY,
            c_name VARCHAR(100) NOT NULL,
            frais_mensuel DECIMAL(10,2) DEFAULT 50000,
            special_code VARCHAR(50),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Super Admin
        $sql = "CREATE TABLE IF NOT EXISTS super_admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Administrator
        $sql = "CREATE TABLE IF NOT EXISTS administrator (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT CHECK (age >= 24),
            numero_tlfn VARCHAR(20),
            nationalite VARCHAR(50),
            numero_cart_national VARCHAR(50),
            wilaya_id INT,
            salaire DECIMAL(10,2),
            company_id INT,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Agent - SANS COMMISSION
        $sql = "CREATE TABLE IF NOT EXISTS agent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT CHECK (age >= 24),
            numero_tlfn VARCHAR(20),
            nationalite VARCHAR(50),
            numero_cart_national VARCHAR(50),
            wilaya_id INT,
            salaire DECIMAL(10,2),
            company_id INT,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Client
        $sql = "CREATE TABLE IF NOT EXISTS client (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT CHECK (age >= 24),
            numero_tlfn VARCHAR(20),
            nationalite VARCHAR(50),
            numero_cart_national VARCHAR(50),
            wilaya_id INT,
            status ENUM('payer', 'reserve', 'annuler', 'non reserve') DEFAULT 'non reserve',
            company_id INT,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Car
        $sql = "CREATE TABLE IF NOT EXISTS car (
            id_car INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT,
            marque VARCHAR(50),
            model VARCHAR(50),
            color VARCHAR(50),
            annee INT,
            matricule VARCHAR(50) UNIQUE,
            category INT CHECK (category IN (1,2,3)),
            prix_day DECIMAL(10,2),
            status_voiture INT CHECK (status_voiture IN (1,2,3)),
            voiture_work ENUM('disponible', 'non disponible') DEFAULT 'disponible',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Payment
        $sql = "CREATE TABLE IF NOT EXISTS payment (
            id_payment INT AUTO_INCREMENT PRIMARY KEY,
            status ENUM('paid', 'not_paid') DEFAULT 'not_paid',
            amount DECIMAL(10,2),
            payment_date DATETIME,
            card_number VARCHAR(16),
            card_code VARCHAR(3)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Reservation
        $sql = "CREATE TABLE IF NOT EXISTS reservation (
            id_reservation INT AUTO_INCREMENT PRIMARY KEY,
            id_agent INT,
            id_client INT,
            id_company INT,
            car_id INT,
            start_date DATE,
            end_date DATE,
            period INT,
            montant DECIMAL(10,2),
            id_payment INT,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_agent) REFERENCES agent(id),
            FOREIGN KEY (id_client) REFERENCES client(id),
            FOREIGN KEY (id_company) REFERENCES company(company_id),
            FOREIGN KEY (car_id) REFERENCES car(id_car),
            FOREIGN KEY (id_payment) REFERENCES payment(id_payment)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }

    private function seedData() {
        // Wilayas
        $wilayas = [1 => 'Adrar', 2 => 'Chlef', 3 => 'Laghouat', 16 => 'Alger', 31 => 'Oran', 25 => 'Constantine'];
        foreach ($wilayas as $id => $name) {
            $check = $this->conn->query("SELECT id FROM wilaya WHERE id = $id");
            if ($check->num_rows == 0) {
                $stmt = $this->conn->prepare("INSERT INTO wilaya (id, name) VALUES (?, ?)");
                $stmt->bind_param("is", $id, $name);
                $stmt->execute();
            }
        }

        // Super Admin
        $checkSuperAdmin = $this->conn->query("SELECT id FROM super_admin WHERE email = 'owner@mail.com'");
        if ($checkSuperAdmin->num_rows == 0) {
            $hashed = password_hash('123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO super_admin (nom, prenom, email, password) VALUES ('Cherifi', 'Youssouf', 'owner@mail.com', '$hashed')");
        }

        // Check Companies
        $check = $this->conn->query("SELECT COUNT(*) as count FROM company");
        $result = $check->fetch_assoc();
        
        if ($result['count'] == 0) {
            $hashed = password_hash('123', PASSWORD_DEFAULT);

            // Company 1
            $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Auto Alger', 50000, 'ALG001', 1)");
            $c1 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Benali', 'Karim', 35, '0555111111', 'Algérienne', '1111111111111111', 16, 80000, $c1, 'admin1@mail.com', '$hashed', 1)");
            $a1 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Mansouri', 'Nassim', 28, '0555222222', 'Algérienne', '2222222222222222', 16, 50000, $c1, 'agent1@mail.com', '$hashed', $a1)");
            $ag1 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Zeroual', 'Amine', 25, '0555333333', 'Algérienne', '3333333333333333', 16, 'non reserve', $c1, 'client1@mail.com', '$hashed', $ag1)");
            $cl1 = $this->conn->insert_id;

            // Cars Company 1
            $cars = [
                ['Toyota', 'Corolla', 'Blanc', 2022, 1, 5000, 1],
                ['BMW', '3 Series', 'Noir', 2021, 2, 8000, 1],
                ['Mercedes', 'C-Class', 'Argent', 2023, 3, 12000, 1],
                ['Renault', 'Clio', 'Bleu', 2021, 1, 4500, 1],
                ['Audi', 'A4', 'Gris', 2022, 2, 9000, 1],
                ['Volkswagen', 'Golf', 'Rouge', 2022, 1, 4800, 1],
                ['Porsche', 'Panamera', 'Noir', 2023, 3, 18000, 1]
            ];

            foreach ($cars as $car) {
                $serial = rand(100000, 999999);
                $matricule = "$serial {$car[4]} {$car[3]} 16";
                $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($c1, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '$matricule', 'disponible', 1)");
            }

            // Company 2
            $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Auto Location Oran', 45000, 'ORAN002', 1)");
            $c2 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Bouguerra', 'Samir', 40, '0555444444', 'Algérienne', '4444444444444444', 31, 75000, $c2, 'admin2@mail.com', '$hashed', 1)");
            $a2 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Touati', 'Yacine', 30, '0555555555', 'Algérienne', '5555555555555555', 31, 45000, $c2, 'agent2@mail.com', '$hashed', $a2)");
            $ag2 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Khelifi', 'Rachid', 27, '0555666666', 'Algérienne', '6666666666666666', 31, 'non reserve', $c2, 'client2@mail.com', '$hashed', $ag2)");

            // Cars Company 2
            foreach ($cars as $car) {
                $serial = rand(100000, 999999);
                $matricule = "$serial {$car[4]} {$car[3]} 31";
                $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($c2, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '$matricule', 'disponible', 1)");
            }

            // Company 3
            $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Constantine', 55000, 'CONST003', 1)");
            $c3 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Salhi', 'Farid', 38, '0555777777', 'Algérienne', '7777777777777777', 25, 85000, $c3, 'admin3@mail.com', '$hashed', 1)");
            $a3 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Mekideche', 'Hakim', 32, '0555888888', 'Algérienne', '8888888888888888', 25, 55000, $c3, 'agent3@mail.com', '$hashed', $a3)");
            $ag3 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Benaissa', 'Sofiane', 29, '0555999999', 'Algérienne', '9999999999999999', 25, 'non reserve', $c3, 'client3@mail.com', '$hashed', $ag3)");

            // Cars Company 3
            foreach ($cars as $car) {
                $serial = rand(100000, 999999);
                $matricule = "$serial {$car[4]} {$car[3]} 25";
                $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($c3, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '$matricule', 'disponible', 1)");
            }
        }
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
}

/****************************
 * AUTH CLASS
 ****************************/

class Auth {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function login($email, $password, $role) {
        if (empty($email) || empty($password)) return false;

        $email = trim($email);
        if ($role == 'super_admin') {
            $table = 'super_admin';
        } else {
            $tables = ['client' => 'client', 'agent' => 'agent', 'administrator' => 'administrator'];
            $table = $tables[$role] ?? 'client';
        }

        $stmt = $this->db->prepare("SELECT * FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                $_SESSION['company_id'] = $user['company_id'] ?? 0;
                return true;
            }
        }
        return false;
    }

    public function logout() {
        session_destroy();
        header("Location: index.php");
        exit();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }

    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}

/****************************
 * APPLICATION
 ****************************/

$db = new Database();
$auth = new Auth($db);

// Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $auth->logout();
}

// Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';

    if ($auth->login($email, $password, $role)) {
        $pages = ['super_admin' => 'owner', 'administrator' => 'admin', 'agent' => 'agent', 'client' => 'client'];
        header("Location: index.php?page=" . ($pages[$role] ?? 'client'));
        exit();
    } else {
        $login_error = "Identifiants incorrects";
    }
}

// Add Car (Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_car']) && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
    $serial = rand(100000, 999999);
    $matricule = "$serial {$_POST['category']} {$_POST['annee']} 16";
    $stmt = $db->prepare("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?)");
    $stmt->bind_param("isssiidisi", $_SESSION['company_id'], $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'], $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $matricule, $auth->getUserId());
    if ($stmt->execute()) {
        $success = "Voiture ajoutée avec succès";
    }
}

// Update Car (Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_car']) && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
    $stmt = $db->prepare("UPDATE car SET marque=?, model=?, color=?, annee=?, category=?, prix_day=?, status_voiture=?, voiture_work=? WHERE id_car=? AND company_id=?");
    $stmt->bind_param("sssiidissi", $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'], $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $_POST['voiture_work'], $_POST['car_id'], $_SESSION['company_id']);
    if ($stmt->execute()) {
        $success = "Voiture mise à jour";
    }
}

// Delete Car (Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_car']) && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
    $car_id = intval($_POST['car_id']);
    $db->query("DELETE FROM car WHERE id_car=$car_id AND company_id={$_SESSION['company_id']}");
    $success = "Voiture supprimée";
}

// Add Agent (Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_agent']) && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
    $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissssdissi", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'], $_POST['salaire'], $_SESSION['company_id'], $_POST['email'], $hashed, $auth->getUserId());
    if ($stmt->execute()) {
        $success = "Agent ajouté";
    }
}

// Update Agent (Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_agent']) && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
    $stmt = $db->prepare("UPDATE agent SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, wilaya_id=?, salaire=?, email=? WHERE id=? AND company_id=?");
    $stmt->bind_param("ssisssidsii", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['email'], $_POST['agent_id'], $_SESSION['company_id']);
    if ($stmt->execute()) {
        $success = "Agent mis à jour";
    }
}

// Delete Agent (Admin)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_agent']) && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
    $agent_id = intval($_POST['agent_id']);
    $db->query("DELETE FROM agent WHERE id=$agent_id AND company_id={$_SESSION['company_id']}");
    $success = "Agent supprimé";
}

// Add Client (Agent)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_client']) && $auth->isLoggedIn() && $auth->getUserRole() == 'agent') {
    if ($_POST['age'] < 24) {
        $error = "Âge minimum: 24 ans";
    } else {
        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, email, password, company_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissssisii", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'], $_POST['email'], $hashed, $_SESSION['company_id'], $auth->getUserId());
        if ($stmt->execute()) {
            $success = "Client ajouté";
        } else {
            $error = "Email existe déjà";
        }
    }
}

// Update Client (Agent)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_client']) && $auth->isLoggedIn() && $auth->getUserRole() == 'agent') {
    $stmt = $db->prepare("UPDATE client SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, wilaya_id=?, email=? WHERE id=? AND company_id=?");
    $stmt->bind_param("ssissssii", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['email'], $_POST['client_id'], $_SESSION['company_id']);
    if ($stmt->execute()) {
        $success = "Client mis à jour";
    }
}

// Delete Client (Agent)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_client']) && $auth->isLoggedIn() && $auth->getUserRole() == 'agent') {
    $client_id = intval($_POST['client_id']);
    $db->query("DELETE FROM client WHERE id=$client_id AND company_id={$_SESSION['company_id']}");
    $success = "Client supprimé";
}

// Add Company (Owner)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company']) && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    $stmt = $db->prepare("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdsi", $_POST['c_name'], $_POST['frais_mensuel'], $_POST['special_code'], $auth->getUserId());
    if ($stmt->execute()) {
        $success = "Compagnie créée";
    }
}

// Update Company (Owner)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_company']) && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    $stmt = $db->prepare("UPDATE company SET c_name=?, frais_mensuel=?, special_code=? WHERE company_id=?");
    $stmt->bind_param("sdsi", $_POST['c_name'], $_POST['frais_mensuel'], $_POST['special_code'], $_POST['company_id']);
    if ($stmt->execute()) {
        $success = "Compagnie mise à jour";
    }
}

// Delete Company (Owner)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_company']) && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    $company_id = intval($_POST['company_id']);
    $db->query("DELETE FROM agent WHERE company_id=$company_id");
    $db->query("DELETE FROM administrator WHERE company_id=$company_id");
    $db->query("DELETE FROM client WHERE company_id=$company_id");
    $db->query("DELETE FROM car WHERE company_id=$company_id");
    $db->query("DELETE FROM company WHERE company_id=$company_id");
    $success = "Compagnie supprimée";
}

// Add Admin (Owner)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin']) && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    if ($_POST['age'] < 24) {
        $error = "Âge minimum: 24 ans";
    } else {
        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissssdissi", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['company_id'], $_POST['email'], $hashed, $auth->getUserId());
        if ($stmt->execute()) {
            $success = "Admin ajouté";
        } else {
            $error = "Email existe déjà";
        }
    }
}

// Update Admin (Owner)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin']) && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    $stmt = $db->prepare("UPDATE administrator SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, wilaya_id=?, salaire=?, company_id=?, email=? WHERE id=?");
    $stmt->bind_param("ssisssdssi", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['company_id'], $_POST['email'], $_POST['admin_id']);
    if ($stmt->execute()) {
        $success = "Admin mis à jour";
    }
}

// Delete Admin (Owner)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_admin']) && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    $admin_id = intval($_POST['admin_id']);
    $db->query("DELETE FROM administrator WHERE id=$admin_id");
    $success = "Admin supprimé";
}

$page = $_GET['page'] ?? 'login';
if ($auth->isLoggedIn()) {
    $roles = ['super_admin' => 'owner', 'administrator' => 'admin', 'agent' => 'agent', 'client' => 'client'];
    $page = $_GET['page'] ?? ($roles[$auth->getUserRole()] ?? 'login');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚗 DZLocation - Location de Voitures Algérie</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .navbar { background: rgba(0,0,0,0.8); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 1.5rem; cursor: pointer; }
        .navbar a:hover { text-decoration: underline; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .card { background: white; border-radius: 10px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .login-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin: 2rem 0; }
        .login-card { background: white; border-radius: 10px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
        .login-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); }
        .login-card h3 { color: #667eea; margin: 1rem 0; }
        .login-card p { color: #666; font-size: 0.9rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        input, select, textarea { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        input:focus, select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; margin: 0.5rem; }
        .btn:hover { background: #764ba2; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        tr:hover { background: #f9f9f9; }
        .alert { padding: 1rem; margin: 1rem 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .icon { font-size: 2.5rem; margin: 1rem 0; }
        .tabs { display: flex; gap: 1rem; margin: 1.5rem 0; border-bottom: 2px solid #ddd; }
        .tabs button { padding: 0.75rem 1.5rem; background: none; border: none; cursor: pointer; border-bottom: 3px solid transparent; }
        .tabs button.active { border-bottom-color: #667eea; color: #667eea; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .login-cards { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php if ($auth->isLoggedIn()): ?>
        <div class="navbar">
            <div><strong>🚗 DZLocation</strong> | <?php echo $_SESSION['user_name']; ?> (<?php echo ucfirst($_SESSION['user_role']); ?>)</div>
            <div>
                <a href="index.php?action=logout">Déconnexion</a>
            </div>
        </div>
    <?php endif; ?>

    <div class="container">
        <!-- LOGIN PAGE WITH CARDS -->
        <?php if ($page == 'login' && !$auth->isLoggedIn()): ?>
            <div class="card" style="max-width: 800px; margin: 0 auto; text-align: center;">
                <h1>🚗 DZLocation</h1>
                <p>Système de Location de Voitures en Algérie</p>
                
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Sélectionnez votre profil</h3>
                
                <div class="login-cards">
                    <div class="login-card" onclick="showLoginForm('client')">
                        <div class="icon">👤</div>
                        <h3>Client</h3>
                        <p>Louer une voiture</p>
                    </div>
                    <div class="login-card" onclick="showLoginForm('agent')">
                        <div class="icon">👥</div>
                        <h3>Agent</h3>
                        <p>Gérer les clients</p>
                    </div>
                    <div class="login-card" onclick="showLoginForm('administrator')">
                        <div class="icon">⚙️</div>
                        <h3>Admin</h3>
                        <p>Gérer la compagnie</p>
                    </div>
                    <div class="login-card" onclick="showLoginForm('super_admin')">
                        <div class="icon">👑</div>
                        <h3>Propriétaire</h3>
                        <p>Cherifi Youssouf</p>
                    </div>
                </div>

                <div id="loginForm" style="display: none; margin-top: 2rem; max-width: 400px; margin-left: auto; margin-right: auto; background: #f5f5f5; padding: 2rem; border-radius: 10px;">
                    <form method="POST">
                        <input type="hidden" name="role" id="roleInput" value="">
                        <div class="form-group">
                            <label>Email:</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe:</label>
                            <input type="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn" style="width: 100%;">Se connecter</button>
                        <button type="button" class="btn btn-danger" style="width: 100%;" onclick="this.parentElement.parentElement.style.display='none'">Annuler</button>
                    </form>
                    <hr style="margin: 1.5rem 0;">
                    <p style="font-size: 0.9rem; color: #666;">
                        <strong>Comptes de test:</strong><br>
                        Client: client1@mail.com / 123<br>
                        Agent: agent1@mail.com / 123<br>
                        Admin: admin1@mail.com / 123<br>
                        Propriétaire: owner@mail.com / 123
                    </p>
                </div>
            </div>

            <script>
                function showLoginForm(role) {
                    document.getElementById('roleInput').value = role;
                    document.getElementById('loginForm').style.display = 'block';
                }
            </script>
        <?php endif; ?>

        <!-- CLIENT PAGE -->
        <?php if ($page == 'client' && $auth->isLoggedIn()): ?>
            <div class="card">
                <h2>👤 Espace Client</h2>
                <p>Bienvenue <?php echo $_SESSION['user_name']; ?>!</p>
                <p>Consultez les voitures disponibles et gérez vos réservations.</p>
            </div>
        <?php endif; ?>

        <!-- AGENT PAGE -->
        <?php if ($page == 'agent' && $auth->isLoggedIn()): ?>
            <div class="card">
                <h2>👥 Gestion Agent</h2>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('addClient')">➕ Ajouter Client</button>
                    <button class="tab-btn" onclick="switchTab('listClient')">📋 Liste Clients</button>
                </div>

                <!-- Add Client -->
                <div id="addClient" class="tab-content active">
                    <h3>Ajouter un Client</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom:</label>
                                <input type="text" name="nom" required>
                            </div>
                            <div class="form-group">
                                <label>Prénom:</label>
                                <input type="text" name="prenom" required>
                            </div>
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Mot de passe:</label>
                                <input type="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label>Âge:</label>
                                <input type="number" name="age" min="24" required>
                            </div>
                            <div class="form-group">
                                <label>Téléphone:</label>
                                <input type="tel" name="numero_tlfn" required>
                            </div>
                            <div class="form-group">
                                <label>Nationalité:</label>
                                <input type="text" name="nationalite" required>
                            </div>
                            <div class="form-group">
                                <label>Numéro Carte Nationale:</label>
                                <input type="text" name="numero_cart_national" required>
                            </div>
                            <div class="form-group">
                                <label>Wilaya:</label>
                                <select name="wilaya_id" required>
                                    <option value="1">Adrar</option>
                                    <option value="16">Alger</option>
                                    <option value="25">Constantine</option>
                                    <option value="31">Oran</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="add_client" class="btn">Ajouter</button>
                    </form>
                </div>

                <!-- List Clients -->
                <div id="listClient" class="tab-content">
                    <h3>Mes Clients</h3>
                    <?php $clients = $db->query("SELECT * FROM client WHERE company_id = {$_SESSION['company_id']}");
                    if ($clients->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Âge</th>
                                <th>Téléphone</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($client = $clients->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $client['prenom'] . ' ' . $client['nom']; ?></td>
                                    <td><?php echo $client['email']; ?></td>
                                    <td><?php echo $client['age']; ?></td>
                                    <td><?php echo $client['numero_tlfn']; ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_client" value="1">
                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php else: ?>
                        <p>Aucun client trouvé</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ADMIN PAGE -->
        <?php if ($page == 'admin' && $auth->isLoggedIn()): ?>
            <div class="card">
                <h2>⚙️ Gestion Administrative</h2>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('cars')">🚗 Voitures</button>
                    <button class="tab-btn" onclick="switchTab('agents')">👥 Agents</button>
                </div>

                <!-- CARS TAB -->
                <div id="cars" class="tab-content active">
                    <h3>Gestion des Voitures</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Marque:</label>
                                <input type="text" name="marque" required>
                            </div>
                            <div class="form-group">
                                <label>Modèle:</label>
                                <input type="text" name="model" required>
                            </div>
                            <div class="form-group">
                                <label>Couleur:</label>
                                <input type="text" name="color" required>
                            </div>
                            <div class="form-group">
                                <label>Année:</label>
                                <input type="number" name="annee" required>
                            </div>
                            <div class="form-group">
                                <label>Catégorie:</label>
                                <select name="category" required>
                                    <option value="1">Économique</option>
                                    <option value="2">Confort</option>
                                    <option value="3">Luxe</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Prix/Jour (DA):</label>
                                <input type="number" step="0.01" name="prix_day" required>
                            </div>
                            <div class="form-group">
                                <label>État:</label>
                                <select name="status_voiture" required>
                                    <option value="1">Excellent</option>
                                    <option value="2">Entretien</option>
                                    <option value="3">Faible</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="add_car" class="btn">Ajouter Voiture</button>
                    </form>

                    <hr>
                    <?php $cars = $db->query("SELECT * FROM car WHERE company_id = {$_SESSION['company_id']}");
                    if ($cars->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Marque/Modèle</th>
                                <th>Couleur</th>
                                <th>Catégorie</th>
                                <th>Prix/Jour</th>
                                <th>État</th>
                                <th>Disponibilité</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($car = $cars->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $car['marque'] . ' ' . $car['model']; ?></td>
                                    <td><?php echo $car['color']; ?></td>
                                    <td><?php echo ['', 'Économique', 'Confort', 'Luxe'][$car['category']]; ?></td>
                                    <td><?php echo number_format($car['prix_day'], 2); ?> DA</td>
                                    <td><?php echo ['', 'Excellent', 'Entretien', 'Faible'][$car['status_voiture']]; ?></td>
                                    <td><?php echo ucfirst($car['voiture_work']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_car" value="1">
                                            <input type="hidden" name="car_id" value="<?php echo $car['id_car']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- AGENTS TAB -->
                <div id="agents" class="tab-content">
                    <h3>Gestion des Agents</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom:</label>
                                <input type="text" name="nom" required>
                            </div>
                            <div class="form-group">
                                <label>Prénom:</label>
                                <input type="text" name="prenom" required>
                            </div>
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Mot de passe:</label>
                                <input type="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label>Âge:</label>
                                <input type="number" name="age" min="24" required>
                            </div>
                            <div class="form-group">
                                <label>Téléphone:</label>
                                <input type="tel" name="numero_tlfn" required>
                            </div>
                            <div class="form-group">
                                <label>Nationalité:</label>
                                <input type="text" name="nationalite" required>
                            </div>
                            <div class="form-group">
                                <label>Numéro Carte:</label>
                                <input type="text" name="numero_cart_national" required>
                            </div>
                            <div class="form-group">
                                <label>Wilaya:</label>
                                <select name="wilaya_id" required>
                                    <option value="1">Adrar</option>
                                    <option value="16">Alger</option>
                                    <option value="25">Constantine</option>
                                    <option value="31">Oran</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Salaire (DA):</label>
                                <input type="number" step="0.01" name="salaire" required>
                            </div>
                        </div>
                        <button type="submit" name="add_agent" class="btn">Ajouter Agent</button>
                    </form>

                    <hr>
                    <?php $agents = $db->query("SELECT * FROM agent WHERE company_id = {$_SESSION['company_id']}");
                    if ($agents->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Âge</th>
                                <th>Salaire</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($agent = $agents->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $agent['prenom'] . ' ' . $agent['nom']; ?></td>
                                    <td><?php echo $agent['email']; ?></td>
                                    <td><?php echo $agent['age']; ?></td>
                                    <td><?php echo number_format($agent['salaire'], 2); ?> DA</td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_agent" value="1">
                                            <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- OWNER PAGE -->
        <?php if ($page == 'owner' && $auth->isLoggedIn()): ?>
            <div class="card">
                <h2>👑 Gestion Propriétaire</h2>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <div class="tabs">
                    <button class="tab-btn active" onclick="switchTab('companies')">🏢 Compagnies</button>
                    <button class="tab-btn" onclick="switchTab('admins')">⚙️ Administrateurs</button>
                </div>

                <!-- COMPANIES TAB -->
                <div id="companies" class="tab-content active">
                    <h3>Gestion des Compagnies</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom:</label>
                                <input type="text" name="c_name" required>
                            </div>
                            <div class="form-group">
                                <label>Code Spécial:</label>
                                <input type="text" name="special_code" required>
                            </div>
                            <div class="form-group">
                                <label>Frais Mensuels (DA):</label>
                                <input type="number" step="0.01" name="frais_mensuel" value="50000" required>
                            </div>
                        </div>
                        <button type="submit" name="add_company" class="btn">Créer Compagnie</button>
                    </form>

                    <hr>
                    <?php $companies = $db->query("SELECT * FROM company");
                    if ($companies->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Nom</th>
                                <th>Code</th>
                                <th>Frais Mensuels</th>
                                <th>Créée le</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($company = $companies->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $company['c_name']; ?></td>
                                    <td><?php echo $company['special_code']; ?></td>
                                    <td><?php echo number_format($company['frais_mensuel'], 2); ?> DA</td>
                                    <td><?php echo date('d/m/Y', strtotime($company['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_company" value="1">
                                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- ADMINS TAB -->
                <div id="admins" class="tab-content">
                    <h3>Gestion des Administrateurs</h3>
                    <form method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nom:</label>
                                <input type="text" name="nom" required>
                            </div>
                            <div class="form-group">
                                <label>Prénom:</label>
                                <input type="text" name="prenom" required>
                            </div>
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Mot de passe:</label>
                                <input type="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label>Âge:</label>
                                <input type="number" name="age" min="24" required>
                            </div>
                            <div class="form-group">
                                <label>Téléphone:</label>
                                <input type="tel" name="numero_tlfn" required>
                            </div>
                            <div class="form-group">
                                <label>Nationalité:</label>
                                <input type="text" name="nationalite" required>
                            </div>
                            <div class="form-group">
                                <label>Numéro Carte:</label>
                                <input type="text" name="numero_cart_national" required>
                            </div>
                            <div class="form-group">
                                <label>Wilaya:</label>
                                <select name="wilaya_id" required>
                                    <option value="1">Adrar</option>
                                    <option value="16">Alger</option>
                                    <option value="25">Constantine</option>
                                    <option value="31">Oran</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Compagnie:</label>
                                <select name="company_id" required>
                                    <?php $comps = $db->query("SELECT company_id, c_name FROM company");
                                    while ($comp = $comps->fetch_assoc()): ?>
                                        <option value="<?php echo $comp['company_id']; ?>"><?php echo $comp['c_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Salaire (DA):</label>
                                <input type="number" step="0.01" name="salaire" required>
                            </div>
                        </div>
                        <button type="submit" name="add_admin" class="btn">Ajouter Admin</button>
                    </form>

                    <hr>
                    <?php $admins = $db->query("SELECT a.*, c.c_name FROM administrator a LEFT JOIN company c ON a.company_id = c.company_id");
                    if ($admins->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Compagnie</th>
                                <th>Salaire</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($admin = $admins->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $admin['prenom'] . ' ' . $admin['nom']; ?></td>
                                    <td><?php echo $admin['email']; ?></td>
                                    <td><?php echo $admin['c_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo number_format($admin['salaire'], 2); ?> DA</td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="delete_admin" value="1">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            const buttons = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>