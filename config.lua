Config = {}

Config.MaxAir = 300.0 -- Pression max de la jauge (en bar)
Config.DefaultAirMin = 280.0 -- Pression min par défaut lors de la prise d'une bouteille
Config.DefaultAirMax = 300.0 -- Pression max par défaut lors de la prise d'une bouteille
Config.BaseConsumption = 0.25 -- Consommation de base au repos (Pour 20 minutes d'autonomie à 300 bars)
Config.RunMultiplier = 2.5 -- Multiplicateur en courant
Config.SprintMultiplier = 3.5 -- Multiplicateur en sprintant
Config.BuddyConsumptionMultiplier = 2.0 -- Le donneur Buddy consomme pour 2 (x2.0)
Config.BuddyBreatherMaxDistance = 3.0 -- Longueur maximale du tuyau Buddy Breather (en mètres, rupture au-delà)
Config.BypassConsumptionMultiplier = 8.0 -- Multiplicateur de consommation avec la soupape Bypass ouverte
Config.DamageOnEmpty = 5 -- Dégâts quand la bouteille est vide (si capelé)

-- ==========================================
-- CONFIGURATION DE LA BUÉE
-- ==========================================
Config.FogBuildUpSpeed = 0.45 -- Vitesse d'apparition de la buée (en % par seconde, augmentée pour un effet plus intense)
Config.FogPurgeSpeed = 0.35   -- Vitesse à laquelle la purge retire la buée petit à petit (en % par seconde, 0.35 = ~3 sec pour purger 100%)
Config.FogMaxOpacity = 0.95   -- Opacité maximale de la buée (0.0 à 1.0)

-- ==========================================
-- TOUCHES (Par défaut)
-- ==========================================
-- Les joueurs pourront changer ces touches dans : Paramètres > Raccourcis > FiveM
Config.Keys = {
    Manometer = "O", -- Touche pour afficher/cacher le manomètre
    ToggleARI = "J", -- Touche pour capeler/décapeler
    ToggleBalise = "K", -- Touche pour activer la balise Homme Mort
    InteractVeh = ";", -- Touche pour s'équiper / ranger l'ARI au camion
    ChangeBottle = "G", -- Touche pour changer la bouteille au camion
    Hub = "M", -- Touche pour ouvrir le HUB (tablette SDCPJ alphaCONTROL)
    TakeHub = "Y", -- Touche pour récupérer/ranger la tablette du camion
    BuddyBreather = "U", -- Demander un branchement Buddy Breather au joueur ciblé
    AcceptBuddy = "Y", -- Accepter une demande de Buddy Breather
    Bypass = "N", -- Touche (Maintenir) pour ouvrir la soupape Bypass et purger la buée
    AcquitEvac = "RETURN", -- Touche pour acquitter l'ordre d'évacuation
    ToggleHelp = "I", -- Touche pour afficher / masquer l'aide des touches
    CarryBottle = "H", -- Touche pour sortir/ranger une bouteille en main au fourgon
    Refill = "E", -- Touche pour réarmer une bouteille ou l'engin à la station de gonflage
    CheckRack = "E" -- Touche pour vérifier les ARI et ouvrir le rack du véhicule
}

Config.WalkingStyle = "fire3" -- Style de marche quand l'ARI est équipé (ex: "move_m@fireman" ou "fire3")

-- ==========================================
-- CONFIGURATION CAMÉRA PREMIÈRE PERSONNE
-- ==========================================
Config.FirstPersonCamera = {
    Enabled = true,        -- Place la caméra devant les yeux et un peu en avant
    ForwardOffset = 0.18,  -- Avancement devant les yeux (en mètres, 0.18 = 18cm en avant)
    EyeHeightOffset = 0.06,-- Alignement vertical précis avec les yeux du pompier (+6cm au-dessus du centre du crâne)
    MaskScale = 1.06       -- Rapprochement immersif de la visière du masque
}

-- ==========================================
-- CONFIGURATION DES TENUES (EUP)
-- ==========================================
-- Component ID : 1 = Masque, 5 = Sacs, 8 = T-shirt, 9 = Pare-balles, 11 = Veste
Config.Clothing = {
    ComponentID = 5, -- Le composant utilisé pour l'ARI (5 pour les sacs/parachutes)
    
    Male = {
        NonCapele_Drawable = 111, -- Numéro de l'ARI dans le dos (décapelé)
        NonCapele_Texture = 0,
        
        Capele_Drawable = 112, -- Numéro de l'ARI capelé
        Capele_Texture = 0
    },
    
    Female = {
        NonCapele_Drawable = 111,
        NonCapele_Texture = 0,
        
        Capele_Drawable = 112,
        Capele_Texture = 0
    }
}

-- ==========================================
-- CONFIGURATION DES VÉHICULES
-- ==========================================
-- Les hash sont calculés côté client/serveur via GetHashKey
-- On stocke les noms ici, le hash sera fait au runtime
Config.VehicleNames = {
    { model = "firenaultsdis20", Name = "FPTL", MaxBottles = 6, InteractionDistance = 3.5 },
    { model = "fiivecosdis20", Name = "FPT", MaxBottles = 8, InteractionDistance = 3.5 },
    { model = "fptlhrsdis20",      Name = "FPTHR", MaxBottles = 8, InteractionDistance = 3.5 },
    { model = "firenault2sdis20",      Name = "FPTL", MaxBottles = 6, InteractionDistance = 3.5 },
    { model = "fptlrenaultsdis20",      Name = "FPTL", MaxBottles = 6, InteractionDistance = 3.5 },
    { model = "vpisdis20",      Name = "VPI", MaxBottles = 6, InteractionDistance = 3.5 },
    { model = "fptivecosdis20",      Name = "FPT", MaxBottles = 8, InteractionDistance = 3.5 },
}

-- Table qui sera remplie au runtime avec les hash
Config.Vehicles = {}

-- ==========================================
-- CONFIGURATION HOMME MORT (BALISE)
-- ==========================================
Config.HommeMort = {
    PreAlerteTime = 25, -- Temps en secondes avant la pré-alerte
    AlerteTime = 15,    -- Temps en secondes APRÈS la pré-alerte avant l'alerte
    SpeedThreshold = 0.1 -- Seuil de vitesse pour considérer que le joueur bouge (0.1 évite les petites animations)
}

-- ==========================================
-- CONFIGURATION DES SONS (Noms des fichiers sans .mp3)
-- ==========================================
Config.Sounds = {
    Respiration = "ari",
    PreAlerte = "ari_pre_alerte",
    Alerte = "ari_alerte",
    Ouverture = "ari_ouverture",
    BaliseActivation = "cle_ari",
    CleOff = "ari_cle_off",
    Sifflet = "ari_sifflet",
    Bypass = "ari_bypass",
    Evac = "evac"
}

-- ==========================================
-- LUMIÈRE PHYSIQUE DU MANOMÈTRE (SUR LE CORPS DU POMPIER)
-- ==========================================
Config.ManometerLight = {
    Enabled = true,
    Bone = 24816, -- SKEL_Spine1 (Os du ventre / sangle abdominale de l'ARI qui suit toutes les animations)
    HaloSize = 0.001, -- Taille de la diode luminescente sur le manomètre
    Offset = {
        x = 0.03, -- Hauteur
        y = 0.13, -- Vers l'avant du ventre
        z = -0.23  -- Sangle droite / niveau du ventre
    },
    Settings = {
        Normal = {
            Range = 0.3, -- Distance réduite pour le vert
            Intensity = 0.8, -- Puissance douce
            FadeInterval = 5000, -- Clignotement en Fade toutes les 5 secondes
            FadeDuration = 1200, -- Durée de la pulsation douce en ms
            Color = { r = 0, g = 255, b = 30 } -- Vert pur MSA
        },
        Half = {
            Range = 0.6, -- Distance réduite pour le orange
            Intensity = 2.4, -- Puissance modérée
            FadeInterval = 3000, -- Clignotement en Fade toutes les 3 secondes
            FadeDuration = 1000, -- Durée de la pulsation douce en ms
            Color = { r = 255, g = 95, b = 0 } -- Vrai orange bien marqué (non jaune)
        },
        Reserve = {
            Range = 1.0,
            Intensity = 5.0,
            Color = { r = 255, g = 20, b = 20 } -- Rouge alarme réserve
        },
        Alert = {
            Range = 1.4,
            Intensity = 3.0,
            Color = { r = 255, g = 0, b = 0 } -- Rouge flash alerte balise
        },
        PreAlert = {
            Range = 0.8,
            Intensity = 3.5,
            Color = { r = 255, g = 95, b = 0 } -- Orange pré-alerte
        }
    },
    Colors = {
        Normal = { r = 0, g = 255, b = 30 },  -- Vert pur
        Half = { r = 255, g = 95, b = 0 },     -- Vrai orange
        Reserve = { r = 255, g = 20, b = 20 }, -- Rouge réserve
        Alert = { r = 255, g = 0, b = 0 },     -- Rouge alerte
        PreAlert = { r = 255, g = 95, b = 0 }  -- Orange
    }
}

-- ==========================================
-- RESTRICTION DES MÉTIERS (ESX / QBCore)
-- ==========================================
Config.JobRestriction = {
    Enabled = true, -- Mettre à true pour réserver l'ARI et le HUB aux pompiers
    Jobs = {
        ["pompier"] = true
    }
}

-- ==========================================
-- CONFIGURATION DU RÉARMEMENT DES BOUTEILLES
-- ==========================================
Config.BottleProp = "v_ind_cs_gascanister" -- Modèle du prop tenu en main
Config.CarriedBottle = {
    Bone = 57005, -- SKEL_R_Hand (Main droite)
    Pos = vec3(0.12, 0.05, -0.05),
    Rot = vec3(0.0, 90.0, 0.0) -- Bouteille orientée verticalement vers le sol
}
Config.CarriedBottleAnim = {
    dict = "missfinale_c2mcs_1", -- Animation de portage de la bouteille
    anim = "fin_c2_mcs_1_camman",
    flag = 49 -- Upper body : permet de marcher, courir et se déplacer
}

-- Stations de réarmement d'air / compresseurs sur la carte
Config.RefillStations = {
    {
        name = "Compresseur CS Davis (Principal)",
        coords = vec3(204.3, -1654.1, 29.8),
        distance = 3.0,
        blip = { enabled = false, sprite = 361, color = 1, scale = 0.75, name = "Réarmement Bouteilles ARI" }
    },
    {
        name = "Compresseur CS El Burro (Poste 1)",
        coords = vec3(1195.45, -1487.90, 34.84),
        distance = 3.0,
        blip = { enabled = false, sprite = 361, color = 3, scale = 0.75, name = "Réarmement Bouteilles ARI" }
    },
    {
        name = "Compresseur CS El Burro (Poste 2)",
        coords = vec3(1193.51, -1487.85, 34.84),
        distance = 3.0,
        blip = { enabled = false }
    },
    {
        name = "Compresseur CS Rockford Hills",
        coords = vec3(-636.5, -121.2, 38.0),
        distance = 3.0,
        blip = { enabled = false, sprite = 361, color = 1, scale = 0.75, name = "Réarmement Bouteilles ARI" }
    },
    {
        name = "Compresseur CS Paleto Bay",
        coords = vec3(-378.8, 6118.9, 31.8),
        distance = 3.0,
        blip = { enabled = false, sprite = 361, color = 1, scale = 0.75, name = "Réarmement Bouteilles ARI" }
    },
    {
        name = "Compresseur CS Sandy Shores",
        coords = vec3(1692.6, 3584.9, 35.6),
        distance = 3.0,
        blip = { enabled = false, sprite = 361, color = 1, scale = 0.75, name = "Réarmement Bouteilles ARI" }
    }
}

-- ==========================================
-- INTÉGRATION RESCUE (GESTION DES VICTIMES & MORT)
-- ==========================================
Config.Rescue = {
    Enabled = true, -- Détecte automatiquement la ressource 'rescue'
    ScenarioType = "Incendie", -- Catégorie dans rescue/config_scenarios.lua
    Subcategory = "Inhalation de fumees" -- Sous-catégorie exacte
}
