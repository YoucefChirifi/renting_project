<?php
/***************************************************************
 * DZLocation - Système de Location de Voitures en Algérie
 * Application complète avec 4 rôles (Client, Agent, Administrateur,Owner)
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
    private $dbname = "Youcef_cherifi_agency";
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
        
        // Table owner (propriétaire du site)
        $sql = "CREATE TABLE IF NOT EXISTS owner (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
    }
    
    private function seedData() {
        // CHECK IF DATA ALREADY SEEDED - Only seed once!
        $checkSeeded = $this->conn->query("SELECT COUNT(*) as count FROM owner");
        if ($checkSeeded && $checkSeeded->fetch_assoc()['count'] > 0) {
            return;
        }
        
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
        
        // Créer le propriétaire du site
        $checkOwner = $this->conn->query("SELECT id FROM owner WHERE email = 'chirifi.youssouf@owner.com'");
        if ($checkOwner->num_rows == 0) {
            $hashed_password = password_hash('owner123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO owner (nom, prenom, email, password) VALUES ('Chirifi', 'Youssouf', 'chirifi.youssouf@owner.com', '$hashed_password')");
        }
        
        // Créer trois entreprises algériennes
        $companies = [
            ['name' => 'Location Auto Alger', 'frais' => 75000, 'code' => 'LAA001'],
            ['name' => 'Rent Car Oran', 'frais' => 65000, 'code' => 'RCO001'],
            ['name' => 'Auto Location Constantine', 'frais' => 70000, 'code' => 'ALC001']
        ];
        
        foreach ($companies as $comp) {
            $checkComp = $this->conn->query("SELECT company_id FROM company WHERE c_name = '{$comp['name']}'");
            if ($checkComp->num_rows == 0) {
                $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code) VALUES ('{$comp['name']}', {$comp['frais']}, '{$comp['code']}')");
                $company_id = $this->conn->insert_id;
                
                $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
                $admin_email = strtolower(str_replace(' ', '', $comp['name'])) . '@admin.com';
                $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password) VALUES ('Admin', 'Principal', 30, '0555000000', 'Algérienne', '0000000000000000', 16, 120000, $company_id, '$admin_email', '$hashed_password')");
                
                $agent_email = strtolower(str_replace(' ', '', $comp['name'])) . '@agent.com';
                $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password) VALUES ('Agent', 'Principal', 28, '0555000001', 'Algérienne', '0000000000000001', 16, 80000, $company_id, '$agent_email', '$hashed_password')");
                
                $client_names = [
                    ['nom' => 'Benali', 'prenom' => 'Ahmed', 'wilaya' => 16],
                    ['nom' => 'Kadri', 'prenom' => 'Fatima', 'wilaya' => 31]
                ];
                
                foreach ($client_names as $idx => $client_data) {
                    $client_email = strtolower(str_replace(' ', '', $comp['name'])) . '.client' . ($idx + 1) . '@client.com';
                    $client_carte = '100000000000000' . ($idx + 1);
                    $client_tel = '05550000' . str_pad($idx + 10, 2, '0', STR_PAD_LEFT);
                    $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password) VALUES ('{$client_data['nom']}', '{$client_data['prenom']}', 25, '$client_tel', 'Algérienne', '$client_carte', {$client_data['wilaya']}, 'non reserve', $company_id, '$client_email', '$hashed_password')");
                }
                
                $cars_data = [
                    ['marque' => 'Renault', 'model' => 'Symbol', 'color' => 'Blanc', 'category' => 1, 'prix' => 5000],
                    ['marque' => 'Peugeot', 'model' => '208', 'color' => 'Gris', 'category' => 2, 'prix' => 8000],
                    ['marque' => 'Hyundai', 'model' => 'i10', 'color' => 'Bleu', 'category' => 1, 'prix' => 4500]
                ];
                
                foreach ($cars_data as $idx => $car_data) {
                    $serial = rand(10000, 99999);
                    $year_short = date('y');
                    $part2 = $car_data['category'] . $year_short;
                    $wilaya = 31;
                    $matricule = "$serial $part2 $wilaya";
                    $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work) VALUES ($company_id, '{$car_data['marque']}', '{$car_data['model']}', '{$car_data['color']}', " . date('Y') . ", {$car_data['category']}, {$car_data['prix']}, 1, '$matricule', 'disponible')");
                }
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
                if ($role != 'owner') {
                    $company_id = $user['company_id'] ?? 0;
                    if ($company_id > 0) {
                        $check_company = $this->db->query("SELECT company_id FROM company WHERE company_id = $company_id");
                        if ($check_company->num_rows == 0) {
                            return false;
                        }
                    }
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                
                if ($role == 'owner') {
                    $_SESSION['company_id'] = 0;
                } else {
                    $_SESSION['company_id'] = $user['company_id'] ?? 1;
                }
                
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
            'administrator' => 'administrator',
            'owner' => 'owner'
        ];
        return $tables[$role] ?? 'client';
    }
    
    public function registerClient($data) {
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, email, password, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissssisi", $data['nom'], $data['prenom'], $data['age'], $data['numero_tlfn'], $data['nationalite'], $data['numero_cart_national'], $data['wilaya_id'], $data['email'], $hashed_password, $data['company_id']);
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
        $sql = "SELECT c.*, co.c_name as company_name FROM car c JOIN company co ON c.company_id = co.company_id WHERE c.voiture_work = 'disponible'";
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
        $start = new DateTime($data['start_date']);
        $end = new DateTime($data['end_date']);
        $period = $start->diff($end)->days;
        
        if ($period <= 0) {
            return false;
        }
        
        $car_stmt = $this->db->prepare("SELECT prix_day FROM car WHERE id_car = ?");
        $car_stmt->bind_param("i", $data['car_id']);
        $car_stmt->execute();
        $car_result = $car_stmt->get_result();
        
        if ($car_result->num_rows == 0) {
            return false;
        }
        
        $car = $car_result->fetch_assoc();
        $montant = $period * $car['prix_day'];
        
        $stmt = $this->db->prepare("INSERT INTO reservation (id_client, id_company, car_id, wilaya_id, start_date, end_date, period, montant, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("iiiissid", $data['id_client'], $data['id_company'], $data['car_id'], $data['wilaya_id'], $data['start_date'], $data['end_date'], $period, $montant);
        
        if ($stmt->execute()) {
            $reservation_id = $this->db->conn->insert_id;
            $this->db->query("UPDATE car SET voiture_work = 'non disponible' WHERE id_car = {$data['car_id']}");
            $this->db->query("UPDATE client SET status = 'reserve' WHERE id = {$data['id_client']}");
            return $reservation_id;
        }
        return false;
    }
    
    public function processPayment($reservation_id, $card_number, $card_code) {
        if (strlen($card_number) != 16 || !is_numeric($card_number)) {
            return false;
        }
        if (strlen($card_code) != 3 || !is_numeric($card_code)) {
            return false;
        }
        
        $stmt = $this->db->prepare("SELECT montant FROM reservation WHERE id_reservation = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        $reservation = $result->fetch_assoc();
        $payment_stmt = $this->db->prepare("INSERT INTO payment (amount, card_number, card_code, status, payment_date) VALUES (?, ?, ?, 'paid', NOW())");
        $payment_stmt->bind_param("dss", $reservation['montant'], $card_number, $card_code);
        
        if ($payment_stmt->execute()) {
            $payment_id = $this->db->conn->insert_id;
            $this->db->query("UPDATE reservation SET id_payment = $payment_id WHERE id_reservation = $reservation_id");
            $this->db->query("UPDATE client SET status = 'payer' WHERE id = (SELECT id_client FROM reservation WHERE id_reservation = $reservation_id)");
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
        
        $sql = "SELECT $date_format as period, COUNT(*) as total_reservations, SUM(r.montant) as total_amount, AVG(r.montant) as avg_amount FROM reservation r WHERE r.id_company = $company_id GROUP BY $group_by ORDER BY period DESC LIMIT 10";
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
    
    public function verifyCompanyExists($company_id) {
        if ($company_id <= 0) return true;
        $check = $this->db->query("SELECT company_id FROM company WHERE company_id = $company_id");
        return $check->num_rows > 0;
    }
}

/*************************************************************** 
 * PARTIE 3: TRAITEMENT DES ACTIONS
 ***************************************************************/

$db = new Database();
$auth = new Auth($db);
$app = new CarRentalApp($db, $auth);

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $auth->logout();
}

if (isset($_GET['action']) && $_GET['action'] == 'get_accounts' && isset($_GET['role'])) {
    header('Content-Type: application/json');
    $role = $_GET['role'];
    $accounts = [];
    
    $default_passwords = [
        'owner' => 'owner123',
        'administrator' => 'admin123',
        'agent' => 'admin123',
        'client' => 'admin123'
    ];
    
    $default_password = $default_passwords[$role] ?? 'admin123';
    $default_password_hash = password_hash($default_password, PASSWORD_DEFAULT);
    
    function getPasswordDisplay($stored_hash, $default_hash, $default_password) {
        if (password_verify($default_password, $stored_hash)) {
            return $default_password;
        } else {
            return 'Mot de passe personnalisé';
        }
    }
    
    switch ($role) {
        case 'owner':
            $result = $db->query("SELECT email, password FROM owner ORDER BY id LIMIT 3");
            while ($row = $result->fetch_assoc()) {
                $accounts[] = [
                    'email' => $row['email'],
                    'password' => getPasswordDisplay($row['password'], $default_password_hash, $default_password)
                ];
            }
            break;
        case 'administrator':
            $result = $db->query("SELECT DISTINCT a.email, a.password FROM administrator a INNER JOIN company c ON a.company_id = c.company_id GROUP BY a.company_id ORDER BY a.id LIMIT 3");
            while ($row = $result->fetch_assoc()) {
                $accounts[] = [
                    'email' => $row['email'],
                    'password' => getPasswordDisplay($row['password'], $default_password_hash, $default_password)
                ];
            }
            break;
        case 'agent':
            $result = $db->query("SELECT DISTINCT a.email, a.password FROM agent a INNER JOIN company c ON a.company_id = c.company_id GROUP BY a.company_id ORDER BY a.id LIMIT 3");
            while ($row = $result->fetch_assoc()) {
                $accounts[] = [
                    'email' => $row['email'],
                    'password' => getPasswordDisplay($row['password'], $default_password_hash, $default_password)
                ];
            }
            break;
        case 'client':
            $result = $db->query("SELECT c.email, c.password, c.company_id FROM client c INNER JOIN company comp ON c.company_id = comp.company_id ORDER BY c.company_id, c.id");
            $company_counts = [];
            while ($row = $result->fetch_assoc()) {
                $company_id = $row['company_id'];
                if (!isset($company_counts[$company_id])) {
                    $company_counts[$company_id] = 0;
                }
                if ($company_counts[$company_id] < 2) {
                    $accounts[] = [
                        'email' => $row['email'],
                        'password' => getPasswordDisplay($row['password'], $default_password_hash, $default_password)
                    ];
                    $company_counts[$company_id]++;
                }
            }
            break;
    }
    
    echo json_encode($accounts);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    
    if ($auth->login($email, $password, $role)) {
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
            case 'owner':
                $redirect = 'owner';
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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_company']) && $auth->getUserRole() == 'owner') {
    $c_name = $_POST['c_name'] ?? '';
    $frais_mensuel = $_POST['frais_mensuel'] ?? 0;
    $special_code = $_POST['special_code'] ?? '';
    
    if ($c_name && $frais_mensuel >= 30000 && $frais_mensuel <= 150000) {
        $stmt = $db->prepare("INSERT INTO company (c_name, frais_mensuel, special_code) VALUES (?, ?, ?)");
        $stmt->bind_param("sds", $c_name, $frais_mensuel, $special_code);
        if ($stmt->execute()) {
            $company_success = "Compagnie ajoutée avec succès!";
        } else {
            $company_error = "Erreur lors de l'ajout de la compagnie.";
        }
    } else {
        $company_error = "Données invalides. Le frais mensuel doit être entre 30000 et 150000 DA.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_admin']) && $auth->getUserRole() == 'owner') {
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $age = $_POST['age'] ?? 0;
    $numero_tlfn = $_POST['numero_tlfn'] ?? '';
    $nationalite = $_POST['nationalite'] ?? '';
    $numero_cart_national = $_POST['numero_cart_national'] ?? '';
    $wilaya_id = $_POST['wilaya_id'] ?? 16;
    $salaire = $_POST['salaire'] ?? 0;
    $company_id = $_POST['company_id'] ?? 0;
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($nom && $prenom && $age >= 24 && $email && $password && $company_id > 0) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissssdiss", $nom, $prenom, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $salaire, $company_id, $email, $hashed_password);
        if ($stmt->execute()) {
            $admin_success = "Administrateur ajouté avec succès!";
        } else {
            $admin_error = "Erreur lors de l'ajout de l'administrateur. L'email existe peut-être déjà.";
        }
    } else {
        $admin_error = "Veuillez remplir tous les champs obligatoires. L'âge minimum est de 24 ans.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_company']) && $auth->getUserRole() == 'owner') {
    $company_id = $_POST['company_id'] ?? 0;
    $c_name = $_POST['c_name'] ?? '';
    $frais_mensuel = $_POST['frais_mensuel'] ?? 0;
    $special_code = $_POST['special_code'] ?? '';
    
    if ($company_id > 0 && $c_name && $frais_mensuel >= 30000 && $frais_mensuel <= 150000) {
        $stmt = $db->prepare("UPDATE company SET c_name=?, frais_mensuel=?, special_code=? WHERE company_id=?");
        $stmt->bind_param("sdsi", $c_name, $frais_mensuel, $special_code, $company_id);
        if ($stmt->execute()) {
            $company_success = "Entreprise mise à jour avec succès!";
        } else {
            $company_error = "Erreur lors de la mise à jour de l'entreprise.";
        }
    } else {
        $company_error = "Données invalides.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_company']) && $auth->getUserRole() == 'owner') {
    $company_id = $_POST['company_id'] ?? 0;
    
    if ($company_id > 0) {
        $db->conn->begin_transaction();
        try {
            $reservation_ids = [];
            $result = $db->query("SELECT id_reservation FROM reservation WHERE id_company = $company_id");
            while ($row = $result->fetch_assoc()) {
                $reservation_ids[] = $row['id_reservation'];
            }
            
            if (!empty($reservation_ids)) {
                $placeholders = str_repeat('?,', count($reservation_ids) - 1) . '?';
                $stmt = $db->prepare("DELETE FROM reservation WHERE id_reservation IN ($placeholders)");
                $stmt->bind_param(str_repeat('i', count($reservation_ids)), ...$reservation_ids);
                $stmt->execute();
            }
            
            $stmt = $db->prepare("DELETE FROM client WHERE company_id=?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM agent WHERE company_id=?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM administrator WHERE company_id=?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM car WHERE company_id=?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM company WHERE company_id=?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            $db->conn->commit();
            $company_success = "Entreprise supprimée avec succès!";
        } catch (Exception $e) {
            $db->conn->rollback();
            $company_error = "Erreur lors de la suppression: " . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_admin']) && $auth->getUserRole() == 'owner') {
    $admin_id = $_POST['admin_id'] ?? 0;
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $age = $_POST['age'] ?? 0;
    $numero_tlfn = $_POST['numero_tlfn'] ?? '';
    $nationalite = $_POST['nationalite'] ?? '';
    $numero_cart_national = $_POST['numero_cart_national'] ?? '';
    $wilaya_id = $_POST['wilaya_id'] ?? 16;
    $salaire = $_POST['salaire'] ?? 0;
    $company_id = $_POST['company_id'] ?? 0;
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($admin_id > 0 && $nom && $prenom && $age >= 24 && $email && $company_id > 0) {
        if (empty($password)) {
            $stmt = $db->prepare("UPDATE administrator SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, numero_cart_national=?, wilaya_id=?, salaire=?, company_id=?, email=? WHERE id=?");
            $stmt->bind_param("ssisssidisi", $nom, $prenom, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $salaire, $company_id, $email, $admin_id);
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE administrator SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, numero_cart_national=?, wilaya_id=?, salaire=?, company_id=?, email=?, password=? WHERE id=?");
            $stmt->bind_param("ssisssidissi", $nom, $prenom, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $salaire, $company_id, $email, $hashed_password, $admin_id);
        }
        
        if ($stmt->execute()) {
            $admin_success = "Administrateur mis à jour avec succès!";
        } else {
            $admin_error = "Erreur lors de la mise à jour.";
        }
    } else {
        $admin_error = "Données invalides.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_admin']) && $auth->getUserRole() == 'owner') {
    $admin_id = $_POST['admin_id'] ?? 0;
    if ($admin_id > 0) {
        $stmt = $db->prepare("DELETE FROM administrator WHERE id=?");
        $stmt->bind_param("i", $admin_id);
        if ($stmt->execute()) {
            $admin_success = "Administrateur supprimé!";
        } else {
            $admin_error = "Erreur lors de la suppression.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_agent']) && $auth->getUserRole() == 'owner') {
    $agent_id = $_POST['agent_id'] ?? 0;
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $age = $_POST['age'] ?? 0;
    $numero_tlfn = $_POST['numero_tlfn'] ?? '';
    $nationalite = $_POST['nationalite'] ?? '';
    $numero_cart_national = $_POST['numero_cart_national'] ?? '';
    $wilaya_id = $_POST['wilaya_id'] ?? 16;
    $salaire = $_POST['salaire'] ?? 0;
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $company_id = $_POST['company_id'] ?? 0;
    
    if ($agent_id > 0 && $nom && $prenom && $age >= 24 && $email && $company_id > 0) {
        if (empty($password)) {
            $stmt = $db->prepare("UPDATE agent SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, numero_cart_national=?, wilaya_id=?, salaire=?, email=?, company_id=? WHERE id=?");
            $stmt->bind_param("ssisssidsi", $nom, $prenom, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $salaire, $email, $company_id, $agent_id);
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE agent SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, numero_cart_national=?, wilaya_id=?, salaire=?, email=?, company_id=?, password=? WHERE id=?");
            $stmt->bind_param("ssisssidssi", $nom, $prenom, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $salaire, $email, $company_id, $hashed_password, $agent_id);
        }
        
        if ($stmt->execute()) {
            $agent_success = "Agent mis à jour!";
        } else {
            $agent_error = "Erreur lors de la mise à jour.";
        }
    } else {
        $agent_error = "Données invalides.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_client']) && $auth->getUserRole() == 'owner') {
    $client_id = $_POST['client_id'] ?? 0;
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $age = $_POST['age'] ?? 0;
    $numero_tlfn = $_POST['numero_tlfn'] ?? '';
    $nationalite = $_POST['nationalite'] ?? '';
    $numero_cart_national = $_POST['numero_cart_national'] ?? '';
    $wilaya_id = $_POST['wilaya_id'] ?? 16;
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $company_id = $_POST['company_id'] ?? 0;
    
    if ($client_id > 0 && $nom && $prenom && $age >= 24 && $email && $company_id > 0) {
        if (empty($password)) {
            $stmt = $db->prepare("UPDATE client SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, numero_cart_national=?, wilaya_id=?, email=?, company_id=? WHERE id=?");
            $stmt->bind_param("ssissssiii", $nom, $prenom, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $email, $company_id, $client_id);
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE client SET nom=?, prenom=?, age=?, numero_tlfn=?, nationalite=?, numero_cart_national=?, wilaya_id=?, email=?, company_id=?, password=? WHERE id=?");
            $stmt->bind_param("ssissssiisi", $nom, $prenom, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $email, $company_id, $hashed_password, $client_id);
        }
        
        if ($stmt->execute()) {
            $client_success = "Client mis à jour!";
        } else {
            $client_error = "Erreur lors de la mise à jour.";
        }
    } else {
        $client_error = "Données invalides.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_owner_profile']) && $auth->getUserRole() == 'owner') {
    $owner_id = $auth->getUserId();
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($nom && $prenom && $email) {
        $password_update = '';
        $types = "sssi";
        $values = [$nom, $prenom, $email, $owner_id];
        
        if (!empty($password)) {
            $password_update = ", password=?";
            $types .= "s";
            $values[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $stmt = $db->prepare("UPDATE owner SET nom=?, prenom=?, email=?$password_update WHERE id=?");
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $prenom . ' ' . $nom;
            $_SESSION['user_email'] = $email;
            $owner_profile_success = "Profil mis à jour!";
        } else {
            $owner_profile_error = "Erreur lors de la mise à jour.";
        }
    } else {
        $owner_profile_error = "Veuillez remplir tous les champs!";
    }
}

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
        case 'owner':
            $page = 'owner';
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DZLocation - Location de Voitures en Algérie</title>
    <style>
        /* ===== VANILLA CSS SYSTEM (Replaces Tailwind) ===== */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-primary: #2563eb;
            --color-primary-dark: #1e40af;
            --color-secondary: #64748b;
            --color-background: #f8fafc;
            --color-white: #ffffff;
            --color-text: #1e293b;
            --color-text-light: #64748b;
            --color-border: #e2e8f0;
            --color-success: #10b981;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --color-info: #3b82f6;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background-color: var(--color-background);
            color: var(--color-text);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            line-height: 1.2;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        h1 { font-size: 2.25rem; }
        h2 { font-size: 1.875rem; }
        h3 { font-size: 1.5rem; }
        h4 { font-size: 1.25rem; }
        h5 { font-size: 1.125rem; }
        h6 { font-size: 1rem; }

        p { margin-bottom: 1rem; }

        /* ===== LAYOUT ===== */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .container-fluid {
            width: 100%;
            padding: 0 1rem;
        }

        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }

        .flex {
            display: flex;
        }

        .flex-row { flex-direction: row; }
        .flex-col { flex-direction: column; }
        .flex-center { justify-content: center; align-items: center; }
        .flex-between { justify-content: space-between; align-items: center; }
        .flex-around { justify-content: space-around; align-items: center; }
        .gap-1 { gap: 0.25rem; }
        .gap-2 { gap: 0.5rem; }
        .gap-4 { gap: 1rem; }
        .gap-6 { gap: 1.5rem; }
        .gap-8 { gap: 2rem; }

        /* ===== SPACING ===== */
        .p-0 { padding: 0; }
        .p-2 { padding: 0.5rem; }
        .p-4 { padding: 1rem; }
        .p-6 { padding: 1.5rem; }
        .p-8 { padding: 2rem; }

        .px-4 { padding-left: 1rem; padding-right: 1rem; }
        .py-4 { padding-top: 1rem; padding-bottom: 1rem; }

        .m-0 { margin: 0; }
        .m-4 { margin: 1rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mt-4 { margin-top: 1rem; }
        .mt-8 { margin-top: 2rem; }

        /* ===== BUTTONS ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background-color: var(--color-primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background-color: var(--color-secondary);
            color: white;
        }

        .btn-secondary:hover {
            background-color: #475569;
        }

        .btn-danger {
            background-color: var(--color-danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
        }

        .btn-outline:hover {
            background-color: var(--color-primary);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.125rem;
        }

        .btn-full {
            width: 100%;
        }

        /* ===== CARDS ===== */
        .card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            background-color: #f9fafb;
        }

        .card-body {
            padding: 1.5rem;
        }

        .card-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--color-border);
            background-color: #f9fafb;
        }

        /* ===== FORMS ===== */
        .form-group {
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--color-text);
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            background-color: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-control:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        /* ===== TABLES ===== */
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .table thead {
            background-color: #f3f4f6;
            border-bottom: 2px solid var(--color-border);
        }

        .table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border);
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        /* ===== ALERTS ===== */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }

        .alert-success {
            background-color: #ecfdf5;
            border-color: var(--color-success);
            color: #065f46;
        }

        .alert-danger {
            background-color: #fef2f2;
            border-color: var(--color-danger);
            color: #7f1d1d;
        }

        .alert-warning {
            background-color: #fffbeb;
            border-color: var(--color-warning);
            color: #78350f;
        }

        .alert-info {
            background-color: #eff6ff;
            border-color: var(--color-info);
            color: #0c2d6b;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: white;
            border-bottom: 1px solid var(--color-border);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-primary);
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
            list-style: none;
        }

        .navbar-item a {
            color: var(--color-text);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .navbar-item a:hover {
            color: var(--color-primary);
        }

        /* ===== DISPLAY UTILITIES ===== */
        .d-none { display: none; }
        .d-block { display: block; }
        .d-flex { display: flex; }
        .d-grid { display: grid; }

        /* ===== TEXT UTILITIES ===== */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-primary { color: var(--color-primary); }
        .text-secondary { color: var(--color-secondary); }
        .text-success { color: var(--color-success); }
        .text-danger { color: var(--color-danger); }
        .text-muted { color: var(--color-text-light); }

        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        .font-normal { font-weight: 400; }

        .text-sm { font-size: 0.875rem; }
        .text-base { font-size: 1rem; }
        .text-lg { font-size: 1.125rem; }
        .text-xl { font-size: 1.25rem; }

        /* ===== BADGES ===== */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-primary {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* ===== MODALS ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 0.75rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--color-text-light);
        }

        /* ===== HERO SECTION ===== */
        .hero {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            background: white;
            border-right: 1px solid var(--color-border);
            min-height: 100vh;
            padding: 2rem 0;
            position: sticky;
            top: 60px;
            max-height: calc(100vh - 60px);
            overflow-y: auto;
        }

        .sidebar-item {
            padding: 0.75rem 1.5rem;
            border-left: 4px solid transparent;
            cursor: pointer;
            transition: var(--transition);
            display: block;
            color: var(--color-text);
            text-decoration: none;
        }

        .sidebar-item:hover {
            background-color: #f3f4f6;
            border-left-color: var(--color-primary);
        }

        .sidebar-item.active {
            background-color: #eff6ff;
            border-left-color: var(--color-primary);
            color: var(--color-primary);
            font-weight: 600;
        }

        /* ===== STATISTICS CARDS ===== */
        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            border: 1px solid var(--color-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-primary);
            margin: 0.5rem 0;
        }

        .stat-card-label {
            color: var(--color-text-light);
            font-size: 0.875rem;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            h1 { font-size: 1.875rem; }
            h2 { font-size: 1.5rem; }
            h3 { font-size: 1.25rem; }

            .navbar-menu {
                gap: 1rem;
            }

            .grid-2, .grid-3, .grid-4 {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 0.875rem;
            }

            .table th, .table td {
                padding: 0.75rem 0.5rem;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }
        }

        /* ===== LOADING ANIMATION ===== */
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid var(--color-border);
            border-top-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

    </style>
</head>
<body>

<!-- HEADER / NAVBAR -->
<nav class="navbar">
    <div class="navbar-container">
        <a href="index.php" class="navbar-brand">DZLocation</a>
        <ul class="navbar-menu">
            <li class="navbar-item"><a href="#home">Accueil</a></li>
            <li class="navbar-item"><a href="#about">À propos</a></li>
            <li class="navbar-item">
                <?php if ($auth->isLoggedIn()): ?>
                    <a href="?action=logout">Déconnexion</a>
                <?php else: ?>
                    <a href="#login">Connexion</a>
                <?php endif; ?>
            </li>
        </ul>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container mt-8">

    <?php if ($page == 'home' && !$auth->isLoggedIn()): ?>
        <!-- HOME PAGE - LOGIN -->
        <div style="max-width: 500px; margin: 0 auto;">
            <div class="card">
                <div class="card-header text-center">
                    <h2>Connexion</h2>
                    <p class="text-muted" style="margin: 0;">Service de location de voitures en Algérie</p>
                </div>
                <div class="card-body">
                    <?php if (isset($login_error)): ?>
                        <div class="alert alert-danger"><?php echo $login_error; ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="role" class="form-label">Rôle</label>
                            <select name="role" id="role" class="form-control" onchange="loadAccounts()">
                                <option value="client">Client</option>
                                <option value="agent">Agent</option>
                                <option value="administrator">Administrateur</option>
                                <option value="owner">Propriétaire</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="Entrez votre email" required>
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Entrez votre mot de passe" required>
                        </div>

                        <button type="submit" name="login" class="btn btn-primary btn-full">Se connecter</button>
                    </form>

                    <hr style="margin: 1.5rem 0;">

                    <div id="accountsInfo" style="background-color: #f3f4f6; padding: 1rem; border-radius: 0.5rem;">
                        <p class="font-semibold mb-4">Comptes de test disponibles:</p>
                        <div id="accountsList"></div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($auth->isLoggedIn()): ?>
        <!-- AUTHENTICATED CONTENT -->
        <?php if ($page == 'client'): ?>
            <h1>Réservation de Voitures</h1>
            <div class="grid grid-3 mt-8">
                <div class="stat-card">
                    <div class="stat-card-label">Votre Statut</div>
                    <div class="stat-card-value">
                        <?php 
                            $client_result = $db->query("SELECT status FROM client WHERE email = '{$_SESSION['user_email']}'");
                            $client = $client_result->fetch_assoc();
                            echo ucfirst($client['status']);
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Voitures Disponibles</div>
                    <div class="stat-card-value">
                        <?php 
                            $cars_result = $db->query("SELECT COUNT(*) as count FROM car WHERE voiture_work = 'disponible'");
                            $cars_count = $cars_result->fetch_assoc();
                            echo $cars_count['count'];
                        ?>
                    </div>
                </div>
            </div>

            <div class="card mt-8">
                <div class="card-header">
                    <h3>Voitures Disponibles</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Marque</th>
                                    <th>Modèle</th>
                                    <th>Couleur</th>
                                    <th>Année</th>
                                    <th>Plaque</th>
                                    <th>État</th>
                                    <th>Prix/jour</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $cars = $app->getAvailableCars();
                                    while ($car = $cars->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo $car['marque']; ?></td>
                                    <td><?php echo $car['model']; ?></td>
                                    <td><?php echo $car['color']; ?></td>
                                    <td><?php echo $car['annee']; ?></td>
                                    <td><?php echo $car['matricule']; ?></td>
                                    <td><span class="badge badge-success"><?php echo $app->getCarStatusText($car['status_voiture']); ?></span></td>
                                    <td><?php echo number_format($car['prix_day'], 0, ',', ' '); ?> DA</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'agent'): ?>
            <h1>Tableau de Bord Agent</h1>
            <div class="grid grid-3 mt-8">
                <div class="stat-card">
                    <div class="stat-card-label">Clients</div>
                    <div class="stat-card-value">
                        <?php 
                            $clients_result = $db->query("SELECT COUNT(*) as count FROM client WHERE company_id = {$_SESSION['company_id']}");
                            echo $clients_result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Voitures Disponibles</div>
                    <div class="stat-card-value">
                        <?php 
                            $cars_result = $db->query("SELECT COUNT(*) as count FROM car WHERE company_id = {$_SESSION['company_id']} AND voiture_work = 'disponible'");
                            echo $cars_result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Réservations (30j)</div>
                    <div class="stat-card-value">
                        <?php 
                            $reservations_result = $db->query("SELECT COUNT(*) as count FROM reservation WHERE id_company = {$_SESSION['company_id']} AND MONTH(start_date) = MONTH(NOW()) AND YEAR(start_date) = YEAR(NOW())");
                            echo $reservations_result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'admin'): ?>
            <h1>Tableau de Bord Administrateur</h1>
            <div class="grid grid-4 mt-8">
                <div class="stat-card">
                    <div class="stat-card-label">Total Clients</div>
                    <div class="stat-card-value">
                        <?php 
                            $result = $db->query("SELECT COUNT(*) as count FROM client WHERE company_id = {$_SESSION['company_id']}");
                            echo $result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Voitures</div>
                    <div class="stat-card-value">
                        <?php 
                            $result = $db->query("SELECT COUNT(*) as count FROM car WHERE company_id = {$_SESSION['company_id']}");
                            echo $result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Agents</div>
                    <div class="stat-card-value">
                        <?php 
                            $result = $db->query("SELECT COUNT(*) as count FROM agent WHERE company_id = {$_SESSION['company_id']}");
                            echo $result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Revenus (30j)</div>
                    <div class="stat-card-value text-success">
                        <?php 
                            $result = $db->query("SELECT SUM(montant) as total FROM reservation WHERE id_company = {$_SESSION['company_id']} AND MONTH(start_date) = MONTH(NOW()) AND YEAR(start_date) = YEAR(NOW())");
                            $total = $result->fetch_assoc()['total'] ?? 0;
                            echo number_format($total, 0, ',', ' ');
                        ?> DA
                    </div>
                </div>
            </div>

        <?php elseif ($page == 'owner'): ?>
            <h1>Tableau de Bord Propriétaire</h1>
            <div class="grid grid-4 mt-8">
                <div class="stat-card">
                    <div class="stat-card-label">Total Entreprises</div>
                    <div class="stat-card-value">
                        <?php 
                            $result = $db->query("SELECT COUNT(*) as count FROM company");
                            echo $result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Administrateurs</div>
                    <div class="stat-card-value">
                        <?php 
                            $result = $db->query("SELECT COUNT(*) as count FROM administrator");
                            echo $result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Agents</div>
                    <div class="stat-card-value">
                        <?php 
                            $result = $db->query("SELECT COUNT(*) as count FROM agent");
                            echo $result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">Clients Totaux</div>
                    <div class="stat-card-value">
                        <?php 
                            $result = $db->query("SELECT COUNT(*) as count FROM client");
                            echo $result->fetch_assoc()['count'];
                        ?>
                    </div>
                </div>
            </div>

            <div class="card mt-8">
                <div class="card-header flex flex-between">
                    <h3>Gestion des Entreprises</h3>
                    <button class="btn btn-primary btn-sm" onclick="openAddCompanyModal()">+ Ajouter</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Frais Mensuel</th>
                                    <th>Code Spécial</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                    $companies = $db->query("SELECT * FROM company");
                                    while ($company = $companies->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td><?php echo $company['company_id']; ?></td>
                                    <td><?php echo $company['c_name']; ?></td>
                                    <td><?php echo number_format($company['frais_mensuel'], 0, ',', ' '); ?> DA</td>
                                    <td><?php echo $company['special_code']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline" onclick="editCompany(<?php echo $company['company_id']; ?>)">Éditer</button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteCompany(<?php echo $company['company_id']; ?>)">Supprimer</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    <?php else: ?>
        <div class="alert alert-warning">
            Veuillez d'abord vous connecter.
        </div>
    <?php endif; ?>

</div>

<script>
    // Load test accounts
    function loadAccounts() {
        const role = document.getElementById('role').value;
        fetch(`?action=get_accounts&role=${role}`)
            .then(response => response.json())
            .then(accounts => {
                let html = '';
                accounts.forEach(account => {
                    html += `
                        <div style="margin-bottom: 0.75rem; padding: 0.75rem; background: white; border-radius: 0.375rem;">
                            <div><strong>${account.email}</strong></div>
                            <div class="text-sm text-muted">Mot de passe: ${account.password}</div>
                        </div>
                    `;
                });
                document.getElementById('accountsList').innerHTML = html;
            });
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadAccounts();
    });

    // Company management functions
    function openAddCompanyModal() {
        alert('Formulaire d\'ajout d\'entreprise');
    }

    function editCompany(companyId) {
        alert('Édition entreprise #' + companyId);
    }

    function deleteCompany(companyId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette entreprise?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="delete_company" value="1"><input type="hidden" name="company_id" value="' + companyId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

</body>
</html>
