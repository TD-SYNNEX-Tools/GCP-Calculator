<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $instance = null;

    public static function connection(array $config): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $driver  = strtolower((string)($config['driver'] ?? 'sqlsrv'));
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver === 'sqlsrv') {
            // Azure SQL Database (Microsoft Drivers for PHP — pdo_sqlsrv).
            $dsn = sprintf(
                'sqlsrv:Server=tcp:%s,%d;Database=%s;Encrypt=%d;TrustServerCertificate=%d;LoginTimeout=30',
                $config['host'],
                $config['port'],
                $config['name'],
                !empty($config['encrypt']) ? 1 : 0,
                !empty($config['trust_cert']) ? 1 : 0
            );
        } else {
            // MySQL (compatibilidade / desenvolvimento local).
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['name']
            );
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
        }

        try {
            self::$instance = new PDO($dsn, $config['user'], $config['pass'], $options);
        } catch (PDOException $e) {
            // Não propaga a mensagem original (pode conter host/usuário do banco).
            error_log('Database connection: ' . $e->getMessage());
            throw new RuntimeException('Falha ao conectar ao banco de dados.', 0, $e);
        }

        return self::$instance;
    }
}
