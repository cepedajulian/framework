<?php
namespace Dasintranet\Framework;

use \PDO;

class Db{
    protected $db;
    protected $lastInsertId;
    
    public function __construct(){
        $this->db();
        $this->lastInsertId = 0;
    }

    //====================== Acces to database =============================================
    public function db(){
        try{
            $this->db = new PDO(
                "mysql:host=" . DatabaseServer . ";charset=UTF8;dbname=" . Database, DatabaseUser, DatabasePassword,
            array(
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'TRADITIONAL'",
                PDO::ATTR_EMULATE_PREPARES => true
            ));
            
            $this->db->setAttribute(PDO::ATTR_ERRMODE,              PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,   PDO::FETCH_OBJ);
            $this->db->setAttribute(PDO::ATTR_ORACLE_NULLS,         PDO::NULL_TO_STRING);
        
        }catch(\PDOException $e){
            echo "\n Error OPEN DB:\n\n" . $e->getCode( ) . "\n\n" . $e->getMessage();
            die();
        }
    }

    public function find($table, $parameter1, $parameter2 = null){
        try{
            if($parameter2 == null){
                $column = 'id';
                $parameter = $parameter1;	
            }else{
                $column = $parameter1;
                $parameter = $parameter2;
            }

            $sql = $this->db->prepare("SELECT * FROM $table WHERE $column=:parameter LIMIT 1");
			$sql->bindValue(":parameter", $parameter);
            $sql->execute();
            $row = $sql->fetch();
            $sql->closeCursor();
            return $row;

        }catch(\PDOException $e){
            $sql->closeCursor();
            echo "\n Error DB:\n\n" . $e->getCode( ) . "\n\n" . $e->getMessage();
            die();
        }
    }

    public function query($strinsql, $parameters = null){
        try{
            $this->db->beginTransaction();
			
            $stringsql = trim($strinsql);
            $sql = $this->db->prepare($strinsql);

            $sql->execute((array) $parameters);
            
            $words = explode(' ', $strinsql);

            if(trim($words[0])=='INSERT'){
                $this->lastInsertId = intval($this->db->lastInsertId());
            }

            $this->db->commit();

            if(trim($words[0])=='SELECT'){
                $rows = $sql->fetchAll();
                $sql->closeCursor();
                return $rows;
            }else{
                $count = $sql->rowCount();
                return $count;
            }

        }catch(\PDOException $e){
            $this->db->rollback();
            echo "\n Error DB:\n\n" . $e->getCode( ) . "\n\n" . $e->getMessage();
            die();
        }
    }

    public function lastInsertId(){
        return $this->lastInsertId;
    }
}
