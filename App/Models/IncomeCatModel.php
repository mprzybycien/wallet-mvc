<?php

namespace App\Models;

use PDO;
use \Core\View;

/**
 * Example user model
 *
 * PHP version 7.0
 */
class IncomeCatModel extends \Core\Model
{

    public function __construct($data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        };
    }
    
    public static function getIncomeCategories()
    {
        
        $sql = 'SELECT * FROM incomes_category_assigned_to_users 
                WHERE user_id = :id
                ORDER BY name';

        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $_SESSION['user_id'], PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByID($id)
    {
        $sql = 'SELECT * FROM incomes_category_assigned_to_users WHERE id = :id';

        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        return $stmt->fetch();
    }

    public function save()
    { 
        $this->validate();
        if(empty($this->errors)) {
            
            $sql = 'INSERT INTO incomes_category_assigned_to_users (user_id, name)
                    VALUES (:user_id, :name)';
            $db = static::getDB();
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':name', $this->name, PDO::PARAM_STR);
            return $stmt->execute();

        }  else return false;
    }


    public function delete()
    {   
        if(static::isCatNameInIncomes($this->catId) > 0)
        {
            $sql = 'DELETE incomes_category_assigned_to_users, incomes
                FROM incomes_category_assigned_to_users
                JOIN incomes 
                WHERE incomes_category_assigned_to_users.id = :id 
                AND incomes.income_category_assigned_to_user_id = :id' ;   
        } else {
            $sql = 'DELETE incomes_category_assigned_to_users
                FROM incomes_category_assigned_to_users
                WHERE id = :id' ;
        }

        
        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $this->catId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    public static function isCatNameInIncomes($catId)
    {
        $sql = 'SELECT * FROM incomes
        WHERE income_category_assigned_to_user_id = :id';

        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $catId, PDO::PARAM_INT);
        $stmt->execute();
    
        return $stmt->rowCount();
    }
    
    public function edit()
    {   
        $this->validate();

        if (empty($this->errors)) {
            $sql = 'UPDATE incomes_category_assigned_to_users 
                    SET name = :name
                    WHERE id = :id';

            $db = static::getDB();
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':name', $this->name, PDO::PARAM_STR);
            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
            return $stmt->execute();
            }
            return false;
    }

    public function validate()
    {
       if (strlen($this->name) > 20) {
               $this->errors[] = 'The category name cannot be longer than 20 characters';
       }
       if (static::categoryNameExist($this->name, $this->id ?? null)) {
        $this->errors[] = 'There is already exist a category with that name';
       }
    }

    public static function categoryNameExist ($name, $ignore_id = null)
    {

        $category = static::findByName($name);
        if($category) {
            if ($category->id != $ignore_id) {
                return true;
            }
        }
        return false;
    }

    public static function findByName($name)
    {
        $sql = 'SELECT * 
                FROM incomes_category_assigned_to_users 
                WHERE user_id = :id 
                AND name = :name';

        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id' , $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());
        $stmt->execute();
        return $stmt->fetch();
    }
}
