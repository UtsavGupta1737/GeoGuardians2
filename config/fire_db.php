<?php
// config/fire_db.php - Dedicated Zero-Config PDO Database Connection for Fire & Rescue CAD

function getFireRescuePdo(): PDO {
    static $firePdo = null;
    if ($firePdo !== null) {
        return $firePdo;
    }

    $dbDir = __DIR__ . '/../database';
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }

    $dbPath = $dbDir . '/fire_rescue.sqlite';
    $isNew = !file_exists($dbPath) || filesize($dbPath) === 0;

    try {
        $firePdo = new PDO("sqlite:" . $dbPath);
        $firePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $firePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Auto initialize tables and seed data if new
        if ($isNew) {
            $schemaFile = $dbDir . '/fire_rescue_schema.sql';
            if (file_exists($schemaFile)) {
                $schemaSql = file_get_contents($schemaFile);
                $firePdo->exec($schemaSql);
            }

            $seedFile = $dbDir . '/fire_rescue_seed.sql';
            if (file_exists($seedFile)) {
                $seedSql = file_get_contents($seedFile);
                $firePdo->exec($seedSql);
            }
        }
    } catch (PDOException $e) {
        // Fallback in-memory DB in worst-case permission failure
        $firePdo = new PDO("sqlite::memory:");
        $firePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $firePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        $schemaFile = $dbDir . '/fire_rescue_schema.sql';
        if (file_exists($schemaFile)) {
            $firePdo->exec(file_get_contents($schemaFile));
        }
        $seedFile = $dbDir . '/fire_rescue_seed.sql';
        if (file_exists($seedFile)) {
            $firePdo->exec(file_get_contents($seedFile));
        }
    }

    return $firePdo;
}
