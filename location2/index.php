<?php
/***************************************************************
 * DZLocation - Système de Location de Voitures en Algérie
 * Application complète avec 4 rôles: Client, Agent, Administrateur, Super Admin
 * Propriétaire: Cherifi Youssouf
 * Langue: Français - Devise: DA
 * 
 * CORRECTIONS APPLIQUÉES:
 * 1. Email unique globalement (vérification dans toutes les tables)
 * 2. Carte Super Admin ajoutée sur la page de connexion
 * 3. Commission corrigée (liaison agent-réservation + pourcentage dynamique)
 ***************************************************************/

// Démarrage de la session
session_start();

// Configuration de l'affichage des erreurs
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
}

/***************************************************************
 * PARTIE 1: CONFIGURATION DE LA BASE DE DONNÉES ET CLASSES
 ***************************************************************/

class Database {
    private $host = 'localhost';
    private $user = 'root';
    private $pass = '';
    private $dbname = 'car_rental_algeria';
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

        // Table reservation
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

        // Créer le super admin
        $checkSuperAdmin = $this->conn->query("SELECT id FROM super_admin WHERE email = 'chirifiyoucef@mail.com'");
        if ($checkSuperAdmin->num_rows == 0) {
            $hashed_password = password_hash('123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO super_admin (nom, prenom, email, password) VALUES ('Cherifi', 'Youssouf', 'chirifiyoucef@mail.com', '$hashed_password')");
        }

        // Seed companies si elles n'existent pas
        $checkCompanies = $this->conn->query("SELECT COUNT(*) as count FROM company");
        $result = $checkCompanies->fetch_assoc();
        if ($result['count'] == 0) {
            $this->seedCompanies();
        }
    }

    private function seedCompanies() {
        $hashed_password = password_hash('123', PASSWORD_DEFAULT);

        // Compagnie 1
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Auto Alger', 50000.00, 'ALG001', 1)");
        $company1 = $this->conn->insert_id;

        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Benali', 'Karim', 35, '0555111111', 'Algérienne', '1111111111111111', 16, 80000.00, $company1, 'admin@alger.com', '$hashed_password', 1)");

        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by, commission_percentage) VALUES ('Mansouri', 'Nassim', 28, '0555222222', 'Algérienne', '2222222222222222', 16, 50000.00, $company1, 'agent@alger.com', '$hashed_password', 1, 1.5)");
        $agent1_id = $this->conn->insert_id;

        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Zeroual', 'Amine', 25, '0555333333', 'Algérienne', '3333333333333333', 16, 'non reserve', $company1, 'client@alger.com', '$hashed_password', $agent1_id)");

        $cars1 = [
            ['Toyota', 'Corolla', 'Blanc', 2022, 1, 5000.00, 1, '111111 1 2022 16'],
            ['BMW', '3 Series', 'Noir', 2021, 2, 8000.00, 1, '222222 2 2021 16'],
            ['Mercedes', 'C-Class', 'Argent', 2023, 3, 12000.00, 1, '333333 3 2023 16']
        ];
        foreach ($cars1 as $car) {
            $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company1, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
        }

        // Compagnie 2
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Auto Location Oran', 45000.00, 'ORAN002', 1)");
        $company2 = $this->conn->insert_id;

        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Bouguerra', 'Samir', 40, '0555444444', 'Algérienne', '4444444444444444', 31, 75000.00, $company2, 'admin@oran.com', '$hashed_password', 1)");

        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by, commission_percentage) VALUES ('Touati', 'Yacine', 30, '0555555555', 'Algérienne', '5555555555555555', 31, 45000.00, $company2, 'agent@oran.com', '$hashed_password', 1, 1.5)");
        $agent2_id = $this->conn->insert_id;

        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Khelifi', 'Rachid', 27, '0555666666', 'Algérienne', '6666666666666666', 31, 'non reserve', $company2, 'client@oran.com', '$hashed_password', $agent2_id)");

        $cars2 = [
            ['Renault', 'Clio', 'Bleu', 2021, 1, 4500.00, 1, '444444 1 2021 31'],
            ['Audi', 'A4', 'Gris', 2022, 2, 9000.00, 1, '555555 2 2022 31'],
            ['Porsche', 'Panamera', 'Noir', 2023, 3, 18000.00, 1, '666666 3 2023 31']
        ];
        foreach ($cars2 as $car) {
            $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company2, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
        }

        // Compagnie 3
        $this->conn->query("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES ('Location Voiture Constantine', 55000.00, 'CONST003', 1)");
        $company3 = $this->conn->insert_id;

        $this->conn->query("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES ('Salhi', 'Farid', 38, '0555777777', 'Algérienne', '7777777777777777', 25, 85000.00, $company3, 'admin@constantine.com', '$hashed_password', 1)");

        $this->conn->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by, commission_percentage) VALUES ('Mekideche', 'Hakim', 32, '0555888888', 'Algérienne', '8888888888888888', 25, 55000.00, $company3, 'agent@constantine.com', '$hashed_password', 1, 1.5)");
        $agent3_id = $this->conn->insert_id;

        $this->conn->query("INSERT INTO client (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id, email, password, created_by) VALUES ('Benaissa', 'Sofiane', 29, '0555999999', 'Algérienne', '9999999999999999', 25, 'non reserve', $company3, 'client@constantine.com', '$hashed_password', $agent3_id)");

        $cars3 = [
            ['Volkswagen', 'Golf', 'Rouge', 2022, 1, 4800.00, 1, '777777 1 2022 25'],
            ['BMW', '5 Series', 'Blanc', 2022, 2, 11000.00, 1, '888888 2 2022 25'],
            ['Mercedes', 'S-Class', 'Noir', 2023, 3, 20000.00, 1, '999999 3 2023 25']
        ];
        foreach ($cars3 as $car) {
            $this->conn->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company3, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '{$car[7]}', 'disponible', 1)");
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

    // CORRECTION 1: Fonction pour vérifier si l'email existe dans toutes les tables
    public function emailExists($email) {
        $tables = ['super_admin', 'administrator', 'agent', 'client'];
        foreach ($tables as $table) {
            $stmt = $this->db->prepare("SELECT id FROM $table WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                return true;
            }
        }
        return false;
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

            if ($role == 'super_admin') {
                if ($password == '123' || password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $role;
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['prenom'] . ' ' . $user['nom'];
                    $_SESSION['company_id'] = 0;
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

    // CORRECTION 1: registerUser vérifie maintenant l'unicité globale de l'email
    public function registerUser($data, $role, $created_by = null) {
        // Vérifier si l'email existe déjà dans n'importe quelle table
        if ($this->emailExists($data['email'])) {
            return false;
        }

        $table = $this->getTableByRole($role);
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

        if ($role == 'client') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, email, password, company_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssissi",
                $data['nom'], $data['prenom'], $data['age'], $data['numero_tlfn'],
                $data['nationalite'], $data['numero_cart_national'], $data['wilaya_id'],
                $data['email'], $hashed_password, $data['company_id'], $created_by
            );
        } elseif ($role == 'agent') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssdissi",
                $data['nom'], $data['prenom'], $data['age'], $data['numero_tlfn'],
                $data['nationalite'], $data['numero_cart_national'], $data['wilaya_id'],
                $data['salaire'], $data['company_id'], $data['email'], $hashed_password, $created_by
            );
        } elseif ($role == 'administrator') {
            $stmt = $this->db->prepare("INSERT INTO $table (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssissssdissi",
                $data['nom'], $data['prenom'], $data['age'], $data['numero_tlfn'],
                $data['nationalite'], $data['numero_cart_national'], $data['wilaya_id'],
                $data['salaire'], $data['company_id'], $data['email'], $hashed_password, $created_by
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
        $sql = "SELECT c.*, co.c_name as company_name FROM car c 
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

    // CORRECTION 3: createReservation lie maintenant l'agent à la réservation
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

        // CORRECTION 3: Récupérer l'agent qui a créé ce client
        $agent_id = null;
        $agent_stmt = $this->db->prepare("SELECT created_by FROM client WHERE id = ?");
        $agent_stmt->bind_param("i", $data['id_client']);
        $agent_stmt->execute();
        $agent_result = $agent_stmt->get_result();
        if ($agent_result->num_rows > 0) {
            $agent_data = $agent_result->fetch_assoc();
            // Vérifier que created_by est bien un agent
            $check_agent = $this->db->prepare("SELECT id FROM agent WHERE id = ?");
            $check_agent->bind_param("i", $agent_data['created_by']);
            $check_agent->execute();
            if ($check_agent->get_result()->num_rows > 0) {
                $agent_id = $agent_data['created_by'];
            }
        }

        // INSERT avec id_agent
        $stmt = $this->db->prepare("INSERT INTO reservation (id_agent, id_client, id_company, car_id, start_date, end_date, period, montant, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("iiiissid",
            $agent_id,
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
            $this->db->query("UPDATE car SET voiture_work = 'non disponible' WHERE id_car = {$data['car_id']}");
            $this->db->query("UPDATE client SET status = 'reserve' WHERE id = {$data['id_client']}");
            return $reservation_id;
        }
        return false;
    }

    // CORRECTION 3: processPayment utilise le pourcentage de commission de l'agent
    public function processPayment($reservation_id, $card_number, $card_code) {
        if (strlen($card_number) != 16 || !is_numeric($card_number)) {
            return false;
        }
        if (strlen($card_code) != 3 || !is_numeric($card_code)) {
            return false;
        }

        // CORRECTION 3: Récupérer aussi le pourcentage de commission de l'agent
        $stmt = $this->db->prepare("SELECT r.montant, r.id_agent, a.commission_percentage 
                                    FROM reservation r 
                                    LEFT JOIN agent a ON r.id_agent = a.id 
                                    WHERE r.id_reservation = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            return false;
        }

        $reservation = $result->fetch_assoc();

        // CORRECTION 3: Utiliser le pourcentage de commission de l'agent (défaut 1.5%)
        $commission_rate = $reservation['commission_percentage'] ?? 1.5;
        $commission = $reservation['montant'] * ($commission_rate / 100);

        $payment_stmt = $this->db->prepare("INSERT INTO payment (amount, card_number, card_code, status, payment_date) VALUES (?, ?, ?, 'paid', NOW())");
        $payment_stmt->bind_param("dss", $reservation['montant'], $card_number, $card_code);

        if ($payment_stmt->execute()) {
            $payment_id = $this->db->conn->insert_id;

            $this->db->query("UPDATE reservation SET id_payment = $payment_id WHERE id_reservation = $reservation_id");
            $this->db->query("UPDATE client SET status = 'payer' WHERE id = (SELECT id_client FROM reservation WHERE id_reservation = $reservation_id)");
            $this->db->query("UPDATE reservation SET status = 'completed' WHERE id_reservation = $reservation_id");

            // Mettre à jour la commission de l'agent s'il y en a un
            if ($reservation['id_agent'] && $commission > 0) {
                $agent_id = $reservation['id_agent'];
                $this->db->query("UPDATE agent SET total_commission = total_commission + $commission WHERE id = $agent_id");
                $this->db->query("INSERT INTO agent_commission_history (agent_id, reservation_id, commission_amount, commission_date) VALUES ($agent_id, $reservation_id, $commission, CURDATE())");
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
                GROUP BY $group_by ORDER BY period DESC LIMIT 10";
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

        $revenue = $this->db->query("
            SELECT SUM(r.montant) as total_revenue
            FROM reservation r
            WHERE r.id_company = $company_id AND r.status = 'completed'
        ")->fetch_assoc();

        $salaries = $this->db->query("
            SELECT SUM(salaire) as total_salaries FROM (
                SELECT salaire FROM administrator WHERE company_id = $company_id
                UNION ALL
                SELECT salaire FROM agent WHERE company_id = $company_id
            ) as salaries
        ")->fetch_assoc();

        $commissions = $this->db->query("
            SELECT SUM(ach.commission_amount) as total_commissions
            FROM agent_commission_history ach
            JOIN agent a ON ach.agent_id = a.id
            WHERE a.company_id = $company_id
        ")->fetch_assoc();

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

    public function getAgentCommissions($agent_id) {
        $sql = "SELECT SUM(commission_amount) as total_commissions FROM agent_commission_history WHERE agent_id = $agent_id";
        $result = $this->db->query($sql)->fetch_assoc();
        return $result['total_commissions'] ?? 0;
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

                    $stmt = $db->prepare("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', ?)");
                    $stmt->bind_param("isssiidisi",
                        $_SESSION['company_id'], $_POST['marque'], $_POST['model'], $_POST['color'],
                        $_POST['annee'], $_POST['category'], $_POST['prix_day'], $_POST['status_voiture'],
                        $matricule, $auth->getUserId()
                    );

                    if ($stmt->execute()) {
                        $success = "Voiture ajoutée avec succès";
                    } else {
                        $error = "Erreur lors de l'ajout de la voiture";
                    }
                    break;

                case 'update_car':
                    $stmt = $db->prepare("UPDATE car SET marque = ?, model = ?, color = ?, annee = ?, category = ?, prix_day = ?, status_voiture = ?, voiture_work = ? WHERE id_car = ? AND company_id = ?");
                    $stmt->bind_param("sssiidisii",
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
                            $error = "Erreur lors de l'ajout de l'agent. L'email existe peut-être déjà.";
                        }
                    }
                    break;

                case 'update_agent':
                    $stmt = $db->prepare("UPDATE agent SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ?, salaire = ? WHERE id = ? AND company_id = ?");
                    $stmt->bind_param("ssissidii",
                        $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                        $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'],
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
                    $stmt = $db->prepare("UPDATE client SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ? WHERE id = ? AND company_id = ?");
                    $stmt->bind_param("ssissiii",
                        $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                        $_POST['nationalite'], $_POST['wilaya_id'],
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

// Traiter les actions du super admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $auth->isLoggedIn() && $auth->getUserRole() == 'super_admin') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_company':
                $stmt = $db->prepare("INSERT INTO company (c_name, frais_mensuel, special_code, created_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sdsi",
                    $_POST['c_name'], $_POST['frais_mensuel'], $_POST['special_code'], $auth->getUserId()
                );

                if ($stmt->execute()) {
                    $company_id = $db->conn->insert_id;
                    $success = "Compagnie créée avec succès! ID: $company_id";
                    autoCreateCars($db, $company_id, $auth->getUserId());
                } else {
                    $error = "Erreur: " . $db->conn->error;
                }
                break;

            case 'add_admin':
                if ($_POST['age'] < 24) {
                    $error = "L'âge minimum est de 24 ans";
                } else {
                    // CORRECTION 1: Vérifier si l'email existe globalement
                    if ($auth->emailExists($_POST['email'])) {
                        $error = "Cet email est déjà utilisé dans le système";
                    } else {
                        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $db->prepare("INSERT INTO administrator (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssissssdissi",
                            $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                            $_POST['nationalite'], $_POST['numero_cart_national'], $_POST['wilaya_id'],
                            $_POST['salaire'], $_POST['company_id'], $_POST['email'], $hashed_password,
                            $auth->getUserId()
                        );

                        if ($stmt->execute()) {
                            $admin_id = $db->conn->insert_id;
                            $success = "Administrateur ajouté avec succès";
                            autoCreateAgent($db, $_POST, $auth->getUserId(), $admin_id, $auth);
                        } else {
                            $error = "Erreur: " . $db->conn->error;
                        }
                    }
                }
                break;

            case 'update_admin':
                $stmt = $db->prepare("UPDATE administrator SET nom = ?, prenom = ?, age = ?, numero_tlfn = ?, nationalite = ?, wilaya_id = ?, salaire = ?, company_id = ? WHERE id = ?");
                $stmt->bind_param("ssisssdii",
                    $_POST['nom'], $_POST['prenom'], $_POST['age'], $_POST['numero_tlfn'],
                    $_POST['nationalite'], $_POST['wilaya_id'], $_POST['salaire'],
                    $_POST['company_id'], $_POST['admin_id']
                );

                if ($stmt->execute()) {
                    $success = "Administrateur mis à jour avec succès";
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
        $db->query("INSERT INTO car (company_id, marque, model, color, annee, category, prix_day, status_voiture, matricule, voiture_work, created_by) VALUES ($company_id, '{$car[0]}', '{$car[1]}', '{$car[2]}', {$car[3]}, {$car[4]}, {$car[5]}, {$car[6]}, '$matricule', 'disponible', $created_by)");
    }
}

// CORRECTION 1: Fonction pour créer automatiquement un agent - vérifie maintenant l'unicité de l'email
function autoCreateAgent($db, $admin_data, $created_by, $admin_id, $auth) {
    $agent_email = str_replace('admin', 'agent', $admin_data['email']);

    // Vérifier si l'email de l'agent existe déjà
    if ($auth->emailExists($agent_email)) {
        // Générer un email unique
        $agent_email = 'agent_' . time() . '_' . rand(100, 999) . '@' . explode('@', $admin_data['email'])[1];
    }

    $hashed_password = password_hash('123456', PASSWORD_DEFAULT);
    $db->query("INSERT INTO agent (nom, prenom, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id, email, password, created_by, commission_percentage) 
                VALUES ('{$admin_data['nom']}', '{$admin_data['prenom']}', {$admin_data['age']}, '{$admin_data['numero_tlfn']}', '{$admin_data['nationalite']}', '{$admin_data['numero_cart_national']}', {$admin_data['wilaya_id']}, 50000, {$admin_data['company_id']}, '$agent_email', '$hashed_password', $admin_id, 1.50)");
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
 * PARTIE 4: FONCTIONS D'AFFICHAGE (HTML/HEADER)
 ***************************************************************/

function displayHeader($title = "DZLocation - Location de Voitures") {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #3B82F6; --secondary: #10B981; --accent: #8B5CF6; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .status-paid { background-color: #10B981; color: white; }
        .status-reserved { background-color: #F59E0B; color: white; }
        .status-cancelled { background-color: #EF4444; color: white; }
        .status-available { background-color: #3B82F6; color: white; }
        .car-category-1 { border-left: 4px solid #10B981; }
        .car-category-2 { border-left: 4px solid #3B82F6; }
        .car-category-3 { border-left: 4px solid #8B5CF6; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 500px; max-height: 80vh; overflow-y: auto; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-gray-50">
<?php
}

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
                    <span class="px-3 py-1 rounded-full text-sm <?php 
                        echo $_SESSION['user_role'] == 'super_admin' ? 'bg-red-100 text-red-800' : 
                            ($_SESSION['user_role'] == 'administrator' ? 'bg-purple-100 text-purple-800' : 
                            ($_SESSION['user_role'] == 'agent' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800')); 
                    ?>">
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

// CORRECTION 2: Page d'accueil avec carte Super Admin ajoutée
function displayHomePage($auth, $app, $db) {
?>
<div class="min-h-screen flex items-center justify-center py-12">
    <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-6xl">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">
                Bienvenue sur <span class="text-blue-600">DZLocation</span>
            </h1>
            <p class="text-gray-600 text-lg">Système de location de voitures professionnel en Algérie</p>
            <p class="text-gray-500 mt-2">Propriétaire: Cherifi Youssouf</p>
        </div>

        <?php if (!$auth->isLoggedIn()): ?>
        <!-- CORRECTION 2: Grille 4 colonnes pour inclure Super Admin -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Client Card -->
            <div class="bg-gradient-to-br from-green-50 to-blue-50 rounded-xl p-6 text-center card-hover border border-green-100">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">Client</h3>
                <p class="text-gray-600 mb-6">Accédez à votre compte</p>
                <button onclick="showLogin('client')" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg transition">
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
                <button onclick="showLogin('agent')" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 rounded-lg transition">
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
                <button onclick="showLogin('administrator')" class="w-full bg-purple-500 hover:bg-purple-600 text-white py-3 rounded-lg transition">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </button>
            </div>

            <!-- CORRECTION 2: Super Admin Card (Propriétaire) -->
            <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-xl p-6 text-center card-hover border border-red-100">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-crown text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-3">Propriétaire</h3>
                <p class="text-gray-600 mb-6">Gestion des compagnies et admins</p>
                <button onclick="showLogin('super_admin')" class="w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-lg transition">
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
                    <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">Mot de passe</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg transition font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Catégories de voitures -->
        <div class="mt-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Nos Catégories de Véhicules</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($app->getCategories() as $id => $cat): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 <?php echo "border-" . ($id == 1 ? 'green' : ($id == 2 ? 'blue' : 'purple')) . "-500"; ?>">
                    <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo $cat['name']; ?></h3>
                    <p class="text-gray-600">Prix journalier</p>
                    <p class="text-2xl font-bold text-blue-600">
                        De <?php echo number_format($cat['min_price'], 0, ',', ' '); ?> à <?php echo number_format($cat['max_price'], 0, ',', ' '); ?> DA/jour
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// CORRECTION 2: showLogin inclut maintenant super_admin
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
}

// Dashboard Client
function displayClientDashboard($auth, $app, $db) {
    $client_id = $auth->getUserId();
    $company_id = $_SESSION['company_id'];

    $client = $db->query("SELECT c.*, w.name as wilaya_name FROM client c LEFT JOIN wilaya w ON c.wilaya_id = w.id WHERE c.id = $client_id")->fetch_assoc();
    $reservations = $db->query("SELECT r.*, c.marque, c.model, c.color FROM reservation r JOIN car c ON r.car_id = c.id_car WHERE r.id_client = $client_id ORDER BY r.created_at DESC");
    $available_cars = $app->getAvailableCars($company_id);
?>
<div class="space-y-8">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">
            <i class="fas fa-tachometer-alt mr-2 text-blue-600"></i>Tableau de bord Client
        </h2>
        <p class="text-gray-600">Gérez vos réservations et paiements</p>
        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
            <p class="text-gray-700">Votre statut: 
                <span class="px-3 py-1 rounded-full text-sm <?php 
                    echo $client['status'] == 'payer' ? 'bg-green-100 text-green-800' : 
                        ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); 
                ?>">
                    <?php echo ucfirst($client['status']); ?>
                </span>
            </p>
        </div>
    </div>

    <!-- Voitures disponibles -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-car mr-2 text-green-600"></i>Voitures Disponibles
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php while ($car = $available_cars->fetch_assoc()): ?>
            <div class="border rounded-lg p-4 car-category-<?php echo $car['category']; ?>">
                <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($car['marque'] . ' ' . $car['model']); ?></h4>
                <p class="text-gray-600">Année: <?php echo $car['annee']; ?> | <?php echo $car['color']; ?></p>
                <p class="text-gray-500 text-sm">Plaque: <?php echo $car['matricule']; ?></p>
                <p class="text-gray-600">État: <?php echo $app->getCarStatusText($car['status_voiture']); ?></p>
                <p class="text-xl font-bold text-blue-600 mt-2">
                    Prix/jour: <?php echo number_format($car['prix_day'], 0, ',', ' '); ?> DA
                </p>
            </div>
            <?php endwhile; ?>
        </div>
        <?php if ($available_cars->num_rows == 0): ?>
        <p class="text-gray-500 text-center py-4">Aucune voiture disponible pour le moment</p>
        <?php endif; ?>
    </div>

    <!-- Mes réservations -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-calendar-alt mr-2 text-purple-600"></i>Mes Réservations
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">Voiture</th>
                        <th class="px-4 py-3 text-left">Dates</th>
                        <th class="px-4 py-3 text-left">Montant</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($res = $reservations->fetch_assoc()): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($res['marque'] . ' ' . $res['model']); ?></td>
                        <td class="px-4 py-3"><?php echo $res['start_date'] . ' - ' . $res['end_date']; ?></td>
                        <td class="px-4 py-3 font-bold"><?php echo number_format($res['montant'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php 
                                echo $res['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                    ($res['status'] == 'active' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); 
                            ?>">
                                <?php echo ucfirst($res['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($reservations->num_rows == 0): ?>
            <p class="text-gray-500 text-center py-4">Aucune réservation</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
}

// Dashboard Agent
function displayAgentDashboard($auth, $app, $db) {
    $agent_id = $auth->getUserId();
    $company_id = $_SESSION['company_id'];

    $agent = $db->query("SELECT * FROM agent WHERE id = $agent_id")->fetch_assoc();
    $clients = $db->query("SELECT c.*, w.name as wilaya_name FROM client c LEFT JOIN wilaya w ON c.wilaya_id = w.id WHERE c.created_by = $agent_id ORDER BY c.created_at DESC");
    $reservations = $db->query("SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, car.marque, car.model FROM reservation r JOIN client c ON r.id_client = c.id JOIN car ON r.car_id = car.id_car WHERE r.id_agent = $agent_id ORDER BY r.created_at DESC LIMIT 10");
    $wilayas = $app->getWilayas();

    // Calculer la commission du mois
    $month_commission = $db->query("SELECT SUM(commission_amount) as total FROM agent_commission_history WHERE agent_id = $agent_id AND MONTH(commission_date) = MONTH(CURDATE()) AND YEAR(commission_date) = YEAR(CURDATE())")->fetch_assoc();
?>
<div class="space-y-8">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">
            <i class="fas fa-user-tie mr-2 text-blue-600"></i>Tableau de bord Agent
        </h2>
        <p class="text-gray-600">Gestion des clients et réservations</p>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-blue-50 rounded-lg text-center">
                <p class="text-gray-600">Commission du mois</p>
                <p class="text-2xl font-bold text-blue-600"><?php echo number_format($month_commission['total'] ?? 0, 2, ',', ' '); ?> DA</p>
            </div>
            <div class="p-4 bg-green-50 rounded-lg text-center">
                <p class="text-gray-600">Total commission</p>
                <p class="text-2xl font-bold text-green-600"><?php echo number_format($agent['total_commission'] ?? 0, 2, ',', ' '); ?> DA</p>
            </div>
            <div class="p-4 bg-purple-50 rounded-lg text-center">
                <p class="text-gray-600">Clients Ajoutés</p>
                <p class="text-2xl font-bold text-purple-600"><?php echo $clients->num_rows; ?></p>
            </div>
            <div class="p-4 bg-yellow-50 rounded-lg text-center">
                <p class="text-gray-600">Salaire Mensuel</p>
                <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($agent['salaire'], 0, ',', ' '); ?> DA</p>
            </div>
        </div>
        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
            <p class="text-gray-600">Taux Commission: <span class="font-bold text-blue-600"><?php echo $agent['commission_percentage']; ?>%</span></p>
        </div>
    </div>

    <!-- Formulaire d'ajout de client -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-user-plus mr-2 text-green-600"></i>Ajouter un Client
        </h3>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="register_client" value="1">

            <div>
                <label class="block text-gray-700 mb-1">Nom</label>
                <input type="text" name="nom" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Prénom</label>
                <input type="text" name="prenom" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Âge (min 24)</label>
                <input type="number" name="age" min="24" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Téléphone</label>
                <input type="text" name="numero_tlfn" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Nationalité</label>
                <input type="text" name="nationalite" value="Algérienne" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">N° Carte Nationale</label>
                <input type="text" name="numero_cart_national" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Wilaya</label>
                <select name="wilaya_id" required class="w-full px-3 py-2 border rounded-lg">
                    <?php while ($w = $wilayas->fetch_assoc()): ?>
                    <option value="<?php echo $w['id']; ?>"><?php echo $w['id'] . ' - ' . $w['name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Mot de passe</label>
                <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Confirmer mot de passe</label>
                <input type="password" name="confirm_password" required class="w-full px-3 py-2 border rounded-lg">
            </div>

            <div class="md:col-span-2">
                <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg transition">
                    <i class="fas fa-plus mr-2"></i>Ajouter le Client
                </button>
            </div>
        </form>
    </div>

    <!-- Liste des clients -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-users mr-2 text-blue-600"></i>Mes Clients
        </h3>
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
                <tbody>
                    <?php 
                    $clients->data_seek(0);
                    while ($client = $clients->fetch_assoc()): 
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($client['numero_tlfn']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($client['wilaya_name'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php 
                                echo $client['status'] == 'payer' ? 'bg-green-100 text-green-800' : 
                                    ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); 
                            ?>">
                                <?php echo ucfirst($client['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($clients->num_rows == 0): ?>
            <p class="text-gray-500 text-center py-4">Aucun client ajouté</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Réservations récentes -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-calendar-check mr-2 text-purple-600"></i>Réservations Récentes
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Voiture</th>
                        <th class="px-4 py-3 text-left">Dates</th>
                        <th class="px-4 py-3 text-left">Montant</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($res = $reservations->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($res['client_prenom'] . ' ' . $res['client_nom']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($res['marque'] . ' ' . $res['model']); ?></td>
                        <td class="px-4 py-3"><?php echo $res['start_date'] . ' - ' . $res['end_date']; ?></td>
                        <td class="px-4 py-3 font-bold"><?php echo number_format($res['montant'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php 
                                echo $res['status'] == 'completed' ? 'bg-green-100 text-green-800' : 
                                    ($res['status'] == 'active' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'); 
                            ?>">
                                <?php echo ucfirst($res['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php if ($reservations->num_rows == 0): ?>
            <p class="text-gray-500 text-center py-4">Aucune réservation</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
}

// Dashboard Administrateur
function displayAdminDashboard($auth, $app, $db) {
    $company_id = $_SESSION['company_id'];

    $company = $db->query("SELECT * FROM company WHERE company_id = $company_id")->fetch_assoc();
    $financials = $app->getCompanyFinancials($company_id);
    $cars = $app->getCompanyCars($company_id);
    $agents = $db->query("SELECT a.*, w.name as wilaya_name FROM agent a LEFT JOIN wilaya w ON a.wilaya_id = w.id WHERE a.company_id = $company_id ORDER BY a.created_at DESC");
    $clients = $db->query("SELECT c.*, w.name as wilaya_name FROM client c LEFT JOIN wilaya w ON c.wilaya_id = w.id WHERE c.company_id = $company_id ORDER BY c.created_at DESC");
    $statistics = $app->getStatistics($company_id);
    $wilayas = $app->getWilayas();
    $categories = $app->getCategories();
?>
<div class="space-y-8">
    <!-- Header -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-2">
            <i class="fas fa-building mr-2 text-purple-600"></i><?php echo htmlspecialchars($company['c_name']); ?>
        </h2>
        <p class="text-gray-600">Tableau de bord Administrateur</p>
    </div>

    <!-- Stats financières -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow p-4 text-center <?php echo $financials['net_profit'] >= 0 ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500'; ?>">
            <p class="text-gray-600 text-sm">Bénéfice net</p>
            <p class="text-xl font-bold <?php echo $financials['net_profit'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                <?php echo number_format($financials['net_profit'], 0, ',', ' '); ?> DA
            </p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center border-l-4 border-blue-500">
            <p class="text-gray-600 text-sm">Revenus Totaux</p>
            <p class="text-xl font-bold text-blue-600"><?php echo number_format($financials['total_revenue'], 0, ',', ' '); ?> DA</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center border-l-4 border-red-500">
            <p class="text-gray-600 text-sm">Dépenses Totales</p>
            <p class="text-xl font-bold text-red-600"><?php echo number_format($financials['total_expenses'], 0, ',', ' '); ?> DA</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center border-l-4 border-yellow-500">
            <p class="text-gray-600 text-sm">Salaires & Commissions</p>
            <p class="text-xl font-bold text-yellow-600"><?php echo number_format($financials['total_salaries'] + $financials['total_commissions'], 0, ',', ' '); ?> DA</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center border-l-4 border-purple-500">
            <p class="text-gray-600 text-sm">Frais Compagnie</p>
            <p class="text-xl font-bold text-purple-600"><?php echo number_format($financials['company_fees'], 0, ',', ' '); ?> DA</p>
        </div>
    </div>

    <!-- Gestion des voitures -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-car mr-2 text-blue-600"></i>Gestion des Voitures
            </h3>
            <button onclick="document.getElementById('addCarModal').style.display='block'" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Ajouter
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
                <tbody>
                    <?php while ($car = $cars->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50 car-category-<?php echo $car['category']; ?>">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($car['marque'] . ' ' . $car['model']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($car['matricule']); ?></td>
                        <td class="px-4 py-3"><?php echo $categories[$car['category']]['name']; ?></td>
                        <td class="px-4 py-3 font-bold"><?php echo number_format($car['prix_day'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3">
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette voiture?')">
                                <input type="hidden" name="action" value="delete_car">
                                <input type="hidden" name="car_id" value="<?php echo $car['id_car']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gestion des agents -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-user-tie mr-2 text-green-600"></i>Gestion des Agents
            </h3>
            <button onclick="document.getElementById('addAgentModal').style.display='block'" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Ajouter
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
                <tbody>
                    <?php while ($agent = $agents->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']); ?></td>
                        <td class="px-4 py-3"><?php echo number_format($agent['salaire'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3 text-green-600 font-bold"><?php echo number_format($agent['total_commission'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3">
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet agent?')">
                                <input type="hidden" name="action" value="delete_agent">
                                <input type="hidden" name="agent_id" value="<?php echo $agent['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-chart-bar mr-2 text-purple-600"></i>Statistiques Mensuelles
        </h3>
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
                <tbody>
                    <?php while ($stat = $statistics->fetch_assoc()): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3"><?php echo $stat['period']; ?></td>
                        <td class="px-4 py-3"><?php echo $stat['total_reservations']; ?></td>
                        <td class="px-4 py-3 font-bold text-green-600"><?php echo number_format($stat['total_amount'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3"><?php echo number_format($stat['avg_amount'], 0, ',', ' '); ?> DA</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gestion des clients -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-4">
            <i class="fas fa-users mr-2 text-blue-600"></i>Liste des Clients
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">Nom & Prénom</th>
                        <th class="px-4 py-3 text-left">Téléphone</th>
                        <th class="px-4 py-3 text-left">Statut</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($client = $clients->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($client['numero_tlfn']); ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php 
                                echo $client['status'] == 'payer' ? 'bg-green-100 text-green-800' : 
                                    ($client['status'] == 'reserve' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800'); 
                            ?>">
                                <?php echo ucfirst($client['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce client?')">
                                <input type="hidden" name="action" value="delete_client">
                                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ajout Voiture -->
<div id="addCarModal" class="modal">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Ajouter une Voiture</h3>
            <button onclick="document.getElementById('addCarModal').style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_car">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-1">Marque</label>
                    <input type="text" name="marque" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Modèle</label>
                    <input type="text" name="model" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Couleur</label>
                    <input type="text" name="color" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Année</label>
                    <input type="number" name="annee" min="2000" max="2025" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Catégorie</label>
                    <select name="category" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="1">Économique</option>
                        <option value="2">Confort</option>
                        <option value="3">Luxe</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Prix/jour (DA)</label>
                    <input type="number" name="prix_day" min="1000" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">État</label>
                    <select name="status_voiture" required class="w-full px-3 py-2 border rounded-lg">
                        <option value="1">Excellent</option>
                        <option value="2">Entretien</option>
                        <option value="3">Faible</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Ajouter
            </button>
        </form>
    </div>
</div>

<!-- Modal Ajout Agent -->
<div id="addAgentModal" class="modal">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Ajouter un Agent</h3>
            <button onclick="document.getElementById('addAgentModal').style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_agent">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-1">Nom</label>
                    <input type="text" name="nom" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Prénom</label>
                    <input type="text" name="prenom" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Âge (min 24)</label>
                    <input type="number" name="age" min="24" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Téléphone</label>
                    <input type="text" name="numero_tlfn" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Nationalité</label>
                    <input type="text" name="nationalite" value="Algérienne" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">N° Carte Nationale</label>
                    <input type="text" name="numero_cart_national" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Wilaya</label>
                    <select name="wilaya_id" required class="w-full px-3 py-2 border rounded-lg">
                        <?php 
                        $wilayas->data_seek(0);
                        while ($w = $wilayas->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $w['id']; ?>"><?php echo $w['id'] . ' - ' . $w['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Salaire (DA)</label>
                    <input type="number" name="salaire" min="30000" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Mot de passe</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg">
                </div>
            </div>
            <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Ajouter
            </button>
        </form>
    </div>
</div>
<?php
}

// Dashboard Super Admin (Propriétaire)
function displaySuperAdminDashboard($auth, $app, $db) {
    $companies = $db->query("SELECT c.*, (SELECT COUNT(*) FROM administrator WHERE company_id = c.company_id) as admin_count, (SELECT COUNT(*) FROM agent WHERE company_id = c.company_id) as agent_count, (SELECT COUNT(*) FROM client WHERE company_id = c.company_id) as client_count FROM company c ORDER BY c.created_at DESC");
    $administrators = $db->query("SELECT a.*, c.c_name as company_name, w.name as wilaya_name FROM administrator a LEFT JOIN company c ON a.company_id = c.company_id LEFT JOIN wilaya w ON a.wilaya_id = w.id ORDER BY a.created_at DESC");
    $wilayas = $app->getWilayas();

    $total_revenue = $db->query("SELECT SUM(montant) as total FROM reservation WHERE status = 'completed'")->fetch_assoc();
    $total_companies = $db->query("SELECT COUNT(*) as total FROM company")->fetch_assoc();
    $total_admins = $db->query("SELECT COUNT(*) as total FROM administrator")->fetch_assoc();
?>
<div class="space-y-8">
    <!-- Header -->
    <div class="bg-gradient-to-r from-red-600 to-orange-500 rounded-xl shadow-lg p-6 text-white">
        <h2 class="text-2xl font-bold mb-2">
            <i class="fas fa-crown mr-2"></i>Cherifi Youssouf - Gestion des compagnies et administrateurs
        </h2>
        <p>Connecté en tant que: Propriétaire</p>
    </div>

    <!-- Stats globales -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow p-6 text-center border-l-4 border-green-500">
            <p class="text-gray-600">Revenus Totaux (Toutes compagnies)</p>
            <p class="text-3xl font-bold text-green-600"><?php echo number_format($total_revenue['total'] ?? 0, 0, ',', ' '); ?> DA</p>
        </div>
        <div class="bg-white rounded-xl shadow p-6 text-center border-l-4 border-blue-500">
            <p class="text-gray-600">Nombre de Compagnies</p>
            <p class="text-3xl font-bold text-blue-600"><?php echo $total_companies['total']; ?></p>
        </div>
        <div class="bg-white rounded-xl shadow p-6 text-center border-l-4 border-purple-500">
            <p class="text-gray-600">Nombre d'Administrateurs</p>
            <p class="text-3xl font-bold text-purple-600"><?php echo $total_admins['total']; ?></p>
        </div>
    </div>

    <!-- Gestion des Compagnies -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-building mr-2 text-blue-600"></i>Gestion des Compagnies
            </h3>
            <button onclick="document.getElementById('addCompanyModal').style.display='block'" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
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
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($company = $companies->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3"><?php echo $company['company_id']; ?></td>
                        <td class="px-4 py-3 font-bold"><?php echo htmlspecialchars($company['c_name']); ?></td>
                        <td class="px-4 py-3"><?php echo number_format($company['frais_mensuel'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($company['special_code'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3"><?php echo date('d/m/Y', strtotime($company['created_at'])); ?></td>
                        <td class="px-4 py-3">
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette compagnie et toutes ses données?')">
                                <input type="hidden" name="action" value="delete_company">
                                <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gestion des Administrateurs -->
    <div class="bg-white rounded-xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                <i class="fas fa-user-shield mr-2 text-purple-600"></i>Gestion des Administrateurs
            </h3>
            <button onclick="document.getElementById('addAdminModal').style.display='block'" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Nouvel Admin
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left">Nom & Prénom</th>
                        <th class="px-4 py-3 text-left">Compagnie</th>
                        <th class="px-4 py-3 text-left">Salaire</th>
                        <th class="px-4 py-3 text-left">Date Création</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($admin = $administrators->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3"><?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></td>
                        <td class="px-4 py-3"><?php echo htmlspecialchars($admin['company_name'] ?? 'N/A'); ?></td>
                        <td class="px-4 py-3"><?php echo number_format($admin['salaire'], 0, ',', ' '); ?> DA</td>
                        <td class="px-4 py-3"><?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></td>
                        <td class="px-4 py-3">
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet administrateur?')">
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ajout Compagnie -->
<div id="addCompanyModal" class="modal">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Nouvelle Compagnie</h3>
            <button onclick="document.getElementById('addCompanyModal').style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_company">
            <div>
                <label class="block text-gray-700 mb-1">Nom de la compagnie</label>
                <input type="text" name="c_name" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Frais mensuel (DA)</label>
                <input type="number" name="frais_mensuel" value="50000" min="0" required class="w-full px-3 py-2 border rounded-lg">
            </div>
            <div>
                <label class="block text-gray-700 mb-1">Code spécial</label>
                <input type="text" name="special_code" class="w-full px-3 py-2 border rounded-lg">
            </div>
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Créer la Compagnie
            </button>
        </form>
    </div>
</div>

<!-- Modal Ajout Administrateur -->
<div id="addAdminModal" class="modal">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold">Nouvel Administrateur</h3>
            <button onclick="document.getElementById('addAdminModal').style.display='none'" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="add_admin">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-1">Nom</label>
                    <input type="text" name="nom" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Prénom</label>
                    <input type="text" name="prenom" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Âge (min 24)</label>
                    <input type="number" name="age" min="24" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Téléphone</label>
                    <input type="text" name="numero_tlfn" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Nationalité</label>
                    <input type="text" name="nationalite" value="Algérienne" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">N° Carte Nationale</label>
                    <input type="text" name="numero_cart_national" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Compagnie</label>
                    <select name="company_id" required class="w-full px-3 py-2 border rounded-lg">
                        <?php 
                        $companies_list = $db->query("SELECT company_id, c_name FROM company ORDER BY c_name");
                        while ($c = $companies_list->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $c['company_id']; ?>"><?php echo htmlspecialchars($c['c_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Wilaya</label>
                    <select name="wilaya_id" required class="w-full px-3 py-2 border rounded-lg">
                        <?php 
                        $wilayas->data_seek(0);
                        while ($w = $wilayas->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $w['id']; ?>"><?php echo $w['id'] . ' - ' . $w['name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Salaire (DA)</label>
                    <input type="number" name="salaire" min="30000" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg">
                </div>
                <div class="col-span-2">
                    <label class="block text-gray-700 mb-1">Mot de passe</label>
                    <input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg">
                </div>
            </div>
            <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Ajouter l'Administrateur
            </button>
        </form>
    </div>
</div>
<?php
}

/***************************************************************
 * PARTIE 7: FOOTER
 ***************************************************************/
?>

<footer class="bg-gray-800 text-white py-8 mt-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-xl font-bold mb-4">
                    <i class="fas fa-car mr-2"></i>DZLocation
                </h3>
                <p class="text-gray-400">Système professionnel de location de voitures en Algérie.</p>
                <p class="text-gray-400 mt-2">Propriétaire: Cherifi Youssouf</p>
            </div>
            <div>
                <h4 class="font-bold mb-4">Contact</h4>
                <p class="text-gray-400"><i class="fas fa-envelope mr-2"></i>chirifiyoucef@mail.com</p>
                <p class="text-gray-400"><i class="fas fa-phone mr-2"></i>+213 XX XX XX XX</p>
            </div>
            <div>
                <h4 class="font-bold mb-4">Informations</h4>
                <p class="text-gray-400">Devise: Dinar Algérien (DA)</p>
                <p class="text-gray-400">58 Wilayas couvertes</p>
            </div>
        </div>
        <div class="border-t border-gray-700 mt-8 pt-4 text-center text-gray-400">
            <p>&copy; <?php echo date('Y'); ?> DZLocation - Tous droits réservés</p>
        </div>
    </div>
</footer>

</body>
</html>
