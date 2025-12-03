<?php
session_start();
ob_start();

// Définir le fuseau horaire pour l'Algérie
date_default_timezone_set('Africa/Algiers');

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'location_voitures_algerie');

// Connexion à la base de données
class Database {
    private static $connection = null;
    
    public static function connect() {
        if (self::$connection === null) {
            try {
                self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASS);
                
                if (self::$connection->connect_error) {
                    die("Erreur de connexion : " . self::$connection->connect_error);
                }
                
                // Créer la base de données si elle n'existe pas
                self::$connection->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$connection->select_db(DB_NAME);
                
                // Créer les tables
                self::createTables();
                
            } catch (Exception $e) {
                die("Erreur de connexion à la base de données: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
    
    private static function createTables() {
        $conn = self::$connection;
        
        // Table wilaya
        $conn->query("CREATE TABLE IF NOT EXISTS wilaya (
            id INT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Table company
        $conn->query("CREATE TABLE IF NOT EXISTS company (
            company_id INT AUTO_INCREMENT PRIMARY KEY,
            c_name VARCHAR(100) NOT NULL,
            id_admin INT,
            special_code VARCHAR(50),
            frais_mensuel DECIMAL(10,2) DEFAULT 30000.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Table admin
        $conn->query("CREATE TABLE IF NOT EXISTS admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT,
            numero_tlfn VARCHAR(20),
            nationalite VARCHAR(50),
            numero_cart_national VARCHAR(50),
            wilaya_id INT,
            salaire DECIMAL(10,2) DEFAULT 0,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            company_id INT,
            FOREIGN KEY (company_id) REFERENCES company(company_id),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Table agent
        $conn->query("CREATE TABLE IF NOT EXISTS agent (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT,
            numero_tlfn VARCHAR(20),
            nationalite VARCHAR(50),
            numero_cart_national VARCHAR(50),
            wilaya_id INT,
            salaire DECIMAL(10,2) DEFAULT 0,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            company_id INT,
            FOREIGN KEY (company_id) REFERENCES company(company_id),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Table client
        $conn->query("CREATE TABLE IF NOT EXISTS client (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            age INT,
            numero_tlfn VARCHAR(20),
            nationalite VARCHAR(50),
            numero_cart_national VARCHAR(50),
            wilaya_id INT,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            status ENUM('payer', 'reserve', 'annuler', 'non_reserve') DEFAULT 'non_reserve',
            company_id INT,
            FOREIGN KEY (company_id) REFERENCES company(company_id),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Table car
        $conn->query("CREATE TABLE IF NOT EXISTS car (
            id_car INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            marque VARCHAR(100) NOT NULL,
            model VARCHAR(100) NOT NULL,
            color VARCHAR(50) NOT NULL,
            annee INT NOT NULL,
            matricule VARCHAR(50) UNIQUE NOT NULL,
            category INT NOT NULL,
            prix_day DECIMAL(10,2) NOT NULL,
            status_voiture ENUM('excellent', 'entretien', 'faible') DEFAULT 'excellent',
            voiture_work ENUM('disponible', 'non_disponible') DEFAULT 'disponible',
            FOREIGN KEY (company_id) REFERENCES company(company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Table reservation
        $conn->query("CREATE TABLE IF NOT EXISTS reservation (
            id_reservation INT AUTO_INCREMENT PRIMARY KEY,
            id_client INT NOT NULL,
            id_agent INT,
            id_admin INT,
            company_id INT NOT NULL,
            car_id INT NOT NULL,
            wilaya_id INT,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            period INT NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            id_payment INT,
            status ENUM('en_attente', 'confirmee', 'annulee', 'terminee') DEFAULT 'en_attente',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_client) REFERENCES client(id),
            FOREIGN KEY (id_agent) REFERENCES agent(id),
            FOREIGN KEY (company_id) REFERENCES company(company_id),
            FOREIGN KEY (car_id) REFERENCES car(id_car),
            FOREIGN KEY (wilaya_id) REFERENCES wilaya(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Table payment
        $conn->query("CREATE TABLE IF NOT EXISTS payment (
            id_payment INT AUTO_INCREMENT PRIMARY KEY,
            id_reservation INT NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            numero_carte VARCHAR(16),
            code_carte VARCHAR(3),
            status ENUM('paye', 'non_paye') DEFAULT 'non_paye',
            date_payment TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_reservation) REFERENCES reservation(id_reservation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Peupler la table wilaya
        self::populateWilaya();
        
        // Créer l'admin propriétaire et les données initiales
        self::initializeData();
    }
    
    private static function populateWilaya() {
        $conn = self::$connection;
        
        // Vérifier si la table wilaya est vide
        $result = $conn->query("SELECT COUNT(*) as count FROM wilaya");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            $wilayas = [
                1 => 'Adrar', 2 => 'Chlef', 3 => 'Laghouat', 4 => 'Oum El Bouaghi',
                5 => 'Batna', 6 => 'Béjaïa', 7 => 'Biskra', 8 => 'Béchar',
                9 => 'Blida', 10 => 'Bouira', 11 => 'Tamanrasset', 12 => 'Tébessa',
                13 => 'Tlemcen', 14 => 'Tiaret', 15 => 'Tizi Ouzou', 16 => 'Alger',
                17 => 'Djelfa', 18 => 'Jijel', 19 => 'Sétif', 20 => 'Saïda',
                21 => 'Skikda', 22 => 'Sidi Bel Abbès', 23 => 'Annaba',
                24 => 'Guelma', 25 => 'Constantine', 26 => 'Médéa',
                27 => 'Mostaganem', 28 => 'M\'Sila', 29 => 'Mascara',
                30 => 'Ouargla', 31 => 'Oran', 32 => 'El Bayadh', 33 => 'Illizi',
                34 => 'Bordj Bou Arreridj', 35 => 'Boumerdès', 36 => 'El Tarf',
                37 => 'Tindouf', 38 => 'Tissemsilt', 39 => 'El Oued',
                40 => 'Khenchela', 41 => 'Souk Ahras', 42 => 'Tipaza',
                43 => 'Mila', 44 => 'Aïn Defla', 45 => 'Naâma',
                46 => 'Aïn Témouchent', 47 => 'Ghardaïa', 48 => 'Relizane',
                49 => 'Timimoun', 50 => 'Bordj Badji Mokhtar', 51 => 'Ouled Djellal',
                52 => 'Béni Abbès', 53 => 'In Salah', 54 => 'In Guezzam',
                55 => 'Touggourt', 56 => 'Djanet', 57 => 'El M\'Ghair',
                58 => 'El Meniaa', 59 => 'Aflou', 60 => 'El Abiodh Sidi Cheikh',
                61 => 'El Aricha', 62 => 'El Kantara', 63 => 'Barika',
                64 => 'Bou Saâda', 65 => 'Bir El Ater', 66 => 'Ksar El Boukhari',
                67 => 'Ksar Chellala', 68 => 'Aïn Oussara', 69 => 'Messaad'
            ];
            
            foreach ($wilayas as $id => $nom) {
                $stmt = $conn->prepare("INSERT INTO wilaya (id, nom) VALUES (?, ?)");
                $stmt->bind_param("is", $id, $nom);
                $stmt->execute();
            }
        }
    }
    
    private static function initializeData() {
        $conn = self::$connection;
        
        // Vérifier si l'admin propriétaire existe
        $result = $conn->query("SELECT COUNT(*) as count FROM admin WHERE email = 'chirifiyoucef@mail.com'");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Créer l'admin propriétaire
            $password = password_hash('123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin (nom, prenom, email, password, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id) 
                                   VALUES ('cherifi', 'youssouf', 'chirifiyoucef@mail.com', ?, 35, '0550123456', 'Algérienne', '1234567890123456', 16, 0, NULL)");
            $stmt->bind_param("s", $password);
            $stmt->execute();
        }
        
        // Vérifier s'il existe des compagnies
        $result = $conn->query("SELECT COUNT(*) as count FROM company");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Créer 3 agences avec leurs données
            self::createSampleData();
        }
    }
    
    private static function createSampleData() {
        $conn = self::$connection;
        
        // Créer 3 compagnies
        $companies = [
            ['Cherifi Youssouf Agency', 'CHER001'],
            ['Location Premium', 'LPREM002'],
            ['Auto Rent Algérie', 'ARA003']
        ];
        
        $company_ids = [];
        
        foreach ($companies as $company) {
            $stmt = $conn->prepare("INSERT INTO company (c_name, special_code, frais_mensuel) VALUES (?, ?, ?)");
            $frais = rand(30000, 150000);
            $stmt->bind_param("ssd", $company[0], $company[1], $frais);
            $stmt->execute();
            $company_ids[] = $conn->insert_id;
        }
        
        // Créer les administrateurs pour chaque compagnie
        $admin_passwords = [];
        for ($i = 0; $i < 3; $i++) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $admin_passwords[] = $password;
            
            $nom = ['Benali', 'Kadri', 'Mansouri'][$i];
            $prenom = ['Karim', 'Fatima', 'Ahmed'][$i];
            $email = ['admin' . ($i+1) . '@agence.com', 'admin2@premium.com', 'admin3@auto.com'][$i];
            
            $stmt = $conn->prepare("INSERT INTO admin (nom, prenom, email, password, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $age = rand(30, 50);
            $tel = '055' . rand(1000000, 9999999);
            $wilaya = rand(1, 69);
            $salaire = rand(50000, 120000);
            $stmt->bind_param("ssssisssidi", $nom, $prenom, $email, $password, $age, $tel, 'Algérienne', 
                            rand(1000000000000000, 9999999999999999), $wilaya, $salaire, $company_ids[$i]);
            $stmt->execute();
            
            // Mettre à jour la compagnie avec l'id_admin
            $admin_id = $conn->insert_id;
            $conn->query("UPDATE company SET id_admin = $admin_id WHERE company_id = {$company_ids[$i]}");
        }
        
        // Créer des agents pour chaque compagnie
        for ($comp_index = 0; $comp_index < 3; $comp_index++) {
            for ($j = 0; $j < 2; $j++) {
                $password = password_hash('agent123', PASSWORD_DEFAULT);
                $noms = ['Bouchenak', 'Zaidi', 'Larbi', 'Brahimi', 'Hamidou', 'Belkacem'];
                $prenoms = ['Samir', 'Leila', 'Nabil', 'Salima', 'Rachid', 'Yasmine'];
                
                $stmt = $conn->prepare("INSERT INTO agent (nom, prenom, email, password, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, salaire, company_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $nom = $noms[($comp_index * 2 + $j) % count($noms)];
                $prenom = $prenoms[($comp_index * 2 + $j) % count($prenoms)];
                $email = "agent" . ($comp_index * 2 + $j + 1) . "@agence.com";
                $age = rand(25, 45);
                $tel = '055' . rand(1000000, 9999999);
                $wilaya = rand(1, 69);
                $salaire = rand(30000, 70000);
                $stmt->bind_param("ssssisssidi", $nom, $prenom, $email, $password, $age, $tel, 'Algérienne', 
                                rand(1000000000000000, 9999999999999999), $wilaya, $salaire, $company_ids[$comp_index]);
                $stmt->execute();
            }
        }
        
        // Créer des clients pour chaque compagnie
        for ($comp_index = 0; $comp_index < 3; $comp_index++) {
            for ($k = 0; $k < 3; $k++) {
                $password = password_hash('client123', PASSWORD_DEFAULT);
                $noms = ['Mokhtari', 'Saadi', 'Taleb', 'Benslimane', 'Ferhat', 'Khelladi', 'Bouguerra', 'Meziane', 'Bouchama'];
                $prenoms = ['Mohamed', 'Nadia', 'Omar', 'Sofia', 'Hakim', 'Linda', 'Bilal', 'Rym', 'Ilyes'];
                
                $stmt = $conn->prepare("INSERT INTO client (nom, prenom, email, password, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, status, company_id) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $nom = $noms[($comp_index * 3 + $k) % count($noms)];
                $prenom = $prenoms[($comp_index * 3 + $k) % count($prenoms)];
                $email = "client" . ($comp_index * 3 + $k + 1) . "@mail.com";
                $age = rand(24, 65);
                $tel = '055' . rand(1000000, 9999999);
                $wilaya = rand(1, 69);
                $status = ['non_reserve', 'reserve', 'payer'][rand(0, 2)];
                $stmt->bind_param("ssssisssisi", $nom, $prenom, $email, $password, $age, $tel, 'Algérienne', 
                                rand(1000000000000000, 9999999999999999), $wilaya, $status, $company_ids[$comp_index]);
                $stmt->execute();
            }
        }
        
        // Créer des voitures pour chaque compagnie
        $cars_data = [
            // Catégorie 1 (4000-6000 DA/jour)
            ['Toyota', 'Corolla', 2020, 1, 4500, 'Blanc'],
            ['Volkswagen', 'Golf', 2019, 1, 4800, 'Noir'],
            ['Renault', 'Clio', 2021, 1, 4200, 'Gris'],
            ['Hyundai', 'i30', 2020, 1, 4600, 'Bleu'],
            ['Kia', 'Rio', 2021, 1, 4400, 'Rouge'],
            
            // Catégorie 2 (6000-12000 DA/jour)
            ['Toyota', 'Camry', 2022, 2, 8500, 'Noir'],
            ['BMW', '3 Series', 2020, 2, 11000, 'Gris'],
            ['Volkswagen', 'Passat', 2021, 2, 7500, 'Blanc'],
            ['Audi', 'A4', 2022, 2, 10500, 'Bleu'],
            ['Mercedes-Benz', 'C-Class', 2023, 2, 12000, 'Noir'],
            
            // Catégorie 3 (12000-20000 DA/jour)
            ['Lexus', 'LS', 2023, 3, 18000, 'Noir'],
            ['Porsche', 'Panamera', 2022, 3, 20000, 'Bleu'],
            ['Mercedes-Benz', 'S-Class', 2023, 3, 19000, 'Argent'],
            ['BMW', '7 Series', 2022, 3, 18500, 'Noir'],
            ['Audi', 'A8', 2023, 3, 19500, 'Gris']
        ];
        
        for ($comp_index = 0; $comp_index < 3; $comp_index++) {
            for ($c = 0; $c < 5; $c++) {
                $car = $cars_data[($comp_index * 5 + $c) % count($cars_data)];
                
                // Générer une plaque d'immatriculation algérienne
                $serial = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                $category = $car[3];
                $year_last_two = $car[2] % 100;
                $wilaya_car = rand(1, 69);
                $matricule = $serial . ' ' . $category . ' ' . $year_last_two . ' ' . $wilaya_car;
                
                $stmt = $conn->prepare("INSERT INTO car (company_id, marque, model, color, annee, matricule, category, prix_day, status_voiture, voiture_work) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $status = ['excellent', 'entretien', 'faible'][rand(0, 2)];
                $work = ['disponible', 'non_disponible'][rand(0, 1)];
                $stmt->bind_param("isssisidss", $company_ids[$comp_index], $car[0], $car[1], $car[5], $car[2], 
                                $matricule, $car[3], $car[4], $status, $work);
                $stmt->execute();
            }
        }
        
        // Créer quelques réservations
        for ($i = 0; $i < 5; $i++) {
            // Récupérer un client, une voiture et un agent aléatoires
            $client_result = $conn->query("SELECT id, company_id FROM client ORDER BY RAND() LIMIT 1");
            $client = $client_result->fetch_assoc();
            
            $car_result = $conn->query("SELECT id_car, prix_day, company_id FROM car WHERE company_id = {$client['company_id']} ORDER BY RAND() LIMIT 1");
            $car = $car_result->fetch_assoc();
            
            $agent_result = $conn->query("SELECT id FROM agent WHERE company_id = {$client['company_id']} ORDER BY RAND() LIMIT 1");
            $agent = $agent_result->fetch_assoc();
            
            $period = rand(1, 14);
            $montant = $car['prix_day'] * $period;
            
            $date_debut = date('Y-m-d');
            $date_fin = date('Y-m-d', strtotime("+$period days"));
            
            $stmt = $conn->prepare("INSERT INTO reservation (id_client, id_agent, company_id, car_id, wilaya_id, date_debut, date_fin, period, montant, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $wilaya = rand(1, 69);
            $status = ['en_attente', 'confirmee', 'annulee'][rand(0, 2)];
            $stmt->bind_param("iiiisssids", $client['id'], $agent['id'], $client['company_id'], $car['id_car'], 
                            $wilaya, $date_debut, $date_fin, $period, $montant, $status);
            $stmt->execute();
            
            // Créer un paiement si le statut est confirmé
            if ($status == 'confirmee') {
                $reservation_id = $conn->insert_id;
                $stmt = $conn->prepare("INSERT INTO payment (id_reservation, montant, numero_carte, code_carte, status) 
                                       VALUES (?, ?, ?, ?, ?)");
                $num_carte = str_pad(rand(1, 9999999999999999), 16, '0', STR_PAD_LEFT);
                $code_carte = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $stmt->bind_param("idsss", $reservation_id, $montant, $num_carte, $code_carte, 'paye');
                $stmt->execute();
            }
        }
    }
}

// Connexion à la base de données
$conn = Database::connect();

// Classe utilitaire pour les fonctions communes
class Utils {
    public static function sanitize($input) {
        return htmlspecialchars(stripslashes(trim($input)));
    }
    
    public static function formatPrice($price) {
        return number_format($price, 0, ',', ' ') . ' DA';
    }
    
    public static function getWilayaName($id) {
        global $conn;
        $stmt = $conn->prepare("SELECT nom FROM wilaya WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['nom'];
        }
        return 'Inconnue';
    }
    
    public static function generateMatricule($category, $year, $wilaya) {
        $serial = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $year_last_two = $year % 100;
        return $serial . ' ' . $category . ' ' . $year_last_two . ' ' . $wilaya;
    }
}
?>

<?php
// Suite du fichier index.php

// Classes pour la gestion des utilisateurs
abstract class User {
    protected $id;
    protected $nom;
    protected $prenom;
    protected $email;
    protected $role;
    protected $company_id;
    
    public function __construct($data) {
        $this->id = $data['id'] ?? null;
        $this->nom = $data['nom'] ?? '';
        $this->prenom = $data['prenom'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->role = $data['role'] ?? '';
        $this->company_id = $data['company_id'] ?? null;
    }
    
    public function getId() { return $this->id; }
    public function getNom() { return $this->nom; }
    public function getPrenom() { return $this->prenom; }
    public function getEmail() { return $this->email; }
    public function getRole() { return $this->role; }
    public function getCompanyId() { return $this->company_id; }
    public function getFullName() { return $this->prenom . ' ' . $this->nom; }
    
    abstract public function getDashboardUrl();
}

class Client extends User {
    private $age;
    private $status;
    
    public function __construct($data) {
        parent::__construct($data);
        $this->age = $data['age'] ?? 0;
        $this->status = $data['status'] ?? 'non_reserve';
        $this->role = 'client';
    }
    
    public function getAge() { return $this->age; }
    public function getStatus() { return $this->status; }
    
    public function getDashboardUrl() {
        return '?page=client_dashboard';
    }
    
    public function getReservations() {
        global $conn;
        $stmt = $conn->prepare("SELECT r.*, c.marque, c.model, c.matricule 
                               FROM reservation r 
                               JOIN car c ON r.car_id = c.id_car 
                               WHERE r.id_client = ? 
                               ORDER BY r.created_at DESC");
        $stmt->bind_param("i", $this->id);
        $stmt->execute();
        return $stmt->get_result();
    }
}

class Agent extends User {
    private $salaire;
    
    public function __construct($data) {
        parent::__construct($data);
        $this->salaire = $data['salaire'] ?? 0;
        $this->role = 'agent';
    }
    
    public function getSalaire() { return $this->salaire; }
    
    public function getDashboardUrl() {
        return '?page=agent_dashboard';
    }
    
    public function getClients() {
        global $conn;
        $stmt = $conn->prepare("SELECT * FROM client WHERE company_id = ?");
        $stmt->bind_param("i", $this->company_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    public function getReservations() {
        global $conn;
        $stmt = $conn->prepare("SELECT r.*, c.marque, c.model, cl.nom as client_nom, cl.prenom as client_prenom 
                               FROM reservation r 
                               JOIN car c ON r.car_id = c.id_car 
                               JOIN client cl ON r.id_client = cl.id 
                               WHERE r.company_id = ? 
                               ORDER BY r.created_at DESC");
        $stmt->bind_param("i", $this->company_id);
        $stmt->execute();
        return $stmt->get_result();
    }
}

class Admin extends User {
    private $salaire;
    private $is_owner;
    
    public function __construct($data) {
        parent::__construct($data);
        $this->salaire = $data['salaire'] ?? 0;
        $this->is_owner = $data['is_owner'] ?? false;
        $this->role = 'admin';
    }
    
    public function getSalaire() { return $this->salaire; }
    public function isOwner() { return $this->is_owner; }
    
    public function getDashboardUrl() {
        if ($this->is_owner) {
            return '?page=owner_dashboard';
        } else {
            return '?page=admin_dashboard';
        }
    }
    
    public function getCompanyStats() {
        global $conn;
        $stats = [];
        
        // Revenu total
        $result = $conn->query("SELECT SUM(montant) as total_revenue FROM reservation 
                               WHERE company_id = {$this->company_id} AND status = 'confirmee'");
        $stats['revenue'] = $result->fetch_assoc()['total_revenue'] ?? 0;
        
        // Nombre de réservations
        $result = $conn->query("SELECT COUNT(*) as total_reservations FROM reservation 
                               WHERE company_id = {$this->company_id}");
        $stats['reservations'] = $result->fetch_assoc()['total_reservations'] ?? 0;
        
        // Nombre de voitures
        $result = $conn->query("SELECT COUNT(*) as total_cars FROM car 
                               WHERE company_id = {$this->company_id}");
        $stats['cars'] = $result->fetch_assoc()['total_cars'] ?? 0;
        
        // Nombre de clients
        $result = $conn->query("SELECT COUNT(*) as total_clients FROM client 
                               WHERE company_id = {$this->company_id}");
        $stats['clients'] = $result->fetch_assoc()['total_clients'] ?? 0;
        
        return $stats;
    }
    
    public function getRevenueByPeriod($period = 'month') {
        global $conn;
        
        switch ($period) {
            case 'day':
                $format = '%Y-%m-%d';
                break;
            case 'year':
                $format = '%Y';
                break;
            default:
                $format = '%Y-%m';
        }
        
        $query = "SELECT DATE_FORMAT(created_at, '$format') as period, 
                         SUM(montant) as revenue, 
                         COUNT(*) as reservations 
                  FROM reservation 
                  WHERE company_id = ? AND status = 'confirmee' 
                  GROUP BY period 
                  ORDER BY period DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $this->company_id);
        $stmt->execute();
        return $stmt->get_result();
    }
}

// Gestionnaire d'authentification
class Auth {
    public static function login($email, $password, $role) {
        global $conn;
        
        $table = '';
        $userClass = '';
        
        switch ($role) {
            case 'client':
                $table = 'client';
                $userClass = 'Client';
                break;
            case 'agent':
                $table = 'agent';
                $userClass = 'Agent';
                break;
            case 'admin':
                $table = 'admin';
                $userClass = 'Admin';
                break;
            default:
                return false;
        }
        
        $stmt = $conn->prepare("SELECT * FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                // Vérifier si c'est le propriétaire
                $is_owner = ($role == 'admin' && $email == 'chirifiyoucef@mail.com');
                
                $userData = array_merge($row, [
                    'role' => $role,
                    'is_owner' => $is_owner
                ]);
                
                $user = new $userClass($userData);
                
                $_SESSION['user'] = serialize($user);
                $_SESSION['role'] = $role;
                
                // Définir un cookie pour rester connecté (30 jours)
                setcookie('user_email', $email, time() + (30 * 24 * 60 * 60), '/');
                setcookie('user_role', $role, time() + (30 * 24 * 60 * 60), '/');
                
                return $user;
            }
        }
        
        return false;
    }
    
    public static function logout() {
        session_destroy();
        setcookie('user_email', '', time() - 3600, '/');
        setcookie('user_role', '', time() - 3600, '/');
        header('Location: index.php');
        exit();
    }
    
    public static function checkAuth($requiredRole = null) {
        if (isset($_SESSION['user'])) {
            $user = unserialize($_SESSION['user']);
            
            if ($requiredRole && $user->getRole() != $requiredRole) {
                header('Location: index.php');
                exit();
            }
            
            return $user;
        }
        
        // Vérifier les cookies
        if (isset($_COOKIE['user_email']) && isset($_COOKIE['user_role'])) {
            $user = self::login($_COOKIE['user_email'], '', $_COOKIE['user_role']);
            if ($user) {
                return $user;
            }
        }
        
        return null;
    }
    
    public static function getCurrentUser() {
        return self::checkAuth();
    }
}

// Vérifier si l'utilisateur est connecté
$currentUser = Auth::checkAuth();
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location de Voitures - Algérie</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #1e40af;
            --accent: #f59e0b;
            --light: #f8fafc;
            --dark: #1e293b;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--dark) 0%, #0f172a 100%);
            color: white;
        }
        
        .stat-card {
            background: white;
            border-left: 4px solid var(--primary);
        }
        
        .car-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .car-category-1 {
            border-color: #10b981;
        }
        
        .car-category-2 {
            border-color: #f59e0b;
        }
        
        .car-category-3 {
            border-color: #ef4444;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="text-gray-800">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-2">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-car text-white text-xl"></i>
                    </div>
                    <a href="index.php" class="text-2xl font-bold text-blue-600">
                        Auto<span class="text-orange-500">DZ</span>Location
                    </a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <?php if ($currentUser): ?>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-medium"><?php echo $currentUser->getFullName(); ?></p>
                                <p class="text-sm text-gray-500 capitalize"><?php echo $currentUser->getRole(); ?></p>
                            </div>
                            <a href="<?php echo $currentUser->getDashboardUrl(); ?>" 
                               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                Tableau de bord
                            </a>
                            <a href="?action=logout" 
                               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                                Déconnexion
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="flex space-x-3">
                            <a href="?page=login" 
                               class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                                Connexion
                            </a>
                            <a href="?page=choose_role" 
                               class="px-6 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-50 transition font-medium">
                                S'inscrire
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Gestion des actions -->
    <?php
    if (isset($_GET['action'])) {
        if ($_GET['action'] == 'logout') {
            Auth::logout();
        }
    }
    ?>
    
    <!-- Contenu principal -->
    <main class="container mx-auto px-4 py-8">
        <?php
        // Déterminer quelle page afficher
        $page = 'home';
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
        } elseif ($currentUser) {
            $page = $currentUser->getDashboardUrl();
            $page = str_replace('?page=', '', $page);
        }
        
        // Inclure la page appropriée
        switch ($page) {
            case 'login':
                includePage('login');
                break;
            case 'choose_role':
                includePage('choose_role');
                break;
            case 'register':
                includePage('register');
                break;
            case 'client_dashboard':
                if ($currentUser && $currentUser->getRole() == 'client') {
                    includePage('client_dashboard');
                } else {
                    includePage('home');
                }
                break;
            case 'agent_dashboard':
                if ($currentUser && $currentUser->getRole() == 'agent') {
                    includePage('agent_dashboard');
                } else {
                    includePage('home');
                }
                break;
            case 'admin_dashboard':
                if ($currentUser && $currentUser->getRole() == 'admin' && !$currentUser->isOwner()) {
                    includePage('admin_dashboard');
                } else {
                    includePage('home');
                }
                break;
            case 'owner_dashboard':
                if ($currentUser && $currentUser->getRole() == 'admin' && $currentUser->isOwner()) {
                    includePage('owner_dashboard');
                } else {
                    includePage('home');
                }
                break;
            case 'cars':
                includePage('cars');
                break;
            case 'reservation':
                includePage('reservation');
                break;
            case 'payment':
                includePage('payment');
                break;
            case 'manage_users':
                if ($currentUser && ($currentUser->getRole() == 'admin' || $currentUser->getRole() == 'agent')) {
                    includePage('manage_users');
                } else {
                    includePage('home');
                }
                break;
            case 'manage_cars':
                if ($currentUser && $currentUser->getRole() == 'admin') {
                    includePage('manage_cars');
                } else {
                    includePage('home');
                }
                break;
            case 'statistics':
                if ($currentUser && $currentUser->getRole() == 'admin') {
                    includePage('statistics');
                } else {
                    includePage('home');
                }
                break;
            default:
                includePage('home');
        }
        
        function includePage($pageName) {
            global $currentUser, $conn;
            
            // Les pages seront générées dynamiquement dans les parties suivantes
            // Pour l'instant, nous allons afficher un message
            if ($pageName == 'home') {
                showHomePage();
            } else {
                echo '<div class="text-center py-12">';
                echo '<h2 class="text-3xl font-bold text-gray-700 mb-4">Page en construction</h2>';
                echo '<p class="text-gray-600">La page "' . $pageName . '" sera disponible dans la prochaine partie.</p>';
                echo '</div>';
            }
        }
        
        function showHomePage() {
            global $currentUser, $conn;
            ?>
            <!-- Section Hero -->
            <section class="gradient-bg text-white rounded-2xl p-8 mb-12 shadow-xl">
                <div class="max-w-3xl">
                    <h1 class="text-5xl font-bold mb-6">Location de Voitures en Algérie</h1>
                    <p class="text-xl mb-8 opacity-90">
                        Trouvez la voiture parfaite pour vos déplacements. Plus de 50 modèles disponibles 
                        dans nos agences partenaires à travers le pays.
                    </p>
                    <?php if (!$currentUser): ?>
                        <div class="flex space-x-4">
                            <a href="?page=login" 
                               class="px-8 py-3 bg-white text-blue-600 rounded-lg font-bold hover:bg-gray-100 transition text-lg">
                                Se connecter
                            </a>
                            <a href="?page=choose_role" 
                               class="px-8 py-3 border-2 border-white text-white rounded-lg font-bold hover:bg-white/10 transition text-lg">
                                Créer un compte
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
                <div class="stat-card p-6 rounded-xl shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Agences</p>
                            <h3 class="text-3xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM company");
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-building text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6 rounded-xl shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Voitures</p>
                            <h3 class="text-3xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM car");
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-car text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6 rounded-xl shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Clients</p>
                            <h3 class="text-3xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM client");
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6 rounded-xl shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Réservations</p>
                            <h3 class="text-3xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM reservation");
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-calendar-check text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Comment ça marche -->
            <section class="mb-12">
                <h2 class="text-3xl font-bold text-center mb-10">Comment ça marche ?</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center p-6">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user-check text-blue-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">1. Créez votre compte</h3>
                        <p class="text-gray-600">Choisissez votre rôle (client, agent ou administrateur) et inscrivez-vous.</p>
                    </div>
                    
                    <div class="text-center p-6">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-car text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">2. Choisissez une voiture</h3>
                        <p class="text-gray-600">Parcourez notre sélection de voitures par catégorie et prix.</p>
                    </div>
                    
                    <div class="text-center p-6">
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-credit-card text-purple-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2">3. Réservez et payez</h3>
                        <p class="text-gray-600">Réservez votre voiture et payez en ligne en toute sécurité.</p>
                    </div>
                </div>
            </section>
            
            <?php if ($currentUser && $currentUser->getRole() == 'client'): ?>
                <!-- Offres pour le client connecté -->
                <section class="mb-12">
                    <h2 class="text-3xl font-bold mb-6">Voitures disponibles dans votre agence</h2>
                    <?php
                    $company_id = $currentUser->getCompanyId();
                    $result = $conn->query("SELECT * FROM car WHERE company_id = $company_id AND voiture_work = 'disponible' LIMIT 6");
                    
                    if ($result->num_rows > 0):
                    ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php while ($car = $result->fetch_assoc()): ?>
                            <div class="car-card bg-white shadow-lg hover:shadow-xl transition-shadow">
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <h3 class="text-xl font-bold"><?php echo $car['marque'] . ' ' . $car['model']; ?></h3>
                                            <p class="text-gray-500"><?php echo $car['annee']; ?> • <?php echo $car['color']; ?></p>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium
                                            <?php echo $car['category'] == 1 ? 'bg-green-100 text-green-800' : 
                                                   ($car['category'] == 2 ? 'bg-yellow-100 text-yellow-800' : 
                                                   'bg-red-100 text-red-800'); ?>">
                                            Catégorie <?php echo $car['category']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <p class="text-2xl font-bold text-blue-600">
                                            <?php echo Utils::formatPrice($car['prix_day']); ?> <span class="text-sm font-normal text-gray-500">/ jour</span>
                                        </p>
                                        <p class="text-gray-600 text-sm mt-1">Immatriculation: <?php echo $car['matricule']; ?></p>
                                    </div>
                                    
                                    <div class="flex justify-between items-center">
                                        <span class="px-3 py-1 rounded-full text-sm font-medium
                                            <?php echo $car['status_voiture'] == 'excellent' ? 'bg-blue-100 text-blue-800' : 
                                                   ($car['status_voiture'] == 'entretien' ? 'bg-yellow-100 text-yellow-800' : 
                                                   'bg-red-100 text-red-800'); ?>">
                                            <?php echo ucfirst($car['status_voiture']); ?>
                                        </span>
                                        
                                        <div class="flex space-x-2">
                                            <a href="?page=reservation&car_id=<?php echo $car['id_car']; ?>" 
                                               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                                Réserver
                                            </a>
                                            <a href="?page=payment&car_id=<?php echo $car['id_car']; ?>" 
                                               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                                Payer
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="text-center mt-8">
                        <a href="?page=cars" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            Voir toutes les voitures
                        </a>
                    </div>
                    <?php else: ?>
                        <p class="text-gray-600 text-center py-8">Aucune voiture disponible pour le moment.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <?php
        }
        ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 mt-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-car text-white text-xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold">Auto<span class="text-orange-500">DZ</span>Location</h3>
                    </div>
                    <p class="text-gray-400">
                        Leader de la location de voitures en Algérie avec des agences dans toutes les wilayas.
                    </p>
                </div>
                
                <div>
                    <h4 class="text-xl font-bold mb-4">Contact</h4>
                    <div class="space-y-2 text-gray-400">
                        <p><i class="fas fa-phone mr-2"></i> 021 23 45 67</p>
                        <p><i class="fas fa-envelope mr-2"></i> contact@autodzlocation.dz</p>
                        <p><i class="fas fa-map-marker-alt mr-2"></i> Alger Centre, Algérie</p>
                    </div>
                </div>
                
                <div>
                    <h4 class="text-xl font-bold mb-4">Liens rapides</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="?page=cars" class="hover:text-white transition">Nos voitures</a></li>
                        <li><a href="?page=login" class="hover:text-white transition">Connexion</a></li>
                        <li><a href="?page=choose_role" class="hover:text-white transition">Inscription</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="text-xl font-bold mb-4">Suivez-nous</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-blue-700 rounded-full flex items-center justify-center hover:bg-blue-600 transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-pink-600 rounded-full flex items-center justify-center hover:bg-pink-500 transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-blue-400 rounded-full flex items-center justify-center hover:bg-blue-300 transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> AutoDZLocation. Tous droits réservés.</p>
                <p class="mt-2">Propriétaire: Cherifi Youssouf</p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts JavaScript -->
    <script>
        // Fonction pour afficher les notifications
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                'bg-blue-500'
            } text-white`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // Validation des formulaires
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return true;
            
            const inputs = form.querySelectorAll('input[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.classList.add('border-red-500');
                    isValid = false;
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            
            return isValid;
        }
        
        // Gestion des messages de session PHP
        <?php if (isset($_SESSION['message'])): ?>
            showNotification('<?php echo $_SESSION['message']['text']; ?>', '<?php echo $_SESSION['message']['type']; ?>');
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
    </script>

    <?php
// Suite du fichier index.php - Pages de connexion et d'inscription

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = Utils::sanitize($_POST['email']);
    $password = Utils::sanitize($_POST['password']);
    $role = Utils::sanitize($_POST['role']);
    
    $user = Auth::login($email, $password, $role);
    
    if ($user) {
        $_SESSION['message'] = [
            'text' => 'Connexion réussie ! Bienvenue ' . $user->getFullName(),
            'type' => 'success'
        ];
        header('Location: ' . $user->getDashboardUrl());
        exit();
    } else {
        $_SESSION['message'] = [
            'text' => 'Email ou mot de passe incorrect.',
            'type' => 'error'
        ];
    }
}

// Traitement du formulaire d'inscription (seulement pour les agents et admins pour créer des comptes)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $role = Utils::sanitize($_POST['role']);
    $currentUserRole = $currentUser ? $currentUser->getRole() : null;
    
    // Seuls les agents et admins peuvent créer des comptes
    if ($currentUser && in_array($currentUserRole, ['agent', 'admin'])) {
        $nom = Utils::sanitize($_POST['nom']);
        $prenom = Utils::sanitize($_POST['prenom']);
        $email = Utils::sanitize($_POST['email']);
        $password = password_hash(Utils::sanitize($_POST['password']), PASSWORD_DEFAULT);
        $age = intval($_POST['age']);
        $numero_tlfn = Utils::sanitize($_POST['numero_tlfn']);
        $nationalite = Utils::sanitize($_POST['nationalite']);
        $numero_cart_national = Utils::sanitize($_POST['numero_cart_national']);
        $wilaya_id = intval($_POST['wilaya_id']);
        $company_id = $currentUser->getCompanyId();
        
        // Vérifier l'âge minimum
        if ($age < 24) {
            $_SESSION['message'] = [
                'text' => 'L\'âge minimum est de 24 ans.',
                'type' => 'error'
            ];
        } else {
            // Vérifier si l'email existe déjà
            $check_stmt = $conn->prepare("SELECT id FROM $role WHERE email = ?");
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $_SESSION['message'] = [
                    'text' => 'Cet email est déjà utilisé.',
                    'type' => 'error'
                ];
            } else {
                // Insérer le nouvel utilisateur
                $table = $role;
                $extra_fields = '';
                $extra_values = '';
                $extra_types = '';
                
                if ($role == 'client') {
                    $extra_fields = ', status';
                    $extra_values = ", 'non_reserve'";
                } else {
                    $salaire = $role == 'admin' ? rand(50000, 120000) : rand(30000, 70000);
                    $extra_fields = ', salaire';
                    $extra_values = ", $salaire";
                }
                
                $stmt = $conn->prepare("INSERT INTO $table (nom, prenom, email, password, age, numero_tlfn, nationalite, numero_cart_national, wilaya_id, company_id $extra_fields) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ? $extra_values)");
                
                if ($role == 'client') {
                    $stmt->bind_param("ssssisssii", $nom, $prenom, $email, $password, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $company_id);
                } else {
                    $stmt->bind_param("ssssisssiid", $nom, $prenom, $email, $password, $age, $numero_tlfn, $nationalite, $numero_cart_national, $wilaya_id, $company_id, $salaire);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = [
                        'text' => ucfirst($role) . ' créé avec succès !',
                        'type' => 'success'
                    ];
                } else {
                    $_SESSION['message'] = [
                        'text' => 'Erreur lors de la création : ' . $conn->error,
                        'type' => 'error'
                    ];
                }
            }
        }
    } else {
        $_SESSION['message'] = [
            'text' => 'Vous n\'êtes pas autorisé à créer des comptes.',
            'type' => 'error'
        ];
    }
}

// Fonctions pour afficher les pages spécifiques
function showLoginPage() {
    ?>
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-lg p-8 mt-8">
        <h2 class="text-3xl font-bold text-center mb-8 text-gray-800">Connexion</h2>
        
        <form method="POST" id="loginForm">
            <input type="hidden" name="login" value="1">
            
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="role">Vous êtes :</label>
                <select name="role" id="role" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Sélectionnez votre rôle</option>
                    <option value="client">Client</option>
                    <option value="agent">Agent</option>
                    <option value="admin">Administrateur</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="email">Email</label>
                <input type="email" name="email" id="email" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="votre@email.com">
            </div>
            
            <div class="mb-6">
                <label class="block text-gray-700 mb-2" for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Votre mot de passe">
            </div>
            
            <div class="mb-6">
                <button type="submit" 
                        class="w-full btn-primary py-3 rounded-lg font-bold text-lg">
                    Se connecter
                </button>
            </div>
            
            <div class="text-center">
                <p class="text-gray-600">Propriétaire du site ?</p>
                <p class="text-sm text-gray-500 mt-1">
                    Email: chirifiyoucef@mail.com<br>
                    Mot de passe: 123
                </p>
            </div>
        </form>
    </div>
    
    <div class="max-w-md mx-auto mt-8 text-center">
        <p class="text-gray-600">Vous n'avez pas de compte ?</p>
        <p class="text-sm text-gray-500 mt-1">Seuls les agents et administrateurs peuvent créer des comptes.</p>
        <p class="text-sm text-gray-500">Contactez votre agence pour obtenir un compte.</p>
    </div>
    <?php
}

function showChooseRolePage() {
    ?>
    <div class="max-w-4xl mx-auto mt-8">
        <h2 class="text-3xl font-bold text-center mb-10 text-gray-800">Choisissez votre rôle</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Client Card -->
            <div class="bg-white rounded-xl shadow-lg p-8 text-center border-2 border-blue-100 hover:border-blue-300 transition">
                <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-user text-blue-600 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold mb-4">Client</h3>
                <p class="text-gray-600 mb-6">
                    Louez des voitures pour vos déplacements personnels ou professionnels.
                </p>
                <ul class="text-left text-gray-600 mb-8 space-y-2">
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Consultez les offres</li>
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Réservez en ligne</li>
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Suivez vos locations</li>
                </ul>
                <p class="text-sm text-gray-500 mb-4">Pour créer un compte client, contactez un agent de votre agence.</p>
            </div>
            
            <!-- Agent Card -->
            <div class="bg-white rounded-xl shadow-lg p-8 text-center border-2 border-green-100 hover:border-green-300 transition">
                <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-user-tie text-green-600 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold mb-4">Agent</h3>
                <p class="text-gray-600 mb-6">
                    Gérez les clients et les réservations pour votre agence de location.
                </p>
                <ul class="text-left text-gray-600 mb-8 space-y-2">
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Créez des comptes clients</li>
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Gérez les réservations</li>
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Suivez les locations</li>
                </ul>
                <p class="text-sm text-gray-500 mb-4">Contactez l'administrateur de votre agence pour obtenir un compte.</p>
            </div>
            
            <!-- Admin Card -->
            <div class="bg-white rounded-xl shadow-lg p-8 text-center border-2 border-purple-100 hover:border-purple-300 transition">
                <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-user-cog text-purple-600 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold mb-4">Administrateur</h3>
                <p class="text-gray-600 mb-6">
                    Gérez complètement votre agence de location de voitures.
                </p>
                <ul class="text-left text-gray-600 mb-8 space-y-2">
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Gérez le personnel</li>
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Gérez le parc auto</li>
                    <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i> Visualisez les statistiques</li>
                </ul>
                <p class="text-sm text-gray-500 mb-4">Contactez le propriétaire du site pour créer une agence.</p>
            </div>
        </div>
        
        <div class="text-center mt-12">
            <p class="text-gray-600">Vous avez déjà un compte ?</p>
            <a href="?page=login" class="inline-block mt-4 px-8 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Se connecter
            </a>
        </div>
    </div>
    <?php
}

function showRegisterPage() {
    global $currentUser, $conn;
    
    // Récupérer la liste des wilayas
    $wilayas_result = $conn->query("SELECT * FROM wilaya ORDER BY nom");
    ?>
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-8 mt-8">
        <h2 class="text-3xl font-bold text-center mb-8 text-gray-800">Créer un compte</h2>
        
        <?php if ($currentUser && in_array($currentUser->getRole(), ['agent', 'admin'])): ?>
            <form method="POST" id="registerForm">
                <input type="hidden" name="register" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="role">Type de compte :</label>
                    <select name="role" id="role" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Sélectionnez le rôle</option>
                        <option value="client">Client</option>
                        <?php if ($currentUser->getRole() == 'admin'): ?>
                            <option value="agent">Agent</option>
                            <option value="admin">Administrateur</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2" for="nom">Nom</label>
                        <input type="text" name="nom" id="nom" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Votre nom">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="prenom">Prénom</label>
                        <input type="text" name="prenom" id="prenom" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Votre prénom">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="email">Email</label>
                    <input type="email" name="email" id="email" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="votre@email.com">
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="password">Mot de passe</label>
                    <input type="password" name="password" id="password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Mot de passe sécurisé">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2" for="age">Âge</label>
                        <input type="number" name="age" id="age" required min="24"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Âge (minimum 24)">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="numero_tlfn">Téléphone</label>
                        <input type="tel" name="numero_tlfn" id="numero_tlfn" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="05X XX XX XX">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2" for="nationalite">Nationalité</label>
                        <input type="text" name="nationalite" id="nationalite" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="Algérienne">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="numero_cart_national">Numéro Carte Nationale</label>
                        <input type="text" name="numero_cart_national" id="numero_cart_national" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="16 chiffres">
                    </div>
                </div>
                
                <div class="mb-8">
                    <label class="block text-gray-700 mb-2" for="wilaya_id">Wilaya</label>
                    <select name="wilaya_id" id="wilaya_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Sélectionnez votre wilaya</option>
                        <?php while ($wilaya = $wilayas_result->fetch_assoc()): ?>
                            <option value="<?php echo $wilaya['id']; ?>">
                                <?php echo $wilaya['id']; ?> - <?php echo $wilaya['nom']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <button type="submit" 
                            class="w-full btn-primary py-3 rounded-lg font-bold text-lg">
                        Créer le compte
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center py-8">
                <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-exclamation-triangle text-red-600 text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold mb-4">Accès refusé</h3>
                <p class="text-gray-600 mb-6">
                    Seuls les agents et administrateurs peuvent créer des comptes.
                </p>
                <a href="?page=login" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    Retour à la connexion
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// Mettre à jour la fonction includePage pour inclure ces pages
function includePage($pageName) {
    global $currentUser, $conn;
    
    switch ($pageName) {
        case 'login':
            showLoginPage();
            break;
        case 'choose_role':
            showChooseRolePage();
            break;
        case 'register':
            showRegisterPage();
            break;
        // ... autres cas
        default:
            showHomePage();
    }
}
?>

<?php
// Suite du fichier index.php - Tableau de bord du client

function showClientDashboard() {
    global $currentUser, $conn;
    $client = $currentUser;
    ?>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Tableau de bord Client</h1>
        <p class="text-gray-600">Bienvenue, <?php echo $client->getFullName(); ?></p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user text-blue-600 text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold"><?php echo $client->getFullName(); ?></h3>
                    <p class="text-gray-500">Client</p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo $client->getEmail(); ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-birthday-cake text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo $client->getAge(); ?> ans</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-map-marker-alt text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo Utils::getWilayaName($client->wilaya_id ?? 0); ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-user-tag text-gray-400 w-6"></i>
                        <span class="ml-3">Statut: <?php echo ucfirst(str_replace('_', ' ', $client->getStatus())); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold mb-4">Actions rapides</h3>
                <div class="space-y-3">
                    <a href="?page=cars" 
                       class="flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">
                        <i class="fas fa-car mr-3"></i>
                        <span>Voir les voitures disponibles</span>
                    </a>
                    <a href="?page=reservation" 
                       class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition">
                        <i class="fas fa-calendar-plus mr-3"></i>
                        <span>Nouvelle réservation</span>
                    </a>
                    <a href="#" onclick="showNotification('Fonctionnalité en développement', 'info')"
                       class="flex items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition">
                        <i class="fas fa-history mr-3"></i>
                        <span>Historique des locations</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="lg:col-span-2">
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Réservations</p>
                            <h3 class="text-2xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM reservation WHERE id_client = " . $client->getId());
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-car text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Voitures louées</p>
                            <h3 class="text-2xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(DISTINCT car_id) as count FROM reservation 
                                                       WHERE id_client = " . $client->getId() . " AND status = 'confirmee'");
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Total dépensé</p>
                            <h3 class="text-2xl font-bold">
                                <?php
                                $result = $conn->query("SELECT SUM(montant) as total FROM reservation 
                                                       WHERE id_client = " . $client->getId() . " AND status = 'confirmee'");
                                $total = $result->fetch_assoc()['total'] ?? 0;
                                echo Utils::formatPrice($total);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Voitures disponibles -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Voitures disponibles</h2>
                    <a href="?page=cars" class="text-blue-600 hover:text-blue-800 font-medium">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php
                $company_id = $client->getCompanyId();
                $result = $conn->query("SELECT * FROM car WHERE company_id = $company_id AND voiture_work = 'disponible' LIMIT 3");
                
                if ($result->num_rows > 0):
                ?>
                <div class="space-y-6">
                    <?php while ($car = $result->fetch_assoc()): ?>
                        <div class="flex flex-col md:flex-row border border-gray-200 rounded-lg overflow-hidden">
                            <div class="md:w-1/3 bg-gray-100 p-4 flex items-center justify-center">
                                <i class="fas fa-car text-gray-400 text-5xl"></i>
                            </div>
                            <div class="md:w-2/3 p-4">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="text-xl font-bold"><?php echo $car['marque'] . ' ' . $car['model']; ?></h3>
                                        <p class="text-gray-500"><?php echo $car['annee']; ?> • <?php echo $car['color']; ?></p>
                                    </div>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium
                                        <?php echo $car['category'] == 1 ? 'bg-green-100 text-green-800' : 
                                               ($car['category'] == 2 ? 'bg-yellow-100 text-yellow-800' : 
                                               'bg-red-100 text-red-800'); ?>">
                                        Catégorie <?php echo $car['category']; ?>
                                    </span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <div>
                                        <p class="text-2xl font-bold text-blue-600">
                                            <?php echo Utils::formatPrice($car['prix_day']); ?> <span class="text-sm font-normal text-gray-500">/ jour</span>
                                        </p>
                                        <p class="text-gray-600 text-sm">Immatriculation: <?php echo $car['matricule']; ?></p>
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <a href="?page=reservation&car_id=<?php echo $car['id_car']; ?>" 
                                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm">
                                            Réserver
                                        </a>
                                        <a href="?page=payment&car_id=<?php echo $car['id_car']; ?>" 
                                           class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                            Payer
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-8">Aucune voiture disponible pour le moment.</p>
                <?php endif; ?>
            </div>
            
            <!-- Mes réservations -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-6">Mes réservations</h2>
                
                <?php
                $reservations = $client->getReservations();
                
                if ($reservations->num_rows > 0):
                ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voiture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durée</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($res = $reservations->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium"><?php echo $res['marque'] . ' ' . $res['model']; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $res['matricule']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm"><?php echo date('d/m/Y', strtotime($res['date_debut'])); ?></div>
                                        <div class="text-sm text-gray-500">au <?php echo date('d/m/Y', strtotime($res['date_fin'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $res['period']; ?> jours
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-600">
                                        <?php echo Utils::formatPrice($res['montant']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = [
                                            'en_attente' => 'bg-yellow-100 text-yellow-800',
                                            'confirmee' => 'bg-green-100 text-green-800',
                                            'annulee' => 'bg-red-100 text-red-800',
                                            'terminee' => 'bg-blue-100 text-blue-800'
                                        ][$res['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $res['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-8">Vous n'avez aucune réservation.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>


<?php
// Suite du fichier index.php - Tableau de bord de l'agent

function showAgentDashboard() {
    global $currentUser, $conn;
    $agent = $currentUser;
    
    // Traitement des actions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_client'])) {
            // Code pour ajouter un client (similaire à showRegisterPage)
        } elseif (isset($_POST['update_client'])) {
            // Code pour mettre à jour un client
        } elseif (isset($_POST['delete_client'])) {
            $client_id = intval($_POST['client_id']);
            $stmt = $conn->prepare("DELETE FROM client WHERE id = ? AND company_id = ?");
            $stmt->bind_param("ii", $client_id, $agent->getCompanyId());
            if ($stmt->execute()) {
                $_SESSION['message'] = [
                    'text' => 'Client supprimé avec succès.',
                    'type' => 'success'
                ];
            }
        }
    }
    ?>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Tableau de bord Agent</h1>
        <p class="text-gray-600">Bienvenue, <?php echo $agent->getFullName(); ?></p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-tie text-green-600 text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold"><?php echo $agent->getFullName(); ?></h3>
                    <p class="text-gray-500">Agent</p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo $agent->getEmail(); ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-money-bill text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo Utils::formatPrice($agent->getSalaire()); ?> / mois</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-building text-gray-400 w-6"></i>
                        <span class="ml-3">Agence #<?php echo $agent->getCompanyId(); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold mb-4">Actions</h3>
                <div class="space-y-3">
                    <a href="?page=manage_users" 
                       class="flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">
                        <i class="fas fa-user-plus mr-3"></i>
                        <span>Ajouter un client</span>
                    </a>
                    <a href="?page=manage_users&action=view_clients" 
                       class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition">
                        <i class="fas fa-users mr-3"></i>
                        <span>Gérer les clients</span>
                    </a>
                    <a href="#" onclick="showNotification('Fonctionnalité en développement', 'info')"
                       class="flex items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition">
                        <i class="fas fa-file-invoice-dollar mr-3"></i>
                        <span>Générer facture</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="lg:col-span-3">
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Clients</p>
                            <h3 class="text-2xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM client WHERE company_id = " . $agent->getCompanyId());
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Réservations</p>
                            <h3 class="text-2xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM reservation WHERE company_id = " . $agent->getCompanyId());
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-car text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Voitures disponibles</p>
                            <h3 class="text-2xl font-bold">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as count FROM car WHERE company_id = " . $agent->getCompanyId() . " AND voiture_work = 'disponible'");
                                echo $result->fetch_assoc()['count'];
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Réservations récentes -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Réservations récentes</h2>
                    <a href="#" onclick="showNotification('Fonctionnalité en développement', 'info')" 
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php
                $reservations = $agent->getReservations();
                
                if ($reservations->num_rows > 0):
                ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voiture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            $count = 0;
                            while ($res = $reservations->fetch_assoc() && $count < 5):
                                $count++;
                            ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium"><?php echo $res['client_prenom'] . ' ' . $res['client_nom']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium"><?php echo $res['marque'] . ' ' . $res['model']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm"><?php echo date('d/m/Y', strtotime($res['date_debut'])); ?></div>
                                        <div class="text-sm text-gray-500">au <?php echo date('d/m/Y', strtotime($res['date_fin'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-600">
                                        <?php echo Utils::formatPrice($res['montant']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = [
                                            'en_attente' => 'bg-yellow-100 text-yellow-800',
                                            'confirmee' => 'bg-green-100 text-green-800',
                                            'annulee' => 'bg-red-100 text-red-800',
                                            'terminee' => 'bg-blue-100 text-blue-800'
                                        ][$res['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $res['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex space-x-2">
                                            <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                                    class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                                Détails
                                            </button>
                                            <?php if ($res['status'] == 'en_attente'): ?>
                                                <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                                        class="px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200">
                                                    Confirmer
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-8">Aucune réservation trouvée.</p>
                <?php endif; ?>
            </div>
            
            <!-- Liste des clients -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Clients de l'agence</h2>
                    <a href="?page=manage_users" 
                       class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-user-plus mr-2"></i> Ajouter client
                    </a>
                </div>
                
                <?php
                $clients = $agent->getClients();
                
                if ($clients->num_rows > 0):
                ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom & Prénom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Âge</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($client = $clients->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium"><?php echo $client['prenom'] . ' ' . $client['nom']; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $client['email']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $client['age']; ?> ans
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $client['numero_tlfn']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = [
                                            'payer' => 'bg-green-100 text-green-800',
                                            'reserve' => 'bg-yellow-100 text-yellow-800',
                                            'annuler' => 'bg-red-100 text-red-800',
                                            'non_reserve' => 'bg-gray-100 text-gray-800'
                                        ][$client['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $client['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex space-x-2">
                                            <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                                    class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                                Modifier
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Supprimer ce client ?');" class="inline">
                                                <input type="hidden" name="delete_client" value="1">
                                                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                <button type="submit" 
                                                        class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                                                    Supprimer
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
                    <p class="text-gray-600 text-center py-8">Aucun client trouvé.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php
// Suite du fichier index.php - Tableau de bord de l'administrateur

function showAdminDashboard() {
    global $currentUser, $conn;
    $admin = $currentUser;
    
    // Traitement des actions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Gestion des voitures
        if (isset($_POST['add_car'])) {
            $marque = Utils::sanitize($_POST['marque']);
            $model = Utils::sanitize($_POST['model']);
            $color = Utils::sanitize($_POST['color']);
            $annee = intval($_POST['annee']);
            $category = intval($_POST['category']);
            $prix_day = floatval($_POST['prix_day']);
            $status_voiture = Utils::sanitize($_POST['status_voiture']);
            $voiture_work = Utils::sanitize($_POST['voiture_work']);
            
            // Validation du prix selon la catégorie
            $min_price = [1 => 4000, 2 => 6000, 3 => 12000][$category] ?? 4000;
            $max_price = [1 => 6000, 2 => 12000, 3 => 20000][$category] ?? 20000;
            
            if ($prix_day < $min_price || $prix_day > $max_price) {
                $_SESSION['message'] = [
                    'text' => "Le prix doit être entre $min_price et $max_price DA/jour pour la catégorie $category",
                    'type' => 'error'
                ];
            } else {
                // Générer la plaque d'immatriculation
                $wilaya = rand(1, 69);
                $matricule = Utils::generateMatricule($category, $annee, $wilaya);
                
                $stmt = $conn->prepare("INSERT INTO car (company_id, marque, model, color, annee, matricule, category, prix_day, status_voiture, voiture_work) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssisidss", $admin->getCompanyId(), $marque, $model, $color, $annee, 
                                $matricule, $category, $prix_day, $status_voiture, $voiture_work);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = [
                        'text' => 'Voiture ajoutée avec succès.',
                        'type' => 'success'
                    ];
                } else {
                    $_SESSION['message'] = [
                        'text' => 'Erreur lors de l\'ajout : ' . $conn->error,
                        'type' => 'error'
                    ];
                }
            }
        }
    }
    
    // Récupérer les statistiques
    $stats = $admin->getCompanyStats();
    ?>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Tableau de bord Administrateur</h1>
        <p class="text-gray-600">Bienvenue, <?php echo $admin->getFullName(); ?></p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-cog text-purple-600 text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold"><?php echo $admin->getFullName(); ?></h3>
                    <p class="text-gray-500">Administrateur</p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo $admin->getEmail(); ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-money-bill text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo Utils::formatPrice($admin->getSalaire()); ?> / mois</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-building text-gray-400 w-6"></i>
                        <span class="ml-3">Agence #<?php echo $admin->getCompanyId(); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Menu admin -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold mb-4">Menu Administration</h3>
                <div class="space-y-3">
                    <a href="?page=manage_users" 
                       class="flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">
                        <i class="fas fa-users-cog mr-3"></i>
                        <span>Gérer utilisateurs</span>
                    </a>
                    <a href="?page=manage_cars" 
                       class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition">
                        <i class="fas fa-car mr-3"></i>
                        <span>Gérer voitures</span>
                    </a>
                    <a href="?page=statistics" 
                       class="flex items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition">
                        <i class="fas fa-chart-bar mr-3"></i>
                        <span>Statistiques</span>
                    </a>
                    <a href="?page=manage_reservations" 
                       class="flex items-center p-3 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 transition">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        <span>Gérer réservations</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="lg:col-span-3">
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-money-bill-wave text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Revenu total</p>
                            <h3 class="text-2xl font-bold"><?php echo Utils::formatPrice($stats['revenue']); ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Réservations</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['reservations']; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-car text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Voitures</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['cars']; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-users text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Clients</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['clients']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analyse financière -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold mb-6">Analyse financière</h2>
                
                <?php
                // Calculer les dépenses
                $frais_result = $conn->query("SELECT frais_mensuel FROM company WHERE company_id = " . $admin->getCompanyId());
                $frais_mensuel = $frais_result->fetch_assoc()['frais_mensuel'] ?? 0;
                
                // Salaire des employés
                $salaires_result = $conn->query("SELECT SUM(salaire) as total_salaires FROM (
                    SELECT salaire FROM admin WHERE company_id = {$admin->getCompanyId()}
                    UNION ALL
                    SELECT salaire FROM agent WHERE company_id = {$admin->getCompanyId()}
                ) as salaries");
                $total_salaires = $salaires_result->fetch_assoc()['total_salaires'] ?? 0;
                
                // Dépenses totales (frais + salaires)
                $depenses_totales = $frais_mensuel + $total_salaires;
                
                // Bénéfice
                $benefice = $stats['revenue'] - $depenses_totales;
                ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-bold mb-4">Revenus vs Dépenses</h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">Revenus</span>
                                    <span class="font-bold text-green-600"><?php echo Utils::formatPrice($stats['revenue']); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" 
                                         style="width: <?php echo min(100, ($stats['revenue'] / max(1, $stats['revenue'] + $depenses_totales)) * 100); ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">Salaires employés</span>
                                    <span class="font-bold text-yellow-600"><?php echo Utils::formatPrice($total_salaires); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" 
                                         style="width: <?php echo min(100, ($total_salaires / max(1, $stats['revenue'] + $depenses_totales)) * 100); ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">Frais mensuels</span>
                                    <span class="font-bold text-blue-600"><?php echo Utils::formatPrice($frais_mensuel); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" 
                                         style="width: <?php echo min(100, ($frais_mensuel / max(1, $stats['revenue'] + $depenses_totales)) * 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-lg font-bold mb-4">Bilan</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Revenus totaux:</span>
                                <span class="font-bold text-green-600"><?php echo Utils::formatPrice($stats['revenue']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Dépenses totales:</span>
                                <span class="font-bold text-red-600"><?php echo Utils::formatPrice($depenses_totales); ?></span>
                            </div>
                            <div class="border-t pt-3">
                                <div class="flex justify-between text-lg">
                                    <span>Bénéfice net:</span>
                                    <span class="font-bold <?php echo $benefice >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo Utils::formatPrice($benefice); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 mt-2">
                                    <?php if ($benefice >= 0): ?>
                                        <i class="fas fa-arrow-up text-green-500 mr-1"></i> L'agence est rentable
                                    <?php else: ?>
                                        <i class="fas fa-arrow-down text-red-500 mr-1"></i> L'agence est déficitaire
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Réservations récentes -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Réservations récentes</h2>
                    <a href="?page=manage_reservations" 
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php
                $result = $conn->query("SELECT r.*, c.marque, c.model, cl.prenom as client_prenom, cl.nom as client_nom 
                                       FROM reservation r 
                                       JOIN car c ON r.car_id = c.id_car 
                                       JOIN client cl ON r.id_client = cl.id 
                                       WHERE r.company_id = {$admin->getCompanyId()} 
                                       ORDER BY r.created_at DESC LIMIT 5");
                
                if ($result->num_rows > 0):
                ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voiture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Période</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($res = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $res['client_prenom'] . ' ' . $res['client_nom']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $res['marque'] . ' ' . $res['model']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm"><?php echo $res['period']; ?> jours</div>
                                        <div class="text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($res['date_debut'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-600">
                                        <?php echo Utils::formatPrice($res['montant']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = [
                                            'en_attente' => 'bg-yellow-100 text-yellow-800',
                                            'confirmee' => 'bg-green-100 text-green-800',
                                            'annulee' => 'bg-red-100 text-red-800',
                                            'terminee' => 'bg-blue-100 text-blue-800'
                                        ][$res['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $res['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-8">Aucune réservation trouvée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php
// Suite du fichier index.php - Tableau de bord de l'administrateur

function showAdminDashboard() {
    global $currentUser, $conn;
    $admin = $currentUser;
    
    // Traitement des actions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Gestion des voitures
        if (isset($_POST['add_car'])) {
            $marque = Utils::sanitize($_POST['marque']);
            $model = Utils::sanitize($_POST['model']);
            $color = Utils::sanitize($_POST['color']);
            $annee = intval($_POST['annee']);
            $category = intval($_POST['category']);
            $prix_day = floatval($_POST['prix_day']);
            $status_voiture = Utils::sanitize($_POST['status_voiture']);
            $voiture_work = Utils::sanitize($_POST['voiture_work']);
            
            // Validation du prix selon la catégorie
            $min_price = [1 => 4000, 2 => 6000, 3 => 12000][$category] ?? 4000;
            $max_price = [1 => 6000, 2 => 12000, 3 => 20000][$category] ?? 20000;
            
            if ($prix_day < $min_price || $prix_day > $max_price) {
                $_SESSION['message'] = [
                    'text' => "Le prix doit être entre $min_price et $max_price DA/jour pour la catégorie $category",
                    'type' => 'error'
                ];
            } else {
                // Générer la plaque d'immatriculation
                $wilaya = rand(1, 69);
                $matricule = Utils::generateMatricule($category, $annee, $wilaya);
                
                $stmt = $conn->prepare("INSERT INTO car (company_id, marque, model, color, annee, matricule, category, prix_day, status_voiture, voiture_work) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssisidss", $admin->getCompanyId(), $marque, $model, $color, $annee, 
                                $matricule, $category, $prix_day, $status_voiture, $voiture_work);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = [
                        'text' => 'Voiture ajoutée avec succès.',
                        'type' => 'success'
                    ];
                } else {
                    $_SESSION['message'] = [
                        'text' => 'Erreur lors de l\'ajout : ' . $conn->error,
                        'type' => 'error'
                    ];
                }
            }
        }
    }
    
    // Récupérer les statistiques
    $stats = $admin->getCompanyStats();
    ?>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Tableau de bord Administrateur</h1>
        <p class="text-gray-600">Bienvenue, <?php echo $admin->getFullName(); ?></p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-cog text-purple-600 text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold"><?php echo $admin->getFullName(); ?></h3>
                    <p class="text-gray-500">Administrateur</p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo $admin->getEmail(); ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-money-bill text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo Utils::formatPrice($admin->getSalaire()); ?> / mois</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-building text-gray-400 w-6"></i>
                        <span class="ml-3">Agence #<?php echo $admin->getCompanyId(); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Menu admin -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold mb-4">Menu Administration</h3>
                <div class="space-y-3">
                    <a href="?page=manage_users" 
                       class="flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">
                        <i class="fas fa-users-cog mr-3"></i>
                        <span>Gérer utilisateurs</span>
                    </a>
                    <a href="?page=manage_cars" 
                       class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition">
                        <i class="fas fa-car mr-3"></i>
                        <span>Gérer voitures</span>
                    </a>
                    <a href="?page=statistics" 
                       class="flex items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition">
                        <i class="fas fa-chart-bar mr-3"></i>
                        <span>Statistiques</span>
                    </a>
                    <a href="?page=manage_reservations" 
                       class="flex items-center p-3 bg-yellow-50 text-yellow-700 rounded-lg hover:bg-yellow-100 transition">
                        <i class="fas fa-calendar-alt mr-3"></i>
                        <span>Gérer réservations</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="lg:col-span-3">
            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-money-bill-wave text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Revenu total</p>
                            <h3 class="text-2xl font-bold"><?php echo Utils::formatPrice($stats['revenue']); ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-check text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Réservations</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['reservations']; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-car text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Voitures</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['cars']; ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-users text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500">Clients</p>
                            <h3 class="text-2xl font-bold"><?php echo $stats['clients']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Analyse financière -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold mb-6">Analyse financière</h2>
                
                <?php
                // Calculer les dépenses
                $frais_result = $conn->query("SELECT frais_mensuel FROM company WHERE company_id = " . $admin->getCompanyId());
                $frais_mensuel = $frais_result->fetch_assoc()['frais_mensuel'] ?? 0;
                
                // Salaire des employés
                $salaires_result = $conn->query("SELECT SUM(salaire) as total_salaires FROM (
                    SELECT salaire FROM admin WHERE company_id = {$admin->getCompanyId()}
                    UNION ALL
                    SELECT salaire FROM agent WHERE company_id = {$admin->getCompanyId()}
                ) as salaries");
                $total_salaires = $salaires_result->fetch_assoc()['total_salaires'] ?? 0;
                
                // Dépenses totales (frais + salaires)
                $depenses_totales = $frais_mensuel + $total_salaires;
                
                // Bénéfice
                $benefice = $stats['revenue'] - $depenses_totales;
                ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-bold mb-4">Revenus vs Dépenses</h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">Revenus</span>
                                    <span class="font-bold text-green-600"><?php echo Utils::formatPrice($stats['revenue']); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" 
                                         style="width: <?php echo min(100, ($stats['revenue'] / max(1, $stats['revenue'] + $depenses_totales)) * 100); ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">Salaires employés</span>
                                    <span class="font-bold text-yellow-600"><?php echo Utils::formatPrice($total_salaires); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-500 h-2 rounded-full" 
                                         style="width: <?php echo min(100, ($total_salaires / max(1, $stats['revenue'] + $depenses_totales)) * 100); ?>%"></div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-600">Frais mensuels</span>
                                    <span class="font-bold text-blue-600"><?php echo Utils::formatPrice($frais_mensuel); ?></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-500 h-2 rounded-full" 
                                         style="width: <?php echo min(100, ($frais_mensuel / max(1, $stats['revenue'] + $depenses_totales)) * 100); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <h3 class="text-lg font-bold mb-4">Bilan</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Revenus totaux:</span>
                                <span class="font-bold text-green-600"><?php echo Utils::formatPrice($stats['revenue']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Dépenses totales:</span>
                                <span class="font-bold text-red-600"><?php echo Utils::formatPrice($depenses_totales); ?></span>
                            </div>
                            <div class="border-t pt-3">
                                <div class="flex justify-between text-lg">
                                    <span>Bénéfice net:</span>
                                    <span class="font-bold <?php echo $benefice >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo Utils::formatPrice($benefice); ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 mt-2">
                                    <?php if ($benefice >= 0): ?>
                                        <i class="fas fa-arrow-up text-green-500 mr-1"></i> L'agence est rentable
                                    <?php else: ?>
                                        <i class="fas fa-arrow-down text-red-500 mr-1"></i> L'agence est déficitaire
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Réservations récentes -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Réservations récentes</h2>
                    <a href="?page=manage_reservations" 
                       class="text-blue-600 hover:text-blue-800 font-medium">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <?php
                $result = $conn->query("SELECT r.*, c.marque, c.model, cl.prenom as client_prenom, cl.nom as client_nom 
                                       FROM reservation r 
                                       JOIN car c ON r.car_id = c.id_car 
                                       JOIN client cl ON r.id_client = cl.id 
                                       WHERE r.company_id = {$admin->getCompanyId()} 
                                       ORDER BY r.created_at DESC LIMIT 5");
                
                if ($result->num_rows > 0):
                ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Voiture</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Période</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($res = $result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $res['client_prenom'] . ' ' . $res['client_nom']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $res['marque'] . ' ' . $res['model']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm"><?php echo $res['period']; ?> jours</div>
                                        <div class="text-sm text-gray-500"><?php echo date('d/m/Y', strtotime($res['date_debut'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-600">
                                        <?php echo Utils::formatPrice($res['montant']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = [
                                            'en_attente' => 'bg-yellow-100 text-yellow-800',
                                            'confirmee' => 'bg-green-100 text-green-800',
                                            'annulee' => 'bg-red-100 text-red-800',
                                            'terminee' => 'bg-blue-100 text-blue-800'
                                        ][$res['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $res['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-8">Aucune réservation trouvée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
?>

<?php
// Suite du fichier index.php - Tableau de bord du propriétaire

function showOwnerDashboard() {
    global $currentUser, $conn;
    
    // Traitement des actions du propriétaire
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_company'])) {
            $c_name = Utils::sanitize($_POST['c_name']);
            $special_code = Utils::sanitize($_POST['special_code']);
            $frais_mensuel = floatval($_POST['frais_mensuel']);
            
            // Créer la compagnie
            $stmt = $conn->prepare("INSERT INTO company (c_name, special_code, frais_mensuel) VALUES (?, ?, ?)");
            $stmt->bind_param("ssd", $c_name, $special_code, $frais_mensuel);
            
            if ($stmt->execute()) {
                $company_id = $conn->insert_id;
                $_SESSION['message'] = [
                    'text' => 'Compagnie créée avec succès.',
                    'type' => 'success'
                ];
                
                // Créer l'administrateur pour cette compagnie
                if (isset($_POST['create_admin']) && $_POST['create_admin'] == '1') {
                    $admin_nom = Utils::sanitize($_POST['admin_nom']);
                    $admin_prenom = Utils::sanitize($_POST['admin_prenom']);
                    $admin_email = Utils::sanitize($_POST['admin_email']);
                    $admin_password = password_hash(Utils::sanitize($_POST['admin_password']), PASSWORD_DEFAULT);
                    $admin_age = intval($_POST['admin_age']);
                    
                    $stmt = $conn->prepare("INSERT INTO admin (nom, prenom, email, password, age, company_id, salaire) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $salaire = rand(50000, 120000);
                    $stmt->bind_param("ssssiid", $admin_nom, $admin_prenom, $admin_email, $admin_password, $admin_age, $company_id, $salaire);
                    
                    if ($stmt->execute()) {
                        $admin_id = $conn->insert_id;
                        $conn->query("UPDATE company SET id_admin = $admin_id WHERE company_id = $company_id");
                        $_SESSION['message']['text'] .= ' Administrateur créé avec succès.';
                    }
                }
            } else {
                $_SESSION['message'] = [
                    'text' => 'Erreur lors de la création : ' . $conn->error,
                    'type' => 'error'
                ];
            }
        } elseif (isset($_POST['update_company'])) {
            $company_id = intval($_POST['company_id']);
            $c_name = Utils::sanitize($_POST['c_name']);
            $special_code = Utils::sanitize($_POST['special_code']);
            $frais_mensuel = floatval($_POST['frais_mensuel']);
            
            $stmt = $conn->prepare("UPDATE company SET c_name = ?, special_code = ?, frais_mensuel = ? WHERE company_id = ?");
            $stmt->bind_param("ssdi", $c_name, $special_code, $frais_mensuel, $company_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = [
                    'text' => 'Compagnie mise à jour avec succès.',
                    'type' => 'success'
                ];
            }
        } elseif (isset($_POST['delete_company'])) {
            $company_id = intval($_POST['company_id']);
            
            // Vérifier s'il y a des données associées
            $check_result = $conn->query("SELECT COUNT(*) as count FROM reservation WHERE company_id = $company_id");
            $count = $check_result->fetch_assoc()['count'];
            
            if ($count > 0) {
                $_SESSION['message'] = [
                    'text' => 'Impossible de supprimer : des réservations sont associées à cette compagnie.',
                    'type' => 'error'
                ];
            } else {
                $conn->query("DELETE FROM car WHERE company_id = $company_id");
                $conn->query("DELETE FROM client WHERE company_id = $company_id");
                $conn->query("DELETE FROM agent WHERE company_id = $company_id");
                $conn->query("DELETE FROM admin WHERE company_id = $company_id");
                $conn->query("DELETE FROM company WHERE company_id = $company_id");
                
                $_SESSION['message'] = [
                    'text' => 'Compagnie supprimée avec succès.',
                    'type' => 'success'
                ];
            }
        }
    }
    
    // Récupérer toutes les compagnies
    $companies_result = $conn->query("SELECT c.*, a.email as admin_email, a.nom as admin_nom, a.prenom as admin_prenom 
                                     FROM company c 
                                     LEFT JOIN admin a ON c.id_admin = a.id 
                                     ORDER BY c.company_id");
    ?>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Tableau de bord Propriétaire</h1>
        <p class="text-gray-600">Bienvenue, <?php echo $currentUser->getFullName(); ?> (Propriétaire du site)</p>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-crown text-red-600 text-4xl"></i>
                    </div>
                    <h3 class="text-xl font-bold"><?php echo $currentUser->getFullName(); ?></h3>
                    <p class="text-gray-500">Propriétaire</p>
                </div>
                
                <div class="space-y-4">
                    <div class="flex items-center">
                        <i class="fas fa-envelope text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo $currentUser->getEmail(); ?></span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-building text-gray-400 w-6"></i>
                        <span class="ml-3"><?php echo $companies_result->num_rows; ?> compagnies</span>
                    </div>
                </div>
            </div>
            
            <!-- Actions propriétaire -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-bold mb-4">Actions Propriétaire</h3>
                <div class="space-y-3">
                    <button onclick="document.getElementById('addCompanyModal').classList.remove('hidden')"
                            class="w-full flex items-center p-3 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition">
                        <i class="fas fa-plus-circle mr-3"></i>
                        <span>Ajouter compagnie</span>
                    </button>
                    <a href="#" onclick="showNotification('Fonctionnalité en développement', 'info')"
                       class="flex items-center p-3 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition">
                        <i class="fas fa-chart-line mr-3"></i>
                        <span>Statistiques globales</span>
                    </a>
                    <a href="#" onclick="showNotification('Fonctionnalité en développement', 'info')"
                       class="flex items-center p-3 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition">
                        <i class="fas fa-file-invoice-dollar mr-3"></i>
                        <span>Revenus totaux</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="lg:col-span-3">
            <!-- Liste des compagnies -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Compagnies de location</h2>
                    <button onclick="document.getElementById('addCompanyModal').classList.remove('hidden')"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-2"></i> Ajouter compagnie
                    </button>
                </div>
                
                <?php if ($companies_result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code spécial</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frais mensuel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Administrateur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($company = $companies_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $company['company_id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium"><?php echo $company['c_name']; ?></div>
                                        <div class="text-sm text-gray-500">Créée le <?php echo date('d/m/Y', strtotime($company['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php echo $company['special_code']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                        <?php echo Utils::formatPrice($company['frais_mensuel']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php if ($company['admin_email']): ?>
                                            <div><?php echo $company['admin_prenom'] . ' ' . $company['admin_nom']; ?></div>
                                            <div class="text-gray-500"><?php echo $company['admin_email']; ?></div>
                                        <?php else: ?>
                                            <span class="text-yellow-600">Aucun administrateur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex space-x-2">
                                            <button onclick="editCompany(<?php echo $company['company_id']; ?>, '<?php echo $company['c_name']; ?>', '<?php echo $company['special_code']; ?>', <?php echo $company['frais_mensuel']; ?>)"
                                                    class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                                Modifier
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Supprimer cette compagnie et toutes ses données ?');" class="inline">
                                                <input type="hidden" name="delete_company" value="1">
                                                <input type="hidden" name="company_id" value="<?php echo $company['company_id']; ?>">
                                                <button type="submit" 
                                                        class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                                                    Supprimer
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
                    <p class="text-gray-600 text-center py-8">Aucune compagnie trouvée.</p>
                <?php endif; ?>
            </div>
            
            <!-- Statistiques globales -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-6">Statistiques globales</h2>
                
                <?php
                // Statistiques globales
                $global_stats = [
                    'total_revenue' => $conn->query("SELECT SUM(montant) as total FROM reservation WHERE status = 'confirmee'")->fetch_assoc()['total'] ?? 0,
                    'total_reservations' => $conn->query("SELECT COUNT(*) as total FROM reservation")->fetch_assoc()['total'] ?? 0,
                    'total_cars' => $conn->query("SELECT COUNT(*) as total FROM car")->fetch_assoc()['total'] ?? 0,
                    'total_clients' => $conn->query("SELECT COUNT(*) as total FROM client")->fetch_assoc()['total'] ?? 0,
                    'total_agents' => $conn->query("SELECT COUNT(*) as total FROM agent")->fetch_assoc()['total'] ?? 0,
                    'total_admins' => $conn->query("SELECT COUNT(*) as total FROM admin WHERE company_id IS NOT NULL")->fetch_assoc()['total'] ?? 0
                ];
                ?>
                
                <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                    <div class="text-center p-6 bg-blue-50 rounded-lg">
                        <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo Utils::formatPrice($global_stats['total_revenue']); ?></div>
                        <p class="text-gray-600">Revenu total</p>
                    </div>
                    
                    <div class="text-center p-6 bg-green-50 rounded-lg">
                        <div class="text-3xl font-bold text-green-600 mb-2"><?php echo $global_stats['total_reservations']; ?></div>
                        <p class="text-gray-600">Réservations totales</p>
                    </div>
                    
                    <div class="text-center p-6 bg-purple-50 rounded-lg">
                        <div class="text-3xl font-bold text-purple-600 mb-2"><?php echo $global_stats['total_cars']; ?></div>
                        <p class="text-gray-600">Voitures totales</p>
                    </div>
                    
                    <div class="text-center p-6 bg-yellow-50 rounded-lg">
                        <div class="text-3xl font-bold text-yellow-600 mb-2"><?php echo $global_stats['total_clients']; ?></div>
                        <p class="text-gray-600">Clients totaux</p>
                    </div>
                    
                    <div class="text-center p-6 bg-red-50 rounded-lg">
                        <div class="text-3xl font-bold text-red-600 mb-2"><?php echo $global_stats['total_agents']; ?></div>
                        <p class="text-gray-600">Agents totaux</p>
                    </div>
                    
                    <div class="text-center p-6 bg-indigo-50 rounded-lg">
                        <div class="text-3xl font-bold text-indigo-600 mb-2"><?php echo $global_stats['total_admins']; ?></div>
                        <p class="text-gray-600">Administrateurs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajout Compagnie -->
    <div id="addCompanyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Ajouter une nouvelle compagnie</h3>
                <button onclick="document.getElementById('addCompanyModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-2xl">
                    &times;
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="add_company" value="1">
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="c_name">Nom de la compagnie</label>
                    <input type="text" name="c_name" id="c_name" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Ex: Location Premium">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2" for="special_code">Code spécial</label>
                        <input type="text" name="special_code" id="special_code" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex: LPREM001">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="frais_mensuel">Frais mensuels (DA)</label>
                        <input type="number" name="frais_mensuel" id="frais_mensuel" required min="20000" max="200000"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Entre 20 000 et 200 000 DA" value="50000">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="create_admin" id="create_admin" value="1" checked
                               class="mr-2 h-5 w-5 text-blue-600 rounded focus:ring-blue-500">
                        <span class="text-gray-700">Créer un administrateur pour cette compagnie</span>
                    </label>
                </div>
                
                <div id="adminFields" class="border-t pt-6">
                    <h4 class="text-lg font-bold mb-4">Informations de l'administrateur</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-gray-700 mb-2" for="admin_nom">Nom</label>
                            <input type="text" name="admin_nom" id="admin_nom"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Nom de l'admin">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="admin_prenom">Prénom</label>
                            <input type="text" name="admin_prenom" id="admin_prenom"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Prénom de l'admin">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 mb-2" for="admin_email">Email</label>
                        <input type="email" name="admin_email" id="admin_email"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="admin@compagnie.com">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-gray-700 mb-2" for="admin_password">Mot de passe</label>
                            <input type="password" name="admin_password" id="admin_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Mot de passe">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="admin_age">Âge</label>
                            <input type="number" name="admin_age" id="admin_age" min="24"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Âge (min 24)" value="30">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="document.getElementById('addCompanyModal').classList.add('hidden')"
                            class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Créer la compagnie
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Modification Compagnie -->
    <div id="editCompanyModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Modifier la compagnie</h3>
                <button onclick="document.getElementById('editCompanyModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-2xl">
                    &times;
                </button>
            </div>
            
            <form method="POST" id="editCompanyForm">
                <input type="hidden" name="update_company" value="1">
                <input type="hidden" name="company_id" id="edit_company_id">
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="edit_c_name">Nom de la compagnie</label>
                    <input type="text" name="c_name" id="edit_c_name" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="edit_special_code">Code spécial</label>
                    <input type="text" name="special_code" id="edit_special_code" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-8">
                    <label class="block text-gray-700 mb-2" for="edit_frais_mensuel">Frais mensuels (DA)</label>
                    <input type="number" name="frais_mensuel" id="edit_frais_mensuel" required min="20000" max="200000"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="document.getElementById('editCompanyModal').classList.add('hidden')"
                            class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion de l'affichage/masquage des champs admin
        document.getElementById('create_admin').addEventListener('change', function() {
            document.getElementById('adminFields').style.display = this.checked ? 'block' : 'none';
        });
        
        // Fonction pour éditer une compagnie
        function editCompany(id, name, code, frais) {
            document.getElementById('edit_company_id').value = id;
            document.getElementById('edit_c_name').value = name;
            document.getElementById('edit_special_code').value = code;
            document.getElementById('edit_frais_mensuel').value = frais;
            document.getElementById('editCompanyModal').classList.remove('hidden');
        }
    </script>
    <?php
}
?>

<?php
// Suite du fichier index.php - Pages de réservation, paiement et gestion

// Page des voitures
function showCarsPage() {
    global $currentUser, $conn;
    
    // Filtres
    $category = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
    $max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 20000;
    $status = isset($_GET['status']) ? Utils::sanitize($_GET['status']) : 'disponible';
    
    // Construire la requête
    $query = "SELECT * FROM car WHERE company_id = " . $currentUser->getCompanyId();
    
    if ($category > 0) {
        $query .= " AND category = $category";
    }
    
    if ($min_price > 0) {
        $query .= " AND prix_day >= $min_price";
    }
    
    if ($max_price > 0) {
        $query .= " AND prix_day <= $max_price";
    }
    
    if ($status && $status != 'all') {
        $query .= " AND voiture_work = '$status'";
    }
    
    $query .= " ORDER BY category, prix_day";
    
    $result = $conn->query($query);
    ?>
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Notre flotte de voitures</h1>
        <p class="text-gray-600">Choisissez la voiture qui correspond à vos besoins</p>
    </div>
    
    <!-- Filtres -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Filtres</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="page" value="cars">
            
            <div>
                <label class="block text-gray-700 mb-2">Catégorie</label>
                <select name="category" class="w-full px-4 py-2 border rounded-lg">
                    <option value="0">Toutes catégories</option>
                    <option value="1" <?php echo $category == 1 ? 'selected' : ''; ?>>Catégorie 1 (4000-6000 DA/j)</option>
                    <option value="2" <?php echo $category == 2 ? 'selected' : ''; ?>>Catégorie 2 (6000-12000 DA/j)</option>
                    <option value="3" <?php echo $category == 3 ? 'selected' : ''; ?>>Catégorie 3 (12000-20000 DA/j)</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 mb-2">Prix min (DA/j)</label>
                <input type="number" name="min_price" value="<?php echo $min_price; ?>" 
                       class="w-full px-4 py-2 border rounded-lg" min="0" max="20000">
            </div>
            
            <div>
                <label class="block text-gray-700 mb-2">Prix max (DA/j)</label>
                <input type="number" name="max_price" value="<?php echo $max_price; ?>" 
                       class="w-full px-4 py-2 border rounded-lg" min="0" max="20000">
            </div>
            
            <div>
                <label class="block text-gray-700 mb-2">Disponibilité</label>
                <select name="status" class="w-full px-4 py-2 border rounded-lg">
                    <option value="all">Tous</option>
                    <option value="disponible" <?php echo $status == 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                    <option value="non_disponible" <?php echo $status == 'non_disponible' ? 'selected' : ''; ?>>Non disponible</option>
                </select>
            </div>
            
            <div class="md:col-span-4 flex space-x-4">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Appliquer les filtres
                </button>
                <a href="?page=cars" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
    
    <!-- Liste des voitures -->
    <?php if ($result->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while ($car = $result->fetch_assoc()): ?>
                <div class="car-card bg-white shadow-lg hover:shadow-xl transition-shadow">
                    <div class="relative">
                        <div class="absolute top-4 right-4">
                            <span class="px-3 py-1 rounded-full text-sm font-medium
                                <?php echo $car['category'] == 1 ? 'bg-green-100 text-green-800' : 
                                       ($car['category'] == 2 ? 'bg-yellow-100 text-yellow-800' : 
                                       'bg-red-100 text-red-800'); ?>">
                                Catégorie <?php echo $car['category']; ?>
                            </span>
                        </div>
                        
                        <div class="p-6">
                            <h3 class="text-2xl font-bold mb-2"><?php echo $car['marque'] . ' ' . $car['model']; ?></h3>
                            <div class="flex items-center text-gray-500 mb-4">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span><?php echo $car['annee']; ?></span>
                                <i class="fas fa-palette ml-4 mr-2"></i>
                                <span><?php echo $car['color']; ?></span>
                            </div>
                            
                            <div class="mb-6">
                                <p class="text-3xl font-bold text-blue-600">
                                    <?php echo Utils::formatPrice($car['prix_day']); ?> 
                                    <span class="text-sm font-normal text-gray-500">/ jour</span>
                                </p>
                                <p class="text-gray-600 text-sm mt-1">
                                    <i class="fas fa-car mr-1"></i> Immatriculation: <?php echo $car['matricule']; ?>
                                </p>
                            </div>
                            
                            <div class="flex justify-between items-center mb-4">
                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                    <?php echo $car['status_voiture'] == 'excellent' ? 'bg-blue-100 text-blue-800' : 
                                           ($car['status_voiture'] == 'entretien' ? 'bg-yellow-100 text-yellow-800' : 
                                           'bg-red-100 text-red-800'); ?>">
                                    <?php echo ucfirst($car['status_voiture']); ?>
                                </span>
                                
                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                    <?php echo $car['voiture_work'] == 'disponible' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $car['voiture_work'] == 'disponible' ? 'Disponible' : 'Non disponible'; ?>
                                </span>
                            </div>
                            
                            <?php if ($currentUser->getRole() == 'client' && $car['voiture_work'] == 'disponible'): ?>
                                <div class="flex space-x-2">
                                    <a href="?page=reservation&car_id=<?php echo $car['id_car']; ?>" 
                                       class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-center">
                                        <i class="fas fa-calendar-plus mr-2"></i> Réserver
                                    </a>
                                    <a href="?page=payment&car_id=<?php echo $car['id_car']; ?>" 
                                       class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-center">
                                        <i class="fas fa-credit-card mr-2"></i> Payer
                                    </a>
                                </div>
                            <?php elseif ($currentUser->getRole() == 'admin'): ?>
                                <div class="flex space-x-2">
                                    <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                            class="flex-1 px-4 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200">
                                        Modifier
                                    </button>
                                    <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                            class="flex-1 px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200">
                                        Supprimer
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-12">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-car text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold mb-2">Aucune voiture trouvée</h3>
            <p class="text-gray-600">Aucune voiture ne correspond à vos critères de recherche.</p>
        </div>
    <?php endif; ?>
    <?php
}

// Page de réservation
function showReservationPage() {
    global $currentUser, $conn;
    
    $car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
    
    if (!$car_id) {
        echo '<div class="text-center py-12">';
        echo '<h2 class="text-3xl font-bold mb-4">Aucune voiture sélectionnée</h2>';
        echo '<a href="?page=cars" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">';
        echo 'Voir les voitures disponibles</a>';
        echo '</div>';
        return;
    }
    
    // Récupérer les informations de la voiture
    $car_result = $conn->query("SELECT * FROM car WHERE id_car = $car_id");
    $car = $car_result->fetch_assoc();
    
    if (!$car) {
        echo '<div class="text-center py-12">';
        echo '<h2 class="text-3xl font-bold mb-4">Voiture non trouvée</h2>';
        echo '</div>';
        return;
    }
    
    // Traitement du formulaire de réservation
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reserver'])) {
        $date_debut = Utils::sanitize($_POST['date_debut']);
        $date_fin = Utils::sanitize($_POST['date_fin']);
        $period = intval($_POST['period']);
        $montant = $car['prix_day'] * $period;
        
        // Vérifier la disponibilité
        $check_stmt = $conn->prepare("SELECT id_reservation FROM reservation 
                                     WHERE car_id = ? AND status NOT IN ('annulee', 'terminee')
                                     AND ((date_debut BETWEEN ? AND ?) OR (date_fin BETWEEN ? AND ?))");
        $check_stmt->bind_param("issss", $car_id, $date_debut, $date_fin, $date_debut, $date_fin);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $_SESSION['message'] = [
                'text' => 'La voiture n\'est pas disponible pour ces dates.',
                'type' => 'error'
            ];
        } else {
            // Créer la réservation
            $stmt = $conn->prepare("INSERT INTO reservation (id_client, company_id, car_id, date_debut, date_fin, period, montant, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')");
            $stmt->bind_param("iiissid", $currentUser->getId(), $currentUser->getCompanyId(), $car_id, 
                            $date_debut, $date_fin, $period, $montant);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = [
                    'text' => 'Réservation effectuée avec succès !',
                    'type' => 'success'
                ];
                
                // Mettre à jour le statut du client
                $conn->query("UPDATE client SET status = 'reserve' WHERE id = " . $currentUser->getId());
                
                header('Location: ?page=client_dashboard');
                exit();
            }
        }
    }
    ?>
    
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Réserver une voiture</h1>
            <p class="text-gray-600">Complétez les informations pour votre réservation</p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Détails de la voiture -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                    <h3 class="text-xl font-bold mb-4">Voiture sélectionnée</h3>
                    
                    <div class="mb-6">
                        <div class="w-full h-48 bg-gray-100 rounded-lg flex items-center justify-center mb-4">
                            <i class="fas fa-car text-gray-400 text-6xl"></i>
                        </div>
                        
                        <h4 class="text-2xl font-bold"><?php echo $car['marque'] . ' ' . $car['model']; ?></h4>
                        <div class="text-gray-500 mb-2"><?php echo $car['annee']; ?> • <?php echo $car['color']; ?></div>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Immatriculation:</span>
                                <span class="font-medium"><?php echo $car['matricule']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Catégorie:</span>
                                <span class="font-medium"><?php echo $car['category']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">État:</span>
                                <span class="font-medium"><?php echo ucfirst($car['status_voiture']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-bold">Prix par jour:</span>
                            <span class="text-2xl font-bold text-blue-600"><?php echo Utils::formatPrice($car['prix_day']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire de réservation -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" id="reservationForm">
                        <input type="hidden" name="reserver" value="1">
                        
                        <div class="mb-8">
                            <h3 class="text-xl font-bold mb-4">Informations de réservation</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-gray-700 mb-2" for="date_debut">Date de début</label>
                                    <input type="date" name="date_debut" id="date_debut" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2" for="date_fin">Date de fin</label>
                                    <input type="date" name="date_fin" id="date_fin" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2" for="period">Durée (jours)</label>
                                <input type="number" name="period" id="period" required min="1" max="365"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Nombre de jours" value="1">
                            </div>
                            
                            <!-- Calcul du prix -->
                            <div class="bg-gray-50 p-6 rounded-lg mb-8">
                                <h4 class="font-bold mb-4">Calcul du montant</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span>Prix par jour:</span>
                                        <span class="font-medium"><?php echo Utils::formatPrice($car['prix_day']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Nombre de jours:</span>
                                        <span id="joursCount" class="font-medium">1</span>
                                    </div>
                                    <div class="border-t pt-3">
                                        <div class="flex justify-between text-lg font-bold">
                                            <span>Total:</span>
                                            <span id="totalPrice" class="text-blue-600"><?php echo Utils::formatPrice($car['prix_day']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label class="flex items-center">
                                    <input type="checkbox" required
                                           class="mr-2 h-5 w-5 text-blue-600 rounded focus:ring-blue-500">
                                    <span class="text-gray-700">J'accepte les <a href="#" class="text-blue-600 hover:underline">conditions générales</a> de location</span>
                                </label>
                            </div>
                            
                            <div>
                                <button type="submit" 
                                        class="w-full btn-primary py-4 rounded-lg font-bold text-lg">
                                    <i class="fas fa-calendar-check mr-2"></i> Confirmer la réservation
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Informations importantes -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mt-6">
                    <h4 class="text-lg font-bold mb-2 text-yellow-800">
                        <i class="fas fa-exclamation-circle mr-2"></i> Informations importantes
                    </h4>
                    <ul class="text-yellow-700 space-y-2">
                        <li class="flex items-start">
                            <i class="fas fa-check mt-1 mr-2 text-sm"></i>
                            <span>L'âge minimum pour louer est de 24 ans</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check mt-1 mr-2 text-sm"></i>
                            <span>Une caution peut être demandée lors de la remise du véhicule</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-check mt-1 mr-2 text-sm"></i>
                            <span>Annulation gratuite jusqu'à 48h avant le début de la location</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Calcul dynamique du prix
        const prixParJour = <?php echo $car['prix_day']; ?>;
        
        function updatePrice() {
            const jours = parseInt(document.getElementById('period').value) || 1;
            const total = prixParJour * jours;
            
            document.getElementById('joursCount').textContent = jours;
            document.getElementById('totalPrice').textContent = 
                new Intl.NumberFormat('fr-DZ', {style:'currency', currency:'DZD', maximumFractionDigits:0})
                .format(total).replace('DZD', 'DA');
        }
        
        document.getElementById('period').addEventListener('input', updatePrice);
        document.getElementById('date_debut').addEventListener('change', function() {
            document.getElementById('date_fin').min = this.value;
        });
        
        document.getElementById('date_fin').addEventListener('change', function() {
            const debut = new Date(document.getElementById('date_debut').value);
            const fin = new Date(this.value);
            const diffTime = Math.abs(fin - debut);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            
            if (diffDays > 0) {
                document.getElementById('period').value = diffDays;
                updatePrice();
            }
        });
        
        // Initialiser le calcul
        updatePrice();
    </script>
    <?php
}

// Page de paiement
function showPaymentPage() {
    global $currentUser, $conn;
    
    $car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
    
    if (!$car_id) {
        echo '<div class="text-center py-12">';
        echo '<h2 class="text-3xl font-bold mb-4">Aucune voiture sélectionnée</h2>';
        echo '<a href="?page=cars" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">';
        echo 'Voir les voitures disponibles</a>';
        echo '</div>';
        return;
    }
    
    // Récupérer les informations de la voiture
    $car_result = $conn->query("SELECT * FROM car WHERE id_car = $car_id");
    $car = $car_result->fetch_assoc();
    
    if (!$car) {
        echo '<div class="text-center py-12">';
        echo '<h2 class="text-3xl font-bold mb-4">Voiture non trouvée</h2>';
        echo '</div>';
        return;
    }
    
    // Traitement du paiement
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payer'])) {
        $numero_carte = str_replace(' ', '', Utils::sanitize($_POST['numero_carte']));
        $code_carte = Utils::sanitize($_POST['code_carte']);
        $period = intval($_POST['period']);
        $montant = $car['prix_day'] * $period;
        
        // Validation simple
        if (strlen($numero_carte) !== 16 || !is_numeric($numero_carte)) {
            $_SESSION['message'] = [
                'text' => 'Le numéro de carte doit contenir 16 chiffres.',
                'type' => 'error'
            ];
        } elseif (strlen($code_carte) !== 3 || !is_numeric($code_carte)) {
            $_SESSION['message'] = [
                'text' => 'Le code de sécurité doit contenir 3 chiffres.',
                'type' => 'error'
            ];
        } else {
            // Créer d'abord une réservation
            $date_debut = date('Y-m-d');
            $date_fin = date('Y-m-d', strtotime("+$period days"));
            
            $stmt = $conn->prepare("INSERT INTO reservation (id_client, company_id, car_id, date_debut, date_fin, period, montant, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmee')");
            $stmt->bind_param("iiissid", $currentUser->getId(), $currentUser->getCompanyId(), $car_id, 
                            $date_debut, $date_fin, $period, $montant);
            
            if ($stmt->execute()) {
                $reservation_id = $conn->insert_id;
                
                // Créer le paiement
                $stmt = $conn->prepare("INSERT INTO payment (id_reservation, montant, numero_carte, code_carte, status) 
                                       VALUES (?, ?, ?, ?, 'paye')");
                $stmt->bind_param("idss", $reservation_id, $montant, $numero_carte, $code_carte);
                
                if ($stmt->execute()) {
                    // Mettre à jour le statut du client
                    $conn->query("UPDATE client SET status = 'payer' WHERE id = " . $currentUser->getId());
                    
                    // Mettre à jour la disponibilité de la voiture
                    $conn->query("UPDATE car SET voiture_work = 'non_disponible' WHERE id_car = $car_id");
                    
                    $_SESSION['message'] = [
                        'text' => 'Paiement effectué avec succès ! Votre réservation est confirmée.',
                        'type' => 'success'
                    ];
                    
                    header('Location: ?page=client_dashboard');
                    exit();
                }
            }
        }
    }
    ?>
    
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Paiement sécurisé</h1>
            <p class="text-gray-600">Finalisez votre réservation en effectuant le paiement</p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Récapitulatif -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6 sticky top-8">
                    <h3 class="text-xl font-bold mb-4">Récapitulatif</h3>
                    
                    <div class="mb-6">
                        <h4 class="text-lg font-bold mb-2"><?php echo $car['marque'] . ' ' . $car['model']; ?></h4>
                        <div class="text-gray-500 mb-4"><?php echo $car['annee']; ?> • <?php echo $car['color']; ?></div>
                        
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Catégorie:</span>
                                <span class="font-medium"><?php echo $car['category']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Immatriculation:</span>
                                <span class="font-medium"><?php echo $car['matricule']; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Prix par jour:</span>
                                <span class="font-medium"><?php echo Utils::formatPrice($car['prix_day']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Nombre de jours:</span>
                                <span id="paymentJours" class="font-medium">1</span>
                            </div>
                            <div class="border-t pt-3">
                                <div class="flex justify-between text-lg font-bold">
                                    <span>Total à payer:</span>
                                    <span id="paymentTotal" class="text-green-600"><?php echo Utils::formatPrice($car['prix_day']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sécurité -->
                <div class="bg-green-50 border border-green-200 rounded-xl p-6 mt-6">
                    <h4 class="text-lg font-bold mb-2 text-green-800">
                        <i class="fas fa-shield-alt mr-2"></i> Paiement sécurisé
                    </h4>
                    <p class="text-green-700 text-sm">
                        Vos informations de paiement sont cryptées et sécurisées. Nous ne stockons pas les détails de votre carte.
                    </p>
                </div>
            </div>
            
            <!-- Formulaire de paiement -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="payer" value="1">
                        
                        <div class="mb-8">
                            <h3 class="text-xl font-bold mb-6">Informations de paiement</h3>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2" for="period">Durée de location (jours)</label>
                                <input type="number" name="period" id="paymentPeriod" required min="1" max="365"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                       value="1">
                            </div>
                            
                            <div class="mb-8">
                                <label class="block text-gray-700 mb-2" for="numero_carte">Numéro de carte</label>
                                <div class="relative">
                                    <input type="text" name="numero_carte" id="numero_carte" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="1234 5678 9012 3456"
                                           maxlength="19"
                                           oninput="formatCardNumber(this)">
                                    <div class="absolute right-3 top-3">
                                        <i class="fab fa-cc-visa text-blue-600 text-2xl mr-2"></i>
                                        <i class="fab fa-cc-mastercard text-red-600 text-2xl"></i>
                                    </div>
                                </div>
                                <p class="text-gray-500 text-sm mt-1">16 chiffres sans espace</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <div>
                                    <label class="block text-gray-700 mb-2" for="code_carte">Code de sécurité (CVV)</label>
                                    <input type="text" name="code_carte" id="code_carte" required
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="123"
                                           maxlength="3">
                                    <p class="text-gray-500 text-sm mt-1">3 chiffres au dos de la carte</p>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2" for="expiry_date">Date d'expiration (MM/AA)</label>
                                    <input type="text" id="expiry_date"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="MM/AA"
                                           maxlength="5"
                                           oninput="formatExpiryDate(this)">
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label class="flex items-center">
                                    <input type="checkbox" required
                                           class="mr-2 h-5 w-5 text-blue-600 rounded focus:ring-blue-500">
                                    <span class="text-gray-700">J'autorise le prélèvement du montant indiqué</span>
                                </label>
                            </div>
                            
                            <div>
                                <button type="submit" 
                                        class="w-full btn-primary py-4 rounded-lg font-bold text-lg">
                                    <i class="fas fa-lock mr-2"></i> Payer maintenant
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Calcul dynamique du prix
        const paymentPrixParJour = <?php echo $car['prix_day']; ?>;
        
        function updatePaymentPrice() {
            const jours = parseInt(document.getElementById('paymentPeriod').value) || 1;
            const total = paymentPrixParJour * jours;
            
            document.getElementById('paymentJours').textContent = jours;
            document.getElementById('paymentTotal').textContent = 
                new Intl.NumberFormat('fr-DZ', {style:'currency', currency:'DZD', maximumFractionDigits:0})
                .format(total).replace('DZD', 'DA');
        }
        
        document.getElementById('paymentPeriod').addEventListener('input', updatePaymentPrice);
        
        // Formatage du numéro de carte
        function formatCardNumber(input) {
            let value = input.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formatted = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            
            input.value = formatted.substring(0, 19);
        }
        
        // Formatage de la date d'expiration
        function formatExpiryDate(input) {
            let value = input.value.replace(/[^0-9]/gi, '');
            
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            
            input.value = value.substring(0, 5);
        }
        
        // Initialiser le calcul
        updatePaymentPrice();
    </script>
    <?php
}
?>


<?php
// Suite du fichier index.php - Pages de gestion et statistiques

// Page de gestion des utilisateurs
function showManageUsersPage() {
    global $currentUser, $conn;
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'view';
    ?>
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Gestion des utilisateurs</h1>
        <div class="flex space-x-4">
            <a href="?page=manage_users&action=view_clients" 
               class="px-4 py-2 <?php echo $action == 'view_clients' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg">
               Clients
            </a>
            <?php if ($currentUser->getRole() == 'admin'): ?>
                <a href="?page=manage_users&action=view_agents" 
                   class="px-4 py-2 <?php echo $action == 'view_agents' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-lg">
                   Agents
                </a>
            <?php endif; ?>
            <a href="?page=register" 
               class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
               <i class="fas fa-user-plus mr-2"></i> Ajouter utilisateur
            </a>
        </div>
    </div>
    
    <?php if ($action == 'view_clients'): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Liste des clients</h2>
            
            <?php
            $result = $conn->query("SELECT * FROM client WHERE company_id = " . $currentUser->getCompanyId() . " ORDER BY nom, prenom");
            
            if ($result->num_rows > 0):
            ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom & Prénom</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Âge</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $user['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium"><?php echo $user['prenom'] . ' ' . $user['nom']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $user['age']; ?> ans
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $user['numero_tlfn']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $user['email']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $status_class = [
                                        'payer' => 'bg-green-100 text-green-800',
                                        'reserve' => 'bg-yellow-100 text-yellow-800',
                                        'annuler' => 'bg-red-100 text-red-800',
                                        'non_reserve' => 'bg-gray-100 text-gray-800'
                                    ][$user['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex space-x-2">
                                        <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                                class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                            Modifier
                                        </button>
                                        <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                                class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                                            Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-gray-600 text-center py-8">Aucun client trouvé.</p>
            <?php endif; ?>
        </div>
    
    <?php elseif ($action == 'view_agents' && $currentUser->getRole() == 'admin'): ?>
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Liste des agents</h2>
            
            <?php
            $result = $conn->query("SELECT * FROM agent WHERE company_id = " . $currentUser->getCompanyId() . " ORDER BY nom, prenom");
            
            if ($result->num_rows > 0):
            ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nom & Prénom</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Âge</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Téléphone</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salaire</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $user['id']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-medium"><?php echo $user['prenom'] . ' ' . $user['nom']; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $user['age']; ?> ans
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $user['numero_tlfn']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php echo $user['email']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                    <?php echo Utils::formatPrice($user['salaire']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex space-x-2">
                                        <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                                class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                            Modifier
                                        </button>
                                        <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                                class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                                            Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-gray-600 text-center py-8">Aucun agent trouvé.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php
}

// Page de gestion des voitures (admin)
function showManageCarsPage() {
    global $currentUser, $conn;
    
    // Récupérer toutes les voitures de l'agence
    $result = $conn->query("SELECT * FROM car WHERE company_id = " . $currentUser->getCompanyId() . " ORDER BY marque, model");
    ?>
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Gestion du parc automobile</h1>
        <div class="flex justify-between items-center">
            <p class="text-gray-600">Gérez les voitures de votre agence</p>
            <button onclick="document.getElementById('addCarModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <i class="fas fa-plus mr-2"></i> Ajouter une voiture
            </button>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg p-6">
        <?php if ($result->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marque & Modèle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Année</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Immatriculation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prix/jour</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disponibilité</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php while ($car = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $car['id_car']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium"><?php echo $car['marque'] . ' ' . $car['model']; ?></div>
                                <div class="text-sm text-gray-500"><?php echo $car['color']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php echo $car['annee']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                                <?php echo $car['matricule']; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    <?php echo $car['category'] == 1 ? 'bg-green-100 text-green-800' : 
                                           ($car['category'] == 2 ? 'bg-yellow-100 text-yellow-800' : 
                                           'bg-red-100 text-red-800'); ?>">
                                    Catégorie <?php echo $car['category']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold">
                                <?php echo Utils::formatPrice($car['prix_day']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-3 py-1 rounded-full text-sm font-medium
                                    <?php echo $car['voiture_work'] == 'disponible' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo $car['voiture_work'] == 'disponible' ? 'Disponible' : 'Non disponible'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex space-x-2">
                                    <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                            class="px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200">
                                        Modifier
                                    </button>
                                    <button onclick="showNotification('Fonctionnalité en développement', 'info')"
                                            class="px-3 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200">
                                        Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p class="text-gray-600 text-center py-8">Aucune voiture trouvée dans votre agence.</p>
        <?php endif; ?>
    </div>
    
    <!-- Modal Ajout Voiture -->
    <div id="addCarModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold">Ajouter une nouvelle voiture</h3>
                <button onclick="document.getElementById('addCarModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600 text-2xl">
                    &times;
                </button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="add_car" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2" for="marque">Marque</label>
                        <select name="marque" id="marque" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Sélectionnez une marque</option>
                            <option value="Toyota">Toyota</option>
                            <option value="Volkswagen">Volkswagen</option>
                            <option value="Renault">Renault</option>
                            <option value="Hyundai">Hyundai</option>
                            <option value="Kia">Kia</option>
                            <option value="Peugeot">Peugeot</option>
                            <option value="BMW">BMW</option>
                            <option value="Audi">Audi</option>
                            <option value="Mercedes-Benz">Mercedes-Benz</option>
                            <option value="Lexus">Lexus</option>
                            <option value="Porsche">Porsche</option>
                            <option value="Volvo">Volvo</option>
                            <option value="Škoda">Škoda</option>
                            <option value="Mazda">Mazda</option>
                            <option value="Nissan">Nissan</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="model">Modèle</label>
                        <input type="text" name="model" id="model" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex: Corolla, Golf, Clio...">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2" for="color">Couleur</label>
                        <input type="text" name="color" id="color" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex: Blanc, Noir, Rouge...">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="annee">Année</label>
                        <input type="number" name="annee" id="annee" required min="2000" max="<?php echo date('Y'); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="<?php echo date('Y'); ?>">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="category">Catégorie</label>
                        <select name="category" id="category" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Sélectionnez</option>
                            <option value="1">Catégorie 1 (4000-6000 DA)</option>
                            <option value="2">Catégorie 2 (6000-12000 DA)</option>
                            <option value="3">Catégorie 3 (12000-20000 DA)</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 mb-2" for="prix_day">Prix par jour (DA)</label>
                        <input type="number" name="prix_day" id="prix_day" required min="4000" max="20000"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex: 4500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="status_voiture">État de la voiture</label>
                        <select name="status_voiture" id="status_voiture" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="excellent">Excellent</option>
                            <option value="entretien">En entretien</option>
                            <option value="faible">Faible</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-8">
                    <label class="block text-gray-700 mb-2" for="voiture_work">Disponibilité</label>
                    <select name="voiture_work" id="voiture_work" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="disponible">Disponible</option>
                        <option value="non_disponible">Non disponible</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="document.getElementById('addCarModal').classList.add('hidden')"
                            class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Ajouter la voiture
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// Page des statistiques
function showStatisticsPage() {
    global $currentUser, $conn;
    
    $period = isset($_GET['period']) ? $_GET['period'] : 'month';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    
    // Statistiques détaillées
    $stats = $currentUser->getCompanyStats();
    $revenue_by_period = $currentUser->getRevenueByPeriod($period);
    
    // Meilleurs clients
    $top_clients = $conn->query("SELECT c.prenom, c.nom, c.email, SUM(r.montant) as total_depense 
                                FROM reservation r 
                                JOIN client c ON r.id_client = c.id 
                                WHERE r.company_id = {$currentUser->getCompanyId()} AND r.status = 'confirmee'
                                GROUP BY r.id_client 
                                ORDER BY total_depense DESC 
                                LIMIT 5");
    
    // Voitures les plus populaires
    $top_cars = $conn->query("SELECT car.marque, car.model, COUNT(r.car_id) as reservations_count, 
                             SUM(r.montant) as revenue_total 
                             FROM reservation r 
                             JOIN car ON r.car_id = car.id_car 
                             WHERE r.company_id = {$currentUser->getCompanyId()} AND r.status = 'confirmee'
                             GROUP BY r.car_id 
                             ORDER BY reservations_count DESC 
                             LIMIT 5");
    ?>
    
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">Statistiques détaillées</h1>
        <p class="text-gray-600">Analysez les performances de votre agence</p>
    </div>
    
    <!-- Filtres période -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Filtres de période</h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="hidden" name="page" value="statistics">
            
            <div>
                <label class="block text-gray-700 mb-2">Période</label>
                <select name="period" class="w-full px-4 py-2 border rounded-lg">
                    <option value="day" <?php echo $period == 'day' ? 'selected' : ''; ?>>Journalier</option>
                    <option value="month" <?php echo $period == 'month' ? 'selected' : ''; ?>>Mensuel</option>
                    <option value="year" <?php echo $period == 'year' ? 'selected' : ''; ?>>Annuel</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 mb-2">Date début</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                       class="w-full px-4 py-2 border rounded-lg">
            </div>
            
            <div>
                <label class="block text-gray-700 mb-2">Date fin</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                       class="w-full px-4 py-2 border rounded-lg">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 w-full">
                    Appliquer
                </button>
            </div>
        </form>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Statistiques principales -->
        <div class="lg:col-span-2">
            <!-- Graphique des revenus -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold mb-6">Évolution des revenus (<?php echo $period == 'day' ? 'Journalière' : ($period == 'month' ? 'Mensuelle' : 'Annuelle'); ?>)</h2>
                
                <?php if ($revenue_by_period->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Période</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenus</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Réservations</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Moyenne/réservation</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $total_period_revenue = 0;
                                $total_period_reservations = 0;
                                while ($row = $revenue_by_period->fetch_assoc()): 
                                    $total_period_revenue += $row['revenue'];
                                    $total_period_reservations += $row['reservations'];
                                    $average = $row['reservations'] > 0 ? $row['revenue'] / $row['reservations'] : 0;
                                ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo $period == 'day' ? date('d/m/Y', strtotime($row['period'])) : 
                                                   ($period == 'month' ? date('m/Y', strtotime($row['period'].'-01')) : 
                                                   $row['period']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-bold text-green-600">
                                            <?php echo Utils::formatPrice($row['revenue']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo $row['reservations']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo Utils::formatPrice($average); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                
                                <!-- Total -->
                                <tr class="bg-gray-50 font-bold">
                                    <td class="px-6 py-4 whitespace-nowrap">TOTAL</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-green-600">
                                        <?php echo Utils::formatPrice($total_period_revenue); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $total_period_reservations; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $total_average = $total_period_reservations > 0 ? $total_period_revenue / $total_period_reservations : 0;
                                        echo Utils::formatPrice($total_average); 
                                        ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-8">Aucune donnée disponible pour cette période.</p>
                <?php endif; ?>
            </div>
            
            <!-- Meilleurs clients -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-2xl font-bold mb-6">Top 5 clients</h2>
                
                <?php if ($top_clients->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total dépensé</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fidélité</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $rank = 1;
                                while ($client = $top_clients->fetch_assoc()): 
                                    $fidelite = $client['total_depense'] > 100000 ? 'Or' : 
                                               ($client['total_depense'] > 50000 ? 'Argent' : 'Bronze');
                                    $fidelite_class = $fidelite == 'Or' ? 'bg-yellow-100 text-yellow-800' : 
                                                     ($fidelite == 'Argent' ? 'bg-gray-100 text-gray-800' : 'bg-orange-100 text-orange-800');
                                ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                    <span class="font-bold text-blue-600"><?php echo $rank++; ?></span>
                                                </div>
                                                <div>
                                                    <div class="font-medium"><?php echo $client['prenom'] . ' ' . $client['nom']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php echo $client['email']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap font-bold text-blue-600">
                                            <?php echo Utils::formatPrice($client['total_depense']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $fidelite_class; ?>">
                                                <?php echo $fidelite; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-8">Aucun client avec réservation confirmée.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar statistiques -->
        <div class="lg:col-span-1">
            <!-- Vue d'ensemble -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h2 class="text-xl font-bold mb-6">Vue d'ensemble</h2>
                
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-bold mb-2">Taux d'occupation</h3>
                        <?php
                        $total_cars = $stats['cars'];
                        $available_cars = $conn->query("SELECT COUNT(*) as count FROM car WHERE company_id = {$currentUser->getCompanyId()} AND voiture_work = 'disponible'")->fetch_assoc()['count'];
                        $occupation_rate = $total_cars > 0 ? (($total_cars - $available_cars) / $total_cars) * 100 : 0;
                        ?>
                        <div class="flex items-center">
                            <div class="flex-1 bg-gray-200 rounded-full h-3">
                                <div class="bg-green-500 h-3 rounded-full" style="width: <?php echo $occupation_rate; ?>%"></div>
                            </div>
                            <span class="ml-3 font-bold"><?php echo round($occupation_rate, 1); ?>%</span>
                        </div>
                        <p class="text-gray-500 text-sm mt-1">
                            <?php echo ($total_cars - $available_cars); ?> voitures sur <?php echo $total_cars; ?> sont occupées
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-bold mb-2">Taux de confirmation</h3>
                        <?php
                        $total_reservations = $stats['reservations'];
                        $confirmed_reservations = $conn->query("SELECT COUNT(*) as count FROM reservation WHERE company_id = {$currentUser->getCompanyId()} AND status = 'confirmee'")->fetch_assoc()['count'];
                        $confirmation_rate = $total_reservations > 0 ? ($confirmed_reservations / $total_reservations) * 100 : 0;
                        ?>
                        <div class="flex items-center">
                            <div class="flex-1 bg-gray-200 rounded-full h-3">
                                <div class="bg-blue-500 h-3 rounded-full" style="width: <?php echo $confirmation_rate; ?>%"></div>
                            </div>
                            <span class="ml-3 font-bold"><?php echo round($confirmation_rate, 1); ?>%</span>
                        </div>
                        <p class="text-gray-500 text-sm mt-1">
                            <?php echo $confirmed_reservations; ?> réservations sur <?php echo $total_reservations; ?> sont confirmées
                        </p>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-bold mb-2">Valeur moyenne/réservation</h3>
                        <?php
                        $average_value = $confirmed_reservations > 0 ? $stats['revenue'] / $confirmed_reservations : 0;
                        ?>
                        <div class="text-center py-4">
                            <p class="text-3xl font-bold text-purple-600"><?php echo Utils::formatPrice($average_value); ?></p>
                            <p class="text-gray-500 text-sm">Par réservation confirmée</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Voitures populaires -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold mb-6">Voitures les plus populaires</h2>
                
                <?php if ($top_cars->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php 
                        $car_rank = 1;
                        while ($car = $top_cars->fetch_assoc()): 
                        ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                        <span class="font-bold text-blue-600"><?php echo $car_rank++; ?></span>
                                    </div>
                                    <div>
                                        <div class="font-medium"><?php echo $car['marque'] . ' ' . $car['model']; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $car['reservations_count']; ?> réservations</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-green-600"><?php echo Utils::formatPrice($car['revenue_total']); ?></div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 text-center py-4">Aucune donnée disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// Mettre à jour la fonction includePage pour inclure toutes les pages
function includePage($pageName) {
    global $currentUser, $conn;
    
    switch ($pageName) {
        case 'login':
            showLoginPage();
            break;
        case 'choose_role':
            showChooseRolePage();
            break;
        case 'register':
            showRegisterPage();
            break;
        case 'client_dashboard':
            if ($currentUser && $currentUser->getRole() == 'client') {
                showClientDashboard();
            } else {
                showHomePage();
            }
            break;
        case 'agent_dashboard':
            if ($currentUser && $currentUser->getRole() == 'agent') {
                showAgentDashboard();
            } else {
                showHomePage();
            }
            break;
        case 'admin_dashboard':
            if ($currentUser && $currentUser->getRole() == 'admin' && !$currentUser->isOwner()) {
                showAdminDashboard();
            } else {
                showHomePage();
            }
            break;
        case 'owner_dashboard':
            if ($currentUser && $currentUser->getRole() == 'admin' && $currentUser->isOwner()) {
                showOwnerDashboard();
            } else {
                showHomePage();
            }
            break;
        case 'cars':
            if ($currentUser) {
                showCarsPage();
            } else {
                showHomePage();
            }
            break;
        case 'reservation':
            if ($currentUser && $currentUser->getRole() == 'client') {
                showReservationPage();
            } else {
                showHomePage();
            }
            break;
        case 'payment':
            if ($currentUser && $currentUser->getRole() == 'client') {
                showPaymentPage();
            } else {
                showHomePage();
            }
            break;
        case 'manage_users':
            if ($currentUser && in_array($currentUser->getRole(), ['agent', 'admin'])) {
                showManageUsersPage();
            } else {
                showHomePage();
            }
            break;
        case 'manage_cars':
            if ($currentUser && $currentUser->getRole() == 'admin') {
                showManageCarsPage();
            } else {
                showHomePage();
            }
            break;
        case 'statistics':
            if ($currentUser && $currentUser->getRole() == 'admin') {
                showStatisticsPage();
            } else {
                showHomePage();
            }
            break;
        default:
            showHomePage();
    }
}
?>