
<?php
/***************************************************************
 * DZLocation - Système de Location de Voitures en Algérie
 * Application complète avec 3 rôles (Client, Agent, Administrateur)
 * Propriétaire: Cherifi Youssouf
 * Langue: Français - Devise: DA
 ***************************************************************/

// Démarrage de la session
session_start();

// Configuration de l'affichage des erreurs
// Configuration de l'affichage des erreurs
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    // Mode développement
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Mode production
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

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
            frais_mensuel DECIMAL(10,2) DEFAULT 50000,
            special_code VARCHAR(50),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);
        
        // Table super_admin (Cherifi Youssouf - Propriétaire)
        $sql = "CREATE TABLE IF NOT EXISTS super_admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
            commission_percentage DECIMAL(5,2) DEFAULT 1.5,
            total_commission DECIMAL(10,2) DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
        
        // Table reservation - CORRIGÉE (supprimé agent_commission)
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
        
        // Table agent_commission_history
        $sql = "CREATE TABLE IF NOT EXISTS agent_commission_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agent_id INT,
            reservation_id INT,
            commission_amount DECIMAL(10,2),
            commission_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agent_id) REFERENCES agent(id),
            FOREIGN KEY (reservation_id) REFERENCES reservation(id_reservation)
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
        56 => 'Djanet', 57 => 'El M\'Ghair', 58 => 'El Meniaa'
    ];
    
    foreach ($wilayas as $id => $name) {
        $check = $this->conn->query("SELECT id FROM wilaya WHERE id = $id");
        if ($check->num_rows == 0) {
            $stmt = $this->conn->prepare("INSERT INTO wilaya (id, name) VALUES (?, ?)");
            $stmt->bind_param("is", $id, $name);
            $stmt->execute();
        }
    }
    
    // FIX: Créer le super admin (Cherifi Youssouf) avec mot de passe hashé
    $checkSuperAdmin = $this->conn->query("SELECT id FROM super_admin WHERE email = 'chirifiyoucef@mail.com'");
    if ($checkSuperAdmin->num_rows == 0) {
        $hashed_password = password_hash('123', PASSWORD_DEFAULT);
        $this->conn->query("INSERT INTO super_admin (nom, prenom, email, password) 
                          VALUES ('Cherifi', 'Youssouf', 'chirifiyoucef@mail.com', '$hashed_password')");
    } else {
        // Si existe déjà, mettre à jour le mot de passe pour être sûr
        $hashed_password = password_hash('123', PASSWORD_DEFAULT);
        $this->conn->query("UPDATE super_admin SET password = '$hashed_password' WHERE email = 'chirifiyoucef@mail.com'");
    }
    
    // Vérifier si nous avons besoin de créer les compagnies
    $checkCompanies = $this->conn->query("SELECT COUNT(*) as count FROM company");
    $result = $checkCompanies->fetch_assoc();
    
    if ($result['count'] == 0) {
        // === COMPAGNIE 1: Location Auto Alger ===
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) 
                           VALUES ('Location Auto Alger', 50000.00, 'ALG001', 1)");
        $company1 = $this->conn->insert_id;
        
        // Admin pour compagnie 1
        $hashed_password = password_hash('123', PASSWORD_DEFAULT);
        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                          VALUES ('Benali', 'Karim', 35, '0555111111', 'Algérienne', 
                          '1111111111111111', 16, 80000.00, $company1, 'admin@alger.com', '$hashed_password', 1)");
        
        // Agent pour compagnie 1
        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                          VALUES ('Mansouri', 'Nassim', 28, '0555222222', 'Algérienne', 
                          '2222222222222222', 16, 50000.00, $company1, 'agent@alger.com', '$hashed_password', 1)");
        
        // Client pour compagnie 1
        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, status, company_id, email, password, created_by) 
                          VALUES ('Zeroual', 'Amine', 25, '0555333333', 'Algérienne', 
                          '3333333333333333', 16, 'non reserve', $company1, 'client@alger.com', '$hashed_password', 1)");
        
        // Voitures pour compagnie 1
        $cars1 = [
            ['Toyota', 'Corolla', 'Blanc', 2022, 1, 5000.00, 1, '111111 1 2022 16'],
            ['BMW', '3 Series', 'Noir', 2021, 2, 8000.00, 1, '222222 2 2021 16'],
            ['Mercedes', 'C-Class', 'Argent', 2023, 3, 12000.00, 1, '333333 3 2023 16']
        ];
        
        foreach ($cars1 as $car) {
            $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) 
                              VALUES ($company1, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
        }
        
        // === COMPAGNIE 2: Auto Location Oran ===
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) 
                           VALUES ('Auto Location Oran', 45000.00, 'ORAN002', 1)");
        $company2 = $this->conn->insert_id;
        
        // Admin pour compagnie 2
        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                          VALUES ('Bouguerra', 'Samir', 40, '0555444444', 'Algérienne', 
                          '4444444444444444', 31, 75000.00, $company2, 'admin@oran.com', '$hashed_password', 1)");
        
        // Agent pour compagnie 2
        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                          VALUES ('Touati', 'Yacine', 30, '0555555555', 'Algérienne', 
                          '5555555555555555', 31, 45000.00, $company2, 'agent@oran.com', '$hashed_password', 1)");
        
        // Client pour compagnie 2
        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, status, company_id, email, password, created_by) 
                          VALUES ('Khelifi', 'Rachid', 27, '0555666666', 'Algérienne', 
                          '6666666666666666', 31, 'non reserve', $company2, 'client@oran.com', '$hashed_password', 1)");
        
        // Voitures pour compagnie 2
        $cars2 = [
            ['Renault', 'Clio', 'Bleu', 2021, 1, 4500.00, 1, '444444 1 2021 31'],
            ['Audi', 'A4', 'Gris', 2022, 2, 9000.00, 1, '555555 2 2022 31'],
            ['Porsche', 'Panamera', 'Noir', 2023, 3, 18000.00, 1, '666666 3 2023 31']
        ];
        
        foreach ($cars2 as $car) {
            $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) 
                              VALUES ($company2, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
        }
        
        // === COMPAGNIE 3: Location Voiture Constantine ===
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) 
                           VALUES ('Location Voiture Constantine', 55000.00, 'CONST003', 1)");
        $company3 = $this->conn->insert_id;
        
        // Admin pour compagnie 3
        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                          VALUES ('Salhi', 'Farid', 38, '0555777777', 'Algérienne', 
                          '7777777777777777', 25, 85000.00, $company3, 'admin@constantine.com', '$hashed_password', 1)");
        
        // Agent pour compagnie 3
        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                          VALUES ('Mekideche', 'Hakim', 32, '0555888888', 'Algérienne', 
                          '8888888888888888', 25, 55000.00, $company3, 'agent@constantine.com', '$hashed_password', 1)");
        
        // Client pour compagnie 3
        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, 
                          numero_cart_national, wilaya_id, status, company_id, email, password, created_by) 
                          VALUES ('Benaissa', 'Sofiane', 29, '0555999999', 'Algérienne', 
                          '9999999999999999', 25, 'non reserve', $company3, 'client@constantine.com', '$hashed_password', 1)");
        
        // Voitures pour compagnie 3
        $cars3 = [
            ['Volkswagen', 'Golf', 'Rouge', 2022, 1, 4800.00, 1, '777777 1 2022 25'],
            ['BMW', '5 Series', 'Blanc', 2022, 2, 11000.00, 1, '888888 2 2022 25'],
            ['Mercedes', 'S-Class', 'Noir', 2023, 3, 20000.00, 1, '999999 3 2023 25']
        ];
        
        foreach ($cars3 as $car) {
            $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) 
                              VALUES ($company3, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
        }
        
        // Créer une réservation de test pour démontrer la commission
        $this->createTestReservation($company1, $hashed_password);
    }
}

private function createTestReservation($company_id, $hashed_password) {
    // Créer un agent supplémentaire pour la démonstration de commission
    $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, 
                      numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                      VALUES ('Test', 'Agent', 30, '0555000000', 'Algérienne', 
                      '0000000000000000', 16, 50000.00, $company_id, 'testagent@test.com', '$hashed_password', 1)");
    $agent_id = $this->conn->insert_id;
    
    // Créer un client pour cet agent
    $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, 
                      numero_cart_national, wilaya_id, status, company_id, email, password, created_by) 
                      VALUES ('Test', 'Client', 26, '0555111222', 'Algérienne', 
                      '1234500000000000', 16, 'non reserve', $company_id, 'testclient@test.com', '$hashed_password', $agent_id)");
    $client_id = $this->conn->insert_id;
    
    // Créer une réservation pour ce client (créé par l'agent)
    $car_id = $this->conn->query("SELECT id_car FROM car WHERE company_id = $company_id LIMIT 1")->fetch_assoc()['id_car'];
    
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+3 days'));
    $period = 3;
    $montant = 15000; // 3 jours × 5000 DA/jour
    
    $this->conn->query("INSERT INTO reservation (id_agent, id_client, id_company, car_id, start_date, end_date, period, montant, status) 
                       VALUES ($agent_id, $client_id, $company_id, $car_id, '$start_date', '$end_date', $period, $montant, 'active')");
    $reservation_id = $this->conn->insert_id;
    
    // Créer un paiement
    $this->conn->query("INSERT INTO payment (amount, card_number, card_code, status, payment_date) 
                       VALUES ($montant, '1234567812345678', '123', 'paid', NOW())");
    $payment_id = $this->conn->insert_id;
    
    // Mettre à jour la réservation avec le paiement
    $this->conn->query("UPDATE reservation SET id_payment = $payment_id, status = 'completed' WHERE id_reservation = $reservation_id");
    
    // Calculer la commission (1.5% du montant)
    $commission = $montant * 0.015;
    
    // Mettre à jour la commission de l'agent
    $this->conn->query("UPDATE agent SET total_commission = total_commission + $commission WHERE id = $agent_id");
    
    // Ajouter à l'historique des commissions
    $this->conn->query("INSERT INTO agent_commission_history (agent_id, reservation_id, commission_amount, commission_date) 
                       VALUES ($agent_id, $reservation_id, $commission, CURDATE())");
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
    
    // In your Auth class login function, update this section:
public function login($email, $password, $role) {
    if ($role == 'super_admin') {
        $table = 'super_admin';
    } else {
        $table = $this->getTableByRole($role);
    }
    
    $stmt = $this->db->prepare("SELECT * FROM $table WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // DEBUG: Check what's in the database
        error_log("Email from DB: " . $user['email']);
        error_log("Password hash from DB: " . $user['password']);
        error_log("Password to verify: " . $password);
        
        // For super admin, check plain password first, then hash
        if ($role == 'super_admin') {
            // Check if password matches directly or needs hashing
            if ($password == '123' || password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                $_SESSION['company_id'] = 0; // Super admin n'a pas de compagnie
                
                // Update password to hash if it's plain
                if ($user['password'] != password_hash($password, PASSWORD_DEFAULT)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $this->db->query("UPDATE super_admin SET password = '$hashed' WHERE id = {$user['id']}");
                }
                
                return true;
            }
        } else {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                
                if ($role != 'super_admin') {
                    $_SESSION['company_id'] = $user['company_id'] ?? 1;
                } else {
                    $_SESSION['company_id'] = 0;
                }
                
                return true;
            }
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
    
    public function registerUser($data, $role, $created_by = null) {
        $table = $this->getTableByRole($role);
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        if ($role == 'client') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, 
                                       numero_cart_national, wilaya_id, email, password, company_id, created_by) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("ssissssissi", 
                $data['nom'], 
                $data['prenom'],
                $data['age'],
                $data['numero_tlfn'],
                $data['nationalite'],
                $data['numero_cart_national'],
                $data['wilaya_id'],
                $data['email'],
                $hashed_password,
                $data['company_id'],
                $created_by
            );
        } elseif ($role == 'agent') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, 
                                       numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("ssissssdissi", 
                $data['nom'], 
                $data['prenom'],
                $data['age'],
                $data['numero_tlfn'],
                $data['nationalite'],
                $data['numero_cart_national'],
                $data['wilaya_id'],
                $data['salaire'],
                $data['company_id'],
                $data['email'],
                $hashed_password,
                $created_by
            );
        } elseif ($role == 'administrator') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, 
                                       numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param("ssissssdissi", 
                $data['nom'], 
                $data['prenom'],
                $data['age'],
                $data['numero_tlfn'],
                $data['nationalite'],
                $data['numero_cart_national'],
                $data['wilaya_id'],
                $data['salaire'],
                $data['company_id'],
                $data['email'],
                $hashed_password,
                $created_by
            );
        }
        
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
        
        // CORRIGÉ: Supprimé agent_commission du INSERT
        $stmt = $this->db->prepare("INSERT INTO reservation 
                                   (id_client, id_company, car_id, start_date, end_date, period, montant, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        
        $stmt->bind_param("iiissid", 
            $data['id_client'],
            $data['id_company'],
            $data['car_id'],
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
        
        // Obtenir le montant de la réservation et l'agent
        $stmt = $this->db->prepare("SELECT r.montant, r.id_agent FROM reservation r WHERE r.id_reservation = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return false;
        }
        
        $reservation = $result->fetch_assoc();
        
        // Calculer la commission (1.5%)
        $commission = $reservation['montant'] * 0.015;
        
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
            
            // Mettre à jour la commission de l'agent s'il y en a un
            if ($reservation['id_agent'] && $commission > 0) {
                $agent_id = $reservation['id_agent'];
                
                // Mettre à jour le total des commissions de l'agent
                $this->db->query("UPDATE agent SET total_commission = total_commission + $commission WHERE id = $agent_id");
                
                // Enregistrer l'historique de commission
                $this->db->query("INSERT INTO agent_commission_history (agent_id, reservation_id, commission_amount, commission_date) 
                                 VALUES ($agent_id, $reservation_id, $commission, CURDATE())");
            }
            
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
                WHERE r.id_company = $company_id AND r.status = 'completed'
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
    
    public function getCompanyFinancials($company_id) {
        $result = [];
        
        // Revenus totaux
        $revenue = $this->db->query("
            SELECT SUM(r.montant) as total_revenue 
            FROM reservation r 
            WHERE r.id_company = $company_id AND r.status = 'completed'
        ")->fetch_assoc();
        
        // Total des salaires
        $salaries = $this->db->query("
            SELECT SUM(salaire) as total_salaries 
            FROM (
                SELECT salaire FROM administrator WHERE company_id = $company_id
                UNION ALL
                SELECT salaire FROM agent WHERE company_id = $company_id
            ) as salaries
        ")->fetch_assoc();
        
        // Total des commissions (calculé à partir de l'historique)
        $commissions = $this->db->query("
            SELECT SUM(ach.commission_amount) as total_commissions 
            FROM agent_commission_history ach
            JOIN agent a ON ach.agent_id = a.id
            WHERE a.company_id = $company_id
        ")->fetch_assoc();
        
        // Frais mensuels de la compagnie
        $company_fees = $this->db->query("
            SELECT frais_mensuel FROM company WHERE company_id = $company_id
        ")->fetch_assoc();
        
        $result['total_revenue'] = $revenue['total_revenue'] ?? 0;
        $result['total_salaries'] = $salaries['total_salaries'] ?? 0;
        $result['total_commissions'] = $commissions['total_commissions'] ?? 0;
        $result['company_fees'] = $company_fees['frais_mensuel'] ?? 50000;
        $result['total_expenses'] = $result['total_salaries'] + $result['total_commissions'] + $result['company_fees'];
        $result['net_profit'] = $result['total_revenue'] - $result['total_expenses'];
        
        return $result;
    }
    
    // Méthode pour obtenir le total des commissions par agent
    public function getAgentCommissions($agent_id) {
        $sql = "SELECT SUM(commission_amount) as total_commissions 
                FROM agent_commission_history 
                WHERE agent_id = $agent_id";
        
        $result = $this->db->query($sql)->fetch_assoc();
        return $result['total_commissions'] ?? 0;
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
            case 'super_admin':
                $redirect = 'super_admin';
                break;
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

// Traiter l'inscription par un agent
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_client'])) {
    if ($auth->isLoggedIn() && $auth->getUserRole() == 'agent') {
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
                'company_id' => $_SESSION['company_id']
            ];
            
            if ($auth->registerUser($data, 'client', $auth->getUserId())) {
                $register_success = "Client ajouté avec succès!";
            } else {
                $register_error = "Erreur lors de l'ajout du client. L'email existe peut-être déjà.";
            }
        }
    }
}

// Traiter l'ajout/modification/suppression par l'administrateur
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_car':
                    $serial = rand(100000, 999999);
                    $category = $_POST['category'];
                    $year = $_POST['annee'];
                    $wilaya = 31;
                    $matricule = "$serial $category $year $wilaya";
                    
                    $stmt = $db->prepare("INSERT INTO car (company_id, marque, model, color, annee, 
                                         category, prix_day, status_voiture, matricule, voiture_work, created_by) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?)");
                    $stmt->bind_param("isssiidisi", 
                        $_SESSION['company_id'], $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'],
                        $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $matricule, $auth->getUserId()
                    );
                    if ($stmt->execute()) {
                        $success = "Voiture ajoutée avec succès";
                    } else {
                        $error = "Erreur lors de l'ajout de la voiture";
                    }
                    break;
                    
                case 'update_car':
                    $stmt = $db->prepare("UPDATE car SET marque = ?, model = ?, color = ?, annee = ?, 
                                         category = ?, prix_day = ?, status_voiture = ?, voiture_work = ? 
                                         WHERE id_car = ? AND company_id = ?");
                    $stmt->bind_param("sssiidissi", 
                        $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'],
                        $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], 
                        $_POST['voiture_work'], $_POST['car_id'], $_SESSION['company_id']
                    );
                    if ($stmt->execute()) {
                        $success = "Voiture mise à jour avec succès";
                    } else {
                        $error = "Erreur lors de la mise à jour de la voiture";
                    }
                    break;
                    
                case 'delete_car':
                    $car_id = intval($_POST['car_id']);
                    $company_id = $_SESSION['company_id'];
                    
                    // Vérifier si la voiture appartient à la compagnie
                    $check = $db->query("SELECT id_car FROM car WHERE id_car = $car_id AND company_id = $company_id");
                    if ($check->num_rows > 0) {
                        $db->query("DELETE FROM car WHERE id_car = $car_id");
                        $success = "Voiture supprimée avec succès";
                    } else {
                        $error = "Voiture non trouvée ou vous n'avez pas les permissions";
                    }
                    break;
                    
                case 'add_agent':
                    if ($_POST['age'] < 24) {
                        $error = "L'âge minimum est de 24 ans";
                    } else {
                        $data = [
                            'nom' => $_POST['nom'],
                            'prenom' => $_POST['prenom'],
                            'age' => $_POST['age'],
                            'numero_tlfn' => $_POST['numero_tlfn'],
                            'nationalite' => $_POST['nationalite'],
                            'numero_cart_national' => $_POST['numero_cart_national'],
                            'wilaya_id' => $_POST['wilaya_id'],
                            'salaire' => $_POST['salaire'],
                            'company_id' => $_SESSION['company_id'],
                            'email' => $_POST['email'],
                            'password' => $_POST['password']
                        ];
                        
                        if ($auth->registerUser($data, 'agent', $auth->getUserId())) {
                            $success = "Agent ajouté avec succès";
                        } else {
                            $error = "Erreur lors de l'ajout de l'agent";
                        }
                    }
                    break;
                    
                case 'update_agent':
                    $stmt = $db->prepare("UPDATE agent SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, 
                                         nationalite = ?, wilaya_id = ?, salaire = ?, email = ? 
                                         WHERE id = ? AND company_id = ?");
                    $stmt->bind_param("ssisssidsii", 
                        $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                        $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['email'],
                        $_POST['agent_id'], $_SESSION['company_id']
                    );
                    if ($stmt->execute()) {
                        $success = "Agent mis à jour avec succès";
                    } else {
                        $error = "Erreur lors de la mise à jour de l'agent";
                    }
                    break;
                    
                case 'delete_agent':
                    $agent_id = intval($_POST['agent_id']);
                    $company_id = $_SESSION['company_id'];
                    
                    $check = $db->query("SELECT id FROM agent WHERE id = $agent_id AND company_id = $company_id");
                    if ($check->num_rows > 0) {
                        $db->query("DELETE FROM agent WHERE id = $agent_id");
                        $success = "Agent supprimé avec succès";
                    } else {
                        $error = "Agent non trouvé ou vous n'avez pas les permissions";
                    }
                    break;
                    
                case 'update_client':
                    $stmt = $db->prepare("UPDATE client SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, 
                                         nationalite = ?, wilaya_id = ?, email = ? 
                                         WHERE id = ? AND company_id = ?");
                    $stmt->bind_param("ssissssii", 
                        $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                        $_POST['nationalite'], $_POST['wilaya_id'], $_POST['email'],
                        $_POST['client_id'], $_SESSION['company_id']
                    );
                    if ($stmt->execute()) {
                        $success = "Client mis à jour avec succès";
                    } else {
                        $error = "Erreur lors de la mise à jour du client";
                    }
                    break;
                    
                case 'delete_client':
                    $client_id = intval($_POST['client_id']);
                    $company_id = $_SESSION['company_id'];
                    
                    $check = $db->query("SELECT id FROM client WHERE id = $client_id AND company_id = $company_id");
                    if ($check->num_rows > 0) {
                        $db->query("DELETE FROM client WHERE id = $client_id");
                        $success = "Client supprimé avec succès";
                    } else {
                        $error = "Client non trouvé ou vous n'avez pas les permissions";
                    }
                    break;
            }
        }
    }
}

// Traiter les actions du super admin (Cherifi Youssouf)
// Traiter les actions du super admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_company':
                $stmt = $db->prepare("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sdsi", 
                    $_POST['c_name'],
                    $_POST['frais_mensuel'],
                    $_POST['special_code'],
                    $auth->getUserId()
                );
                if ($stmt->execute()) {
                    $company_id = $db->conn->insert_id;
                    $success = "Compagnie créée avec succès! ID: $company_id";
                    
                    // Auto-create cars for the new company
                    autoCreateCars($db, $company_id, $auth->getUserId());
                } else {
                    $error = "Erreur: " . $db->conn->error;
                }
                break;
                
            case 'add_admin':
                if ($_POST['age'] < 24) {
                    $error = "L'âge minimum est de 24 ans";
                } else {
                    // Vérifier si l'email existe déjà
                    $check = $db->query("SELECT id FROM administrator WHERE email = '{$_POST['email']}'");
                    if ($check->num_rows > 0) {
                        $error = "Cet email est déjà utilisé";
                    } else {
                        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $db->prepare("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, 
                                             numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssissssdissi", 
                            $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                            $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'],
                            $_POST['salaire'], $_POST['company_id'], $_POST['email'], $hashed_password,
                            $auth->getUserId()
                        );
                        if ($stmt->execute()) {
                            $admin_id = $db->conn->insert_id;
                            $success = "Administrateur ajouté avec succès";
                            
                            // Auto-create agent for this admin (avec commission 1.5%)
                            autoCreateAgent($db, $_POST, $auth->getUserId(), $admin_id);
                        } else {
                            $error = "Erreur: " . $db->conn->error;
                        }
                    }
                }
                break;
                
            case 'update_admin':
                $stmt = $db->prepare("UPDATE administrator SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, 
                                     nationalite = ?, wilaya_id = ?, salaire = ?, company_id = ?, email = ? 
                                     WHERE id = ?");
                $stmt->bind_param("ssisssdssi", 
                    $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                    $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'],
                    $_POST['company_id'], $_POST['email'], $_POST['admin_id']
                );
                if ($stmt->execute()) {
                    $success = "Administrateur mis à jour avec succès";
                } else {
                    $error = "Erreur lors de la mise à jour";
                }
                break;
                
            case 'delete_company':
                $company_id = intval($_POST['company_id']);
                
                // Vérifier d'abord s'il y a des données
                $check_reservations = $db->query("SELECT COUNT(*) as count FROM reservation WHERE id_company = $company_id")->fetch_assoc();
                
                if ($check_reservations['count'] > 0) {
                    $error = "Impossible de supprimer cette compagnie car elle contient des réservations actives.";
                } else {
                    // Supprimer dans l'ordre (enfant -> parent)
                    $db->query("DELETE FROM agent_commission_history WHERE agent_id IN (SELECT id FROM agent WHERE company_id = $company_id)");
                    $db->query("DELETE FROM payment WHERE id_payment IN (SELECT id_payment FROM reservation WHERE id_company = $company_id)");
                    $db->query("DELETE FROM reservation WHERE id_company = $company_id");
                    $db->query("DELETE FROM car WHERE company_id = $company_id");
                    $db->query("DELETE FROM client WHERE company_id = $company_id");
                    $db->query("DELETE FROM agent WHERE company_id = $company_id");
                    $db->query("DELETE FROM administrator WHERE company_id = $company_id");
                    $db->query("DELETE FROM company WHERE company_id = $company_id");
                    
                    $success = "Compagnie supprimée avec succès";
                }
                break;
                
            case 'delete_admin':
                $admin_id = intval($_POST['admin_id']);
                
                // Vérifier si l'admin a créé des agents
                $check_agents = $db->query("SELECT COUNT(*) as count FROM agent WHERE created_by = $admin_id")->fetch_assoc();
                
                if ($check_agents['count'] > 0) {
                    $error = "Impossible de supprimer cet administrateur car il a créé des agents.";
                } else {
                    $db->query("DELETE FROM administrator WHERE id = $admin_id");
                    $success = "Administrateur supprimé avec succès";
                }
                break;
        }
    }
}

// Fonction pour créer automatiquement des voitures pour une nouvelle compagnie
function autoCreateCars($db, $company_id, $created_by) {
    $cars = [
        ['Toyota', 'Corolla', 'Blanc', 2022, 1, 5000, 1],
        ['BMW', '3 Series', 'Noir', 2021, 2, 8000, 1],
        ['Mercedes', 'C-Class', 'Argent', 2023, 3, 12000, 1],
        ['Renault', 'Clio', 'Bleu', 2021, 1, 4500, 1],
        ['Volkswagen', 'Golf', 'Rouge', 2022, 1, 4800, 1]
    ];
    
    foreach ($cars as $car) {
        $serial = rand(100000, 999999);
        $matricule = "$serial {$car[4]} {$car[3]} " . rand(1, 58);
        $db->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) 
                   VALUES ($company_id, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '$matricule', 'disponible', $created_by)");
    }
}

// Fonction pour créer automatiquement un agent pour un nouvel admin
function autoCreateAgent($db, $admin_data, $created_by, $admin_id) {
    $agent_email = str_replace('admin', 'agent', $admin_data['email']);
    $hashed_password = password_hash('123456', PASSWORD_DEFAULT);
    
    $db->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by, commission_percentage) 
               VALUES ('{$admin_data['nom']}', '{$admin_data['prenom']}', {$admin_data['age']}, '{$admin_data['numero_tlfn']}', '{$admin_data['nationalite']}', 
               '{$admin_data['numero_cart_national']}', {$admin_data['wilaya_id']}, 50000, {$admin_data['company_id']}, '$agent_email', '$hashed_password', $admin_id, 1.50)");
}
// Déterminer la page à afficher
$page = 'home';
if (isset($_GET['page'])) {
    $page = $_GET['page'];
} elseif ($auth->isLoggedIn()) {
    $role = $auth->getUserRole();
    switch ($role) {
        case 'super_admin':
            $page = 'super_admin';
            break;
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
                            <?php echo $_SESSION['user_role'] == 'super_admin' ? 'bg-red-100 text-red-800' :
                                  ($_SESSION['user_role'] == 'administrator' ? 'bg-purple-100 text-purple-800' : 
                                  ($_SESSION['user_role'] == 'agent' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800')); ?>">
                            <?php echo $_SESSION['user_role'] == 'super_admin' ? 'Propriétaire' : ucfirst($_SESSION['user_role']); ?>
                        </span>
                        <a href="index.php?action=logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                            <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="text-gray-700 hover:text-blue-600 px-3 py-2">
                            <i class="fas fa-home mr-1"></i>Accueil
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
if (isset($success)) {
    echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">' . htmlspecialchars($success) . '</div>';
}
if (isset($error)) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">' . htmlspecialchars($error) . '</div>';
}

// Afficher la page appropriée
switch ($page) {
    case 'home':
        displayHomePage($auth, $app, $db);
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
    case 'super_admin':
        if ($auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
            displaySuperAdminDashboard($auth, $app, $db);
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
                    Système de location de voitures professionnel en Algérie
                </p>
                <p class="text-gray-500 mt-2">
                    Propriétaire: Cherifi Youssouf
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
                    <p class="text-gray-600 mb-6">Accédez à votre compte</p>
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
                        <span class="text-gray-500 text-sm">* L'inscription se fait uniquement par un agent</span>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition">
                            <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Information sur les catégories -->
            <div class="mt-12">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Catégories de Voitures</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php
                    $categories = $app->getCategories();
                    foreach ($categories as $id => $category):
                    ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden card-hover car-category-<?php echo $id; ?>">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800"><?php echo $category['name']; ?></h3>
                                    <p class="text-gray-600">Prix journalier</p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-sm 
                                    <?php echo $id == 1 ? 'bg-green-100 text-green-800' : 
                                          ($id == 2 ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'); ?>">
                                    Catégorie <?php echo $id; ?>
                                </span>
                            </div>
                            
                            <div class="mb-4">
                                <p class="text-gray-700">
                                    <i class="fas fa-money-bill-wave text-gray-400 mr-2"></i>
                                    De <?php echo number_format($category['min_price'], 0, ',', ' '); ?> à 
                                    <?php echo number_format($category['max_price'], 0, ',', ' '); ?> DA/jour
                                </p>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-blue-600">
                                    Location flexible
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
        SELECT r.*, c.marque, c.model, c.matricule, p.status as payment_status
        FROM reservation r
        JOIN car c ON r.car_id = c.id_car
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
                case 'register_client':
                    if ($_POST['age'] < 24) {
                        $error = "L'âge minimum est de 24 ans";
                    } elseif ($_POST['password'] != $_POST['confirm_password']) {
                        $error = "Les mots de passe ne correspondent pas";
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
                            'company_id' => $company_id
                        ];
                        
                        if ($auth->registerUser($data, 'client', $agent_id)) {
                            $success = "Client ajouté avec succès!";
                        } else {
                            $error = "Erreur lors de l'ajout du client. L'email existe peut-être déjà.";
                        }
                    }
                    break;
            }
        }
    }
    
    // Obtenir les statistiques de l'agent
    $agent_info = $db->query("SELECT salaire, total_commission, commission_percentage FROM agent WHERE id = $agent_id")->fetch_assoc();
    
    // Obtenir les commissions du mois
    $current_month = date('Y-m');
    $month_commission = $db->query("
        SELECT SUM(commission_amount) as total 
        FROM agent_commission_history 
        WHERE agent_id = $agent_id 
        AND DATE_FORMAT(commission_date, '%Y-%m') = '$current_month'
    ")->fetch_assoc();
    
    // Obtenir le nombre total de clients
    $total_clients = $db->query("SELECT COUNT(*) as count FROM client WHERE created_by = $agent_id AND company_id = $company_id")->fetch_assoc();
    
    // Obtenir les réservations de l'agent
    $reservations = $db->query("
        SELECT r.*, c.marque, c.model, cl.nom as client_nom, cl.prenom as client_prenom,
               p.status as payment_status
        FROM reservation r
        JOIN car c ON r.car_id = c.id_car
        JOIN client cl ON r.id_client = cl.id
        LEFT JOIN payment p ON r.id_payment = p.id_payment
        WHERE r.id_agent = $agent_id
        ORDER BY r.start_date DESC
        LIMIT 10
    ");
    
    // Obtenir les clients de l'agent
    $clients = $db->query("
        SELECT c.*, w.name as wilaya_name 
        FROM client c 
        LEFT JOIN wilaya w ON c.wilaya_id = w.id 
        WHERE c.created_by = $agent_id AND c.company_id = $company_id
        ORDER BY c.id DESC
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
                    <p class="text-lg">Commission du mois: 
                        <span class="font-bold">
                            <?php echo number_format($month_commission['total'] ?? 0, 0, ',', ' '); ?> DA
                        </span>
                    </p>
                    <p class="text-sm text-blue-100">Total commission: 
                        <span class="font-bold">
                            <?php echo number_format($agent_info['total_commission'] ?? 0, 0, ',', ' '); ?> DA
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
        
        <!-- Statistiques rapides -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Clients Ajoutés</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $total_clients['count']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-money-bill-wave text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Salaire Mensuel</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($agent_info['salaire'], 0, ',', ' '); ?> DA
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-percentage text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Taux Commission</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $agent_info['commission_percentage']; ?>%</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Gestion des clients -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-users mr-2"></i>Mes Clients
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
            
            <!-- Réservations -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">
                    <i class="fas fa-history mr-2"></i>Mes Réservations
                </h2>
                
                <div class="space-y-4">
                    <?php while ($res = $reservations->fetch_assoc()): 
                        // Calculer la commission pour cette réservation
                        $commission = $res['montant'] * 0.015;
                    ?>
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
                                <i class="fas fa-money-bill-wave mr-1"></i>
                                <?php echo number_format($res['montant'], 0, ',', ' '); ?> DA
                            </div>
                            <div>
                                <i class="fas fa-coins mr-1"></i>
                                <?php echo number_format($commission, 0, ',', ' '); ?> DA
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
                <input type="hidden" name="register_client" value="1">
                <input type="hidden" name="action" value="register_client">
                
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
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Mot de passe</label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Confirmer le mot de passe</label>
                        <input type="password" name="confirm_password" required 
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
    
    // Obtenir les statistiques financières
    $financials = $app->getCompanyFinancials($company_id);
    
    // Obtenir les statistiques détaillées
    $stats_day = $app->getStatistics($company_id, 'day');
    $stats_month = $app->getStatistics($company_id, 'month');
    $stats_year = $app->getStatistics($company_id, 'year');
    
    // Obtenir les données
    $cars = $app->getCompanyCars($company_id);
    $agents = $db->query("SELECT * FROM agent WHERE company_id = $company_id");
    $clients = $db->query("SELECT * FROM client WHERE company_id = $company_id ORDER BY id DESC LIMIT 10");
    
    // Obtenir le nom de la compagnie
    $company_name = $db->query("SELECT c_name FROM company WHERE company_id = $company_id")->fetch_assoc();
    ?>
    
    <div class="min-h-screen">
        <!-- En-tête du tableau de bord -->
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Tableau de Bord Administrateur</h1>
                    <p class="text-purple-100 mt-2"><?php echo htmlspecialchars($company_name['c_name']); ?></p>
                </div>
                <div class="text-right">
                    <p class="text-lg">Bénéfice net: 
                        <span class="font-bold <?php echo $financials['net_profit'] >= 0 ? 'text-green-300' : 'text-red-300'; ?>">
                            <?php echo number_format($financials['net_profit'], 0, ',', ' '); ?> DA
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Vue d'ensemble financière -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-money-bill-wave text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Revenus Totaux</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($financials['total_revenue'], 0, ',', ' '); ?> DA
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-sack-dollar text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Dépenses Totales</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($financials['total_expenses'], 0, ',', ' '); ?> DA
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-user-tie text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Salaires & Commissions</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($financials['total_salaries'] + $financials['total_commissions'], 0, ',', ' '); ?> DA
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-chart-line text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-gray-600">Frais Compagnie</p>
                        <p class="text-2xl font-bold text-gray-800">
                            <?php echo number_format($financials['company_fees'], 0, ',', ' '); ?> DA
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
                                <th class="px-4 py-3 text-left">Actions</th>
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
                                    <div class="flex space-x-2">
                                        <button onclick="showEditCarForm(
                                            <?php echo $car['id_car']; ?>, 
                                            '<?php echo htmlspecialchars($car['marque']); ?>',
                                            '<?php echo htmlspecialchars($car['model']); ?>',
                                            '<?php echo htmlspecialchars($car['color']); ?>',
                                            <?php echo $car['annee']; ?>,
                                            <?php echo $car['category']; ?>,
                                            <?php echo $car['prix_day']; ?>,
                                            <?php echo $car['status_voiture']; ?>,
                                            '<?php echo $car['voiture_work']; ?>'
                                        )" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteCar(<?php echo $car['id_car']; ?>)" 
                                                class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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
                                <th class="px-4 py-3 text-left">Salaire</th>
                                <th class="px-4 py-3 text-left">Commission</th>
                                <th class="px-4 py-3 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($agent = $agents->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($agent['email']); ?></div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-green-600">
                                        <?php echo number_format($agent['salaire'], 0, ',', ' '); ?> DA
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-blue-600">
                                        <?php echo number_format($agent['total_commission'], 0, ',', ' '); ?> DA
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex space-x-2">
                                        <button onclick="showEditAgentForm(
                                            <?php echo $agent['id']; ?>, 
                                            '<?php echo htmlspecialchars($agent['nom']); ?>',
                                            '<?php echo htmlspecialchars($agent['prenom']); ?>',
                                            <?php echo $agent['age']; ?>,
                                            '<?php echo htmlspecialchars($agent['numero_tlfn']); ?>',
                                            '<?php echo htmlspecialchars($agent['nationalite']); ?>',
                                            <?php echo $agent['wilaya_id']; ?>,
                                            <?php echo $agent['salaire']; ?>,
                                            '<?php echo htmlspecialchars($agent['email']); ?>'
                                        )" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteAgent(<?php echo $agent['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Statistiques détaillées -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-bar mr-2"></i>Statistiques Détaillées
                </h2>
                <div class="flex space-x-2">
                    <button onclick="showStats('day')" id="dayBtn" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                        Journalier
                    </button>
                    <button onclick="showStats('month')" id="monthBtn" 
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        Mensuel
                    </button>
                    <button onclick="showStats('year')" id="yearBtn" 
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        Annuel
                    </button>
                </div>
            </div>
            
            <!-- Day Stats -->
            <div id="dayStats" class="stats-section">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Réservations</th>
                                <th class="px-4 py-3 text-left">Revenus</th>
                                <th class="px-4 py-3 text-left">Moyenne/Réservation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                            $stats_day->data_seek(0);
                            while ($stat = $stats_day->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="px-4 py-3"><?php echo $stat['period']; ?></td>
                                <td class="px-4 py-3"><?php echo $stat['total_reservations']; ?></td>
                                <td class="px-4 py-3 font-medium text-blue-600">
                                    <?php echo number_format($stat['total_amount'], 0, ',', ' '); ?> DA
                                </td>
                                <td class="px-4 py-3">
                                    <?php echo number_format($stat['avg_amount'], 0, ',', ' '); ?> DA
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Month Stats -->
            <div id="monthStats" class="stats-section hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Mois</th>
                                <th class="px-4 py-3 text-left">Réservations</th>
                                <th class="px-4 py-3 text-left">Revenus</th>
                                <th class="px-4 py-3 text-left">Moyenne/Réservation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                            $stats_month->data_seek(0);
                            while ($stat = $stats_month->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="px-4 py-3"><?php echo $stat['period']; ?></td>
                                <td class="px-4 py-3"><?php echo $stat['total_reservations']; ?></td>
                                <td class="px-4 py-3 font-medium text-blue-600">
                                    <?php echo number_format($stat['total_amount'], 0, ',', ' '); ?> DA
                                </td>
                                <td class="px-4 py-3">
                                    <?php echo number_format($stat['avg_amount'], 0, ',', ' '); ?> DA
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Year Stats -->
            <div id="yearStats" class="stats-section hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Année</th>
                                <th class="px-4 py-3 text-left">Réservations</th>
                                <th class="px-4 py-3 text-left">Revenus</th>
                                <th class="px-4 py-3 text-left">Moyenne/Réservation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php 
                            $stats_year->data_seek(0);
                            while ($stat = $stats_year->fetch_assoc()): 
                            ?>
                            <tr>
                                <td class="px-4 py-3"><?php echo $stat['period']; ?></td>
                                <td class="px-4 py-3"><?php echo $stat['total_reservations']; ?></td>
                                <td class="px-4 py-3 font-medium text-blue-600">
                                    <?php echo number_format($stat['total_amount'], 0, ',', ' '); ?> DA
                                </td>
                                <td class="px-4 py-3">
                                    <?php echo number_format($stat['avg_amount'], 0, ',', ' '); ?> DA
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Liste des clients récents -->
        <div class="bg-white rounded-xl shadow-lg p-6 mt-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-users mr-2"></i>Clients Récents
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">Nom & Prénom</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Téléphone</th>
                            <th class="px-4 py-3 text-left">Statut</th>
                            <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while ($client = $clients->fetch_assoc()): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium"><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></div>
                            </td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($client['email']); ?></td>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($client['numero_tlfn']); ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded text-xs 
                                    <?php echo $client['status'] == 'payer' ? 'bg-green-100 text-green-800' :
                                          ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' :
                                          ($client['status'] == 'annuler' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); ?>">
                                    <?php echo ucfirst($client['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex space-x-2">
                                    <button onclick="showEditClientForm(
                                        <?php echo $client['id']; ?>, 
                                        '<?php echo htmlspecialchars($client['nom']); ?>',
                                        '<?php echo htmlspecialchars($client['prenom']); ?>',
                                        <?php echo $client['age']; ?>,
                                        '<?php echo htmlspecialchars($client['numero_tlfn']); ?>',
                                        '<?php echo htmlspecialchars($client['nationalite']); ?>',
                                        <?php echo $client['wilaya_id']; ?>,
                                        '<?php echo htmlspecialchars($client['email']); ?>'
                                    )" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteClient(<?php echo $client['id']; ?>)" 
                                            class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modals pour l'administrateur -->
    <?php includeAdminModals($app, $db); ?>
    
    <script>
    function showStats(period) {
        // Hide all stats sections
        document.querySelectorAll('.stats-section').forEach(section => {
            section.classList.add('hidden');
        });
        
        // Show selected stats
        document.getElementById(period + 'Stats').classList.remove('hidden');
        
        // Update active button
        document.querySelectorAll('#dayBtn, #monthBtn, #yearBtn').forEach(btn => {
            btn.classList.remove('bg-blue-600', 'text-white');
            btn.classList.add('bg-gray-200', 'text-gray-700');
        });
        
        document.getElementById(period + 'Btn').classList.add('bg-blue-600', 'text-white');
        document.getElementById(period + 'Btn').classList.remove('bg-gray-200', 'text-gray-700');
    }
    </script>
    <?php
}

function displaySuperAdminDashboard($auth, $app, $db) {
    // Obtenir les compagnies
    $companies = $db->query("SELECT * FROM company ORDER BY company_id DESC");
    
    // Obtenir les administrateurs
    $admins = $db->query("SELECT a.*, c.c_name as company_name FROM administrator a JOIN company c ON a.company_id = c.company_id ORDER BY a.id DESC");
    ?>
    
    <div class="min-h-screen">
        <!-- En-tête du tableau de bord -->
        <div class="bg-gradient-to-r from-red-600 to-orange-600 text-white rounded-xl p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold">Tableau de Bord Propriétaire</h1>
                    <p class="text-red-100 mt-2">Cherifi Youssouf - Gestion des compagnies et administrateurs</p>
                </div>
                <div class="text-right">
                    <p class="text-lg">Connecté en tant que: 
                        <span class="font-bold">Propriétaire</span>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Gestion des compagnies -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-building mr-2"></i>Gestion des Compagnies
                    </h2>
                    <button onclick="showAddCompanyForm()" 
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Compagnie
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">Nom</th>
                                <th class="px-4 py-3 text-left">Frais Mensuel</th>
                                <th class="px-4 py-3 text-left">Code Spécial</th>
                                <th class="px-4 py-3 text-left">Date Création</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($company = $companies->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3"><?php echo $company['company_id']; ?></td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($company['c_name']); ?></td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-blue-600">
                                        <?php echo number_format($company['frais_mensuel'], 0, ',', ' '); ?> DA
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono"><?php echo htmlspecialchars($company['special_code']); ?></td>
                                <td class="px-4 py-3"><?php echo date('d/m/Y', strtotime($company['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Gestion des administrateurs -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-user-shield mr-2"></i>Gestion des Administrateurs
                    </h2>
                    <button onclick="showAddAdminForm()" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-user-plus mr-2"></i>Nouvel Admin
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left">Nom & Prénom</th>
                                <th class="px-4 py-3 text-left">Compagnie</th>
                                <th class="px-4 py-3 text-left">Email</th>
                                <th class="px-4 py-3 text-left">Salaire</th>
                                <th class="px-4 py-3 text-left">Date Création</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($admin = $admins->fetch_assoc()): ?>
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></div>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($admin['company_name']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td class="px-4 py-3 font-medium text-green-600">
                                    <?php echo number_format($admin['salaire'], 0, ',', ' '); ?> DA
                                </td>
                                <td class="px-4 py-3"><?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals pour le super admin -->
    <?php includeSuperAdminModals($app, $db); ?>
    <?php
}

function includeAdminModals($app, $db) {
    ?>
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
    
    <!-- Modal de modification de voiture -->
    <div id="editCarModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Modifier la Voiture</h3>
                <button onclick="closeModal('editCarModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_car">
                <input type="hidden" name="car_id" id="editCarId">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Marque</label>
                            <input type="text" name="marque" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editMarque">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Modèle</label>
                            <input type="text" name="model" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editModel">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Couleur</label>
                            <input type="text" name="color" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editColor">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Année</label>
                            <input type="number" name="annee" required min="2000" max="2025"
                                   class="w-full px-4 py-2 border rounded-lg" id="editAnnee">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Catégorie</label>
                        <select name="category" required class="w-full px-4 py-2 border rounded-lg" id="editCategory">
                            <option value="1">Économique (4000-6000 DA/jour)</option>
                            <option value="2">Confort (6000-12000 DA/jour)</option>
                            <option value="3">Luxe (12000-20000 DA/jour)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Prix par jour (DA)</label>
                        <input type="number" name="prix_day" required min="4000" max="20000"
                               class="w-full px-4 py-2 border rounded-lg" id="editPrix">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">État de la voiture</label>
                            <select name="status_voiture" required class="w-full px-4 py-2 border rounded-lg" id="editStatus">
                                <option value="1">Excellent</option>
                                <option value="2">Entretien</option>
                                <option value="3">Faible</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Disponibilité</label>
                            <select name="voiture_work" required class="w-full px-4 py-2 border rounded-lg" id="editDispo">
                                <option value="disponible">Disponible</option>
                                <option value="non disponible">Non disponible</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editCarModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Modifier la voiture
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de suppression de voiture -->
    <div id="deleteCarModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Confirmer la suppression</h3>
                <button onclick="closeModal('deleteCarModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="delete_car">
                <input type="hidden" name="car_id" id="deleteCarId">
                
                <p class="text-gray-600 mb-6">Êtes-vous sûr de vouloir supprimer cette voiture ?</p>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('deleteCarModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Supprimer
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
    
    <!-- Modal de modification d'agent -->
    <div id="editAgentModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Modifier l'Agent</h3>
                <button onclick="closeModal('editAgentModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_agent">
                <input type="hidden" name="agent_id" id="editAgentId">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Nom</label>
                            <input type="text" name="nom" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editAgentNom">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Prénom</label>
                            <input type="text" name="prenom" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editAgentPrenom">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Âge (minimum 24)</label>
                        <input type="number" name="age" min="24" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editAgentAge">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Téléphone</label>
                        <input type="tel" name="numero_tlfn" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editAgentPhone">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Nationalité</label>
                        <input type="text" name="nationalite" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editAgentNationalite">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya</label>
                        <select name="wilaya_id" required class="w-full px-4 py-2 border rounded-lg" id="editAgentWilaya">
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
                               class="w-full px-4 py-2 border rounded-lg" id="editAgentSalaire">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editAgentEmail">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editAgentModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                                        <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Modifier l'agent
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de suppression d'agent -->
    <div id="deleteAgentModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Confirmer la suppression</h3>
                <button onclick="closeModal('deleteAgentModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="delete_agent">
                <input type="hidden" name="agent_id" id="deleteAgentId">
                
                <p class="text-gray-600 mb-6">Êtes-vous sûr de vouloir supprimer cet agent ?</p>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('deleteAgentModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de modification de client -->
    <div id="editClientModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Modifier le Client</h3>
                <button onclick="closeModal('editClientModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_client">
                <input type="hidden" name="client_id" id="editClientId">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Nom</label>
                            <input type="text" name="nom" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editClientNom">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Prénom</label>
                            <input type="text" name="prenom" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editClientPrenom">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Âge (minimum 24)</label>
                        <input type="number" name="age" min="24" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editClientAge">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Téléphone</label>
                        <input type="tel" name="numero_tlfn" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editClientPhone">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Nationalité</label>
                        <input type="text" name="nationalite" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editClientNationalite">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya</label>
                        <select name="wilaya_id" required class="w-full px-4 py-2 border rounded-lg" id="editClientWilaya">
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
                               class="w-full px-4 py-2 border rounded-lg" id="editClientEmail">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editClientModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Modifier le client
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de suppression de client -->
    <div id="deleteClientModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Confirmer la suppression</h3>
                <button onclick="closeModal('deleteClientModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="delete_client">
                <input type="hidden" name="client_id" id="deleteClientId">
                
                <p class="text-gray-600 mb-6">Êtes-vous sûr de vouloir supprimer ce client ?</p>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('deleteClientModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    function showAddCarForm() {
        document.getElementById('addCarModal').style.display = 'block';
    }
    
    function showEditCarForm(id, marque, model, color, annee, category, prix, status, dispo) {
        document.getElementById('editCarId').value = id;
        document.getElementById('editMarque').value = marque;
        document.getElementById('editModel').value = model;
        document.getElementById('editColor').value = color;
        document.getElementById('editAnnee').value = annee;
        document.getElementById('editCategory').value = category;
        document.getElementById('editPrix').value = prix;
        document.getElementById('editStatus').value = status;
        document.getElementById('editDispo').value = dispo;
        
        document.getElementById('editCarModal').style.display = 'block';
    }
    
    function deleteCar(id) {
        document.getElementById('deleteCarId').value = id;
        document.getElementById('deleteCarModal').style.display = 'block';
    }
    
    function showAddAgentForm() {
        document.getElementById('addAgentModal').style.display = 'block';
    }
    
    function showEditAgentForm(id, nom, prenom, age, phone, nationalite, wilaya, salaire, email) {
        document.getElementById('editAgentId').value = id;
        document.getElementById('editAgentNom').value = nom;
        document.getElementById('editAgentPrenom').value = prenom;
        document.getElementById('editAgentAge').value = age;
        document.getElementById('editAgentPhone').value = phone;
        document.getElementById('editAgentNationalite').value = nationalite;
        document.getElementById('editAgentWilaya').value = wilaya;
        document.getElementById('editAgentSalaire').value = salaire;
        document.getElementById('editAgentEmail').value = email;
        
        document.getElementById('editAgentModal').style.display = 'block';
    }
    
    function deleteAgent(id) {
        document.getElementById('deleteAgentId').value = id;
        document.getElementById('deleteAgentModal').style.display = 'block';
    }
    
    function showEditClientForm(id, nom, prenom, age, phone, nationalite, wilaya, email) {
        document.getElementById('editClientId').value = id;
        document.getElementById('editClientNom').value = nom;
        document.getElementById('editClientPrenom').value = prenom;
        document.getElementById('editClientAge').value = age;
        document.getElementById('editClientPhone').value = phone;
        document.getElementById('editClientNationalite').value = nationalite;
        document.getElementById('editClientWilaya').value = wilaya;
        document.getElementById('editClientEmail').value = email;
        
        document.getElementById('editClientModal').style.display = 'block';
    }
    
    function deleteClient(id) {
        document.getElementById('deleteClientId').value = id;
        document.getElementById('deleteClientModal').style.display = 'block';
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

function includeSuperAdminModals($app, $db) {
    // Obtenir la liste des compagnies pour le select
    $companies = $db->query("SELECT company_id, c_name FROM company ORDER BY c_name");
    ?>
    
    <!-- Modal d'ajout de compagnie -->
    <div id="addCompanyModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Ajouter une Compagnie</h3>
                <button onclick="closeModal('addCompanyModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_company">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Nom de la Compagnie *</label>
                        <input type="text" name="c_name" required 
                               class="w-full px-4 py-2 border rounded-lg" 
                               placeholder="Ex: Location Auto Alger">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Frais Mensuel (DA) *</label>
                            <input type="number" name="frais_mensuel" required min="20000" max="100000" step="5000"
                                   class="w-full px-4 py-2 border rounded-lg" value="50000">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Code Spécial *</label>
                            <input type="text" name="special_code" required 
                                   class="w-full px-4 py-2 border rounded-lg" 
                                   placeholder="Ex: LOCAUTO001">
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 p-3 rounded-lg">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            Après création de la compagnie, vous pourrez ajouter des administrateurs.
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addCompanyModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-plus mr-2"></i>Créer la compagnie
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal d'ajout d'administrateur -->
    <div id="addAdminModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Ajouter un Administrateur</h3>
                <button onclick="closeModal('addAdminModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add_admin">
                <input type="hidden" name="company_id" id="addAdminCompanyId" value="">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Nom *</label>
                            <input type="text" name="nom" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Prénom *</label>
                            <input type="text" name="prenom" required 
                                   class="w-full px-4 py-2 border rounded-lg">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Âge (minimum 24) *</label>
                        <input type="number" name="age" min="24" max="65" required 
                               class="w-full px-4 py-2 border rounded-lg" value="30">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Téléphone *</label>
                            <input type="tel" name="numero_tlfn" required 
                                   class="w-full px-4 py-2 border rounded-lg" 
                                   placeholder="0555123456">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Nationalité *</label>
                            <input type="text" name="nationalite" required 
                                   class="w-full px-4 py-2 border rounded-lg" 
                                   value="Algérienne">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Numéro Carte Nationale *</label>
                        <input type="text" name="numero_cart_national" required 
                               class="w-full px-4 py-2 border rounded-lg" 
                               placeholder="1234567890123456">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya *</label>
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
                        <label class="block text-gray-700 mb-2">Compagnie *</label>
                        <select name="company_id" id="companySelect" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Sélectionnez une compagnie</option>
                            <?php 
                            $companies->data_seek(0);
                            while ($company = $companies->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['c_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Salaire (DA) *</label>
                        <input type="number" name="salaire" required min="30000" max="150000" step="5000"
                               class="w-full px-4 py-2 border rounded-lg" value="80000">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border rounded-lg" 
                               placeholder="admin@compagnie.com">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Mot de passe *</label>
                        <input type="password" name="password" required 
                               class="w-full px-4 py-2 border rounded-lg" 
                               placeholder="Minimum 6 caractères" minlength="6">
                    </div>
                    
                    <div class="bg-yellow-50 p-3 rounded-lg">
                        <p class="text-sm text-yellow-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            Le mot de passe par défaut recommandé est <strong>123456</strong>
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('addAdminModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-user-plus mr-2"></i>Créer l'administrateur
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de modification d'administrateur -->
    <div id="editAdminModal" class="modal">
        <div class="modal-content fade-in">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Modifier l'Administrateur</h3>
                <button onclick="closeModal('editAdminModal')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_admin">
                <input type="hidden" name="admin_id" id="editAdminId">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Nom *</label>
                            <input type="text" name="nom" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editAdminNom">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Prénom *</label>
                            <input type="text" name="prenom" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editAdminPrenom">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Âge (minimum 24) *</label>
                        <input type="number" name="age" min="24" max="65" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editAdminAge">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Téléphone *</label>
                            <input type="tel" name="numero_tlfn" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editAdminPhone">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Nationalité *</label>
                            <input type="text" name="nationalite" required 
                                   class="w-full px-4 py-2 border rounded-lg" id="editAdminNationalite">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Wilaya *</label>
                        <select name="wilaya_id" required class="w-full px-4 py-2 border rounded-lg" id="editAdminWilaya">
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
                        <label class="block text-gray-700 mb-2">Compagnie *</label>
                        <select name="company_id" required class="w-full px-4 py-2 border rounded-lg" id="editAdminCompanyId">
                            <option value="">Sélectionnez une compagnie</option>
                            <?php 
                            $companies->data_seek(0);
                            while ($company = $companies->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $company['company_id']; ?>"><?php echo htmlspecialchars($company['c_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Salaire (DA) *</label>
                        <input type="number" name="salaire" required min="30000" max="150000" step="5000"
                               class="w-full px-4 py-2 border rounded-lg" id="editAdminSalaire">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Email *</label>
                        <input type="email" name="email" required 
                               class="w-full px-4 py-2 border rounded-lg" id="editAdminEmail">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeModal('editAdminModal')" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Formulaires cachés pour les suppressions -->
    <form method="POST" id="deleteCompanyForm" style="display: none;">
        <input type="hidden" name="action" value="delete_company">
        <input type="hidden" name="company_id" id="deleteCompanyId">
    </form>
    
    <form method="POST" id="deleteAdminForm" style="display: none;">
        <input type="hidden" name="action" value="delete_admin">
        <input type="hidden" name="admin_id" id="deleteAdminId">
    </form>
    
    <script>
    // Pré-sélectionner la compagnie si spécifiée
    document.addEventListener('DOMContentLoaded', function() {
        const companyId = document.getElementById('addAdminCompanyId').value;
        if (companyId > 0) {
            document.getElementById('companySelect').value = companyId;
        }
    });
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
                    Système professionnel de location de voitures en Algérie
                </p>
                <p class="text-gray-500 text-sm mt-2">Propriétaire: Cherifi Youssouf</p>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Contact Propriétaire</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><i class="fas fa-envelope mr-2"></i>chirifiyoucef@mail.com</li>
                    <li><i class="fas fa-phone mr-2"></i>+213 XXX XXX XXX</li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Liens Rapides</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="index.php" class="hover:text-white">Accueil</a></li>
                    <li><a href="#" class="hover:text-white">Conditions générales</a></li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-bold mb-4">Sécurité</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><i class="fas fa-shield-alt mr-2"></i>Système sécurisé</li>
                    <li><i class="fas fa-lock mr-2"></i>Données protégées</li>
                    <li><i class="fas fa-user-shield mr-2"></i>Authentification multi-niveaux</li>
                </ul>
            </div>
        </div>
        
        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> DZLocation. Tous droits réservés.</p>
            <p class="mt-2 text-sm">Développé par Cherifi Youssouf</p>
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

// Confirmation pour les actions de suppression
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

