<?php

namespace App\Middleware;



class RateLimit
{


  private $db;
  private $logger;
  private $requests_per_minute;

  public function __construct($db, $logger, $requests_per_minute = 60) {
    $this->db = $db;
    $this->logger = $logger;
    $this->requests_per_minute = $requests_per_minute;
    $this->initDatabase();
  }

  public function __invoke($request, $response, $next) {    
    $ip = $_SERVER['REMOTE_ADDR'];
    try {
      $query = $this->db->prepare("SELECT COUNT(*) AS requests FROM `xrequests` WHERE `ip` = '$ip' AND `ts` >= datetime('now', '-1 minute')");
      $query->execute();
      $result = $query->fetch();
      if ($result) {
        if ($result['requests'] >= $this->requests_per_minute) {
          return $this->tooManyRequests($response);
        }
      }
      $this->db->exec("INSERT INTO `xrequests` (ip) VALUES ('$ip')");
      $this->db->exec("DELETE FROM `xrequests` WHERE `ts` <= datetime('now', '-5 minutes')");
    } catch (PDOException $ex) {
      $log->error($ex->getMessage());
    }
    $response = $next($request, $response);
    return $response;
  }

  protected function tooManyRequests($response) {
    $response->getBody()->write('Too many requests.');
    return $response->withStatus(429)->withHeader('RateLimit-Limit', $this->requests_per_minute);
  }

  protected function initDatabase() {
    try {
      $this->db->exec("CREATE TABLE IF NOT EXISTS `xrequests` (
        `id` INTEGER PRIMARY KEY,
        `ip` varchar(45) NOT NULL DEFAULT '',
        `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
      )");
    } catch (PDOException $ex) {
      $this->logger->error($ex->getMessage());
    }
  }

}