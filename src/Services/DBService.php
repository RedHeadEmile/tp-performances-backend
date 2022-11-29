<?php

namespace App\Services;

class DBService
{
  private static ?DBService $instance = null;

  private \PDO $pdo;

  public function __construct()
  {
    $this->pdo = new \PDO( 'mysql:host=db;dbname=tp;charset=utf8mb4', 'root', 'root' );
  }

  /**
   * @return \PDO
   */
  public function getInstanciedPDO(): \PDO
  {
    return $this->pdo;
  }

  /**
   * @return \PDO
   */
  public static function getPDO(): \PDO
  {
    if (self::$instance === null)
      self::$instance = new DBService();
    return self::$instance->getInstanciedPDO();
  }
}