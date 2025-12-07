<?php
/***************************************************************
 * DZLocation - Système de Location de Voitures en Algérie
 * Application complète avec 3 rôles (Client, Agent, Administrateur)
 * Langue: Français - Devise: DA
 ***************************************************************/

// Démarrage de la session
session_start();

// Configuration de l'affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

/***************************************************************
 * PARTIE 1: CONFIGURATION DE LA BASE DE DONNÉES ET CLASSES
 ***************************************************************/

class Database {
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $dbname = "car_rental_algeria";
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
        
        // Créer la base de données si elle n'existe pas
        $this->conn->query("CREATE DATABASE IF NOT EXISTS $this->dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->conn->select_db($this->dbname);
    }
    
    private function createTables() {
        // Table wilaya
        $sql = "CREATE TABLE IF NOT EXISTS wilaya (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table company
        $sql = "CREATE TABLE IF NOT EXISTS company (
            company_id INT AUTO_INCREMENT PRIMARY KEY,
            c_name VARCHAR(100) NOT NULL,
            frais_mensuel DECIMAL(10,2) CHECK (frais_mensuel BETWEEN 30000 AND 150000),
            special_code VARCHAR(50)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table administrator
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
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table agent
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
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table client
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
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table car
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
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table payment
        $sql = "CREATE TABLE IF NOT EXISTS payment (
            id_payment INT AUTO_INCREMENT PRIMARY KEY,
            status ENUM('paid', 'not_paid') DEFAULT 'not_paid',
            amount DECIMAL(10,2),
            payment_date DATETIME,
            card_number VARCHAR(16),
            card_code VARCHAR(3)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table reservation
        $sql = "CREATE TABLE IF NOT EXISTS reservation (
            id_reservation INT AUTO_INCREMENT PRIMARY KEY,
            id_client INT,
            id_company INT,
            car_id INT,
            wilaya_id INT,
            start_date DATE,
            end_date DATE,
            period INT,
            montant DECIMAL(10,2),
            id_payment INT,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            FOREIGN KEY (id_client) REFERENCES client(id),
            FOREIGN KEY (id_company) REFERENCES company(company_id),
            FOREIGN KEY (car_id) REFERENCES car(id_car),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (id_payment) REFERENCES payment(id_payment)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }
    
    private function seedData() {
        // Insérer les wilayas
        $wilayas = [
            1 => 'Adrar', 2 => 'Chlef', 3 => 'Laghouat', 4 => 'Oum El Bouaghi', 5 => 'Batna',
            6 => 'Béjaïa', 7 => 'Biskra', 8 => 'Béchar', 9 => 'Blida', 10 => 'Bouira',
            11 => 'Tamanrasset', 12 => 'Tébessa', 13 => 'Tlemcen', 14 => 'Tiaret', 15 => 'Tizi Ouzou',
            16 => 'Alger', 17 => 'Djelfa', 18 => 'Jijel', 19 => 'Sétif', 20 => 'Saïda',
            21 => 'Skikda', 22 => 'Sidi Bel Abbès', 23 => 'Annaba', 24 => 'Guelma', 25 => 'Constantine',
            26 => 'Médéa', 27 => 'Mostaganem', 28 => 'M\'Sila', 29 => 'Mascara', 30 => 'Ouargla',
            31 => 'Oran', 32 => 'El Bayadh', 33 => 'Illizi', 34 => 'Bordj Bou Arreridj', 35 => 'Boumerdès',
            36 => 'El Tarf', 37 => 'Tindouf', 38 => 'Tissemsilt', 39 => 'El Oued', 40 => 'Khenchela',
            41 => 'Souk Ahras', 42 => 'Tipaza', 43 => 'Mila', 44 => 'Aïn Defla', 45 => 'Naâma',
            46 => 'Aïn Témouchent', 47 => 'Ghardaïa', 48 => 'Relizane', 49 => 'Timimoun', 50 => 'Bordj Badji Mokhtar',
            51 => 'Ouled Djellal', 52 => 'Béni Abbès', 53 => 'In Salah', 54 => 'In Guezzam', 55 => 'Touggourt',
            56 => 'Djanet', 57 => 'El M\'Ghair', 58 => 'El Meniaa', 59 => 'Aflou', 60 => 'El Abiodh Sidi Cheikh',
            61 => 'El Aricha', 62 => 'El Kantara', 63 => 'Barika', 64 => 'Bou Saâda', 65 => 'Bir El Ater',
            66 => 'Ksar El Boukhari', 67 => 'Ksar Chellala', 68 => 'Aïn Oussara', 69 => 'Messaad'
        ];
        
        foreach ($wilayas as $id => $name) {
            $check = $this->conn->query("SELECT id FROM wilaya WHERE id = $id");
            if ($check->num_rows == 0) {
                $stmt = $this->conn->prepare("INSERT INTO wilaya (id, name) VALUES (?, ?)");
                $stmt->bind_param("is", $id, $name);
                $stmt->execute();
            }
        }
        
        // Vérifier si l'agence cherifi_youssouf existe
        $checkCompany = $this->conn->query("SELECT company_id FROM company WHERE c_name LIKE '%cherifi_youssouf%'");
        if ($checkCompany->num_rows == 0) {
            // Créer l'agence
            $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code) 
                               VALUES ('Agence Cherifi Youssouf', 75000, 'CHERIFI001')");
            $company_id = $this->conn->insert_id;
            
            // Créer l'administrateur
            $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, 
                              numero_cart_national, wilaya_id, salaire, company_id, email, password) 
                              VALUES ('Cherifi', 'Youssouf', 35, '0555123456', 'Algérienne', 
                              '1234567890123456', 16, 120000, $company_id, 'admin@cherifi.com', '$hashed_password')");
            
            // Créer un agent
            $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, 
                              numero_cart_national, wilaya_id, salaire, company_id, email, password) 
                              VALUES ('Agent', 'Principal', 28, '0555123457', 'Algérienne', 
                              '1234567890123457', 16, 80000, $company_id, 'agent@cherifi.com', '$hashed_password')");
            
            // Créer un client de test
            $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, 
                              numero_cart_national, wilaya_id, status, company_id, email, password) 
                              VALUES ('Client', 'Test', 25, '0555123458', 'Algérienne', 
                              '1234567890123458', 31, 'non reserve', $company_id, 'client@cherifi.com', '$hashed_password')");
            
            // Créer des voitures d'exemple
            $cars = [
                ['Toyota', 'Corolla', 2022, 1, 5000, 1, 'Blanc'],
                ['BMW', '3 Series', 2021, 2, 8000, 1, 'Noir'],
                ['Mercedes-Benz', 'S-Class', 2023, 3, 15000, 1, 'Argent'],
                ['Volkswagen', 'Golf', 2021, 1, 4500, 1, 'Bleu'],
                ['Renault', 'Clio', 2020, 1, 4000, 1, 'Rouge'],
                ['Audi', 'A4', 2022, 2, 9000, 1, 'Gris'],
                ['Porsche', 'Panamera', 2023, 3, 18000, 1, 'Noir']
            ];
            
            foreach ($cars as $car) {
                $serial = rand(100000, 999999);
                $matricule = "$serial {$car[3]} {$car[2]} 31"; // Format: serial category year wilaya
                $this->conn->query("INSERT INTO car (company_id, marque, model, annee, category, prix_day, 
                                  status_voiture, matricule, color, voiture_work) 
                                  VALUES ($company_id, '{$car[0]}', '{$car[1]}', {$car[2]}, {$car[3]}, {$car[4]}, 
                                  {$car[5]}, '$matricule', '{$car[6]}', 'disponible')");
            }
        }
    }
    
    public function query($sql) {
        return $this->conn->query($sql);
    }
    
    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }
    
    public function escape($string) {
        return $this->conn->real_escape_string($string);
    }
}

/***************************************************************
 * PARTIE 2: CLASSES D'AUTHENTIFICATION ET D'APPLICATION
 ***************************************************************/

class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($email, $password, $role) {
        $table = $this->getTableByRole($role);
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
                $_SESSION['company_id'] = $user['company_id'] ?? 1;
                
                // Cookie pour 30 jours
                setcookie('user_id', $user['id'], time() + (30 * 24 * 60 * 60), "/");
                setcookie('user_role', $role, time() + (30 * 24 * 60 * 60), "/");
                
                return true;
            }
        }
        return false;
    }
    
    public function logout() {
        session_destroy();
        setcookie('user_id', '', time() - 3600, "/");
        setcookie('user_role', '', time() - 3600, "/");
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
    
    private function getTableByRole($role) {
        $tables = [
            'client' => 'client',
            'agent' => 'agent',
            'administrator' => 'administrator'
        ];
        return $tables[$role] ?? 'client';
    }
    
    public function registerClient($data) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, 
                                   numero_cart_national, wilaya_id, email, password, company_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssissssisi", 
            $data['nom'], 
            $data['prenom'],
            $data['age'],
            $data['numero_tlfn'],
            $data['nationalite'],
            $data['numero_cart_national'],
            $data['wilaya_id'],
            $data['email'],
            $hashed_password,
            $data['company_id']
        );
        
        return $stmt->execute();
    }
}

class CarRentalApp {
    private $db;
    private $auth;
    
    public function __construct($database, $auth) {
        $this->db = $database;
        $this->auth = $auth;
    }
    
    public function getAvailableCars($company_id = null, $category = null) {
        $sql = "SELECT c.*, co.c_name as company_name 
                FROM car c 
                JOIN company co ON c.company_id = co.company_id 
                WHERE c.voiture_work = 'disponible'";
        
        if ($company_id) {
            $sql .= " AND c.company_id = $company_id";
        }
        
        if ($category) {
            $sql .= " AND c.category = $category";
        }
        
        $sql .= " ORDER BY c.prix_day ASC";
        
        return $this->db->query($sql);
    }
    
    public function getCompanyCars($company_id) {
        $stmt = $this->db->prepare("SELECT * FROM car WHERE company_id = ? ORDER BY category, marque");
        $stmt->bind_param("i", $company_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function createReservation($data) {
        // Calculer la période en jours
        $start = new DateTime($data['start_date']);
        $end = new DateTime($data['end_date']);
        $period = $start->diff($end)->days;
        
        // Vérifier que la période est positive
        if ($period <= 0) {
            return false;
        }
        
        // Obtenir le prix de la voiture
        $car_stmt = $this->db->prepare("SELECT prix_day FROM car WHERE id_car = ?");
        $car_stmt->bind_param("i", $data['car_id']);
        $car_stmt->execute();
        $car_result = $car_stmt->get_result();
        
        if ($car_result->num_rows == 0) {
            return false;
        }
        
        $car = $car_result->fetch_assoc();
        $montant = $period * $car['prix_day'];
        
        $stmt = $this->db->prepare("INSERT INTO reservation 
                                   (id_client, id_company, car_id, wilaya_id, 
                                    start_date, end_date, period, montant, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        
        $stmt->bind_param("iiiissid", 
            $data['id_client'],
            $data['id_company'],
            $data['car_id'],
            $data['wilaya_id'],
            $data['start_date'],
            $data['end_date'],
            $period,
            $montant
        );
        
        if ($stmt->execute()) {
            $reservation_id = $this->db->conn->insert_id;
            
            // Mettre à jour le statut de la voiture
            $this->db->query("UPDATE car SET voiture_work = 'non disponible' WHERE id_car = {$data['car_id']}");
            
            // Mettre à jour le statut du client
            $this->db->query("UPDATE client SET status = 'reserve' WHERE id = {$data['id_client']}");
            
            return $reservation_id;
        }
        
        return false;
    }
    
    public function processPayment($reservation_id, $card_number, $card_code) {
        // Valider les informations de la carte
        if (strlen($card_number) != 16 || !is_numeric($card_number)) {
            return false;
        }
        
        if (strlen($card_code) != 3 || !is_numeric($card_code)) {
            return false;
        }
        
        // Obtenir le montant de la réservation
        $stmt = $this->db->prepare("SELECT montant FROM reservation WHERE id_reservation = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        $reservation = $result->fetch_assoc();
        
        // Créer le paiement
        $payment_stmt = $this->db->prepare("INSERT INTO payment (amount, card_number, card_code, status, payment_date) 
                                           VALUES (?, ?, ?, 'paid', NOW())");
        $payment_stmt->bind_param("dss", $reservation['montant'], $card_number, $card_code);
        
        if ($payment_stmt->execute()) {
            $payment_id = $this->db->conn->insert_id;
            
            // Mettre à jour la réservation avec le paiement
            $this->db->query("UPDATE reservation SET id_payment = $payment_id WHERE id_reservation = $reservation_id");
            
            // Mettre à jour le statut du client
            $this->db->query("UPDATE client SET status = 'payer' WHERE id = (SELECT id_client FROM reservation WHERE id_reservation = $reservation_id)");
            
            // Marquer la réservation comme complétée
            $this->db->query("UPDATE reservation SET status = 'completed' WHERE id_reservation = $reservation_id");
            
            return true;
        }
        
        return false;
    }
    
    public function getStatistics($company_id, $period = 'month') {
        $date_format = '';
        $group_by = '';
        
        switch ($period) {
            case 'day':
                $date_format = "DATE(r.start_date)";
                $group_by = "DATE(r.start_date)";
                break;
            case 'month':
                $date_format = "DATE_FORMAT(r.start_date, '%Y-%m')";
                $group_by = "DATE_FORMAT(r.start_date, '%Y-%m')";
                break;
            case 'year':
                $date_format = "YEAR(r.start_date)";
                $group_by = "YEAR(r.start_date)";
                break;
        }
        
        $sql = "SELECT $date_format as period, COUNT(*) as total_reservations, 
                SUM(r.montant) as total_amount, AVG(r.montant) as avg_amount 
                FROM reservation r 
                WHERE r.id_company = $company_id 
                GROUP BY $group_by 
                ORDER BY period DESC 
                LIMIT 10";
        
        return $this->db->query($sql);
    }
    
    public function getWilayas() {
        return $this->db->query("SELECT * FROM wilaya ORDER BY id");
    }
    
    public function getCategories() {
        return [
            1 => ['name' => 'Économique', 'min_price' => 4000, 'max_price' => 6000],
            2 => ['name' => 'Confort', 'min_price' => 6000, 'max_price' => 12000],
            3 => ['name' => 'Luxe', 'min_price' => 12000, 'max_price' => 20000]
        ];
    }
    
    public function getCarStatusText($status) {
        switch ($status) {
            case 1: return 'Excellent';
            case 2: return 'Entretien';
            case 3: return 'Faible';
            default: return 'Inconnu';
        }
    }
}

/***************************************************************
 * PARTIE 3: TRAITEMENT DES ACTIONS
 ***************************************************************/

// Initialiser la base de données et l'authentification
$db = new Database();
$auth = new Auth($db);
$app = new CarRentalApp($db, $auth);

// Traiter la déconnexion
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $auth->logout();
}

// Traiter la connexion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    
    if ($auth->login($email, $password, $role)) {
        // Rediriger selon le rôle
        switch ($role) {
            case 'client':
                $redirect = 'client';
                break;
            case 'agent':
                $redirect = 'agent';
                break;
            case 'administrator':
                $redirect = 'admin';
                break;
            default:
                $redirect = 'client';
        }
        header("Location: index.php?page=$redirect");
        exit();
    } else {
        $login_error = "Identifiants incorrects";
    }
}

// Traiter l'inscription client
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    // Validation de l'âge
    if ($_POST['age'] < 24) {
        $register_error = "L'âge minimum est de 24 ans";
    } elseif ($_POST['password'] != $_POST['confirm_password']) {
        $register_error = "Les mots de passe ne correspondent pas";
    } else {
        $data = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'age' => $_POST['age'],
            'numero_tlfn' => $_POST['numero_tlfn'],
            'nationalite' => $_POST['nationalite'],
            'numero_cart_national' => $_POST['numero_cart_national'],
            'wilaya_id' => $_POST['wilaya_id'],
            'email' => $_POST['email'],
            'password' => $_POST['password'],
            'company_id' => 1 // Par défaut la première agence
        ];
        
        if ($auth->registerClient($data)) {
            $register_success = "Compte créé avec succès! Vous pouvez maintenant vous connecter.";
        } else {
            $register_error = "Erreur lors de la création du compte. L'email existe peut-être déjà.";
        }
    }
}

// Déterminer la page à afficher
$page = 'home';
if (isset($_GET['page'])) {
    $page = $_GET['page'];
} elseif ($auth->isLoggedIn()) {
    $role = $auth->getUserRole();
    switch ($role) {
        case 'client':
            $page = 'client';
            break;
        case 'agent':
            $page = 'agent';
            break;
        case 'administrator':
            $page = 'admin';
            break;
    }
}

/***************************************************************
 * PARTIE 4: FONCTIONS D'AFFICHAGE (HTML/HEADER)
 ***************************************************************/

// Fonction pour afficher l'en-tête HTML
function displayHeader($title = "DZLocation - Location de Voitures") {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <!-- Tailwind CSS CDN -->
        <script src="https://cdn.tailwindcss.com"></script>
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: #3B82F6;
                --secondary: #10B981;
                --accent: #8B5CF6;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f8fafc;
            }
            
            .card-hover {
                transition: all 0.3s ease;
            }
            
            .card-hover:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            }
            
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            .status-paid { background-color: #10B981; color: white; }
            .status-reserved { background-color: #F59E0B; color: white; }
            .status-cancelled { background-color: #EF4444; color: white; }
            .status-available { background-color: #3B82F6; color: white; }
            
            .car-category-1 { border-left: 4px solid #10B981; }
            .car-category-2 { border-left: 4px solid #3B82F6; }
            .car-category-3 { border-left: 4px solid #8B5CF6; }
            
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
            }
            
            .modal-content {
                background-color: white;
                margin: 5% auto;
                padding: 20px;
                border-radius: 10px;
                width: 90%;
                max-width: 500px;
                max-height: 80vh;
                overflow-y: auto;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            .fade-in {
                animation: fadeIn 0.3s ease-in-out;
            }
        </style>
    </head>
    <body class="bg-gray-50">
    <?php
}

// Fonction pour afficher la navigation
function displayNavigation($auth, $app) {
    ?>
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center">
                        <i class="fas fa-car text-2xl text-blue-600 mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <span class="text-blue-600">DZ</span>Location
                        </h1>
                    </a>
                    <span class="ml-2 text-sm text-gray-600">Algérie</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <?php if ($auth->isLoggedIn()): ?>
                        <span class="text-gray-700">
                            <i class="fas fa-user mr-1"></i>
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </span>
                        <span class="px-3 py-1 rounded-full text-sm 
                            <?php echo $_SESSION['user_role'] == 'administrator' ? 'bg-purple-100 text-purple-800' : 
                                  ($_SESSION['user_role'] == 'agent' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'); ?>">
                            <?php echo ucfirst($_SESSION['user_role']); ?>
                        </span>
                        <a href="index.php?action=logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                            <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="text-gray-700 hover:text-blue-600 px-3 py-2">
                            <i class="fas fa-home mr-1"></i>Accueil
                        </a>
                        <a href="index.php?page=register" class="text-blue-600 hover:text-blue-800 px-3 py-2">
                            <i class="fas fa-user-plus mr-1"></i>Inscription
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <?php
}

/***************************************************************
 * PARTIE 5: PAGES DE L'APPLICATION
 ***************************************************************/

// Afficher l'en-tête
displayHeader();

// Afficher la navigation
displayNavigation($auth, $app);

// Contenu principal
echo '<main class="container mx-auto px-4 py-8">';

// Afficher les messages
if (isset($login_error)) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">' . htmlspecialchars($login_error) . '</div>';
}
if (isset($register_error)) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">' . htmlspecialchars($register_error) . '</div>';
}
if (isset($register_success)) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">' . htmlspecialchars($register_success) . '</div>';
}

// Afficher la page appropriée
switch ($page) {
    case 'home':
        displayHomePage($auth, $app, $db);
        break;
    case 'register':
        displayRegisterPage($auth, $app);
        break;
    case 'client':
        if ($auth->isLoggedIn() && $auth->getUserRole() == 'client') {
            displayClientDashboard($auth, $app, $db);
        } else {
            displayHomePage($auth, $app, $db);
        }
        break;
    case 'agent':
        if ($auth->isLoggedIn() && $auth->getUserRole() == 'agent') {
            displayAgentDashboard($auth, $app, $db);
        } else {
            displayHomePage($auth, $app, $db);
        }
        break;
    case 'admin':
        if ($auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
            displayAdminDashboard($auth, $app, $db);
        } else {
            displayHomePage($auth, $app, $db);
        }
        break;
    default:
        displayHomePage($auth, $app, $db);
}

echo '</main>';

/***************************************************************
 * PARTIE 6: FONCTIONS D'AFFICHAGE DES PAGES
 ***************************************************************/

function displayHomePage($auth, $app, $db) {
    ?>
    <div class="min-h-screen flex items-center justify-center py-12">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-6xl">
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-800 mb-4">
                    Bienvenue sur <span class="text-blue-600">DZLocation</span>
                </h1>
                <p class="text-gray-600 text-lg">
                    Location de voitures en Algérie - Service professionnel et fiable
                </p>
            </div>
            
            <?php if (!$auth->isLoggedIn()): ?>
            <!-- Sélection du rôle -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                <!-- Client Card -->
                <div class="bg-gradient-to-br from-green-50 to-blue-50 rounded-xl p-6 text-center card-hover border border-green-100">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Client</h3>
                    <p class="text-gray-600 mb-6">Réservez votre voiture en ligne</p>
                    <button onclick="showLogin('client')" 
                            class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                    </button>
                </div>
                
                <!-- Agent Card -->
                <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-6 text-center card-hover border border-blue-100">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-tie text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Agent</h3>
                    <p class="text-gray-600 mb-6">Gestion des clients et réservations</p>
                    <button onclick="showLogin('agent')" 
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                    </button>
                </div>
                
                <!-- Admin Card -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 text-center card-hover border border-purple-100">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-shield text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-3">Administrateur</h3>
                    <p class="text-gray-600 mb-6">Gestion complète de l'agence</p>
                    <button onclick="showLogin('administrator')" 
                            class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-lg transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                    </button>
                </div>
            </div>
            
            <!-- Formulaire de connexion -->
            <div id="loginForm" class="hidden bg-gray-50 rounded-xl p-8 fade-in">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center" id="formTitle"></h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="login" value="1">
                    <input type="hidden" name="role" id="loginRole">
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Mot de passe</label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <a href="index.php?page=register" class="text-blue-600 hover:text-blue-800 text-sm">
                            Pas de compte ? S'inscrire
                        </a>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition">
                            <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Voitures disponibles -->
            <div class="mt-12">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Voitures Disponibles</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php
                    $cars = $app->getAvailableCars();
                    $count = 0;
                    while ($car = $cars->fetch_assoc()):
                        if ($count >= 6) break;
                        $count++;
                        $category_info = $app->getCategories()[$car['category']];
                    ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden card-hover car-category-<?php echo $car['category']; ?>">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800"><?php echo htmlspecialchars($car['marque'] . ' ' . $car['model']); ?></h3>
                                    <p class="text-gray-600">Année: <?php echo $car['annee']; ?></p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-sm 
                                    <?php echo $car['category'] == 1 ? 'bg-green-100 text-green-800' : 
                                          ($car['category'] == 2 ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'); ?>">
                                    Catégorie <?php echo $car['category']; ?>
                                </span>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-gray-700">
                                    <i class="fas fa-palette text-gray-400 mr-2"></i>
                                    Couleur: <?php echo htmlspecialchars($car['color']); ?>
                                </p>
                                <p class="text-gray-700">
                                    <i class="fas fa-id-card text-gray-400 mr-2"></i>
                                    Plaque: <?php echo htmlspecialchars($car['matricule']); ?>
                                </p>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-2xl font-bold text-blue-600">
                                    <?php echo number_format($car['prix_day'], 0, ',', ' '); ?> DA/jour
                                </span>
                                <span class="px-3 py-1 rounded-full text-sm bg-green-100 text-green-800">
                                    <i class="fas fa-check mr-1"></i>Disponible
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    
                    <?php if ($count == 0): ?>
                    <div class="md:col-span-3 text-center py-8 text-gray-500">
                        <i class="fas fa-car text-3xl mb-3"></i>
                        <p>Aucune voiture disponible pour le moment</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function showLogin(role) {
        document.getElementById('loginForm').classList.remove('hidden');
        document.getElementById('loginRole').value = role;
        
        const titles = {
            'client': 'Connexion Client',
            'agent': 'Connexion Agent',
            'administrator': 'Connexion Administrateur'
        };
        
        document.getElementById('formTitle').textContent = titles[role];
        
        // Scroll to form
        document.getElementById('loginForm').scrollIntoView({ behavior: 'smooth' });
    }
    </script>
    <?php
}

function displayRegisterPage($auth, $app) {
    ?>
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-2xl">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Inscription Client</h1>
                <p class="text-gray-600 mt-2">Créez votre compte pour réserver des voitures</p>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="register" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Informations personnelles -->
                    <div>
                        <label class="block text-gray-700 mb-2">Nom *</label>
                        <input type="text" name="nom" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Prénom *</label>
                        <input type="text" name="prenom" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Âge * (minimum 24)</label>
                        <input type="number" name="age" min="24" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Téléphone *</label>
                        <input type="tel" name="numero_tlfn" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Nationalité *</label>
                        <input type="text" name="nationalite" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Numéro Carte Nationale *</label>
                        <input type="text" name="numero_cart_national" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya *</label>
                        <select name="wilaya_id" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Sélectionnez une wilaya</option>
                            <?php
                            $wilayas = $app->getWilayas();
                            while ($wilaya = $wilayas->fetch_assoc()):
                            ?>
                                <option value="<?php echo $wilaya['id']; ?>">
                                    <?php echo $wilaya['id']; ?> - <?php echo htmlspecialchars($wilaya['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Informations du compte -->
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Mot de passe *</label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Confirmer le mot de passe *</label>
                        <input type="password" name="confirm_password" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="terms" required 
                           class="h-4 w-4 text-blue-600 rounded focus:ring-blue-500">
                    <label for="terms" class="ml-2 text-gray-700">
                        J'accepte les <a href="#" class="text-blue-600 hover:text-blue-800">conditions générales</a>
                    </label>
                </div>
                
                <div class="flex justify-between items-center pt-6">
                    <a href="index.php" class="text-blue-600 hover:text-blue-800 font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Retour à l'accueil
                    </a>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg transition">
                        <i class="fas fa-user-plus mr-2"></i>S'inscrire
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

function displayClientDashboard($auth, $app, $db) {
    $client_id = $auth->getUserId();
    $company_id = $_SESSION['company_id'];
    
    // Traiter les actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'reserve':
                    $reservation_data = [
                        'id_client' => $client_id,
                        'id_company' => $company_id,
                        'car_id' => $_POST['car_id'],
                        'wilaya_id' => $_POST['wilaya_id'],
                        'start_date' => $_POST['start_date'],
                        'end_date' => $_POST['end_date']
                    ];
                    
                    $reservation_id = $app->createReservation($reservation_data);
                    
                    if ($reservation_id) {
                        $success = "Réservation créée avec succès!";
                    } else {
                        $error = "Erreur lors de la réservation";
                    }
                    break;
                    
                case 'payer':
                    if (strlen($_POST['card_number']) == 16 && strlen($_POST['card_code']) == 3) {
                        if ($app->processPayment($_POST['reservation_id'], $_POST['card_number'], $_POST['card_code'])) {
                            $success = "Paiement effectué avec succès!";
                        } else {
                            $error = "Erreur lors du paiement";
                        }
                    } else {
                        $error = "Numéro de carte (16 chiffres) et code (3 chiffres) requis";
                    }
                    break;
            }
        }
    }
    
    // Obtenir les réservations du client
    $reservations = $db->query("
        SELECT r.*, c.marque, c.model, c.matricule, w.name as wilaya_name, p.status as payment_status
        FROM reservation r
        JOIN car c ON r.car_id = c.id_car
        JOIN wilaya w ON r.wilaya_id = w.id
        LEFT JOIN payment p ON r.id_payment = p.id_payment
        WHERE r.id_client = $client_id
        ORDER BY r.start_date DESC
    ");
    
    // Obtenir le statut du client
    $client_status = $db->query("SELECT status FROM client WHERE id = $client_id")->fetch_assoc();
    ?>
    
    <div class="min-h-screen">
        <!-- En-tête du tableau de bord -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Tableau de Bord Client</h1>
                    <p class="text-blue-100 mt-2">Gérez vos réservations et paiements</p>
                </div>
                <div class="text-right">
                    <p class="text-lg">Votre statut: 
                        <span class="font-bold px-3 py-1 rounded <?php 
                            echo $client_status['status'] == 'payer' ? 'bg-green-500' : 
                                 ($client_status['status'] == 'reserve' ? 'bg-yellow-500' : 
                                 ($client_status['status'] == 'annuler' ? 'bg-red-500' : 'bg-gray-500')); ?>">
                            <?php echo ucfirst($client_status['status']); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Colonne de gauche: Voitures disponibles -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-car mr-2"></i>Voitures Disponibles
                    </h2>
                    
                    <!-- Liste des voitures -->
                    <div class="space-y-6">
                        <?php
                        $cars = $app->getAvailableCars($company_id);
                        while ($car = $cars->fetch_assoc()):
                            $category_info = $app->getCategories()[$car['category']];
                        ?>
                        <div class="border border-gray-200 rounded-xl p-6 card-hover car-category-<?php echo $car['category']; ?>">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-4">
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-800">
                                                <?php echo htmlspecialchars($car['marque'] . ' ' . $car['model']); ?>
                                            </h3>
                                            <p class="text-gray-600">
                                                <i class="fas fa-calendar mr-1"></i>Année: <?php echo $car['annee']; ?>
                                                | <i class="fas fa-palette ml-2 mr-1"></i><?php echo htmlspecialchars($car['color']); ?>
                                            </p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-sm 
                                            <?php echo $car['category'] == 1 ? 'bg-green-100 text-green-800' : 
                                                  ($car['category'] == 2 ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'); ?>">
                                            <?php echo $category_info['name']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 mb-6">
                                        <div>
                                            <p class="text-gray-700 mb-2">
                                                <i class="fas fa-id-card text-gray-400 mr-2"></i>
                                                <span class="font-medium">Plaque:</span><br>
                                                <span class="font-mono"><?php echo htmlspecialchars($car['matricule']); ?></span>
                                            </p>
                                            <p class="text-gray-700">
                                                <i class="fas fa-wrench text-gray-400 mr-2"></i>
                                                <span class="font-medium">État:</span> 
                                                <?php echo $app->getCarStatusText($car['status_voiture']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-gray-700 mb-2">
                                                <i class="fas fa-tag text-gray-400 mr-2"></i>
                                                <span class="font-medium">Prix/jour:</span>
                                            </p>
                                            <p class="text-2xl font-bold text-blue-600">
                                                <?php echo number_format($car['prix_day'], 0, ',', ' '); ?> DA
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Boutons d'action -->
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <button onclick="showReservationForm(<?php echo $car['id_car']; ?>, '<?php echo htmlspecialchars($car['marque'] . ' ' . $car['model']); ?>', <?php echo $car['prix_day']; ?>)" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition mr-3">
                                    <i class="fas fa-calendar-plus mr-2"></i>Réserver
                                </button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        
                        <?php if ($cars->num_rows == 0): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-car text-3xl mb-3"></i>
                            <p>Aucune voiture disponible pour le moment</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Colonne de droite: Réservations -->
            <div>
                <!-- Liste des réservations -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-history mr-2"></i>Mes Réservations
                    </h2>
                    
                    <div class="space-y-4">
                        <?php while ($res = $reservations->fetch_assoc()): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-bold text-gray-800">
                                    <?php echo htmlspecialchars($res['marque'] . ' ' . $res['model']); ?>
                                </h4>
                                <span class="px-2 py-1 rounded text-xs 
                                    <?php echo $res['status'] == 'active' ? 'bg-yellow-100 text-yellow-800' :
                                          ($res['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo $res['status'] == 'active' ? 'Active' :
                                          ($res['status'] == 'completed' ? 'Terminée' : 'Annulée'); ?>
                                </span>
                            </div>
                            
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="far fa-calendar mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($res['start_date'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($res['end_date'])); ?>
                            </p>
                            
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                <?php echo htmlspecialchars($res['wilaya_name']); ?>
                            </p>
                            
                            <p class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-money-bill-wave mr-1"></i>
                                <?php echo number_format($res['montant'], 0, ',', ' '); ?> DA
                            </p>
                            
                            <div class="flex justify-between items-center mt-3">
                                <span class="text-xs px-2 py-1 rounded 
                                    <?php echo $res['payment_status'] == 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $res['payment_status'] == 'paid' ? 'Payé' : 'En attente'; ?>
                                </span>
                                
                                <?php if ($res['payment_status'] != 'paid' && $res['status'] == 'active'): ?>
                                <button onclick="showPaymentForm(<?php echo $res['id_reservation']; ?>, '<?php echo htmlspecialchars($res['marque'] . ' ' . $res['model']); ?>', <?php echo $res['montant']; ?>)" 
                                        class="text-sm bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded">
                                    Payer
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        
                        <?php if ($reservations->num_rows == 0): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-car text-3xl mb-3"></i>
                            <p>Aucune réservation</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de réservation -->
    <div id="reservationModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800" id="reservationTitle"></h3>
                <button onclick="closeModal('reservationModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="reservationForm">
                <input type="hidden" name="action" value="reserve">
                <input type="hidden" name="car_id" id="modalCarId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Date de début</label>
                        <input type="date" name="start_date" required 
                               class="w-full px-4 py-2 border rounded-lg" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Date de fin</label>
                        <input type="date" name="end_date" required 
                               class="w-full px-4 py-2 border rounded-lg" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya de prise en charge</label>
                        <select name="wilaya_id" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Sélectionnez une wilaya</option>
                            <?php
                            $wilayas = $app->getWilayas();
                            while ($wilaya = $wilayas->fetch_assoc()):
                            ?>
                                <option value="<?php echo $wilaya['id']; ?>"><?php echo htmlspecialchars($wilaya['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-700">Prix estimé: <span id="estimatedPrice" class="font-bold text-blue-600">0</span> DA</p>
                        <p class="text-xs text-gray-500 mt-1">Le calcul se fait automatiquement selon la durée</p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('reservationModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Confirmer la réservation
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de paiement -->
    <div id="paymentModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800" id="paymentTitle"></h3>
                <button onclick="closeModal('paymentModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="paymentForm">
                <input type="hidden" name="action" value="payer">
                <input type="hidden" name="reservation_id" id="paymentReservationId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Numéro de carte (16 chiffres)</label>
                        <input type="text" name="card_number" maxlength="16" required 
                               pattern="\d{16}" placeholder="1234567812345678"
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Code de sécurité (3 chiffres)</label>
                        <input type="text" name="card_code" maxlength="3" required 
                               pattern="\d{3}" placeholder="123"
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            Montant à payer: <span id="paymentAmount" class="font-bold">0</span> DA
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('paymentModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        Payer maintenant
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    let dailyPrice = 0;
    
    function showReservationForm(carId, carName, price) {
        dailyPrice = price;
        document.getElementById('modalCarId').value = carId;
        document.getElementById('reservationTitle').textContent = 'Réserver: ' + carName;
        document.getElementById('reservationModal').style.display = 'block';
        
        // Réinitialiser le formulaire
        document.getElementById('reservationForm').reset();
        document.getElementById('estimatedPrice').textContent = '0';
    }
    
    function showPaymentForm(reservationId, carName, amount) {
        document.getElementById('paymentReservationId').value = reservationId;
        document.getElementById('paymentTitle').textContent = 'Payer: ' + carName;
        document.getElementById('paymentAmount').textContent = amount.toLocaleString();
        document.getElementById('paymentModal').style.display = 'block';
        
        // Réinitialiser le formulaire
        document.getElementById('paymentForm').reset();
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Calculer le prix estimé
    document.addEventListener('DOMContentLoaded', function() {
        const startDate = document.querySelector('#reservationForm input[name="start_date"]');
        const endDate = document.querySelector('#reservationForm input[name="end_date"]');
        const estimatedPrice = document.getElementById('estimatedPrice');
        
        function calculatePrice() {
            if (startDate && startDate.value && endDate && endDate.value && dailyPrice > 0) {
                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                const diffTime = Math.abs(end - start);
                const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (days > 0) {
                    estimatedPrice.textContent = (days * dailyPrice).toLocaleString();
                } else {
                    estimatedPrice.textContent = '0';
                }
            }
        }
        
        if (startDate) startDate.addEventListener('change', calculatePrice);
        if (endDate) endDate.addEventListener('change', calculatePrice);
        
        // Fermer les modales en cliquant à l'extérieur
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    });
    </script>
    <?php
}

function displayAgentDashboard($auth, $app, $db) {
    $agent_id = $auth->getUserId();
    $company_id = $_SESSION['company_id'];
    
    // Traiter les actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_client':
                    // Vérifier l'âge
                    if ($_POST['age'] < 24) {
                        $error = "L'âge minimum est de 24 ans";
                    } else {
                        $hashed_password = password_hash('client123', PASSWORD_DEFAULT);
                        $stmt = $db->prepare("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, 
                                             numero_cart_national, wilaya_id, company_id, email, password) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssissssiss", 
                            $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                            $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'],
                            $company_id, $_POST['email'], $hashed_password
                        );
                        if ($stmt->execute()) {
                            $success = "Client ajouté avec succès";
                        } else {
                            $error = "Erreur lors de l'ajout du client";
                        }
                    }
                    break;
            }
        }
    }
    
    // Obtenir les statistiques
    $total_clients = $db->query("SELECT COUNT(*) as count FROM client WHERE company_id = $company_id")->fetch_assoc();
    $total_cars = $db->query("SELECT COUNT(*) as count FROM car WHERE company_id = $company_id")->fetch_assoc();
    $available_cars = $db->query("SELECT COUNT(*) as count FROM car WHERE company_id = $company_id AND voiture_work = 'disponible'")->fetch_assoc();
    
    // Obtenir les clients
    $clients = $db->query("
        SELECT c.*, w.name as wilaya_name 
        FROM client c 
        LEFT JOIN wilaya w ON c.wilaya_id = w.id 
        WHERE c.company_id = $company_id
        ORDER BY c.id DESC
        LIMIT 10
    ");
    
    // Obtenir les réservations récentes
    $reservations = $db->query("
        SELECT r.*, cl.nom as client_nom, cl.prenom as client_prenom, 
               ca.marque, ca.model, w.name as wilaya_name, p.status as payment_status
        FROM reservation r
        JOIN client cl ON r.id_client = cl.id
        JOIN car ca ON r.car_id = ca.id_car
        JOIN wilaya w ON r.wilaya_id = w.id
        LEFT JOIN payment p ON r.id_payment = p.id_payment
        WHERE r.id_company = $company_id
        ORDER BY r.start_date DESC
        LIMIT 10
    ");
    ?>
    
    <div class="min-h-screen">
        <!-- En-tête du tableau de bord -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Tableau de Bord Agent</h1>
                    <p class="text-blue-100 mt-2">Gestion des clients et réservations</p>
                </div>
                <div class="text-right">
                    <p class="text-lg">Clients: <span class="font-bold"><?php echo $total_clients['count']; ?></span></p>
                    <p class="text-sm text-blue-100">Voitures disponibles: <span class="font-bold"><?php echo $available_cars['count']; ?>/<?php echo $total_cars['count']; ?></span></p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques rapides -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Total Clients</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $total_clients['count']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-car text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Voitures Disponibles</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $available_cars['count']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Revenus du Mois</p>
                        <?php
                        $month_revenue = $db->query("
                            SELECT SUM(montant) as total 
                            FROM reservation 
                            WHERE id_company = $company_id 
                            AND MONTH(start_date) = MONTH(NOW())
                            AND YEAR(start_date) = YEAR(NOW())
                        ")->fetch_assoc();
                        ?>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($month_revenue['total'] ?? 0, 0, ',', ' '); ?> DA
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Gestion des clients -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-users mr-2"></i>Gestion des Clients
                    </h2>
                    <button onclick="showAddClientForm()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-user-plus mr-2"></i>Nouveau Client
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Nom & Prénom</th>
                                <th class="px-4 py-3 text-left">Téléphone</th>
                                <th class="px-4 py-3 text-left">Wilaya</th>
                                <th class="px-4 py-3 text-left">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($client = $clients->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($client['email']); ?></div>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($client['numero_tlfn']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($client['wilaya_name']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs 
                                        <?php echo $client['status'] == 'payer' ? 'bg-green-100 text-green-800' :
                                              ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' :
                                              ($client['status'] == 'annuler' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                                        <?php echo ucfirst($client['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Réservations récentes -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-history mr-2"></i>Réservations Récentes
                </h2>
                
                <div class="space-y-4">
                    <?php while ($res = $reservations->fetch_assoc()): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h4 class="font-bold text-gray-800">
                                    <?php echo htmlspecialchars($res['client_prenom'] . ' ' . $res['client_nom']); ?>
                                </h4>
                                <p class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars($res['marque'] . ' ' . $res['model']); ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 rounded text-xs 
                                <?php echo $res['status'] == 'active' ? 'bg-yellow-100 text-yellow-800' :
                                      ($res['status'] == 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo $res['status'] == 'active' ? 'Active' :
                                      ($res['status'] == 'completed' ? 'Terminée' : 'Annulée'); ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 mb-2">
                            <div>
                                <i class="far fa-calendar mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($res['start_date'])); ?>
                            </div>
                            <div>
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo $res['period']; ?> jours
                            </div>
                            <div>
                                <i class="fas fa-map-marker-alt mr-1"></i>
                                <?php echo htmlspecialchars($res['wilaya_name']); ?>
                            </div>
                            <div>
                                <i class="fas fa-money-bill-wave mr-1"></i>
                                <?php echo number_format($res['montant'], 0, ',', ' '); ?> DA
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-xs px-2 py-1 rounded 
                                <?php echo $res['payment_status'] == 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <?php echo $res['payment_status'] == 'paid' ? 'Payé' : 'En attente'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    
                    <?php if ($reservations->num_rows == 0): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-car text-3xl mb-3"></i>
                        <p>Aucune réservation</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal d'ajout de client -->
    <div id="addClientModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Ajouter un Client</h3>
                <button onclick="closeModal('addClientModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_client">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Nom</label>
                            <input type="text" name="nom" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Prénom</label>
                            <input type="text" name="prenom" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Âge (minimum 24)</label>
                        <input type="number" name="age" min="24" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Téléphone</label>
                        <input type="tel" name="numero_tlfn" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Nationalité</label>
                        <input type="text" name="nationalite" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Numéro Carte Nationale</label>
                        <input type="text" name="numero_cart_national" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya</label>
                        <select name="wilaya_id" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Sélectionnez une wilaya</option>
                            <?php
                            $wilayas = $app->getWilayas();
                            while ($wilaya = $wilayas->fetch_assoc()):
                            ?>
                                <option value="<?php echo $wilaya['id']; ?>"><?php echo htmlspecialchars($wilaya['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addClientModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Ajouter le client
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function showAddClientForm() {
        document.getElementById('addClientModal').style.display = 'block';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Fermer les modales en cliquant à l'extérieur
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    </script>
    <?php
}

function displayAdminDashboard($auth, $app, $db) {
    $admin_id = $auth->getUserId();
    $company_id = $_SESSION['company_id'];
    
    // Traiter les actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_car':
                    // Générer le numéro de plaque
                    $serial = rand(100000, 999999);
                    $category = $_POST['category'];
                    $year = $_POST['annee'];
                    $wilaya = 31; // Wilaya par défaut (Oran)
                    $matricule = "$serial $category $year $wilaya";
                    
                    $stmt = $db->prepare("INSERT INTO car (company_id, marque, model, color, annee, 
                                     category, prix_day, status_voiture, matricule, voiture_work) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible')");
                    $stmt->bind_param("isssiidis", 
                        $company_id, $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'],
                        $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $matricule
                    );
                    if ($stmt->execute()) {
                        $success = "Voiture ajoutée avec succès";
                    } else {
                        $error = "Erreur lors de l'ajout de la voiture";
                    }
                    break;
                    
                case 'add_agent':
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, 
                                     numero_cart_national, wilaya_id, salaire, company_id, email, password) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssissssdiss", 
                        $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                        $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'],
                        $_POST['salaire'], $company_id, $_POST['email'], $hashed_password
                    );
                    if ($stmt->execute()) {
                        $success = "Agent ajouté avec succès";
                    } else {
                        $error = "Erreur lors de l'ajout de l'agent";
                    }
                    break;
            }
        }
    }
    
    // Obtenir les statistiques
    $total_cars = $db->query("SELECT COUNT(*) as count FROM car WHERE company_id = $company_id")->fetch_assoc();
    $total_clients = $db->query("SELECT COUNT(*) as count FROM client WHERE company_id = $company_id")->fetch_assoc();
    $total_agents = $db->query("SELECT COUNT(*) as count FROM agent WHERE company_id = $company_id")->fetch_assoc();
    
    // Obtenir les revenus totaux
    $total_revenue = $db->query("
        SELECT SUM(r.montant) as total 
        FROM reservation r
        JOIN payment p ON r.id_payment = p.id_payment
        WHERE r.id_company = $company_id AND p.status = 'paid'
    ")->fetch_assoc();
    
    // Obtenir les voitures
    $cars = $app->getCompanyCars($company_id);
    
    // Obtenir les agents
    $agents = $db->query("SELECT * FROM agent WHERE company_id = $company_id");
    
    // Obtenir les statistiques
    $stats_month = $app->getStatistics($company_id, 'month');
    ?>
    
    <div class="min-h-screen">
        <!-- En-tête du tableau de bord -->
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Tableau de Bord Administrateur</h1>
                    <p class="text-purple-100 mt-2">Gestion complète de l'agence</p>
                </div>
                <div class="text-right">
                    <p class="text-lg">Chiffre d'affaires total: 
                        <span class="font-bold">
                            <?php echo number_format($total_revenue['total'] ?? 0, 0, ',', ' '); ?> DA
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Vue d'ensemble des statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-car text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Total Voitures</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $total_cars['count']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-users text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Clients</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $total_clients['count']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-user-tie text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Agents</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $total_agents['count']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">CA du Mois</p>
                        <?php
                        $month_stats = $stats_month->fetch_assoc();
                        ?>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($month_stats['total_amount'] ?? 0, 0, ',', ' '); ?> DA
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Gestion des voitures -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-car mr-2"></i>Gestion des Voitures
                    </h2>
                    <button onclick="showAddCarForm()" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Voiture
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Marque/Modèle</th>
                                <th class="px-4 py-3 text-left">Plaque</th>
                                <th class="px-4 py-3 text-left">Catégorie</th>
                                <th class="px-4 py-3 text-left">Prix</th>
                                <th class="px-4 py-3 text-left">Disponibilité</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($car = $cars->fetch_assoc()): 
                                $categories = $app->getCategories();
                                $category_name = $categories[$car['category']]['name'];
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($car['marque']); ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($car['model']); ?> | <?php echo $car['annee']; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-sm"><?php echo htmlspecialchars($car['matricule']); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs 
                                        <?php echo $car['category'] == 1 ? 'bg-green-100 text-green-800' :
                                              ($car['category'] == 2 ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'); ?>">
                                        <?php echo $category_name; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-blue-600">
                                        <?php echo number_format($car['prix_day'], 0, ',', ' '); ?> DA
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs 
                                        <?php echo $car['voiture_work'] == 'disponible' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $car['voiture_work'] == 'disponible' ? 'Disponible' : 'Indisponible'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Gestion des agents -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-user-tie mr-2"></i>Gestion des Agents
                    </h2>
                    <button onclick="showAddAgentForm()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-user-plus mr-2"></i>Nouvel Agent
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Nom & Prénom</th>
                                <th class="px-4 py-3 text-left">Téléphone</th>
                                <th class="px-4 py-3 text-left">Salaire</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($agent = $agents->fetch_assoc()): 
                                $wilaya = $db->query("SELECT name FROM wilaya WHERE id = {$agent['wilaya_id']}")->fetch_assoc();
                            ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($agent['email']); ?></div>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($agent['numero_tlfn']); ?></td>
                                <td class="px-4 py-3 font-medium text-green-600">
                                    <?php echo number_format($agent['salaire'], 0, ',', ' '); ?> DA
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-chart-bar mr-2"></i>Statistiques Mensuelles
            </h2>
            
            <div class="space-y-4">
                <?php 
                $stats_month->data_seek(0);
                while ($stat = $stats_month->fetch_assoc()): 
                ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-medium"><?php echo $stat['period']; ?></span>
                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                            <?php echo $stat['total_reservations']; ?> réservations
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-purple-600">
                        <?php echo number_format($stat['total_amount'], 0, ',', ' '); ?> DA
                    </p>
                </div>
                <?php endwhile; ?>
                
                <?php if ($stats_month->num_rows == 0): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-chart-line text-3xl mb-3"></i>
                    <p>Aucune donnée statistique disponible</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal d'ajout de voiture -->
    <div id="addCarModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Ajouter une Voiture</h3>
                <button onclick="closeModal('addCarModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_car">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Marque</label>
                            <input type="text" name="marque" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Modèle</label>
                            <input type="text" name="model" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Couleur</label>
                            <input type="text" name="color" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Année</label>
                            <input type="number" name="annee" required min="2000" max="2025"
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Catégorie</label>
                        <select name="category" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Sélectionnez une catégorie</option>
                            <option value="1">Économique (4000-6000 DA/jour)</option>
                            <option value="2">Confort (6000-12000 DA/jour)</option>
                            <option value="3">Luxe (12000-20000 DA/jour)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Prix par jour (DA)</label>
                        <input type="number" name="prix_day" required min="4000" max="20000"
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">État de la voiture</label>
                        <select name="status_voiture" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="1">Excellent</option>
                            <option value="2">Entretien</option>
                            <option value="3">Faible</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addCarModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Ajouter la voiture
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal d'ajout d'agent -->
    <div id="addAgentModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Ajouter un Agent</h3>
                <button onclick="closeModal('addAgentModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_agent">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Nom</label>
                            <input type="text" name="nom" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Prénom</label>
                            <input type="text" name="prenom" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Âge (minimum 24)</label>
                        <input type="number" name="age" min="24" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Téléphone</label>
                        <input type="tel" name="numero_tlfn" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Nationalité</label>
                        <input type="text" name="nationalite" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Numéro Carte Nationale</label>
                        <input type="text" name="numero_cart_national" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya</label>
                        <select name="wilaya_id" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Sélectionnez une wilaya</option>
                            <?php
                            $wilayas = $app->getWilayas();
                            while ($wilaya = $wilayas->fetch_assoc()):
                            ?>
                                <option value="<?php echo $wilaya['id']; ?>"><?php echo htmlspecialchars($wilaya['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Salaire (DA)</label>
                        <input type="number" name="salaire" required min="30000" max="150000"
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Mot de passe</label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addAgentModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Ajouter l'agent
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function showAddCarForm() {
        document.getElementById('addCarModal').style.display = 'block';
    }
    
    function showAddAgentForm() {
        document.getElementById('addAgentModal').style.display = 'block';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    
    // Fermer les modales en cliquant à l'extérieur
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    </script>
    <?php
}

/***************************************************************
 * PARTIE 7: PIED DE PAGE ET JAVASCRIPT
 ***************************************************************/

// Fermer la balise main
echo '</main>';

// Afficher le pied de page
?>
<footer class="bg-gray-900 text-white mt-12">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <h3 class="text-xl font-bold mb-4">
                    <span class="text-blue-400">DZ</span>Location
                </h3>
                <p class="text-gray-400">
                    Location de voitures professionnelle en Algérie. Service fiable et sécurisé.
                </p>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Contact</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><i class="fas fa-phone mr-2"></i>+213 555 123 456</li>
                    <li><i class="fas fa-envelope mr-2"></i>contact@dzlocation.dz</li>
                    <li><i class="fas fa-map-marker-alt mr-2"></i>Alger, Algérie</li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Liens Rapides</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="index.php" class="hover:text-white">Accueil</a></li>
                    <li><a href="index.php?page=register" class="hover:text-white">Inscription Client</a></li>
                    <li><a href="#" class="hover:text-white">Conditions générales</a></li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Suivez-nous</h4>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-white">
                        <i class="fab fa-facebook text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white">
                        <i class="fab fa-twitter text-xl"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-white">
                        <i class="fab fa-instagram text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> DZLocation. Tous droits réservés.</p>
            <p class="mt-2 text-sm">Devant la gare TGV, Centre-ville, Alger</p>
        </div>
    </div>
</footer>

<script>
// Gestion de l'inactivité (déconnexion après 30 minutes)
let timeout;
function resetTimer() {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        alert('Session expirée. Veuillez vous reconnecter.');
        window.location.href = 'index.php?action=logout';
    }, 30 * 60 * 1000); // 30 minutes
}

document.addEventListener('mousemove', resetTimer);
document.addEventListener('keypress', resetTimer);
resetTimer();

// Validation des formulaires
function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], select[required]');
    
    for (let input of inputs) {
        if (!input.value.trim()) {
            input.focus();
            alert('Veuillez remplir tous les champs obligatoires');
            return false;
        }
    }
    return true;
}

// Validation de l'âge
function checkMinimumAge(input) {
    const age = parseInt(input.value);
    if (age < 24) {
        alert('L\'âge minimum est de 24 ans');
        input.value = 24;
        input.focus();
    }
}

// Validation des cartes
function validateCardNumber(input) {
    const value = input.value.replace(/\D/g, '');
    if (value.length !== 16) {
        input.setCustomValidity('Le numéro de carte doit contenir 16 chiffres');
    } else {
        input.setCustomValidity('');
    }
}

function validateCardCode(input) {
    const value = input.value.replace(/\D/g, '');
    if (value.length !== 3) {
        input.setCustomValidity('Le code de sécurité doit contenir 3 chiffres');
    } else {
        input.setCustomValidity('');
    }
}

// Validation des dates
function validateDates(startId, endId) {
    const start = new Date(document.getElementById(startId).value);
    const end = new Date(document.getElementById(endId).value);
    
    if (start >= end) {
        alert('La date de fin doit être après la date de début');
        return false;
    }
    
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > 365) {
        alert('La durée maximum de location est de 365 jours');
        return false;
    }
    
    return true;
}

// Ajouter des confirmations pour les actions de suppression
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('button[onclick*="delete"]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
                e.preventDefault();
                return false;
            }
        });
    });
});
</script>
</body>
</html>
<?php
// Fin du fichier