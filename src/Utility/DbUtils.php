<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2020 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Cli\Utility;

use PDO;

/**
 * Utility methods for command line database options.
 */
final class DbUtils {
    /**
     * Create a connection to a MySQL database.
     *
     * This is a useful method to use with the `CliApplication::addFactory` class. Here is an example usage:
     *
     * ```
     * $this->addFactory(\PDO::class, [DbUtils::class, 'createMySQL'], [CliApplication::OPT_PREFIX => 'db-']);
     * $this->getContainer()->setShared(true);
     * ```
     *
     * This will wire up the parameters of this method to the command line and also mark the PDO as a shared class to
     * only be instantiated once.
     *
     * @param string $name The name of the database.
     * @param string $host The host of the database.
     * @param string $username The username to connect to the database.
     * @param string $password The password to connect to the database.
     * @return PDO
     */
    public static function createMySQL(string $name, string $host = 'localhost', string $username = 'root', string $password = ''): PDO {
        $dsn = "mysql:dbname=$name;host=$host;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password);
        return $pdo;
    }
}
