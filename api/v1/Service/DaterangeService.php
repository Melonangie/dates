<?php

namespace Daterange\v1\Service;

use Daterange\v1\Database\DBConnection as DBConnection;
use Daterange\v1\Exception\RestException as RestException;
use Daterange\v1\Model\Daterange as Daterange;
use Daterange\v1\Model\DaterangeQueryBuilder as DaterangeQueryBuilder;
use Exception;
use PDO;
use PDOException;

/**
 * Class DaterangeService
 *
 * @package Daterange\v1\Service
 */
class DaterangeService {

  /**
   * The Database schema.
   *
   * @var DBConnection
   */
  private $db;

  /**
   * DaterangeService constructor.
   *
   * @param \Daterange\v1\Database\DBConnection $db
   */
  public function __construct(DBConnection $db) {

    @set_exception_handler([$this, 'exception_handler']);

    // Set PDO.
    $this->db = $db;



  }

  /**
   * Exception.
   *
   * @param $exception
   */
  public function exception_handler($exception): void {
    // Set headers.
    header('Content-Type: ' . CONTENT_TYPE_CHARSET_JSON);
    http_response_code($exception->getCode());

    // Set message.
    echo $exception->getMessage();
  }

  /**
   * Saves a record to the Database.
   *
   * @param Daterange $daterange
   *
   * @return array
   */
  public function createDaterange(Daterange $daterange): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Insert the person to the main DB table.
      $query = $this->db->pdo()->prepare(sprintf('INSERT INTO `%s` (%s, %s, %s) VALUES (:date_starts, :date_ends, :price)', $this->db->get(TABLE), DATE_STARTS, DATE_ENDS, PRICE));
      $query->bindValue(':date_starts', $daterange->getDateStart(), PDO::PARAM_STR);
      $query->bindValue(':date_ends', $daterange->getDateEnd(), PDO::PARAM_STR);
      $query->bindValue(':price', (string) $daterange->getPrice(), PDO::PARAM_STR);
      $query->execute();

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    return ['Code:' => '200', 'Message:' => 'Successfully created record.', 'Error:' => ''];
  }

  /**
   * Gets a specific record.
   *
   * @param string $date_start
   *
   * @return Daterange
   */
  public function readDaterange(string $date_start): Daterange {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Gets a specific record from the DB.
      $query = $this->db->pdo()->prepare(sprintf('SELECT * FROM `%s` WHERE `%s`=:date_start', $this->db->get(TABLE), DATE_STARTS));
      $query->bindParam(':date_start', $date_start, PDO::PARAM_STR);
      $query->execute();
      $date = $query->fetchObject(DATERANGE_CLASS);

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return object.
    return $date;
  }

  /**
   * Gets all records.
   *
   * @return array
   */
  public function readDaterangeAll(): array {

    $dates = [];

    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Gets all persons from the DB.
      $query = $this->db->pdo()->query(sprintf('SELECT * FROM `%s` ORDER BY `date_start`', $this->db->get(TABLE)));

      // Get selected person emails and phones.
      while ($date = $query->fetchObject(DATERANGE_CLASS)) {
        $dates[] = $date;
      }

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return array of objects.
    return $dates;

  }

  /**
   * Gets a record based on a query string.
   *
   * @param array $extraParameters
   *
   * @return array
   */
  public function readDaterangeQuery(array $extraParameters) {

    $persons = [];

    // Get the  dynamic query.
    $dynamic_query = new DaterangeQueryBuilder($this->db->get(TABLE), $extraParameters);

    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Gets all persons from the DB.
      $query = $this->db->pdo()->prepare($dynamic_query->getQueryStatement());
      $query->execute($dynamic_query->getParamStatement());

      // Get selected person emails and phones.
      while ($person = $query->fetchObject('Persons\v1\Model\Person')) {

        if ($dynamic_query->getFields() == '*' || $dynamic_query->getFilterEmails()) {
          $emails = $this->db->pdo()
            ->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['EMAILS_VIEW']));
          $emails->bindParam(1, $person->getId(), PDO::PARAM_INT);
          $emails->execute();
          while ($email = $emails->fetch(PDO::FETCH_OBJ)) {
            $person->addEmail($email);
          }
        }

        if ($dynamic_query->getFields() == '*' || $dynamic_query->getFilterPhones()) {
          $phones = $this->db->pdo()
            ->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['PHONES_VIEW']));
          $phones->bindParam(1, $person->getId(), PDO::PARAM_INT);
          $phones->execute();
          while ($phone = $phones->fetch(PDO::FETCH_OBJ)) {
            $person->addPhone($phone);
          }
        }

        $persons[] = $person;
      }

      // Commits transaction.
      $this->db->pdo()->commit();

    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'PDO Connection error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response.
    return $persons;

  }

  /**
   * Updates a record.
   *
   * @param Daterange $daterange
   *
   * @return array
   */
  public function updateDaterange(Daterange $daterange): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Insert the person to the main DB table.
      $query = $this->db->pdo()->prepare(sprintf('UPDATE `%s` SET `%s`=:modified, `%s`=:date_end, `%s`=:price WHERE `%s`=:date_start', $this->db->get(TABLE), MODIFIED, DATE_ENDS, PRICE, DATE_STARTS));
      $query->bindValue(':modified', date('Y-m-d H:i:s'), PDO::PARAM_STR);
      $query->bindValue(':date_end', $daterange->getDateEnd(), PDO::PARAM_STR);
      $query->bindValue(':price', (string) $daterange->getPrice(), PDO::PARAM_STR);
      $query->bindValue(':date_start', $daterange->getDateStart(), PDO::PARAM_STR);
      $query->execute();

      // Commits transaction.
      $this->db->pdo()->commit();

    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'PDO Connection error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response.
    return ['Code:' => '200', 'Message:' => 'Successfully updated record. Start date: ' . $daterange->getDateStart(), 'Error:' => ''];
  }

  /**
   * Deletes a Daterange from the Database, based on a id.
   *
   * @param string $date_start
   *
   * @return array
   */
  public function deleteDaterange(string $date_start): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Deletes record.
      $query = $this->db->pdo()->prepare(sprintf('DELETE FROM `%s` WHERE `%s`=:date_start', $this->db->get(TABLE), DATE_STARTS));
      $query->bindValue(':date_start', $date_start, PDO::PARAM_STR);
      $query->execute();

      // Commits transaction.
      $this->db->pdo()->commit();

    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response.
    return ['Code:' => '200', 'Message:' => 'Successfully deleted record. Date Start: ' . $date_start, 'Error:' => ''];
  }

  /**
   * Deletes a Daterange Emails from the Database, based on a id.
   *
   * @return array
   */
  public function deleteDaterangeAll(): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Gets a specific person from the DB.
      $query = $this->db->pdo()->query(sprintf('TRUNCATE TABLE `%s`', $this->db->get(TABLE)));

      // Commits transaction.
      $this->db->pdo()->commit();

    }
    catch (PDOException | Exception $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response
    return ['Code:' => '200', 'Message:' => 'Successfully truncated database table.', 'Error:' => ''];
  }

  /**
   * Check if record exists.
   *
   * @param string $field
   * @param        $field_value
   * @param int    $pdo_param
   * @param null   $table
   *
   * @return bool
   */
  public function verifyExist(string $field, $field_value, $pdo_param = PDO::PARAM_STR, $table = NULL): bool {

    if ($table === NULL) {
      $table = $this->db->get(TABLE);
    }

    $exist = FALSE;

    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Checks record exist.
      $some_table = $this->db->pdo()->prepare(sprintf('SELECT 1 from `%s` WHERE `%s`=:field_value LIMIT 1', $table, $field));
      $some_table->bindValue(':field_value', $field_value, $pdo_param);
      $some_table->execute();
      if ($some_table->fetchColumn()) {
        $exist = TRUE;
      }

      // Commits transaction.
      $this->db->pdo()->commit();

    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    return $exist;
  }

  /**
   * Database connection object.
   *
   * @return \Daterange\v1\Database\DBConnection
   */
  public function getDb(): DBConnection {
    return $this->db;
  }

}