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

