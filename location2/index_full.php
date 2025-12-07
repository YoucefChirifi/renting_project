<?php
/***************************************************************
 * DZLocation - Système de Location de Voitures en Algérie
 * Application complète avec 4 rôles: Client, Agent, Administrateur, Super Admin
 * Propriétaire: Cherifi Youssouf
 * Langue: Français - Devise: DA
 * 
 * CORRECTIONS APPLIQUÉES:
 * 1. Email unique globalement (FIXED - emailExists function corrected)
 * 2. Carte Super Admin ajoutée sur la page de connexion
 * 3. Commission corrigée (liaison agent-réservation + pourcentage dynamique)
 * 4. AJOUT: Fonctions d'édition/modification pour tous les éléments
 * 5. AJOUT: Admin peut modifier la commission des agents
 ***************************************************************/

session_start();

if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_ADDR'] == '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

/***************************************************************
 * PARTIE 1: CONFIGURATION DE LA BASE DE DONNÉES
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
        $sql = "CREATE TABLE IF NOT EXISTS wilaya (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS company (
            company_id INT AUTO_INCREMENT PRIMARY KEY,
            c_name VARCHAR(100) NOT NULL,
            frais_mensuel DECIMAL(10,2) DEFAULT 50000,
            special_code VARCHAR(50),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS super_admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS administrator (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT,
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

        $sql = "CREATE TABLE IF NOT EXISTS agent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT,
            numero_tlfn VARCHAR(20),
            nationalite VARCHAR(50),
            numero_cart_national VARCHAR(50),
            wilaya_id INT,
            salaire DECIMAL(10,2),
            company_id INT,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255),
            commission_percentage DECIMAL(5,2) DEFAULT 1.5,
            total_commission DECIMAL(10,2) DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS client (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT,
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

        $sql = "CREATE TABLE IF NOT EXISTS car (
            id_car INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT,
            marque VARCHAR(50),
            model VARCHAR(50),
            color VARCHAR(50),
            annee INT,
            matricule VARCHAR(50) UNIQUE,
            category INT,
            prix_day DECIMAL(10,2),
            status_voiture INT,
            voiture_work ENUM('disponible', 'non disponible') DEFAULT 'disponible',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

        $sql = "CREATE TABLE IF NOT EXISTS payment (
            id_payment INT AUTO_INCREMENT PRIMARY KEY,
            status ENUM('paid', 'not_paid') DEFAULT 'not_paid',
            amount DECIMAL(10,2),
            payment_date DATETIME,
            card_number VARCHAR(16),
            card_code VARCHAR(3)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->conn->query($sql);

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

        $checkSuperAdmin = $this->conn->query("SELECT id FROM super_admin WHERE email = 'chirifiyoucef@mail.com'");
        if ($checkSuperAdmin->num_rows == 0) {
            $hashed_password = password_hash('123', PASSWORD_DEFAULT);
            $this->conn->query("INSERT INTO super_admin (nom, prenom, email, password) VALUES ('Cherifi', 'Youssouf', 'chirifiyoucef@mail.com', '$hashed_password')");
        }

        $checkCompanies = $this->conn->query("SELECT COUNT(*) as count FROM company");
        $result = $checkCompanies->fetch_assoc();
        if ($result['count'] == 0) {
            $this->seedCompanies();
        }
    }

    private function seedCompanies() {
        $hashed_password = password_hash('123', PASSWORD_DEFAULT);

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
