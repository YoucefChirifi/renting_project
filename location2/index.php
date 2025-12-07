<?php
/**
 * ====================================================================
 * DZ LOCATION - SYSTÈME DE LOCATION DE VOITURES EN ALGÉRIE
 * Version 3.0 - Complete Multi-Agency Car Rental System
 * Propriétaire: Cherifi Youssouf (chirifiyoucef@mail.com)
 * Database: chirifiyoucef_agence13
 * ====================================================================
 * 
 * NOUVELLES FONCTIONNALITÉS:
 * 1. Navigation Temporelle (Forward/Backward Days)
 * 2. Auto-reset des réservations complétées
 * 3. Age minimum 24 ans (validation stricte)
 * 4. Dashboard amélioré pour Agent/Admin
 * 5. Interface Client avec boutons Réserver/Payer
 * 6. Système de paiement avec bilan
 * ====================================================================
 */

// Configuration initiale
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Initialisation de la date simulée
if (!isset($_SESSION['simulated_date'])) {
    $_SESSION['simulated_date'] = date('Y-m-d');
}

// ========== CLASSE DATABASE ==========
class Database {
    private $host = 'localhost';
    private $user = 'root';
    private $pass = '';
    private $dbname = 'chirifiyoucef_agence13';
    public $conn;

    public function __construct() {
        $this->connect();
        $this->createTables();
        $this->seedData();
    }

    private function connect() {
        $this->conn = new mysqli($this->host, $this->user, $this->pass);
        if ($this->conn->connect_error) {
            die("Erreur de connexion: " . $this->conn->connect_error);
        }
        
        // Créer la base de données
        $this->conn->query("CREATE DATABASE IF NOT EXISTS $this->dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->conn->select_db($this->dbname);
        $this->conn->query("SET NAMES utf8mb4");
    }

    private function createTables() {
        // Table wilaya
        $this->conn->query("CREATE TABLE IF NOT EXISTS wilaya (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table company
        $this->conn->query("CREATE TABLE IF NOT EXISTS company (
            company_id INT AUTO_INCREMENT PRIMARY KEY,
            c_name VARCHAR(100) NOT NULL UNIQUE,
            frais_mensuel DECIMAL(10,2) DEFAULT 50000,
            special_code VARCHAR(50) UNIQUE,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table super_admin
        $this->conn->query("CREATE TABLE IF NOT EXISTS super_admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table administrator
        $this->conn->query("CREATE TABLE IF NOT EXISTS administrator (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table agent
        $this->conn->query("CREATE TABLE IF NOT EXISTS agent (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table client
        $this->conn->query("CREATE TABLE IF NOT EXISTS client (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table car
        $this->conn->query("CREATE TABLE IF NOT EXISTS car (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table payment
        $this->conn->query("CREATE TABLE IF NOT EXISTS payment (
            id_payment INT AUTO_INCREMENT PRIMARY KEY,
            status ENUM('paid', 'not_paid') DEFAULT 'not_paid',
            amount DECIMAL(10,2),
            payment_date DATETIME,
            card_number VARCHAR(16),
            card_code VARCHAR(3)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table reservation
        $this->conn->query("CREATE TABLE IF NOT EXISTS reservation (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Table agent_commission_history
        $this->conn->query("CREATE TABLE IF NOT EXISTS agent_commission_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agent_id INT,
            reservation_id INT,
            commission_amount DECIMAL(10,2),
            commission_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (agent_id) REFERENCES agent(id),
            FOREIGN KEY (reservation_id) REFERENCES reservation(id_reservation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function seedData() {
        // Insérer les 69 wilayas
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
            46 => 'Aïn Témouchent', 47 => 'Ghardaïa', 48 => 'Relizane', 49 => 'Timimoun',
            50 => 'Bordj Badji Mokhtar', 51 => 'Ouled Djellal', 52 => 'Béni Abbès', 53 => 'In Salah',
            54 => 'In Guezzam', 55 => 'Touggourt', 56 => 'Djanet', 57 => 'El M\'Ghair', 58 => 'El Meniaa',
            59 => 'Aflou', 60 => 'El Abiodh Sidi Cheikh', 61 => 'El Aricha', 62 => 'El Kantara',
            63 => 'Barika', 64 => 'Bou Saâda', 65 => 'Bir El Ater', 66 => 'Ksar El Boukhari',
            67 => 'Ksar Chellala', 68 => 'Aïn Oussara', 69 => 'Messaad'
        ];

        foreach ($wilayas as $id => $name) {
            $check = $this->conn->query("SELECT id FROM wilaya WHERE id = $id");
            if ($check->num_rows == 0) {
                $this->conn->query("INSERT INTO wilaya (id, name) VALUES ($id, '$name')");
            }
        }

        // Super Admin
        $check = $this->conn->query("SELECT id FROM super_admin WHERE email = 'chirifiyoucef@mail.com'");
        if ($check->num_rows == 0) {
            $pwd = password_hash('123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO super_admin (nom, prenom, email, password) VALUES ('Cherifi', 'Youssouf', 'chirifiyoucef@mail.com', '$pwd')");
        }

        // Seed companies
        $check = $this->conn->query("SELECT COUNT(*) as count FROM company");
        $result = $check->fetch_assoc();
        if ($result['count'] == 0) {
            $this->seedCompanies();
        }
    }

    private function seedCompanies() {
        $pwd = password_hash('123', PASSWORD_DEFAULT);

        // Compagnie 1: Location Auto Alger
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Auto Alger', 50000, 'ALG001', 1)");
        $c1 = $this->conn->insert_id;
        
        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
        VALUES ('Benali', 'Karim', 35, '0555111111', 'Algérienne', '1111111111111111', 16, 80000, $c1, 'admin@alger.com', '$pwd', 1)");
        
        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
        VALUES ('Mansouri', 'Nassim', 28, '0555222222', 'Algérienne', '2222222222222222', 16, 50000, $c1, 'agent@alger.com', '$pwd', 1)");
        
        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) 
        VALUES ('Zeroual', 'Amine', 25, '0555333333', 'Algérienne', '3333333333333333', 16, 'non reserve', $c1, 'client@alger.com', '$pwd', 1)");

        $this->createDefaultCars($c1);

        // Compagnie 2: Auto Location Oran
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Auto Location Oran', 45000, 'ORAN002', 1)");
        $c2 = $this->conn->insert_id;
        
        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
        VALUES ('Bouguerra', 'Samir', 40, '0555444444', 'Algérienne', '4444444444444444', 31, 75000, $c2, 'admin@oran.com', '$pwd', 1)");
        
        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
        VALUES ('Touati', 'Yacine', 30, '0555555555', 'Algérienne', '5555555555555555', 31, 45000, $c2, 'agent@oran.com', '$pwd', 1)");
        
        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) 
        VALUES ('Khelifi', 'Rachid', 27, '0555666666', 'Algérienne', '6666666666666666', 31, 'non reserve', $c2, 'client@oran.com', '$pwd', 1)");

        $this->createDefaultCars($c2);

        // Compagnie 3: Location Voiture Constantine
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Voiture Constantine', 55000, 'CONST003', 1)");
        $c3 = $this->conn->insert_id;
        
        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
        VALUES ('Salhi', 'Farid', 38, '0555777777', 'Algérienne', '7777777777777777', 25, 85000, $c3, 'admin@constantine.com', '$pwd', 1)");
        
        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) 
        VALUES ('Mekideche', 'Hakim', 32, '0555888888', 'Algérienne', '8888888888888888', 25, 55000, $c3, 'agent@constantine.com', '$pwd', 1)");
        
        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) 
        VALUES ('Benaissa', 'Sofiane', 29, '0555999999', 'Algérienne', '9999999999999999', 25, 'non reserve', $c3, 'client@constantine.com', '$pwd', 1)");

        $this->createDefaultCars($c3);
    }

    private function createDefaultCars($company_id) {
        $cars = [
            ['Toyota', 'Corolla', 'Blanc', 2022, 1, 5000, 1],
            ['BMW', '3 Series', 'Noir', 2021, 2, 8000, 1],
            ['Mercedes', 'C-Class', 'Argent', 2023, 3, 15000, 1],
            ['Renault', 'Clio', 'Bleu', 2021, 1, 4500, 1],
            ['Audi', 'A4', 'Gris', 2022, 2, 9000, 1],
            ['Peugeot', '208', 'Jaune', 2023, 1, 4800, 1],
            ['Nissan', 'Maxima', 'Noir', 2022, 3, 18000, 1]
        ];

        foreach ($cars as $car) {
            $serial = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $category = $car[4];
            $year = $car[3];
            $wilaya = 31;
            $matricule = "$serial $category $year $wilaya";

            $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) 
            VALUES ($company_id, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '$matricule', 'disponible', 1)");
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

// ========== CLASSE AUTH ==========
class Auth {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

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

            if (($role == 'super_admin' && ($password == '123' || password_verify($password, $user['password']))) ||
                ($role != 'super_admin' && password_verify($password, $user['password']))) {
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $role;
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                $_SESSION['company_id'] = $user['company_id'] ?? 0;
                
                // Initialiser la date simulée pour ce user
                if (!isset($_SESSION['simulated_date'])) {
                    $_SESSION['simulated_date'] = date('Y-m-d');
                }

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

    private function getTableByRole($role) {
        return ['client' => 'client', 'agent' => 'agent', 'administrator' => 'administrator'][$role] ?? 'client';
    }

    public function registerUser($data, $role, $created_by = null) {
        // Validation âge minimum 24 ans
        if ($data['age'] < 24) {
            return false;
        }

        $table = $this->getTableByRole($role);
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        if ($role == 'client') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, email, password, company_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssissi", $data['nom'], $data['prenom'], $data['age'], $data['numero_tlfn'], $data['nationalite'], $data['numero_cart_national'], $data['wilaya_id'], $data['email'], $hashed_password, $data['company_id'], $created_by);
        } else {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssdissi", $data['nom'], $data['prenom'], $data['age'], $data['numero_tlfn'], $data['nationalite'], $data['numero_cart_national'], $data['wilaya_id'], $data['salaire'], $data['company_id'], $data['email'], $hashed_password, $created_by);
        }
        
        return $stmt->execute();
    }
}

// ========== CLASSE TIME MANAGER ==========
class TimeManager {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function getCurrentDate() {
        return $_SESSION['simulated_date'] ?? date('Y-m-d');
    }

    public function moveForward() {
        $current = new DateTime($_SESSION['simulated_date']);
        $current->modify('+1 day');
        $_SESSION['simulated_date'] = $current->format('Y-m-d');
        $this->checkAndCompleteReservations();
    }

    public function moveBackward() {
        $current = new DateTime($_SESSION['simulated_date']);
        $current->modify('-1 day');
        $_SESSION['simulated_date'] = $current->format('Y-m-d');
    }

    public function resetToToday() {
        $_SESSION['simulated_date'] = date('Y-m-d');
        $this->checkAndCompleteReservations();
    }

    // Vérifier et compléter les réservations expirées
    private function checkAndCompleteReservations() {
        $current_date = $this->getCurrentDate();
        
        // Trouver toutes les réservations actives dont la date de fin est dépassée
        $sql = "SELECT id_reservation, car_id, id_client FROM reservation 
                WHERE status = 'active' AND end_date < '$current_date'";
        $result = $this->db->query($sql);

        while ($row = $result->fetch_assoc()) {
            // Marquer la réservation comme complétée
            $this->db->query("UPDATE reservation SET status = 'completed' WHERE id_reservation = {$row['id_reservation']}");
            
            // Libérer la voiture
            $this->db->query("UPDATE car SET voiture_work = 'disponible' WHERE id_car = {$row['car_id']}");
            
            // Réinitialiser le statut du client
            $this->db->query("UPDATE client SET status = 'non reserve' WHERE id = {$row['id_client']}");
        }
    }

    public function getDateInfo() {
        $current = new DateTime($this->getCurrentDate());
        $real = new DateTime(date('Y-m-d'));
        $diff = $real->diff($current);
        
        return [
            'current' => $current->format('Y-m-d'),
            'formatted' => $current->format('d/m/Y'),
            'day_name' => $this->getDayName($current->format('w')),
            'diff_days' => $diff->format('%R%a'),
            'is_today' => ($current->format('Y-m-d') == $real->format('Y-m-d'))
        ];
    }

    private function getDayName($day) {
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return $days[$day];
    }
}

// ========== CLASSE CAR RENTAL APP ==========
class CarRentalApp {
    private $db;
    private $auth;
    private $timeManager;

    public function __construct($database, $auth, $timeManager) {
        $this->db = $database;
        $this->auth = $auth;
        $this->timeManager = $timeManager;
    }

    public function getAvailableCars($company_id = null, $category = null) {
        $sql = "SELECT c.*, co.c_name as company_name FROM car c 
                JOIN company co ON c.company_id = co.company_id 
                WHERE c.voiture_work = 'disponible'";
        
        if ($company_id) $sql .= " AND c.company_id = $company_id";
        if ($category) $sql .= " AND c.category = $category";
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

        if ($period <= 0) return false;

        $car_stmt = $this->db->prepare("SELECT prix_day FROM car WHERE id_car = ?");
        $car_stmt->bind_param("i", $data['car_id']);
        $car_stmt->execute();
        $car_result = $car_stmt->get_result();

        if ($car_result->num_rows == 0) return false;

        $car = $car_result->fetch_assoc();
        $montant = $period * $car['prix_day'];

        $stmt = $this->db->prepare("INSERT INTO reservation (id_agent, id_client, id_company, car_id, start_date, end_date, period, montant, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $agent_id = $data['id_agent'] ?? NULL;
        $stmt->bind_param("iiiiissid", $agent_id, $data['id_client'], $data['id_company'], $data['car_id'], $data['start_date'], $data['end_date'], $period, $montant);

        if ($stmt->execute()) {
            $reservation_id = $this->db->conn->insert_id;
            $this->db->query("UPDATE car SET voiture_work = 'non disponible' WHERE id_car = {$data['car_id']}");
            $this->db->query("UPDATE client SET status = 'reserve' WHERE id = {$data['id_client']}");
            return $reservation_id;
        }
        return false;
    }

    public function processPayment($reservation_id, $card_number, $card_code) {
        // Validation carte
        if (strlen($card_number) != 16 || !is_numeric($card_number)) return false;
        if (strlen($card_code) != 3 || !is_numeric($card_code)) return false;

        $stmt = $this->db->prepare("SELECT r.montant, r.id_agent, r.car_id, r.id_client, r.start_date, r.end_date, r.period FROM reservation r WHERE r.id_reservation = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) return false;

        $reservation = $result->fetch_assoc();
        $commission = $reservation['montant'] * 0.015;

        $payment_stmt = $this->db->prepare("INSERT INTO payment (amount, card_number, card_code, status, payment_date) VALUES (?, ?, ?, 'paid', NOW())");
        $payment_stmt->bind_param("dss", $reservation['montant'], $card_number, $card_code);

        if ($payment_stmt->execute()) {
            $payment_id = $this->db->conn->insert_id;
            $this->db->query("UPDATE reservation SET id_payment = $payment_id, status = 'completed' WHERE id_reservation = $reservation_id");
            $this->db->query("UPDATE client SET status = 'payer' WHERE id = {$reservation['id_client']}");
            $this->db->query("UPDATE car SET voiture_work = 'disponible' WHERE id_car = {$reservation['car_id']}");

            if ($reservation['id_agent']) {
                $this->db->query("UPDATE agent SET total_commission = total_commission + $commission WHERE id = {$reservation['id_agent']}");
                $this->db->query("INSERT INTO agent_commission_history (agent_id, reservation_id, commission_amount, commission_date) VALUES ({$reservation['id_agent']}, $reservation_id, $commission, CURDATE())");
            }
            
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'reservation' => $reservation
            ];
        }
        return false;
    }

    public function getStatistics($company_id, $period = 'month') {
        $where = "r.id_company = $company_id AND r.status = 'completed'";
        
        if ($period == 'day') {
            $sql = "SELECT DATE(r.start_date) as period, COUNT(*) as total_reservations, SUM(r.montant) as total_amount FROM reservation r WHERE $where GROUP BY DATE(r.start_date) ORDER BY period DESC LIMIT 30";
        } elseif ($period == 'month') {
            $sql = "SELECT DATE_FORMAT(r.start_date, '%Y-%m') as period, COUNT(*) as total_reservations, SUM(r.montant) as total_amount FROM reservation r WHERE $where GROUP BY DATE_FORMAT(r.start_date, '%Y-%m') ORDER BY period DESC LIMIT 12";
        } else {
            $sql = "SELECT YEAR(r.start_date) as period, COUNT(*) as total_reservations, SUM(r.montant) as total_amount FROM reservation r WHERE $where GROUP BY YEAR(r.start_date) ORDER BY period DESC LIMIT 5";
        }
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
        return ['', 'Excellent', 'Entretien', 'Faible'][$status] ?? 'Inconnu';
    }

    public function getCompanyFinancials($company_id) {
        $revenue = $this->db->query("SELECT COALESCE(SUM(r.montant), 0) as total_revenue FROM reservation r WHERE r.id_company = $company_id AND r.status = 'completed'")->fetch_assoc();
        $salaries = $this->db->query("SELECT COALESCE(SUM(salaire), 0) as total_salaries FROM (SELECT salaire FROM administrator WHERE company_id = $company_id UNION ALL SELECT salaire FROM agent WHERE company_id = $company_id) as s")->fetch_assoc();
        $company = $this->db->query("SELECT frais_mensuel FROM company WHERE company_id = $company_id")->fetch_assoc();

        $total_revenue = $revenue['total_revenue'] ?? 0;
        $total_salaries = $salaries['total_salaries'] ?? 0;
        $company_fees = $company['frais_mensuel'] ?? 50000;
        $total_expenses = $total_salaries + $company_fees;

        return [
            'total_revenue' => $total_revenue,
            'total_salaries' => $total_salaries,
            'company_fees' => $company_fees,
            'total_expenses' => $total_expenses,
            'net_profit' => $total_revenue - $total_expenses
        ];
    }

    public function getAllCompanies() {
        return $this->db->query("SELECT * FROM company ORDER BY created_at DESC");
    }
}
// ========== INITIALISATION ==========
$db = new Database();
$auth = new Auth($db);
$timeManager = new TimeManager($db);
$app = new CarRentalApp($db, $auth, $timeManager);

$success = $error = '';

// ========== GESTION DE LA NAVIGATION TEMPORELLE ==========
if (isset($_GET['time_action']) && $auth->isLoggedIn()) {
    switch ($_GET['time_action']) {
        case 'forward':
            $timeManager->moveForward();
            $success = "Date avancée d'un jour";
            break;
        case 'backward':
            $timeManager->moveBackward();
            $success = "Date reculée d'un jour";
            break;
        case 'reset':
            $timeManager->resetToToday();
            $success = "Date réinitialisée à aujourd'hui";
            break;
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=" . ($_GET['page'] ?? 'home'));
    exit();
}

// ========== LOGOUT ==========
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    $auth->logout();
}

// ========== LOGIN ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';

    if ($auth->login($email, $password, $role)) {
        $redirect = ['super_admin' => 'owner', 'client' => 'client', 'agent' => 'agent', 'administrator' => 'admin'][$role] ?? 'client';
        header("Location: index.php?page=$redirect");
        exit();
    } else {
        $error = "Identifiants incorrects";
    }
}

// ========== REGISTER CLIENT (BY AGENT) ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_client'])) {
    if ($auth->isLoggedIn() && $auth->getUserRole() == 'agent') {
        if ($_POST['age'] < 24) {
            $error = "L'âge minimum est de 24 ans";
        } elseif ($_POST['password'] != $_POST['confirm_password']) {
            $error = "Les mots de passe ne correspondent pas";
        } else {
            $data = [
                'nom' => $_POST['nom'],
                'prenom' => $_POST['prenom'],
                'age' => intval($_POST['age']),
                'numero_tlfn' => $_POST['numero_tlfn'],
                'nationalite' => $_POST['nationalite'],
                'numero_cart_national' => $_POST['numero_cart_national'],
                'wilaya_id' => $_POST['wilaya_id'],
                'email' => $_POST['email'],
                'password' => $_POST['password'],
                'company_id' => $_SESSION['company_id']
            ];
            if ($auth->registerUser($data, 'client', $auth->getUserId())) {
                $success = "Client ajouté avec succès!";
            } else {
                $error = "Erreur lors de l'ajout du client (Email existe ou âge < 24 ans)";
            }
        }
    }
}

// ========== CREATE RESERVATION (CLIENT) ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_reservation'])) {
    if ($auth->isLoggedIn() && $auth->getUserRole() == 'client') {
        $data = [
            'id_client' => $auth->getUserId(),
            'id_company' => $_SESSION['company_id'],
            'car_id' => $_POST['car_id'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date']
        ];
        
        $reservation_id = $app->createReservation($data);
        if ($reservation_id) {
            $_SESSION['pending_reservation_id'] = $reservation_id;
            $success = "Réservation créée! Procédez au paiement.";
        } else {
            $error = "Erreur lors de la réservation. Vérifiez les dates.";
        }
    }
}

// ========== PROCESS PAYMENT (CLIENT) ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    if ($auth->isLoggedIn() && $auth->getUserRole() == 'client') {
        $reservation_id = $_POST['reservation_id'];
        $card_number = $_POST['card_number'];
        $card_code = $_POST['card_code'];
        
        $result = $app->processPayment($reservation_id, $card_number, $card_code);
        if ($result) {
            $_SESSION['payment_success'] = $result;
            $success = "Paiement effectué avec succès!";
            unset($_SESSION['pending_reservation_id']);
        } else {
            $error = "Erreur de paiement. Vérifiez les informations de la carte (16 chiffres + 3 chiffres code)";
        }
    }
}

// ========== ADMINISTRATOR ACTIONS ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator' && isset($_POST['action'])) {
    $company_id = $_SESSION['company_id'];

    switch ($_POST['action']) {
        case 'add_car':
            if ($_POST['age'] ?? 0) { // Si age est présent, valider
                if (intval($_POST['age']) < 24) {
                    $error = "L'âge minimum est de 24 ans";
                    break;
                }
            }
            
            $serial = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $category = $_POST['category'];
            $year = $_POST['annee'];
            $wilaya = 31;
            $matricule = "$serial $category $year $wilaya";

            $stmt = $db->prepare("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?)");
            $stmt->bind_param("isssiidisi", $company_id, $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'], $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $matricule, $auth->getUserId());
            $success = $stmt->execute() ? "Voiture ajoutée!" : "Erreur: " . $db->conn->error;
            break;

        case 'update_car':
            $stmt = $db->prepare("UPDATE car SET marque = ?, model = ?, color = ?, annee = ?, category = ?, prix_day = ?, status_voiture = ?, voiture_work = ? WHERE id_car = ? AND company_id = ?");
            $stmt->bind_param("sssiidisii", $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'], $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $_POST['voiture_work'], $_POST['car_id'], $company_id);
            $success = $stmt->execute() ? "Voiture mise à jour!" : "Erreur";
            break;

        case 'delete_car':
            $car_id = intval($_POST['car_id']);
            $db->query("DELETE FROM car WHERE id_car = $car_id AND company_id = $company_id");
            $success = "Voiture supprimée!";
            break;

        case 'add_agent':
            if (intval($_POST['age']) < 24) {
                $error = "L'âge minimum est de 24 ans";
            } else {
                $data = [
                    'nom' => $_POST['nom'],
                    'prenom' => $_POST['prenom'],
                    'age' => intval($_POST['age']),
                    'numero_tlfn' => $_POST['numero_tlfn'],
                    'nationalite' => $_POST['nationalite'],
                    'numero_cart_national' => $_POST['numero_cart_national'],
                    'wilaya_id' => $_POST['wilaya_id'],
                    'salaire' => $_POST['salaire'],
                    'company_id' => $company_id,
                    'email' => $_POST['email'],
                    'password' => $_POST['password']
                ];
                $success = $auth->registerUser($data, 'agent', $auth->getUserId()) ? "Agent ajouté!" : "Erreur: Email existe ou âge < 24 ans";
            }
            break;

       case 'update_profile':
    if ($_POST['age'] >= 24) {
        // Validation de l'email
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Email invalide";
            break;
        }
        
        // Construire la requête SQL
        $sql = "UPDATE administrator SET nom=?, prenom=?, age=?, numero_tlfn=?, 
                nationalite=?, numero_cart_national=?, wilaya_id=?, salaire=?, email=?";
        
        $types = "ssisssids";  // wilaya_id est 'i' (integer), salaire est 'd' (decimal)
        $values = [
            $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
            $_POST['nationalite'], $_POST['numero_cart_national'], 
            (int)$_POST['wilaya_id'],  // Conversion explicite en integer
            (float)$_POST['salaire'],  // Conversion explicite en float
            $email
        ];
        
        // Mot de passe optionnel
        if (!empty($_POST['password'])) {
            $sql .= ", password=?";
            $types .= "s";
            $values[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        // Clause WHERE
        $sql .= " WHERE id=?";
        $types .= "i";
        $values[] = $admin_id;
        
        // Exécution
        $stmt = $db->prepare($sql);
        if ($stmt === false) {
            $error = "Erreur de préparation: " . $db->error;
            break;
        }
        
        $stmt->bind_param($types, ...$values);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $_POST['prenom'] . ' ' . $_POST['nom'];
            $_SESSION['user_email'] = $email;
            $success = "Profil mis à jour avec succès";
        } else {
            $error = "Erreur lors de la mise à jour: " . $stmt->error;
        }
    } else {
        $error = "L'âge minimum est de 24 ans";
    }
    break;

        case 'delete_agent':
            $agent_id = intval($_POST['agent_id']);
            $db->query("DELETE FROM agent WHERE id = $agent_id AND company_id = $company_id");
            $success = "Agent supprimé!";
            break;

        case 'update_client':
            if (intval($_POST['age']) < 24) {
                $error = "L'âge minimum est de 24 ans";
                break;
            }
            $stmt = $db->prepare("UPDATE client SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ?, email = ? WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ssissssii", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['email'], $_POST['client_id'], $company_id);
            $success = $stmt->execute() ? "Client mise à jour!" : "Erreur";
            break;

        case 'delete_client':
            $client_id = intval($_POST['client_id']);
            $db->query("DELETE FROM client WHERE id = $client_id AND company_id = $company_id");
            $success = "Client supprimé!";
            break;
    }
}

// ========== SUPER ADMIN ACTIONS ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_company':
            $stmt = $db->prepare("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdsi", $_POST['c_name'], $_POST['frais_mensuel'], $_POST['special_code'], $auth->getUserId());
            if ($stmt->execute()) {
                $company_id = $db->conn->insert_id;
                // Auto-create 7 cars
                for ($i = 0; $i < 7; $i++) {
                    $serial = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $cat = ($i % 3) + 1;
                    $year = 2020 + $i;
                    $matricule = "$serial $cat $year 31";
                    $prix = 4000 + ($cat * 2000) + ($i * 500);
                    $db->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company_id, 'Toyota', 'Model$i', 'Blanc', $year, $cat, $prix, 1, '$matricule', 'disponible', 1)");
                }
                $success = "Compagnie créée avec 7 voitures!";
            } else {
                $error = "Erreur: " . $db->conn->error;
            }
            break;

        case 'add_admin':
            if (intval($_POST['age']) < 24) {
                $error = "L'âge minimum est de 24 ans";
            } else {
                $check = $db->query("SELECT id FROM administrator WHERE email = '{$_POST['email']}'");
                if ($check->num_rows > 0) {
                    $error = "Cet email est déjà utilisé";
                } else {
                    $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssissssdissi", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['company_id'], $_POST['email'], $hashed, $auth->getUserId());
                    $success = $stmt->execute() ? "Administrateur ajouté!" : "Erreur: " . $db->conn->error;
                }
            }
            break;

        case 'update_admin':
            if (intval($_POST['age']) < 24) {
                $error = "L'âge minimum est de 24 ans";
                break;
            }
            $stmt = $db->prepare("UPDATE administrator SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ?, salaire = ?, company_id = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssisssdssi", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['company_id'], $_POST['email'], $_POST['admin_id']);
            $success = $stmt->execute() ? "Administrateur mise à jour!" : "Erreur";
            break;

        case 'delete_company':
            $cid = intval($_POST['company_id']);
            $db->query("DELETE FROM agent_commission_history WHERE agent_id IN (SELECT id FROM agent WHERE company_id = $cid)");
            $db->query("DELETE FROM reservation WHERE id_company = $cid");
            $db->query("DELETE FROM car WHERE company_id = $cid");
            $db->query("DELETE FROM client WHERE company_id = $cid");
            $db->query("DELETE FROM agent WHERE company_id = $cid");
            $db->query("DELETE FROM administrator WHERE company_id = $cid");
            $db->query("DELETE FROM company WHERE company_id = $cid");
            $success = "Compagnie supprimée!";
            break;

        case 'delete_admin':
            $aid = intval($_POST['admin_id']);
            $db->query("DELETE FROM administrator WHERE id = $aid");
            $success = "Administrateur supprimé!";
            break;
    }
}

// ========== DÉTERMINER LA PAGE ==========
$page = $_GET['page'] ?? 'home';
if ($auth->isLoggedIn() && $page == 'home') {
    $role = $auth->getUserRole();
    $page = ['super_admin' => 'owner', 'client' => 'client', 'agent' => 'agent', 'administrator' => 'admin'][$role] ?? 'home';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DZLocation - Location de Voitures en Algérie</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(to bottom, #f8fafc 0%, #e2e8f0 100%);
        }
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        }
        .card-hover { 
            transition: all 0.3s ease; 
        }
        .card-hover:hover { 
            transform: translateY(-4px); 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
        }
        .btn-primary { 
            @apply px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-md; 
        }
        .btn-danger { 
            @apply px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition shadow-md; 
        }
        .btn-success { 
            @apply px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition shadow-md; 
        }
        .btn-warning { 
            @apply px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition shadow-md; 
        }
        table { 
            @apply w-full border-collapse; 
        }
        thead { 
            @apply bg-gray-200; 
        }
        td, th { 
            @apply border border-gray-300 px-3 py-2 text-left text-sm; 
        }
        .time-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 20px;
            padding: 8px 16px;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            overflow-y: auto;
        }
        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .car-card {
            border-left: 4px solid;
        }
        .car-card.cat-1 { border-color: #10b981; }
        .car-card.cat-2 { border-color: #3b82f6; }
        .car-card.cat-3 { border-color: #8b5cf6; }
        .receipt {
            background: white;
            border: 2px dashed #cbd5e0;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Bar -->
    <nav class="gradient-bg text-white shadow-2xl sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <div class="flex items-center space-x-4">
                    <a href="index.php" class="flex items-center space-x-2 hover:opacity-80 transition">
                        <i class="fas fa-car text-3xl"></i>
                        <div>
                            <h1 class="text-2xl font-bold">DZLocation</h1>
                            <p class="text-xs text-gray-200">Location de voitures - Algérie</p>
                        </div>
                    </a>
                    <?php if ($auth->isLoggedIn()): ?>
                        <div class="flex items-center space-x-2 ml-6">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-user mr-1"></i>
                                <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </span>
                            <span class="px-3 py-1 bg-white/30 rounded-full text-sm font-semibold">
                                <?php 
                                $role_labels = [
                                    'super_admin' => '👑 Propriétaire',
                                    'administrator' => '🏢 Admin',
                                    'agent' => '👨‍💼 Agent',
                                    'client' => '👤 Client'
                                ];
                                echo $role_labels[$auth->getUserRole()] ?? ucfirst($auth->getUserRole());
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Time Navigation & User Actions -->
                <div class="flex items-center space-x-4">
                    <?php if ($auth->isLoggedIn()): 
                        $dateInfo = $timeManager->getDateInfo();
                    ?>
                        <!-- Time Control Panel -->
                        <div class="flex items-center space-x-2 bg-white/20 rounded-lg px-4 py-2">
                            <div class="time-badge flex items-center space-x-2">
                                <i class="fas fa-calendar-day"></i>
                                <div class="text-left">
                                    <div class="text-xs opacity-80"><?= $dateInfo['day_name'] ?></div>
                                    <div class="font-bold"><?= $dateInfo['formatted'] ?></div>
                                </div>
                            </div>
                            <div class="flex flex-col space-y-1">
                                <a href="?time_action=backward&page=<?= $page ?>" class="bg-white/30 hover:bg-white/50 px-2 py-1 rounded text-xs transition" title="Jour précédent">
                                    <i class="fas fa-chevron-left"></i> Reculer
                                </a>
                                <a href="?time_action=forward&page=<?= $page ?>" class="bg-white/30 hover:bg-white/50 px-2 py-1 rounded text-xs transition" title="Jour suivant">
                                    Avancer <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                            <?php if (!$dateInfo['is_today']): ?>
                                <a href="?time_action=reset&page=<?= $page ?>" class="bg-yellow-500 hover:bg-yellow-600 px-3 py-2 rounded text-xs transition" title="Retour à aujourd'hui">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <a href="index.php?action=logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition shadow-lg flex items-center space-x-2">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Déconnexion</span>
                        </a>
                    <?php else: ?>
                        <a href="index.php" class="hover:bg-white/20 px-4 py-2 rounded-lg transition">
                            <i class="fas fa-home mr-2"></i>Accueil
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Messages Flash -->
    <?php if ($success): ?>
        <div class="max-w-7xl mx-auto mt-4 px-4">
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md flex items-center">
                <i class="fas fa-check-circle text-2xl mr-3"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="max-w-7xl mx-auto mt-4 px-4">
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md flex items-center">
                <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <div class="max-w-7xl mx-auto p-4 mt-6">
        <?php
        // ========== PAGE ROUTING ==========
        if ($page == 'home' && !$auth->isLoggedIn()):
            // HOME PAGE
        ?>
            <div class="min-h-screen flex items-center justify-center py-12">
                <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-6xl">
                    <div class="text-center mb-12">
                        <h1 class="text-5xl font-bold text-gray-800 mb-4">
                            Bienvenue sur <span class="text-blue-600">DZ</span><span class="text-purple-600">Location</span>
                        </h1>
                        <p class="text-gray-600 text-lg">Système professionnel de location de voitures en Algérie</p>
                        <p class="text-gray-500 mt-2">Propriétaire: <strong>Cherifi Youssouf</strong></p>
                        <div class="mt-4 inline-block bg-yellow-100 text-yellow-800 px-4 py-2 rounded-lg">
                            <i class="fas fa-info-circle mr-2"></i>
                            Base de données: <strong>chirifiyoucef_agence13</strong>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                        <!-- Client Card -->
                        <div class="bg-gradient-to-br from-green-50 to-blue-50 rounded-xl p-6 text-center card-hover border-2 border-green-200">
                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user text-green-600 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-3">Client</h3>
                            <p class="text-gray-600 mb-6 text-sm">Réservez votre voiture</p>
                            <button onclick="showLogin('client')" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg transition shadow-lg font-semibold">
                                <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                            </button>
                            <div class="mt-3 text-xs text-gray-600 bg-gray-100 p-2 rounded">
                                client@alger.com / 123
                            </div>
                        </div>

                        <!-- Agent Card -->
                        <div class="bg-gradient-to-br from-blue-50 to-purple-50 rounded-xl p-6 text-center card-hover border-2 border-blue-200">
                            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-tie text-blue-600 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-3">Agent</h3>
                            <p class="text-gray-600 mb-6 text-sm">Gérer les clients</p>
                            <button onclick="showLogin('agent')" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg transition shadow-lg font-semibold">
                                <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                            </button>
                            <div class="mt-3 text-xs text-gray-600 bg-gray-100 p-2 rounded">
                                agent@alger.com / 123
                            </div>
                        </div>

                        <!-- Admin Card -->
                        <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-6 text-center card-hover border-2 border-purple-200">
                            <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-shield text-purple-600 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-3">Administrateur</h3>
                            <p class="text-gray-600 mb-6 text-sm">Gestion complète</p>
                            <button onclick="showLogin('administrator')" class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-lg transition shadow-lg font-semibold">
                                <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                            </button>
                            <div class="mt-3 text-xs text-gray-600 bg-gray-100 p-2 rounded">
                                admin@alger.com / 123
                            </div>
                        </div>

                        <!-- Owner Card -->
                        <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-xl p-6 text-center card-hover border-2 border-red-200">
                            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-crown text-red-600 text-3xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-800 mb-3">Propriétaire</h3>
                            <p class="text-gray-600 mb-6 text-sm">Gestion multi-agences</p>
                            <button onclick="showLogin('super_admin')" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-lg transition shadow-lg font-semibold">
                                <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                            </button>
                            <div class="mt-3 text-xs text-gray-600 bg-gray-100 p-2 rounded">
                                chirifiyoucef@mail.com / 123
                            </div>
                        </div>
                    </div>

                    <!-- Login Form (Hidden by default) -->
                    <div id="loginForm" class="hidden bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl p-8 shadow-inner">
                        <h2 id="formTitle" class="text-2xl font-bold text-gray-800 mb-6 text-center"></h2>
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="login" value="1">
                            <input type="hidden" name="role" id="loginRole">
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">
                                    <i class="fas fa-envelope mr-2"></i>Email
                                </label>
                                <input type="email" name="email" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">
                                    <i class="fas fa-lock mr-2"></i>Mot de passe
                                </label>
                                <input type="password" name="password" required class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            </div>
                            <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white py-3 rounded-lg transition font-semibold shadow-lg">
                                <i class="fas fa-sign-in-alt mr-2"></i>Se Connecter
                            </button>
                        </form>
                    </div>

                    <!-- Categories Info -->
                    <div class="mt-12">
                        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">
                            <i class="fas fa-star mr-2 text-yellow-500"></i>
                            Nos Catégories de Véhicules
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <?php foreach ($app->getCategories() as $id => $cat): ?>
                                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 cat-<?= $id ?> card-hover">
                                    <h3 class="text-xl font-bold text-gray-800 mb-2"><?= $cat['name'] ?></h3>
                                    <p class="text-gray-600 mb-3">Prix par jour</p>
                                    <p class="text-3xl font-bold text-blue-600">
                                        <?= number_format($cat['min_price'], 0, ',', ' ') ?> - <?= number_format($cat['max_price'], 0, ',', ' ') ?> DA
                                    </p>
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
                        'administrator': 'Connexion Administrateur',
                        'super_admin': 'Connexion Propriétaire'
                    };
                    document.getElementById('formTitle').textContent = titles[role];
                    document.getElementById('loginForm').scrollIntoView({ behavior: 'smooth' });
                }
            </script>

        


        <?php
        elseif ($page == 'client' && $auth->isLoggedIn() && $auth->getUserRole() == 'client'):
            // CLIENT DASHBOARD
            $client_id = $auth->getUserId();
            $company_id = $_SESSION['company_id'];
            
            // Get client info
            $client = $db->query("SELECT c.*, w.name as wilaya_name FROM client c LEFT JOIN wilaya w ON c.wilaya_id = w.id WHERE c.id = $client_id")->fetch_assoc();
            
            // Get available cars for this company
            $cars = $app->getAvailableCars($company_id);
            
            // Get client's reservations
            $reservations = $db->query("SELECT r.*, c.marque, c.model, c.color, c.matricule, c.prix_day, p.status as payment_status FROM reservation r JOIN car c ON r.car_id = c.id_car LEFT JOIN payment p ON r.id_payment = p.id_payment WHERE r.id_client = $client_id ORDER BY r.created_at DESC");
        ?>
            <div class="space-y-8">
                <!-- Client Info Card -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-user-circle mr-2 text-blue-600"></i>
                        Mon Profil
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Nom complet</p>
                            <p class="font-semibold"><?= $client['prenom'] ?> <?= $client['nom'] ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Email</p>
                            <p class="font-semibold"><?= $client['email'] ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Téléphone</p>
                            <p class="font-semibold"><?= $client['numero_tlfn'] ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Âge</p>
                            <p class="font-semibold"><?= $client['age'] ?> ans</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Wilaya</p>
                            <p class="font-semibold"><?= $client['wilaya_name'] ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Statut</p>
                            <span class="px-3 py-1 rounded-full text-sm font-semibold <?= $client['status'] == 'payer' ? 'bg-green-100 text-green-800' : ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                <?= ucfirst($client['status']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- My Reservations -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-clipboard-list mr-2 text-purple-600"></i>
                        Mes Réservations
                    </h2>
                    <?php if ($reservations->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Voiture</th>
                                        <th>Plaque</th>
                                        <th>Dates</th>
                                        <th>Durée</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Paiement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($res = $reservations->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= $res['marque'] ?> <?= $res['model'] ?></td>
                                            <td><code><?= $res['matricule'] ?></code></td>
                                            <td><?= date('d/m/Y', strtotime($res['start_date'])) ?> → <?= date('d/m/Y', strtotime($res['end_date'])) ?></td>
                                            <td><?= $res['period'] ?> jours</td>
                                            <td class="font-bold text-green-600"><?= number_format($res['montant'], 0, ',', ' ') ?> DA</td>
                                            <td>
                                                <span class="status-badge status-<?= $res['status'] ?>">
                                                    <?= ucfirst($res['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($res['payment_status'] == 'paid'): ?>
                                                    <span class="status-badge bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle"></i> Payé
                                                    </span>
                                                <?php else: ?>
                                                    <button onclick="openPaymentModal(<?= $res['id_reservation'] ?>, <?= $res['montant'] ?>, '<?= $res['marque'] ?> <?= $res['model'] ?>')" class="text-blue-600 hover:underline text-sm">
                                                        <i class="fas fa-credit-card"></i> Payer maintenant
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-5xl mb-4"></i>
                            <p>Aucune réservation pour le moment</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Available Cars -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6">
                        <i class="fas fa-car mr-2 text-green-600"></i>
                        Voitures Disponibles
                    </h2>
                    <?php if ($cars->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php while ($car = $cars->fetch_assoc()): ?>
                                <div class="bg-white border-2 border-gray-200 rounded-xl p-4 car-card cat-<?= $car['category'] ?> card-hover">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="font-bold text-lg text-gray-800"><?= $car['marque'] ?></h3>
                                            <p class="text-gray-600 text-sm"><?= $car['model'] ?></p>
                                        </div>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                            Cat. <?= $car['category'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 mb-4 text-sm">
                                        <p><strong>Couleur:</strong> <?= $car['color'] ?></p>
                                        <p><strong>Année:</strong> <?= $car['annee'] ?></p>
                                        <p><strong>Plaque:</strong> <code class="bg-gray-100 px-2 py-1 rounded"><?= $car['matricule'] ?></code></p>
                                        <p><strong>État:</strong> <?= $app->getCarStatusText($car['status_voiture']) ?></p>
                                    </div>
                                    
                                    <div class="bg-green-50 p-3 rounded-lg mb-4">
                                        <p class="text-3xl font-bold text-green-600">
                                            <?= number_format($car['prix_day'], 0, ',', ' ') ?> <span class="text-sm">DA/jour</span>
                                        </p>
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        <button onclick="openReservationModal(<?= $car['id_car'] ?>, '<?= $car['marque'] ?> <?= $car['model'] ?>', <?= $car['prix_day'] ?>, '<?= $car['matricule'] ?>')" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg transition text-sm font-semibold">
                                            <i class="fas fa-calendar-check mr-1"></i>Réserver
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-car-crash text-6xl mb-4"></i>
                            <p class="text-lg">Aucune voiture disponible pour le moment</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Réservation -->
            <div id="reservationModal" class="modal">
                <div class="modal-content">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-calendar-alt mr-2 text-green-600"></i>
                        Nouvelle Réservation
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="create_reservation" value="1">
                        <input type="hidden" id="res_car_id" name="car_id">
                        
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="font-semibold text-gray-700">Voiture sélectionnée:</p>
                            <p id="res_car_info" class="text-lg font-bold text-blue-600"></p>
                            <p class="text-sm text-gray-600" id="res_car_plate"></p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">
                                    <i class="fas fa-calendar-day mr-1"></i>Date Début
                                </label>
                                <input type="date" name="start_date" id="start_date" min="<?= $timeManager->getCurrentDate() ?>" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">
                                    <i class="fas fa-calendar-day mr-1"></i>Date Fin
                                </label>
                                <input type="date" name="end_date" id="end_date" min="<?= $timeManager->getCurrentDate() ?>" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-700 mb-2">Estimation du coût:</p>
                            <p id="estimated_cost" class="text-3xl font-bold text-green-600">0 DA</p>
                            <p id="duration_info" class="text-sm text-gray-600 mt-1"></p>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg transition font-semibold">
                                <i class="fas fa-check mr-2"></i>Confirmer la Réservation
                            </button>
                            <button type="button" onclick="closeModal('reservationModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal Paiement -->
            <div id="paymentModal" class="modal">
                <div class="modal-content">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-credit-card mr-2 text-blue-600"></i>
                        Paiement Sécurisé
                    </h3>
                    <form method="POST" class="space-y-4" onsubmit="return validatePayment()">
                        <input type="hidden" name="process_payment" value="1">
                        <input type="hidden" id="pay_reservation_id" name="reservation_id">
                        
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="font-semibold text-gray-700">Réservation:</p>
                            <p id="pay_car_info" class="text-lg font-bold text-blue-600"></p>
                            <p id="pay_amount" class="text-2xl font-bold text-green-600 mt-2"></p>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-semibold">
                                <i class="fas fa-credit-card mr-1"></i>Numéro de Carte (16 chiffres)
                            </label>
                            <input type="text" name="card_number" id="card_number" maxlength="16" placeholder="1234567812345678" required class="w-full border-2 px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-lg">
                            <p class="text-xs text-gray-500 mt-1">Entrez exactement 16 chiffres</p>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-semibold">
                                <i class="fas fa-lock mr-1"></i>Code de Sécurité (3 chiffres)
                            </label>
                            <input type="text" name="card_code" id="card_code" maxlength="3" placeholder="123" required class="w-full border-2 px-4 py-3 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-lg">
                            <p class="text-xs text-gray-500 mt-1">Code à 3 chiffres au dos de la carte</p>
                        </div>

                        <div class="bg-green-50 p-4 rounded-lg border-2 border-green-200">
                            <p class="text-sm text-green-700">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Paiement 100% sécurisé - Toute combinaison de 16 chiffres + 3 chiffres est acceptée pour la démo
                            </p>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg transition font-semibold">
                                <i class="fas fa-check-circle mr-2"></i>Payer Maintenant
                            </button>
                            <button type="button" onclick="closeModal('paymentModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                let currentCarPrice = 0;

                function openReservationModal(carId, carName, price, plate) {
                    currentCarPrice = price;
                    document.getElementById('res_car_id').value = carId;
                    document.getElementById('res_car_info').textContent = carName;
                    document.getElementById('res_car_plate').textContent = 'Plaque: ' + plate;
                    document.getElementById('reservationModal').style.display = 'block';
                    calculateCost();
                }

                function openPaymentModal(reservationId, amount, carName) {
                    document.getElementById('pay_reservation_id').value = reservationId;
                    document.getElementById('pay_car_info').textContent = carName;
                    document.getElementById('pay_amount').textContent = amount.toLocaleString('fr-DZ') + ' DA';
                    document.getElementById('paymentModal').style.display = 'block';
                }

                function closeModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                document.getElementById('start_date').addEventListener('change', calculateCost);
                document.getElementById('end_date').addEventListener('change', calculateCost);

                function calculateCost() {
                    const startDate = new Date(document.getElementById('start_date').value);
                    const endDate = new Date(document.getElementById('end_date').value);
                    
                    if (startDate && endDate && endDate > startDate) {
                        const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
                        const total = days * currentCarPrice;
                        document.getElementById('estimated_cost').textContent = total.toLocaleString('fr-DZ') + ' DA';
                        document.getElementById('duration_info').textContent = days + ' jour(s) × ' + currentCarPrice.toLocaleString('fr-DZ') + ' DA';
                    }
                }

                function validatePayment() {
                    const cardNumber = document.getElementById('card_number').value;
                    const cardCode = document.getElementById('card_code').value;
                    
                    if (cardNumber.length !== 16 || !/^\d+$/.test(cardNumber)) {
                        alert('Le numéro de carte doit contenir exactement 16 chiffres');
                        return false;
                    }
                    
                    if (cardCode.length !== 3 || !/^\d+$/.test(cardCode)) {
                        alert('Le code de sécurité doit contenir exactement 3 chiffres');
                        return false;
                    }
                    
                    return true;
                }

                // Close modal on outside click
                window.onclick = function(event) {
                    if (event.target.classList.contains('modal')) {
                        event.target.style.display = 'none';
                    }
                }
            </script>

        
        <?php
        elseif ($page == 'agent' && $auth->isLoggedIn() && $auth->getUserRole() == 'agent'):
            // AGENT DASHBOARD
            $agent_id = $auth->getUserId();
            $company_id = $_SESSION['company_id'];
            
            // Get agent info
            $agent = $db->query("SELECT a.*, w.name as wilaya_name, c.c_name FROM agent a LEFT JOIN wilaya w ON a.wilaya_id = w.id LEFT JOIN company c ON a.company_id = c.company_id WHERE a.id = $agent_id")->fetch_assoc();
            
            // Get reservations details (NO REVENUE - only details)
            $reservations = $db->query("SELECT r.*, cl.nom, cl.prenom, cl.email, cl.numero_tlfn, car.marque, car.model, car.matricule, p.status as payment_status FROM reservation r JOIN client cl ON r.id_client = cl.id JOIN car car ON r.car_id = car.id_car LEFT JOIN payment p ON r.id_payment = p.id_payment WHERE r.id_company = $company_id ORDER BY r.created_at DESC LIMIT 50");
            
            // Get clients created by this agent
            $clients = $db->query("SELECT cl.*, w.name as wilaya_name FROM client cl LEFT JOIN wilaya w ON cl.wilaya_id = w.id WHERE cl.company_id = $company_id ORDER BY cl.created_at DESC");
        ?>
            <div class="space-y-8">
                <!-- Agent Info Card -->
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl shadow-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold mb-2">
                                <i class="fas fa-user-tie mr-2"></i>
                                Tableau de Bord Agent
                            </h2>
                            <p class="text-xl"><?= $agent['prenom'] ?> <?= $agent['nom'] ?></p>
                            <p class="text-sm opacity-90"><?= $agent['c_name'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm opacity-90">Salaire Mensuel</p>
                            <p class="text-3xl font-bold"><?= number_format($agent['salaire'], 0, ',', ' ') ?> DA</p>
                            <p class="text-sm opacity-90 mt-2">Commission: <?= $agent['commission_percentage'] ?>%</p>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <?php
                    $total_reservations = $db->query("SELECT COUNT(*) as count FROM reservation WHERE id_company = $company_id")->fetch_assoc()['count'];
                    $active_reservations = $db->query("SELECT COUNT(*) as count FROM reservation WHERE id_company = $company_id AND status = 'active'")->fetch_assoc()['count'];
                    $completed_reservations = $db->query("SELECT COUNT(*) as count FROM reservation WHERE id_company = $company_id AND status = 'completed'")->fetch_assoc()['count'];
                    $total_clients = $db->query("SELECT COUNT(*) as count FROM client WHERE company_id = $company_id")->fetch_assoc()['count'];
                    ?>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Réservations</p>
                                <p class="text-3xl font-bold text-blue-600"><?= $total_reservations ?></p>
                            </div>
                            <i class="fas fa-clipboard-list text-4xl text-blue-200"></i>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">En Cours</p>
                                <p class="text-3xl font-bold text-yellow-600"><?= $active_reservations ?></p>
                            </div>
                            <i class="fas fa-hourglass-half text-4xl text-yellow-200"></i>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Complétées</p>
                                <p class="text-3xl font-bold text-green-600"><?= $completed_reservations ?></p>
                            </div>
                            <i class="fas fa-check-circle text-4xl text-green-200"></i>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Clients</p>
                                <p class="text-3xl font-bold text-purple-600"><?= $total_clients ?></p>
                            </div>
                            <i class="fas fa-users text-4xl text-purple-200"></i>
                        </div>
                    </div>
                </div>

                <!-- Reservations Table (Details Only - NO REVENUE) -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-list-alt mr-2 text-blue-600"></i>
                        Détails des Réservations
                    </h2>
                    <?php if ($reservations->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Contact</th>
                                        <th>Voiture</th>
                                        <th>Plaque</th>
                                        <th>Date Début</th>
                                        <th>Date Fin</th>
                                        <th>Durée</th>
                                        <th>Statut</th>
                                        <th>Paiement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($res = $reservations->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $res['id_reservation'] ?></td>
                                            <td><?= $res['prenom'] ?> <?= $res['nom'] ?></td>
                                            <td>
                                                <div class="text-xs">
                                                    <div><?= $res['email'] ?></div>
                                                    <div class="text-gray-600"><?= $res['numero_tlfn'] ?></div>
                                                </div>
                                            </td>
                                            <td><?= $res['marque'] ?> <?= $res['model'] ?></td>
                                            <td><code class="bg-gray-100 px-2 py-1 rounded text-xs"><?= $res['matricule'] ?></code></td>
                                            <td><?= date('d/m/Y', strtotime($res['start_date'])) ?></td>
                                            <td><?= date('d/m/Y', strtotime($res['end_date'])) ?></td>
                                            <td class="text-center"><?= $res['period'] ?> j</td>
                                            <td>
                                                <span class="status-badge status-<?= $res['status'] ?>">
                                                    <?= ucfirst($res['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-xs px-2 py-1 rounded <?= $res['payment_status'] == 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                    <?= $res['payment_status'] == 'paid' ? 'Payé' : 'En attente' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-5xl mb-4"></i>
                            <p>Aucune réservation</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Clients Management -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-users mr-2 text-green-600"></i>
                            Gestion des Clients
                        </h2>
                        <button onclick="openAddClientModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition font-semibold">
                            <i class="fas fa-user-plus mr-2"></i>Ajouter Client
                        </button>
                    </div>
                    <?php if ($clients->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Âge</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Wilaya</th>
                                        <th>Carte Nationale</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($client = $clients->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $client['id'] ?></td>
                                            <td><?= $client['prenom'] ?> <?= $client['nom'] ?></td>
                                            <td><?= $client['age'] ?> ans</td>
                                            <td><?= $client['email'] ?></td>
                                            <td><?= $client['numero_tlfn'] ?></td>
                                            <td><?= $client['wilaya_name'] ?></td>
                                            <td><code class="text-xs"><?= $client['numero_cart_national'] ?></code></td>
                                            <td>
                                                <span class="text-xs px-2 py-1 rounded <?= $client['status'] == 'payer' ? 'bg-green-100 text-green-800' : ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                                    <?= ucfirst($client['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-user-slash text-5xl mb-4"></i>
                            <p>Aucun client enregistré</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Add Client -->
            <div id="addClientModal" class="modal">
                <div class="modal-content">
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-user-plus mr-2 text-green-600"></i>
                        Ajouter un Nouveau Client
                    </h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="register_client" value="1">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Nom *</label>
                                <input type="text" name="nom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Prénom *</label>
                                <input type="text" name="prenom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Âge * (minimum 24 ans)</label>
                                <input type="number" name="age" min="24" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Téléphone *</label>
                                <input type="tel" name="numero_tlfn" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Nationalité *</label>
                                <input type="text" name="nationalite" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">N° Carte Nationale *</label>
                                <input type="text" name="numero_cart_national" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-semibold">Wilaya *</label>
                            <select name="wilaya_id" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="">-- Sélectionner --</option>
                                <?php
                                $wilayas = $app->getWilayas();
                                while ($w = $wilayas->fetch_assoc()) {
                                    echo "<option value='{$w['id']}'>{$w['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-semibold">Email *</label>
                            <input type="email" name="email" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Mot de passe *</label>
                                <input type="password" name="password" required minlength="3" class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Confirmer *</label>
                                <input type="password" name="confirm_password" required minlength="3" class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>

                        <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                            <p class="text-sm text-yellow-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                L'âge minimum requis est de 24 ans
                            </p>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg transition font-semibold">
                                <i class="fas fa-check mr-2"></i>Ajouter Client
                            </button>
                            <button type="button" onclick="closeModal('addClientModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                <i class="fas fa-times mr-2"></i>Annuler
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function openAddClientModal() {
                    document.getElementById('addClientModal').style.display = 'block';
                }

                function closeModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                window.onclick = function(event) {
                    if (event.target.classList.contains('modal')) {
                        event.target.style.display = 'none';
                    }
                }
            </script>

        
        <?php
        elseif ($page == 'admin' && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator'):
            // ADMINISTRATOR DASHBOARD
            $admin_id = $auth->getUserId();
            $company_id = $_SESSION['company_id'];
            
            // Get admin info
            $admin = $db->query("SELECT a.*, w.name as wilaya_name, c.c_name FROM administrator a LEFT JOIN wilaya w ON a.wilaya_id = w.id LEFT JOIN company c ON a.company_id = c.company_id WHERE a.id = $admin_id")->fetch_assoc();
            
            // Get financials
            $financials = $app->getCompanyFinancials($company_id);
            
            // Get statistics period
            $period = $_GET['period'] ?? 'month';
            $stats = $app->getStatistics($company_id, $period);
        ?>
            <div class="space-y-8">
                <!-- Admin Header -->
                <div class="bg-gradient-to-r from-purple-500 to-pink-600 rounded-xl shadow-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold mb-2">
                                <i class="fas fa-user-shield mr-2"></i>
                                Tableau de Bord Administrateur
                            </h2>
                            <p class="text-xl"><?= $admin['prenom'] ?> <?= $admin['nom'] ?></p>
                            <p class="text-sm opacity-90"><?= $admin['c_name'] ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm opacity-90">Salaire Mensuel</p>
                            <p class="text-3xl font-bold"><?= number_format($admin['salaire'], 0, ',', ' ') ?> DA</p>
                        </div>
                    </div>
                </div>

                <!-- Financial Overview -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-green-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Revenus Totaux</p>
                            <i class="fas fa-dollar-sign text-green-500 text-2xl"></i>
                        </div>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($financials['total_revenue'], 0, ',', ' ') ?> DA</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-blue-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Salaires Employés</p>
                            <i class="fas fa-users text-blue-500 text-2xl"></i>
                        </div>
                        <p class="text-2xl font-bold text-blue-600"><?= number_format($financials['total_salaries'], 0, ',', ' ') ?> DA</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-orange-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Frais Mensuels</p>
                            <i class="fas fa-building text-orange-500 text-2xl"></i>
                        </div>
                        <p class="text-2xl font-bold text-orange-600"><?= number_format($financials['company_fees'], 0, ',', ' ') ?> DA</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-red-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Dépenses Totales</p>
                            <i class="fas fa-credit-card text-red-500 text-2xl"></i>
                        </div>
                        <p class="text-2xl font-bold text-red-600"><?= number_format($financials['total_expenses'], 0, ',', ' ') ?> DA</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 <?= $financials['net_profit'] >= 0 ? 'border-green-500' : 'border-red-500' ?>">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Bénéfice Net</p>
                            <i class="fas fa-chart-line <?= $financials['net_profit'] >= 0 ? 'text-green-500' : 'text-red-500' ?> text-2xl"></i>
                        </div>
                        <p class="text-2xl font-bold <?= $financials['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= number_format($financials['net_profit'], 0, ',', ' ') ?> DA
                        </p>
                        <p class="text-xs mt-2 <?= $financials['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $financials['net_profit'] >= 0 ? '✓ Profitable' : '✗ En Déficit' ?>
                        </p>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
                            Statistiques Complètes
                        </h3>
                        <div class="flex space-x-2">
                            <a href="?page=admin&period=day" class="px-4 py-2 rounded-lg text-sm font-semibold transition <?= $period == 'day' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                                <i class="fas fa-calendar-day mr-1"></i>Quotidien
                            </a>
                            <a href="?page=admin&period=month" class="px-4 py-2 rounded-lg text-sm font-semibold transition <?= $period == 'month' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                                <i class="fas fa-calendar-alt mr-1"></i>Mensuel
                            </a>
                            <a href="?page=admin&period=year" class="px-4 py-2 rounded-lg text-sm font-semibold transition <?= $period == 'year' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">
                                <i class="fas fa-calendar mr-1"></i>Annuel
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($stats->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Période</th>
                                        <th>Nombre de Réservations</th>
                                        <th>Revenus Totaux</th>
                                        <th>Revenu Moyen/Réservation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_reservations = 0;
                                    $total_amount = 0;
                                    while ($s = $stats->fetch_assoc()): 
                                        $avg = $s['total_reservations'] > 0 ? $s['total_amount'] / $s['total_reservations'] : 0;
                                        $total_reservations += $s['total_reservations'];
                                        $total_amount += $s['total_amount'];
                                    ?>
                                        <tr>
                                            <td class="font-semibold"><?= $s['period'] ?></td>
                                            <td class="text-center"><?= $s['total_reservations'] ?></td>
                                            <td class="font-bold text-green-600"><?= number_format($s['total_amount'], 0, ',', ' ') ?> DA</td>
                                            <td class="text-blue-600"><?= number_format($avg, 0, ',', ' ') ?> DA</td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <tr class="bg-gray-100 font-bold">
                                        <td>TOTAL</td>
                                        <td class="text-center"><?= $total_reservations ?></td>
                                        <td class="text-green-600"><?= number_format($total_amount, 0, ',', ' ') ?> DA</td>
                                        <td class="text-blue-600"><?= $total_reservations > 0 ? number_format($total_amount / $total_reservations, 0, ',', ' ') : 0 ?> DA</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-chart-line text-5xl mb-4"></i>
                            <p>Aucune statistique disponible pour cette période</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Cars Management -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-car mr-2 text-blue-600"></i>
                            Gestion des Voitures
                        </h3>
                        <button onclick="openCarModal('add')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition font-semibold">
                            <i class="fas fa-plus mr-2"></i>Ajouter Voiture
                        </button>
                    </div>
                    
                    <?php
                    $cars = $app->getCompanyCars($company_id);
                    if ($cars->num_rows > 0):
                    ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Marque/Modèle</th>
                                        <th>Couleur</th>
                                        <th>Année</th>
                                        <th>Plaque</th>
                                        <th>Cat.</th>
                                        <th>Prix/Jour</th>
                                        <th>État</th>
                                        <th>Disponibilité</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($car = $cars->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $car['id_car'] ?></td>
                                            <td class="font-semibold"><?= $car['marque'] ?> <?= $car['model'] ?></td>
                                            <td><?= $car['color'] ?></td>
                                            <td><?= $car['annee'] ?></td>
                                            <td><code class="bg-gray-100 px-2 py-1 rounded text-xs"><?= $car['matricule'] ?></code></td>
                                            <td class="text-center">
                                                <span class="px-2 py-1 rounded text-xs font-semibold <?= $car['category'] == 1 ? 'bg-green-100 text-green-800' : ($car['category'] == 2 ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800') ?>">
                                                    <?= $car['category'] ?>
                                                </span>
                                            </td>
                                            <td class="font-bold text-green-600"><?= number_format($car['prix_day'], 0, ',', ' ') ?> DA</td>
                                            <td><?= $app->getCarStatusText($car['status_voiture']) ?></td>
                                            <td>
                                                <span class="text-xs px-2 py-1 rounded <?= $car['voiture_work'] == 'disponible' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                    <?= ucfirst($car['voiture_work']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="flex gap-2">
                                                    <button onclick='editCar(<?= json_encode($car) ?>)' class="text-blue-600 hover:underline text-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Confirmer la suppression?')">
                                                        <input type="hidden" name="action" value="delete_car">
                                                        <input type="hidden" name="car_id" value="<?= $car['id_car'] ?>">
                                                        <button type="submit" class="text-red-600 hover:underline text-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-car-crash text-5xl mb-4"></i>
                            <p>Aucune voiture enregistrée</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Modal Car Management -->
                <div id="carModal" class="modal">
                    <div class="modal-content" style="max-width: 800px;">
                        <h3 id="carModalTitle" class="text-2xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-car mr-2 text-blue-600"></i>
                            Gérer Voiture
                        </h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" id="carAction" value="add_car">
                            <input type="hidden" name="car_id" id="car_id">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Marque *</label>
                                    <input type="text" name="marque" id="marque" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Modèle *</label>
                                    <input type="text" name="model" id="model" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Couleur *</label>
                                    <input type="text" name="color" id="color" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Année *</label>
                                    <input type="number" name="annee" id="annee" min="2000" max="2030" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Catégorie *</label>
                                    <select name="category" id="category" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">--</option>
                                        <option value="1">1 - Économique (4000-6000 DA)</option>
                                        <option value="2">2 - Confort (6000-12000 DA)</option>
                                        <option value="3">3 - Luxe (12000-20000 DA)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Prix/Jour (DA) *</label>
                                    <input type="number" name="prix_day" id="prix_day" min="1000" max="50000" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">État Voiture *</label>
                                    <select name="status_voiture" id="status_voiture" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="1">Excellent</option>
                                        <option value="2">Entretien</option>
                                        <option value="3">Faible</option>
                                    </select>
                                </div>
                                <div id="workStatusDiv">
                                    <label class="block text-gray-700 mb-2 font-semibold">Disponibilité</label>
                                    <select name="voiture_work" id="voiture_work" class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="disponible">Disponible</option>
                                        <option value="non disponible">Non Disponible</option>
                                    </select>
                                </div>
                            </div>

                            <div class="bg-blue-50 p-3 rounded-lg border border-blue-200">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    La plaque d'immatriculation sera générée automatiquement
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-check mr-2"></i>Enregistrer
                                </button>
                                <button type="button" onclick="closeModal('carModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-times mr-2"></i>Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <script>
                    function openCarModal(mode) {
                        document.getElementById('carAction').value = 'add_car';
                        document.getElementById('carModalTitle').innerHTML = '<i class="fas fa-plus mr-2 text-blue-600"></i>Ajouter Voiture';
                        document.getElementById('car_id').value = '';
                        document.getElementById('marque').value = '';
                        document.getElementById('model').value = '';
                        document.getElementById('color').value = '';
                        document.getElementById('annee').value = '';
                        document.getElementById('category').value = '';
                        document.getElementById('prix_day').value = '';
                        document.getElementById('status_voiture').value = '1';
                        document.getElementById('workStatusDiv').style.display = 'none';
                        document.getElementById('carModal').style.display = 'block';
                    }

                    function editCar(car) {
                        document.getElementById('carAction').value = 'update_car';
                        document.getElementById('carModalTitle').innerHTML = '<i class="fas fa-edit mr-2 text-blue-600"></i>Modifier Voiture';
                        document.getElementById('car_id').value = car.id_car;
                        document.getElementById('marque').value = car.marque;
                        document.getElementById('model').value = car.model;
                        document.getElementById('color').value = car.color;
                        document.getElementById('annee').value = car.annee;
                        document.getElementById('category').value = car.category;
                        document.getElementById('prix_day').value = car.prix_day;
                        document.getElementById('status_voiture').value = car.status_voiture;
                        document.getElementById('voiture_work').value = car.voiture_work;
                        document.getElementById('workStatusDiv').style.display = 'block';
                        document.getElementById('carModal').style.display = 'block';
                    }

                    function closeModal(modalId) {
                        document.getElementById(modalId).style.display = 'none';
                    }
                </script>
                <!-- Agents Management -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-user-tie mr-2 text-green-600"></i>
                            Gestion des Agents
                        </h3>
                        <button onclick="openAgentModal('add')" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition font-semibold">
                            <i class="fas fa-plus mr-2"></i>Ajouter Agent
                        </button>
                    </div>
                    
                    <?php
                    $agents = $db->query("SELECT a.*, w.name as wilaya_name FROM agent a LEFT JOIN wilaya w ON a.wilaya_id = w.id WHERE a.company_id = $company_id ORDER BY a.created_at DESC");
                    if ($agents->num_rows > 0):
                    ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Âge</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Wilaya</th>
                                        <th>Salaire</th>
                                        <th>Commission</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($agent = $agents->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $agent['id'] ?></td>
                                            <td class="font-semibold"><?= $agent['prenom'] ?> <?= $agent['nom'] ?></td>
                                            <td><?= $agent['age'] ?> ans</td>
                                            <td><?= $agent['email'] ?></td>
                                            <td><?= $agent['numero_tlfn'] ?></td>
                                            <td><?= $agent['wilaya_name'] ?></td>
                                            <td class="font-bold text-green-600"><?= number_format($agent['salaire'], 0, ',', ' ') ?> DA</td>
                                            <td class="text-center">
                                                <div><?= $agent['commission_percentage'] ?>%</div>
                                                <div class="text-xs text-gray-600"><?= number_format($agent['total_commission'], 0, ',', ' ') ?> DA</div>
                                            </td>
                                            <td>
                                                <div class="flex gap-2">
                                                    <button onclick='editAgent(<?= json_encode($agent) ?>)' class="text-blue-600 hover:underline text-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Confirmer la suppression?')">
                                                        <input type="hidden" name="action" value="delete_agent">
                                                        <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
                                                        <button type="submit" class="text-red-600 hover:underline text-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-user-slash text-5xl mb-4"></i>
                            <p>Aucun agent enregistré</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Modal Agent Management -->
                <div id="agentModal" class="modal">
                    <div class="modal-content" style="max-width: 800px;">
                        <h3 id="agentModalTitle" class="text-2xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-user-tie mr-2 text-green-600"></i>
                            Gérer Agent
                        </h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" id="agentAction" value="add_agent">
                            <input type="hidden" name="agent_id" id="agent_id">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Nom *</label>
                                    <input type="text" name="nom" id="agentNom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Prénom *</label>
                                    <input type="text" name="prenom" id="agentPrenom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Âge * (min 24)</label>
                                    <input type="number" name="age" id="agentAge" min="24" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Téléphone *</label>
                                    <input type="tel" name="numero_tlfn" id="agentTel" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Nationalité *</label>
                                    <input type="text" name="nationalite" id="agentNat" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">N° Carte Nationale *</label>
                                    <input type="text" name="numero_cart_national" id="agentCard" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Wilaya *</label>
                                    <select name="wilaya_id" id="agentWilaya" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                        <option value="">-- Sélectionner --</option>
                                        <?php
                                        $wilayas = $app->getWilayas();
                                        while ($w = $wilayas->fetch_assoc()) {
                                            echo "<option value='{$w['id']}'>{$w['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Salaire (DA) *</label>
                                    <input type="number" name="salaire" id="agentSalaire" min="20000" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Email *</label>
                                    <input type="email" name="email" id="agentEmail" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                            </div>

                            <div id="agentPasswordDiv">
                                <label class="block text-gray-700 mb-2 font-semibold">Mot de passe *</label>
                                <input type="password" name="password" id="agentPassword" class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>

                            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    L'âge minimum requis est de 24 ans
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-check mr-2"></i>Enregistrer
                                </button>
                                <button type="button" onclick="closeModal('agentModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-times mr-2"></i>Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Clients Management -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-users mr-2 text-purple-600"></i>
                            Gestion des Clients
                        </h3>
                    </div>
                    
                    <?php
                    $clients = $db->query("SELECT c.*, w.name as wilaya_name FROM client c LEFT JOIN wilaya w ON c.wilaya_id = w.id WHERE c.company_id = $company_id ORDER BY c.created_at DESC");
                    if ($clients->num_rows > 0):
                    ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Âge</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Wilaya</th>
                                        <th>Carte Nationale</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($client = $clients->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $client['id'] ?></td>
                                            <td class="font-semibold"><?= $client['prenom'] ?> <?= $client['nom'] ?></td>
                                            <td><?= $client['age'] ?> ans</td>
                                            <td><?= $client['email'] ?></td>
                                            <td><?= $client['numero_tlfn'] ?></td>
                                            <td><?= $client['wilaya_name'] ?></td>
                                            <td><code class="text-xs"><?= $client['numero_cart_national'] ?></code></td>
                                            <td>
                                                <span class="text-xs px-2 py-1 rounded <?= $client['status'] == 'payer' ? 'bg-green-100 text-green-800' : ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                                    <?= ucfirst($client['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="flex gap-2">
                                                    <button onclick='editClient(<?= json_encode($client) ?>)' class="text-blue-600 hover:underline text-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Confirmer la suppression?')">
                                                        <input type="hidden" name="action" value="delete_client">
                                                        <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
                                                        <button type="submit" class="text-red-600 hover:underline text-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-user-slash text-5xl mb-4"></i>
                            <p>Aucun client enregistré</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Modal Client Management -->
                <div id="clientModal" class="modal">
                    <div class="modal-content" style="max-width: 800px;">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-user-edit mr-2 text-purple-600"></i>
                            Modifier Client
                        </h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_client">
                            <input type="hidden" name="client_id" id="client_id">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Nom *</label>
                                    <input type="text" name="nom" id="clientNom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Prénom *</label>
                                    <input type="text" name="prenom" id="clientPrenom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Âge * (min 24)</label>
                                    <input type="number" name="age" id="clientAge" min="24" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Téléphone *</label>
                                    <input type="tel" name="numero_tlfn" id="clientTel" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Nationalité *</label>
                                    <input type="text" name="nationalite" id="clientNat" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Wilaya *</label>
                                    <select name="wilaya_id" id="clientWilaya" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                        <option value="">-- Sélectionner --</option>
                                        <?php
                                        $wilayas = $app->getWilayas();
                                        while ($w = $wilayas->fetch_assoc()) {
                                            echo "<option value='{$w['id']}'>{$w['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Email *</label>
                                    <input type="email" name="email" id="clientEmail" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>

                            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    L'âge minimum requis est de 24 ans
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-check mr-2"></i>Mettre à jour
                                </button>
                                <button type="button" onclick="closeModal('clientModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-times mr-2"></i>Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                function openAgentModal(mode) {
                    document.getElementById('agentAction').value = 'add_agent';
                    document.getElementById('agentModalTitle').innerHTML = '<i class="fas fa-plus mr-2 text-green-600"></i>Ajouter Agent';
                    document.getElementById('agent_id').value = '';
                    document.getElementById('agentNom').value = '';
                    document.getElementById('agentPrenom').value = '';
                    document.getElementById('agentAge').value = '';
                    document.getElementById('agentTel').value = '';
                    document.getElementById('agentNat').value = '';
                    document.getElementById('agentCard').value = '';
                    document.getElementById('agentWilaya').value = '';
                    document.getElementById('agentSalaire').value = '';
                    document.getElementById('agentEmail').value = '';
                    document.getElementById('agentPassword').value = '';
                    document.getElementById('agentPassword').required = true;
                    document.getElementById('agentModal').style.display = 'block';
                }

                function editAgent(agent) {
                    document.getElementById('agentAction').value = 'update_agent';
                    document.getElementById('agentModalTitle').innerHTML = '<i class="fas fa-edit mr-2 text-green-600"></i>Modifier Agent';
                    document.getElementById('agent_id').value = agent.id;
                    document.getElementById('agentNom').value = agent.nom;
                    document.getElementById('agentPrenom').value = agent.prenom;
                    document.getElementById('agentAge').value = agent.age;
                    document.getElementById('agentTel').value = agent.numero_tlfn;
                    document.getElementById('agentNat').value = agent.nationalite;
                    document.getElementById('agentCard').value = agent.numero_cart_national;
                    document.getElementById('agentWilaya').value = agent.wilaya_id;
                    document.getElementById('agentSalaire').value = agent.salaire;
                    document.getElementById('agentEmail').value = agent.email;
                    document.getElementById('agentPassword').value = '';
                    document.getElementById('agentPassword').required = false;
                    document.getElementById('agentPasswordDiv').style.display = 'none';
                    document.getElementById('agentModal').style.display = 'block';
                }

                function editClient(client) {
                    document.getElementById('client_id').value = client.id;
                    document.getElementById('clientNom').value = client.nom;
                    document.getElementById('clientPrenom').value = client.prenom;
                    document.getElementById('clientAge').value = client.age;
                    document.getElementById('clientTel').value = client.numero_tlfn;
                    document.getElementById('clientNat').value = client.nationalite;
                    document.getElementById('clientWilaya').value = client.wilaya_id;
                    document.getElementById('clientEmail').value = client.email;
                    document.getElementById('clientModal').style.display = 'block';
                }

                window.onclick = function(event) {
                    if (event.target.classList.contains('modal')) {
                        event.target.style.display = 'none';
                    }
                }
            </script>

        <?php
        
        elseif ($page == 'owner' && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin'):
            // SUPER ADMIN (OWNER) DASHBOARD
            $companies = $app->getAllCompanies();
        ?>
            <div class="space-y-8">
                <!-- Owner Header -->
                <div class="bg-gradient-to-r from-red-500 to-orange-600 rounded-xl shadow-2xl p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-3xl font-bold mb-2">
                                <i class="fas fa-crown mr-2"></i>
                                Tableau de Bord Propriétaire
                            </h2>
                            <p class="text-xl">Cherifi Youssouf</p>
                            <p class="text-sm opacity-90">Gestion Multi-Agences</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm opacity-90">Base de Données</p>
                            <p class="text-2xl font-bold">chirifiyoucef_agence13</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <?php
                $total_companies = $db->query("SELECT COUNT(*) as count FROM company")->fetch_assoc()['count'];
                $total_admins = $db->query("SELECT COUNT(*) as count FROM administrator")->fetch_assoc()['count'];
                $total_agents = $db->query("SELECT COUNT(*) as count FROM agent")->fetch_assoc()['count'];
                $total_clients = $db->query("SELECT COUNT(*) as count FROM client")->fetch_assoc()['count'];
                $total_cars = $db->query("SELECT COUNT(*) as count FROM car")->fetch_assoc()['count'];
                ?>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-red-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Compagnies</p>
                            <i class="fas fa-building text-red-500 text-2xl"></i>
                        </div>
                        <p class="text-3xl font-bold text-red-600"><?= $total_companies ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-purple-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Administrateurs</p>
                            <i class="fas fa-user-shield text-purple-500 text-2xl"></i>
                        </div>
                        <p class="text-3xl font-bold text-purple-600"><?= $total_admins ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-blue-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Agents</p>
                            <i class="fas fa-user-tie text-blue-500 text-2xl"></i>
                        </div>
                        <p class="text-3xl font-bold text-blue-600"><?= $total_agents ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-green-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Clients</p>
                            <i class="fas fa-users text-green-500 text-2xl"></i>
                        </div>
                        <p class="text-3xl font-bold text-green-600"><?= $total_clients ?></p>
                    </div>
                    <div class="bg-white rounded-xl shadow-lg p-6 card-hover border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-600">Voitures</p>
                            <i class="fas fa-car text-yellow-500 text-2xl"></i>
                        </div>
                        <p class="text-3xl font-bold text-yellow-600"><?= $total_cars ?></p>
                    </div>
                </div>

                <!-- Companies Management -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-building mr-2 text-red-600"></i>
                            Gestion des Compagnies
                        </h3>
                        <button onclick="openCompanyModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition font-semibold">
                            <i class="fas fa-plus mr-2"></i>Créer Compagnie
                        </button>
                    </div>

                    <?php if ($companies->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php while ($company = $companies->fetch_assoc()):
                                $financials = $app->getCompanyFinancials($company['company_id']);
                            ?>
                                <div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl p-6 border-2 border-gray-200 card-hover">
                                    <div class="flex justify-between items-start mb-4">
                                        <h4 class="font-bold text-lg text-gray-800"><?= $company['c_name'] ?></h4>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                            #<?= $company['company_id'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 text-sm mb-4">
                                        <p><strong>Code:</strong> <?= $company['special_code'] ?></p>
                                        <p><strong>Frais:</strong> <?= number_format($company['frais_mensuel'], 0, ',', ' ') ?> DA/mois</p>
                                        <p><strong>Créée:</strong> <?= date('d/m/Y', strtotime($company['created_at'])) ?></p>
                                    </div>

                                    <div class="border-t pt-3 space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Revenus:</span>
                                            <span class="font-bold text-green-600"><?= number_format($financials['total_revenue'], 0, ',', ' ') ?> DA</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Dépenses:</span>
                                            <span class="font-bold text-red-600"><?= number_format($financials['total_expenses'], 0, ',', ' ') ?> DA</span>
                                        </div>
                                        <div class="flex justify-between pt-2 border-t">
                                            <span class="font-semibold">Bénéfice:</span>
                                            <span class="font-bold text-lg <?= $financials['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= number_format($financials['net_profit'], 0, ',', ' ') ?> DA
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-4 pt-3 border-t">
                                        <form method="POST" onsubmit="return confirm('Confirmer la suppression de cette compagnie et toutes ses données?')">
                                            <input type="hidden" name="action" value="delete_company">
                                            <input type="hidden" name="company_id" value="<?= $company['company_id'] ?>">
                                            <button type="submit" class="w-full bg-red-500 hover:bg-red-600 text-white py-2 rounded-lg transition text-sm font-semibold">
                                                <i class="fas fa-trash mr-2"></i>Supprimer Compagnie
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-building text-6xl mb-4"></i>
                            <p class="text-lg">Aucune compagnie créée</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Modal Create Company -->
                <div id="companyModal" class="modal">
                    <div class="modal-content">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-building mr-2 text-red-600"></i>
                            Créer Nouvelle Compagnie
                        </h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="add_company">
                            
                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Nom de la Compagnie *</label>
                                <input type="text" name="c_name" required placeholder="Ex: Location Auto Annaba" class="w-full border-2 px-4 py-3 rounded-lg focus:ring-2 focus:ring-red-500">
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Frais Mensuels (DA) *</label>
                                <input type="number" name="frais_mensuel" required min="10000" max="200000" value="50000" class="w-full border-2 px-4 py-3 rounded-lg focus:ring-2 focus:ring-red-500">
                                <p class="text-xs text-gray-500 mt-1">Entre 10,000 et 200,000 DA</p>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Code Spécial *</label>
                                <input type="text" name="special_code" required placeholder="Ex: ANN004" class="w-full border-2 px-4 py-3 rounded-lg focus:ring-2 focus:ring-red-500">
                            </div>

                            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                                <p class="text-sm text-green-800">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    7 voitures seront automatiquement créées pour cette compagnie
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-check mr-2"></i>Créer Compagnie
                                </button>
                                <button type="button" onclick="closeModal('companyModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-times mr-2"></i>Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Administrators Management -->
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-user-shield mr-2 text-purple-600"></i>
                            Gestion des Administrateurs
                        </h3>
                        <button onclick="openOwnerAdminModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition font-semibold">
                            <i class="fas fa-plus mr-2"></i>Ajouter Administrateur
                        </button>
                    </div>

                    <?php
                    $admins = $db->query("SELECT a.*, c.c_name, w.name as wilaya_name FROM administrator a LEFT JOIN company c ON a.company_id = c.company_id LEFT JOIN wilaya w ON a.wilaya_id = w.id ORDER BY a.created_at DESC");
                    if ($admins->num_rows > 0):
                    ?>
                        <div class="overflow-x-auto">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom & Prénom</th>
                                        <th>Âge</th>
                                        <th>Compagnie</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Wilaya</th>
                                        <th>Salaire</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($admin = $admins->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?= $admin['id'] ?></td>
                                            <td class="font-semibold"><?= $admin['prenom'] ?> <?= $admin['nom'] ?></td>
                                            <td><?= $admin['age'] ?> ans</td>
                                            <td><?= $admin['c_name'] ?? 'N/A' ?></td>
                                            <td><?= $admin['email'] ?></td>
                                            <td><?= $admin['numero_tlfn'] ?></td>
                                            <td><?= $admin['wilaya_name'] ?></td>
                                            <td class="font-bold text-green-600"><?= number_format($admin['salaire'], 0, ',', ' ') ?> DA</td>
                                            <td>
                                                <div class="flex gap-2">
                                                    <button onclick='editOwnerAdmin(<?= json_encode($admin) ?>)' class="text-blue-600 hover:underline text-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Confirmer la suppression?')">
                                                        <input type="hidden" name="action" value="delete_admin">
                                                        <input type="hidden" name="admin_id" value="<?= $admin['id'] ?>">
                                                        <button type="submit" class="text-red-600 hover:underline text-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-user-slash text-5xl mb-4"></i>
                            <p>Aucun administrateur</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Modal Admin Management -->
                <div id="ownerAdminModal" class="modal">
                    <div class="modal-content" style="max-width: 800px;">
                        <h3 id="ownerAdminModalTitle" class="text-2xl font-bold text-gray-800 mb-4">
                            <i class="fas fa-user-shield mr-2 text-purple-600"></i>
                            Gérer Administrateur
                        </h3>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" id="ownerAdminAction" value="add_admin">
                            <input type="hidden" name="admin_id" id="owner_admin_id">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Nom *</label>
                                    <input type="text" name="nom" id="ownerAdminNom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Prénom *</label>
                                    <input type="text" name="prenom" id="ownerAdminPrenom" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Âge * (min 24)</label>
                                    <input type="number" name="age" id="ownerAdminAge" min="24" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Téléphone *</label>
                                    <input type="tel" name="numero_tlfn" id="ownerAdminTel" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Nationalité *</label>
                                    <input type="text" name="nationalite" id="ownerAdminNat" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">N° Carte Nationale *</label>
                                    <input type="text" name="numero_cart_national" id="ownerAdminCard" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Wilaya *</label>
                                    <select name="wilaya_id" id="ownerAdminWilaya" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                        <option value="">-- Sélectionner --</option>
                                        <?php
                                        $wilayas = $app->getWilayas();
                                        while ($w = $wilayas->fetch_assoc()) {
                                            echo "<option value='{$w['id']}'>{$w['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 font-semibold">Compagnie *</label>
                                <select name="company_id" id="ownerAdminCompany" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                    <option value="">-- Sélectionner --</option>
                                    <?php
                                    $comps = $app->getAllCompanies();
                                    while ($comp = $comps->fetch_assoc()) {
                                        echo "<option value='{$comp['company_id']}'>{$comp['c_name']}</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Salaire (DA) *</label>
                                    <input type="number" name="salaire" id="ownerAdminSalaire" min="30000" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 mb-2 font-semibold">Email *</label>
                                    <input type="email" name="email" id="ownerAdminEmail" required class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>

                            <div id="ownerAdminPasswordDiv">
                                <label class="block text-gray-700 mb-2 font-semibold">Mot de passe *</label>
                                <input type="password" name="password" id="ownerAdminPassword" class="w-full border-2 px-3 py-2 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>

                            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    L'âge minimum requis est de 24 ans
                                </p>
                            </div>

                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-check mr-2"></i>Enregistrer
                                </button>
                                <button type="button" onclick="closeModal('ownerAdminModal')" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white py-3 rounded-lg transition font-semibold">
                                    <i class="fas fa-times mr-2"></i>Annuler
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                function openCompanyModal() {
                    document.getElementById('companyModal').style.display = 'block';
                }

                function openOwnerAdminModal() {
                    document.getElementById('ownerAdminAction').value = 'add_admin';
                    document.getElementById('ownerAdminModalTitle').innerHTML = '<i class="fas fa-plus mr-2 text-purple-600"></i>Ajouter Administrateur';
                    document.getElementById('owner_admin_id').value = '';
                    document.getElementById('ownerAdminPassword').required = true;
                    document.getElementById('ownerAdminPasswordDiv').style.display = 'block';
                    document.getElementById('ownerAdminModal').style.display = 'block';
                }

                function editOwnerAdmin(admin) {
                    document.getElementById('ownerAdminAction').value = 'update_admin';
                    document.getElementById('ownerAdminModalTitle').innerHTML = '<i class="fas fa-edit mr-2 text-purple-600"></i>Modifier Administrateur';
                    document.getElementById('owner_admin_id').value = admin.id;
                    document.getElementById('ownerAdminNom').value = admin.nom;
                    document.getElementById('ownerAdminPrenom').value = admin.prenom;
                    document.getElementById('ownerAdminAge').value = admin.age;
                    document.getElementById('ownerAdminTel').value = admin.numero_tlfn;
                    document.getElementById('ownerAdminNat').value = admin.nationalite;
                    document.getElementById('ownerAdminCard').value = admin.numero_cart_national;
                    document.getElementById('ownerAdminWilaya').value = admin.wilaya_id;
                    document.getElementById('ownerAdminCompany').value = admin.company_id;
                    document.getElementById('ownerAdminSalaire').value = admin.salaire;
                    document.getElementById('ownerAdminEmail').value = admin.email;
                    document.getElementById('ownerAdminPassword').required = false;
                    document.getElementById('ownerAdminPasswordDiv').style.display = 'none';
                    document.getElementById('ownerAdminModal').style.display = 'block';
                }

                function closeModal(modalId) {
                    document.getElementById(modalId).style.display = 'none';
                }

                window.onclick = function(event) {
                    if (event.target.classList.contains('modal')) {
                        event.target.style.display = 'none';
                    }
                }
            </script>

        <?php
        
        else:
            // DEFAULT / ERROR PAGE
        ?>
            <div class="text-center py-20">
                <i class="fas fa-exclamation-triangle text-6xl text-yellow-500 mb-4"></i>
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Accès Non Autorisé</h2>
                <p class="text-gray-600 mb-6">Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
                <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition font-semibold inline-block">
                    <i class="fas fa-home mr-2"></i>Retour à l'Accueil
                </a>
            </div>
        <?php
        endif;
        ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-12 py-8">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">
                        <i class="fas fa-car mr-2"></i>DZLocation
                    </h3>
                    <p class="text-gray-400 text-sm">
                        Système professionnel de gestion de location de voitures en Algérie. 
                        Solution complète pour multi-agences avec gestion avancée.
                    </p>
                </div>
                
                <div>
                    <h4 class="font-bold mb-4">Fonctionnalités</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Gestion Multi-Agences</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Navigation Temporelle</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Validation Âge 24 ans</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Dashboard Complet</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Paiement Sécurisé</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Auto-reset Réservations</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold mb-4">Informations</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><i class="fas fa-user text-blue-400 mr-2"></i>Propriétaire: Cherifi Youssouf</li>
                        <li><i class="fas fa-envelope text-blue-400 mr-2"></i>chirifiyoucef@mail.com</li>
                        <li><i class="fas fa-database text-blue-400 mr-2"></i>DB: chirifiyoucef_agence13</li>
                        <li><i class="fas fa-code text-blue-400 mr-2"></i>Version: 3.0 Enhanced</li>
                        <li><i class="fas fa-calendar text-blue-400 mr-2"></i><?= date('Y') ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-6 text-center">
                <p class="text-gray-400 text-sm">
                    © <?= date('Y') ?> DZLocation - Système de Location de Voitures en Algérie
                    <br>
                    <span class="text-xs">Développé avec <i class="fas fa-heart text-red-500"></i> pour la gestion professionnelle</span>
                </p>
            </div>
        </div>
    </footer>

    <!-- Global Modal Close Script -->
    <script>
        // Close all modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });

        // Auto-hide success/error messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Confirmation for delete actions
        function confirmDelete(itemName) {
            return confirm('Êtes-vous sûr de vouloir supprimer ' + itemName + ' ?');
        }

        // Number formatting helper
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-DZ', {
                style: 'currency',
                currency: 'DZD',
                minimumFractionDigits: 0
            }).format(amount);
        }

        // Date formatting helper
        function formatDate(dateString) {
            const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
            return new Date(dateString).toLocaleDateString('fr-FR', options);
        }

        // Console welcome message
        console.log('%c🚗 DZLocation System v3.0', 'color: #3b82f6; font-size: 20px; font-weight: bold;');
        console.log('%cPropriétaire: Cherifi Youssouf', 'color: #10b981; font-size: 14px;');
        console.log('%cDatabase: chirifiyoucef_agence13', 'color: #8b5cf6; font-size: 14px;');
        console.log('%c✓ Navigation Temporelle', 'color: #f59e0b;');
        console.log('%c✓ Validation Âge 24 ans', 'color: #f59e0b;');
        console.log('%c✓ Dashboard Complet', 'color: #f59e0b;');
        console.log('%c✓ Auto-reset Réservations', 'color: #f59e0b;');
        console.log('%c✓ Paiement Sécurisé (16+3 digits)', 'color: #f59e0b;');
    </script>

    <!-- Print Styles -->
    <style media="print">
        nav, footer, button, .modal { display: none !important; }
        body { background: white !important; }
        .card-hover { box-shadow: none !important; }
    </style>

</body>
</html>
