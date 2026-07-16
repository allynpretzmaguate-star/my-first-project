<?php
/**
 * Database Connection
 * Uses PDO so every query elsewhere in the app can use prepared
 * statements (protects against SQL injection).
 */

class Database
{
    private static ?PDO $instance = null;

    // ---- EDIT THESE TO MATCH YOUR XAMPP / MySQL SETUP ----
    private const HOST    = '127.0.0.1';
    private const DBNAME  = 'phpmyadmin';
    private const USER    = 'root';
    private const PASS    = '';        // default XAMPP root password is blank
    private const CHARSET = 'utf8mb4';
    // -------------------------------------------------------

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . self::HOST . ';dbname=' . self::DBNAME . ';charset=' . self::CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, self::USER, self::PASS, $options);
            } catch (PDOException $e) {
                // Never leak DB credentials/details to the browser
                error_log('DB Connection Error: ' . $e->getMessage());
                die('Database connection failed. Please check server logs / config/database.php.');
            }
        }

        return self::$instance;
    }
}