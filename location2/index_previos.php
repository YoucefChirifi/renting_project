<?php
/*************************************************************** 
 * SYSTÈME DE LOCATION DE VOITURES EN ALGÉRIE
 * Propriétaire: Cherifi Youssouf
 * Version: 2.0 - Corrigée et améliorée
 *
 * CORRECTIONS APPLIQUÉES:
 * 1. Suppression de la fonction commission pour les agents
 * 2. Ajout des droits du propriétaire (super_admin)
 * 3. Correction du bug Duplicate entry '0' for key 'email'
 * 4. Support de 4 types de comptes: client, agent, admin, owner
 ***************************************************************/ 

session_start();

/*************************************************************** 
 * PARTIE 1: BASE DE DONNÉES
 ***************************************************************/

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
            email VARCHAR(100) UNIQUE NOT NULL,
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
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        // Table agent - CORRIGÉE: suppression des colonnes commission
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
            email VARCHAR(100) UNIQUE NOT NULL,
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

        // Table reservation - SANS agent_commission
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

        // Créer le super admin (Cherifi Youssouf)
        $checkSuperAdmin = $this->conn->query("SELECT id FROM super_admin WHERE email = 'chirifiyoucef@mail.com'");
        if ($checkSuperAdmin->num_rows == 0) {
            $hashed_password = password_hash('123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO super_admin (nom, prenom, email, password) VALUES ('Cherifi', 'Youssouf', 'chirifiyoucef@mail.com', '$hashed_password')");
        }

        // Vérifier si nous avons besoin de créer les compagnies
        $checkCompanies = $this->conn->query("SELECT COUNT(*) as count FROM company");
        $result = $checkCompanies->fetch_assoc();
        
        if ($result['count'] == 0) {
            // === COMPAGNIE 1: Location Auto Alger ===
            $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Auto Alger', 50000.00, 'ALG001', 1)");
            $company1 = $this->conn->insert_id;

            // Admin pour compagnie 1
            $hashed_password = password_hash('123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Benali', 'Karim', 35, '0555111111', 'Algérienne', '1111111111111111', 16, 80000.00, $company1, 'admin@alger.com', '$hashed_password', 1)");

            // Agent pour compagnie 1
            $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Mansouri', 'Nassim', 28, '0555222222', 'Algérienne', '2222222222222222', 16, 50000.00, $company1, 'agent@alger.com', '$hashed_password', 1)");

            // Client pour compagnie 1
            $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Zeroual', 'Amine', 25, '0555333333', 'Algérienne', '3333333333333333', 16, 'non reserve', $company1, 'client@alger.com', '$hashed_password', 1)");

            // Voitures pour compagnie 1
            $cars1 = [
                ['Toyota', 'Corolla', 'Blanc', 2022, 1, 5000.00, 1, '111111 1 2022 16'],
                ['BMW', '3 Series', 'Noir', 2021, 2, 8000.00, 1, '222222 2 2021 16'],
                ['Mercedes', 'C-Class', 'Argent', 2023, 3, 12000.00, 1, '333333 3 2023 16']
            ];
            foreach ($cars1 as $car) {
                $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company1, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
            }

            // === COMPAGNIE 2: Auto Location Oran ===
            $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Auto Location Oran', 45000.00, 'ORAN002', 1)");
            $company2 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Bouguerra', 'Samir', 40, '0555444444', 'Algérienne', '4444444444444444', 31, 75000.00, $company2, 'admin@oran.com', '$hashed_password', 1)");

            $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Touati', 'Yacine', 30, '0555555555', 'Algérienne', '5555555555555555', 31, 45000.00, $company2, 'agent@oran.com', '$hashed_password', 1)");

            $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Khelifi', 'Rachid', 27, '0555666666', 'Algérienne', '6666666666666666', 31, 'non reserve', $company2, 'client@oran.com', '$hashed_password', 1)");

            $cars2 = [
                ['Renault', 'Clio', 'Bleu', 2021, 1, 4500.00, 1, '444444 1 2021 31'],
                ['Audi', 'A4', 'Gris', 2022, 2, 9000.00, 1, '555555 2 2022 31'],
                ['Porsche', 'Panamera', 'Noir', 2023, 3, 18000.00, 1, '666666 3 2023 31']
            ];
            foreach ($cars2 as $car) {
                $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company2, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
            }

            // === COMPAGNIE 3: Location Voiture Constantine ===
            $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Voiture Constantine', 55000.00, 'CONST003', 1)");
            $company3 = $this->conn->insert_id;

            $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Salhi', 'Farid', 38, '0555777777', 'Algérienne', '7777777777777777', 25, 85000.00, $company3, 'admin@constantine.com', '$hashed_password', 1)");

            $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Mekideche', 'Hakim', 32, '0555888888', 'Algérienne', '8888888888888888', 25, 55000.00, $company3, 'agent@constantine.com', '$hashed_password', 1)");

            $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Benaissa', 'Sofiane', 29, '0555999999', 'Algérienne', '9999999999999999', 25, 'non reserve', $company3, 'client@constantine.com', '$hashed_password', 1)");

            $cars3 = [
                ['Volkswagen', 'Golf', 'Rouge', 2022, 1, 4800.00, 1, '777777 1 2022 25'],
                ['BMW', '5 Series', 'Blanc', 2022, 2, 11000.00, 1, '888888 2 2022 25'],
                ['Mercedes', 'S-Class', 'Noir', 2023, 3, 20000.00, 1, '999999 3 2023 25']
            ];
            foreach ($cars3 as $car) {
                $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company3, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
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
 * PARTIE 2: CLASSES D'AUTHENTIFICATION
 ***************************************************************/

class Auth {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    public function login($email, $password, $role) {
        // CORRECTION: Validation stricte de l'email
        if (empty($email) || empty($password)) {
            return false;
        }

        $email = trim($email);
        $password = trim($password);

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

            // Vérification du mot de passe
            if ($role == 'super_admin') {
                if ($password == '123' || password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $role;
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                    $_SESSION['company_id'] = 0;

                    // Hasher le mot de passe s'il n'est pas encore hashé
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
                    $_SESSION['company_id'] = $user['company_id'] ?? 1;
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
        // CORRECTION: Validation complète des données
        if (empty($data['email']) || empty($data['password'])) {
            return false;
        }

        $data['email'] = trim($data['email']);
        $data['password'] = trim($data['password']);

        // Vérifier si l'email existe déjà
        $table = $this->getTableByRole($role);
        $check = $this->db->query("SELECT id FROM $table WHERE email = '{$data['email']}'");
        if ($check->num_rows > 0) {
            return false;
        }

        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        if ($role == 'client') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, email, password, company_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssisii", 
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
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
        } else {
            return false;
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
        // Calculer la période en jours
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

        // CORRIGÉ: Pas de commission automatique
        $stmt = $this->db->prepare("INSERT INTO reservation (id_client, id_company, car_id, start_date, end_date, period, montant, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("iiissid", $data['id_client'], $data['id_company'], $data['car_id'], $data['start_date'], $data['end_date'], $period, $montant);

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

        $stmt = $this->db->prepare("SELECT r.montant FROM reservation r WHERE r.id_reservation = ?");
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
        $sql = "SELECT $date_format as period, COUNT(*) as total_reservations, SUM(r.montant) as total_amount, AVG(r.montant) as avg_amount FROM reservation r WHERE r.id_company = $company_id AND r.status = 'completed' GROUP BY $group_by ORDER BY period DESC LIMIT 10";
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

        $revenue = $this->db->query("SELECT SUM(r.montant) as total_revenue FROM reservation r WHERE r.id_company = $company_id AND r.status = 'completed'")->fetch_assoc();

        $salaries = $this->db->query("SELECT SUM(salaire) as total_salaries FROM (SELECT salaire FROM administrator WHERE company_id = $company_id UNION ALL SELECT salaire FROM agent WHERE company_id = $company_id) as salaries")->fetch_assoc();

        $company_fees = $this->db->query("SELECT frais_mensuel FROM company WHERE company_id = $company_id")->fetch_assoc();

        $result['total_revenue'] = $revenue['total_revenue'] ?? 0;
        $result['total_salaries'] = $salaries['total_salaries'] ?? 0;
        $result['company_fees'] = $company_fees['frais_mensuel'] ?? 50000;
        $result['total_expenses'] = $result['total_salaries'] + $result['company_fees'];
        $result['net_profit'] = $result['total_revenue'] - $result['total_expenses'];

        return $result;
    }
}

/*************************************************************** 
 * PARTIE 3: TRAITEMENT DES ACTIONS
 ***************************************************************/

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
        switch ($role) {
            case 'super_admin': $redirect = 'super_admin'; break;
            case 'client': $redirect = 'client'; break;
            case 'agent': $redirect = 'agent'; break;
            case 'administrator': $redirect = 'admin'; break;
            default: $redirect = 'client';
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
        if ($_POST['age'] < 24) {
            $register_error = "L'âge minimum est de 24 ans";
        } elseif ($_POST['password'] != $_POST['confirm_password']) {
            $register_error = "Les mots de passe ne correspondent pas";
        } elseif (empty($_POST['email'])) {
            $register_error = "L'email est obligatoire";
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

// Actions de l'administrateur
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_car':
                $serial = rand(100000, 999999);
                $category = $_POST['category'];
                $year = $_POST['annee'];
                $wilaya = 31;
                $matricule = "$serial $category $year $wilaya";
                $stmt = $db->prepare("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?)");
                $stmt->bind_param("isssiidisi", $_SESSION['company_id'], $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'], $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $matricule, $auth->getUserId());
                if ($stmt->execute()) {
                    $success = "Voiture ajoutée avec succès";
                } else {
                    $error = "Erreur lors de l'ajout de la voiture";
                }
                break;

            case 'update_car':
                $stmt = $db->prepare("UPDATE car SET marque = ?, model = ?, color = ?, annee = ?, category = ?, prix_day = ?, status_voiture = ?, voiture_work = ? WHERE id_car = ? AND company_id = ?");
                $stmt->bind_param("sssiidissi", $_POST['marque'], $_POST['model'], $_POST['color'], $_POST['annee'], $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'], $_POST['voiture_work'], $_POST['car_id'], $_SESSION['company_id']);
                if ($stmt->execute()) {
                    $success = "Voiture mise à jour avec succès";
                } else {
                    $error = "Erreur lors de la mise à jour de la voiture";
                }
                break;

            case 'delete_car':
                $car_id = intval($_POST['car_id']);
                $company_id = $_SESSION['company_id'];
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
                $stmt = $db->prepare("UPDATE agent SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ?, salaire = ?, email = ? WHERE id = ? AND company_id = ?");
                $stmt->bind_param("ssisssidsii", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['email'], $_POST['agent_id'], $_SESSION['company_id']);
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
                $stmt = $db->prepare("UPDATE client SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ?, email = ? WHERE id = ? AND company_id = ?");
                $stmt->bind_param("ssissssii", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['email'], $_POST['client_id'], $_SESSION['company_id']);
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

// Actions du super admin (PROPRIÉTAIRE)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_company':
                $stmt = $db->prepare("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sdsi", $_POST['c_name'], $_POST['frais_mensuel'], $_POST['special_code'], $auth->getUserId());
                if ($stmt->execute()) {
                    $success = "Compagnie créée avec succès!";
                    autoCreateCars($db, $db->conn->insert_id, $auth->getUserId());
                } else {
                    $error = "Erreur: " . $db->conn->error;
                }
                break;

            case 'update_company':
                $stmt = $db->prepare("UPDATE company SET c_name = ?, frais_mensuel = ?, special_code = ? WHERE company_id = ?");
                $stmt->bind_param("sdsi", $_POST['c_name'], $_POST['frais_mensuel'], $_POST['special_code'], $_POST['company_id']);
                if ($stmt->execute()) {
                    $success = "Compagnie mise à jour avec succès";
                } else {
                    $error = "Erreur lors de la mise à jour";
                }
                break;

            case 'delete_company':
                $company_id = intval($_POST['company_id']);
                $check_reservations = $db->query("SELECT COUNT(*) as count FROM reservation WHERE id_company = $company_id")->fetch_assoc();
                if ($check_reservations['count'] > 0) {
                    $error = "Impossible de supprimer cette compagnie car elle contient des réservations actives.";
                } else {
                    $db->query("DELETE FROM agent WHERE company_id = $company_id");
                    $db->query("DELETE FROM administrator WHERE company_id = $company_id");
                    $db->query("DELETE FROM client WHERE company_id = $company_id");
                    $db->query("DELETE FROM car WHERE company_id = $company_id");
                    $db->query("DELETE FROM company WHERE company_id = $company_id");
                    $success = "Compagnie supprimée avec succès";
                }
                break;

            case 'add_admin':
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
                        'company_id' => $_POST['company_id'],
                        'email' => $_POST['email'],
                        'password' => $_POST['password']
                    ];
                    if ($auth->registerUser($data, 'administrator', $auth->getUserId())) {
                        $success = "Administrateur ajouté avec succès";
                    } else {
                        $error = "Erreur lors de l'ajout de l'administrateur";
                    }
                }
                break;

            case 'update_admin':
                $stmt = $db->prepare("UPDATE administrator SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ?, salaire = ?, company_id = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssisssdssi", $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'], $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'], $_POST['company_id'], $_POST['email'], $_POST['admin_id']);
                if ($stmt->execute()) {
                    $success = "Administrateur mis à jour avec succès";
                } else {
                    $error = "Erreur lors de la mise à jour";
                }
                break;

            case 'delete_admin':
                $admin_id = intval($_POST['admin_id']);
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

// Fonctions utilitaires
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
        $db->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company_id, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '$matricule', 'disponible', $created_by)");
    }
}

// Déterminer la page à afficher
$page = 'home';
if (isset($_GET['page'])) {
    $page = $_GET['page'];
} elseif ($auth->isLoggedIn()) {
    $role = $auth->getUserRole();
    switch ($role) {
        case 'super_admin': $page = 'super_admin'; break;
        case 'client': $page = 'client'; break;
        case 'agent': $page = 'agent'; break;
        case 'administrator': $page = 'admin'; break;
    }
}

/*************************************************************** 
 * PARTIE 4: AFFICHAGE HTML - PAGES
 ***************************************************************/
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DZLocation - Location de Voitures</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; margin-bottom: 2rem; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: white; text-decoration: none; margin-left: 1.5rem; }
        .navbar a:hover { text-decoration: underline; }
        .card { background: white; padding: 2rem; margin-bottom: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; }
        input, textarea, select { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #764ba2; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #667eea; color: white; }
        tr:hover { background: #f9f9f9; }
        .alert { padding: 1rem; margin-bottom: 1rem; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-payer { color: green; font-weight: 600; }
        .status-reserve { color: orange; font-weight: 600; }
        .status-annuler { color: red; font-weight: 600; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin: 2rem 0; }
        .stat-box { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .stat-box h3 { color: #667eea; margin-bottom: 0.5rem; }
        .stat-box p { font-size: 2rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($auth->isLoggedIn()): ?>
            <div class="navbar">
                <div><strong>🚗 DZLocation</strong> | <?php echo $_SESSION['user_name']; ?> (<?php echo ucfirst($_SESSION['user_role']); ?>)</div>
                <div>
                    <a href="index.php?page=home">Accueil</a>
                    <a href="index.php?action=logout">Déconnexion</a>
                </div>
            </div>
        <?php else: ?>
            <div class="navbar">
                <div><strong>🚗 DZLocation - Bienvenue</strong></div>
                <div></div>
            </div>
        <?php endif; ?>

        <!-- PAGE D'ACCUEIL -->
        <?php if ($page == 'home'): ?>
            <div class="card">
                <h1>🚗 Système de Location de Voitures - DZLocation</h1>
                <p style="margin-top: 1rem; color: #666;">Bienvenue sur notre plateforme de location de voitures en Algérie!</p>

                <?php if (!$auth->isLoggedIn()): ?>
                    <div style="margin-top: 2rem;">
                        <h2>Connexion</h2>
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-danger"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label>Mot de passe:</label>
                                <input type="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label>Type de compte:</label>
                                <select name="role" required>
                                    <option value="client">Client</option>
                                    <option value="agent">Agent</option>
                                    <option value="administrator">Administrateur</option>
                                    <option value="super_admin">Propriétaire (Cherifi Youssouf)</option>
                                </select>
                            </div>
                            <button type="submit" name="login" class="btn">Se connecter</button>
                        </form>
                    </div>

                    <hr style="margin: 2rem 0;">
                    <div>
                        <h3>Comptes de test:</h3>
                        <table>
                            <tr>
                                <th>Type</th>
                                <th>Email</th>
                                <th>Mot de passe</th>
                            </tr>
                            <tr>
                                <td>Propriétaire</td>
                                <td>chirifiyoucef@mail.com</td>
                                <td>123</td>
                            </tr>
                            <tr>
                                <td>Administrateur</td>
                                <td>admin@alger.com</td>
                                <td>123</td>
                            </tr>
                            <tr>
                                <td>Agent</td>
                                <td>agent@alger.com</td>
                                <td>123</td>
                            </tr>
                            <tr>
                                <td>Client</td>
                                <td>client@alger.com</td>
                                <td>123</td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- PAGE CLIENT -->
        <?php if ($page == 'client' && $auth->isLoggedIn() && $auth->getUserRole() == 'client'): ?>
            <div class="card">
                <h2>Bienvenue Client</h2>
                <p>Consultez les voitures disponibles et gérez vos réservations</p>
            </div>
        <?php endif; ?>

        <!-- PAGE AGENT -->
        <?php if ($page == 'agent' && $auth->isLoggedIn() && $auth->getUserRole() == 'agent'): ?>
            <div class="card">
                <h2>Gestion Agent</h2>
                <p>Vous pouvez ajouter et gérer les clients.</p>
                <?php if (isset($register_success)): ?>
                    <div class="alert alert-success"><?php echo $register_success; ?></div>
                <?php endif; ?>
                <?php if (isset($register_error)): ?>
                    <div class="alert alert-danger"><?php echo $register_error; ?></div>
                <?php endif; ?>

                <h3>Ajouter un Client</h3>
                <form method="POST">
                    <input type="hidden" name="register_client" value="1">
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
                        <label>Confirmer le mot de passe:</label>
                        <input type="password" name="confirm_password" required>
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
                        <label>Numéro de carte nationale:</label>
                        <input type="text" name="numero_cart_national" required>
                    </div>
                    <div class="form-group">
                        <label>Wilaya:</label>
                        <select name="wilaya_id" required>
                            <option value="">Sélectionner une wilaya</option>
                            <?php $wilayas = $app->getWilayas();
                            while ($wilaya = $wilayas->fetch_assoc()): ?>
                                <option value="<?php echo $wilaya['id']; ?>"><?php echo $wilaya['name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">Ajouter le client</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- PAGE ADMINISTRATEUR -->
        <?php if ($page == 'admin' && $auth->isLoggedIn() && $auth->getUserRole() == 'administrator'): ?>
            <div class="card">
                <h2>🛠️ Gestion Administrative</h2>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- TAB DE NAVIGATION -->
                <div style="margin: 2rem 0; border-bottom: 2px solid #ddd; display: flex; gap: 1rem;">
                    <a href="?page=admin&tab=cars" style="padding: 1rem; color: #667eea; text-decoration: none;">🚗 Voitures</a>
                    <a href="?page=admin&tab=agents" style="padding: 1rem; color: #667eea; text-decoration: none;">👤 Agents</a>
                    <a href="?page=admin&tab=clients" style="padding: 1rem; color: #667eea; text-decoration: none;">👥 Clients</a>
                    <a href="?page=admin&tab=stats" style="padding: 1rem; color: #667eea; text-decoration: none;">📊 Statistiques</a>
                </div>

                <!-- GESTION DES VOITURES -->
                <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'cars'): ?>
                    <h3>Gestion des Voitures</h3>
                    <form method="POST" style="margin: 1.5rem 0;">
                        <input type="hidden" name="action" value="add_car">
                        <h4>Ajouter une voiture</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
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
                        <button type="submit" class="btn">Ajouter</button>
                    </form>

                    <hr>
                    <h4>Voitures de la compagnie</h4>
                    <?php $cars = $app->getCompanyCars($_SESSION['company_id']);
                    if ($cars->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Marque/Modèle</th>
                                <th>Plaque</th>
                                <th>Catégorie</th>
                                <th>Prix/Jour</th>
                                <th>État</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($car = $cars->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $car['marque'] . ' ' . $car['model']; ?></td>
                                    <td><?php echo $car['matricule']; ?></td>
                                    <td><?php echo ['', 'Économique', 'Confort', 'Luxe'][$car['category']]; ?></td>
                                    <td><?php echo number_format($car['prix_day'], 2); ?> DA</td>
                                    <td><?php echo $app->getCarStatusText($car['status_voiture']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_car">
                                            <input type="hidden" name="car_id" value="<?php echo $car['id_car']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer la suppression?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php else: ?>
                        <p>Aucune voiture trouvée</p>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- GESTION DES AGENTS -->
                <?php if (isset($_GET['tab']) && $_GET['tab'] == 'agents'): ?>
                    <h3>Gestion des Agents</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_agent">
                        <h4>Ajouter un Agent</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
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
                                <label>Numéro de carte nationale:</label>
                                <input type="text" name="numero_cart_national" required>
                            </div>
                            <div class="form-group">
                                <label>Wilaya:</label>
                                <select name="wilaya_id" required>
                                    <?php $wilayas = $app->getWilayas();
                                    while ($wilaya = $wilayas->fetch_assoc()): ?>
                                        <option value="<?php echo $wilaya['id']; ?>"><?php echo $wilaya['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Salaire (DA):</label>
                                <input type="number" step="0.01" name="salaire" required>
                            </div>
                        </div>
                        <button type="submit" class="btn">Ajouter l'agent</button>
                    </form>

                    <hr>
                    <?php $agents = $db->query("SELECT * FROM agent WHERE company_id = {$_SESSION['company_id']}");
                    if ($agents->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Nom & Prénom</th>
                                <th>Email</th>
                                <th>Salaire</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($agent = $agents->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $agent['prenom'] . ' ' . $agent['nom']; ?></td>
                                    <td><?php echo $agent['email']; ?></td>
                                    <td><?php echo number_format($agent['salaire'], 2); ?> DA</td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_agent">
                                            <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- GESTION DES CLIENTS -->
                <?php if (isset($_GET['tab']) && $_GET['tab'] == 'clients'): ?>
                    <h3>Gestion des Clients</h3>
                    <?php $clients = $db->query("SELECT * FROM client WHERE company_id = {$_SESSION['company_id']}");
                    if ($clients->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Nom & Prénom</th>
                                <th>Email</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($client = $clients->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $client['prenom'] . ' ' . $client['nom']; ?></td>
                                    <td><?php echo $client['email']; ?></td>
                                    <td><span class="status-<?php echo $client['status']; ?>"><?php echo ucfirst($client['status']); ?></span></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_client">
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
                <?php endif; ?>

                <!-- STATISTIQUES -->
                <?php if (isset($_GET['tab']) && $_GET['tab'] == 'stats'): ?>
                    <h3>Statistiques de la Compagnie</h3>
                    <?php $financials = $app->getCompanyFinancials($_SESSION['company_id']); ?>
                    <div class="grid">
                        <div class="stat-box">
                            <h3>Revenus</h3>
                            <p><?php echo number_format($financials['total_revenue'], 2); ?> DA</p>
                        </div>
                        <div class="stat-box">
                            <h3>Dépenses</h3>
                            <p><?php echo number_format($financials['total_expenses'], 2); ?> DA</p>
                        </div>
                        <div class="stat-box">
                            <h3>Bénéfice Net</h3>
                            <p><?php echo number_format($financials['net_profit'], 2); ?> DA</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- PAGE SUPER ADMIN (PROPRIÉTAIRE) -->
        <?php if ($page == 'super_admin' && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin'): ?>
            <div class="card">
                <h2>👑 Gestion Propriétaire - Cherifi Youssouf</h2>
                <p>Gérez toutes les compagnies et administrateurs</p>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- TAB DE NAVIGATION -->
                <div style="margin: 2rem 0; border-bottom: 2px solid #ddd; display: flex; gap: 1rem;">
                    <a href="?page=super_admin&tab=companies" style="padding: 1rem; color: #667eea; text-decoration: none;">🏢 Compagnies</a>
                    <a href="?page=super_admin&tab=admins" style="padding: 1rem; color: #667eea; text-decoration: none;">⚙️ Administrateurs</a>
                </div>

                <!-- GESTION DES COMPAGNIES -->
                <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'companies'): ?>
                    <h3>Gestion des Compagnies</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_company">
                        <h4>Créer une nouvelle compagnie</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Nom de la compagnie:</label>
                                <input type="text" name="c_name" required>
                            </div>
                            <div class="form-group">
                                <label>Frais mensuels (DA):</label>
                                <input type="number" step="0.01" name="frais_mensuel" value="50000" required>
                            </div>
                            <div class="form-group">
                                <label>Code spécial:</label>
                                <input type="text" name="special_code" required>
                            </div>
                        </div>
                        <button type="submit" class="btn">Créer la compagnie</button>
                    </form>

                    <hr>
                    <h4>Compagnies existantes</h4>
                    <?php $companies = $db->query("SELECT * FROM company");
                    if ($companies->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Frais Mensuels</th>
                                <th>Code Spécial</th>
                                <th>Date Création</th>
                                <th>Actions</th>
                            </tr>
                            <?php while ($company = $companies->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $company['company_id']; ?></td>
                                    <td><?php echo $company['c_name']; ?></td>
                                    <td><?php echo number_format($company['frais_mensuel'], 2); ?> DA</td>
                                    <td><?php echo $company['special_code']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($company['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_company">
                                            <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer la suppression?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- GESTION DES ADMINISTRATEURS -->
                <?php if (isset($_GET['tab']) && $_GET['tab'] == 'admins'): ?>
                    <h3>Gestion des Administrateurs</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_admin">
                        <h4>Ajouter un administrateur</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
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
                                <label>Numéro de carte nationale:</label>
                                <input type="text" name="numero_cart_national" required>
                            </div>
                            <div class="form-group">
                                <label>Wilaya:</label>
                                <select name="wilaya_id" required>
                                    <?php $wilayas = $app->getWilayas();
                                    while ($wilaya = $wilayas->fetch_assoc()): ?>
                                        <option value="<?php echo $wilaya['id']; ?>"><?php echo $wilaya['name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Salaire (DA):</label>
                                <input type="number" step="0.01" name="salaire" required>
                            </div>
                            <div class="form-group">
                                <label>Compagnie:</label>
                                <select name="company_id" required>
                                    <?php $companies = $db->query("SELECT company_id, c_name FROM company");
                                    while ($company = $companies->fetch_assoc()): ?>
                                        <option value="<?php echo $company['company_id']; ?>"><?php echo $company['c_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn">Ajouter l'administrateur</button>
                    </form>

                    <hr>
                    <h4>Administrateurs existants</h4>
                    <?php $admins = $db->query("SELECT a.*, c.c_name FROM administrator a LEFT JOIN company c ON a.company_id = c.company_id");
                    if ($admins->num_rows > 0): ?>
                        <table>
                            <tr>
                                <th>Nom & Prénom</th>
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
                                            <input type="hidden" name="action" value="delete_admin">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer?')">Supprimer</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>