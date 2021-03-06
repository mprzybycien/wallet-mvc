<?php

namespace App\Models;

use PDO;
use \App\Token;
use \App\Mail;
use \Core\View;

/**
 * Example user model
 *
 * PHP version 7.0
 */
class User extends \Core\Model
{

    /**
     * Get all the users as an associative array
     *
     * @return array
     */
    
    public function __construct($data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        };
    }
    
    public function save()
    {
        $this->validate();
        
        if(empty($this->errors)) {
            
            $password_hash = password_hash($this->password, PASSWORD_DEFAULT);
            
            $token = new Token;
            $hashed_token = $token->getHash();
            $this->activation_token = $token->getValue();

            $sql = 'INSERT INTO users (name, email, password_hash, activation_hash)
                    VALUES (:name, :email, :password_hash, :activation_hash)';

            $db = static::getDB();
            $stmt = $db->prepare($sql);

            $stmt->bindValue(':name', $this->name, PDO::PARAM_STR);
            $stmt->bindValue(':email', $this->email, PDO::PARAM_STR);
            $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR);
            $stmt->bindValue(':activation_hash', $hashed_token, PDO::PARAM_STR);

            return $stmt->execute();
        }
        
        return false;
    }
    
    public function validate()
    {
       // Name
       if ($this->name == '') {
           $this->errors[] = 'Name is required';
       }

       // email address
       if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
           $this->errors[] = 'Invalid email';
       }
        
        
        //Tutaj musi byc jeszcze jeden parametr. zeby sprawdzić czy ktoś zakłada konto czy resetuje hasło. Jak resetuje hasło to nie moze wywalać błędu ze taki email istnieje. Po to to jest.    
       if (static::emailExists($this->email, $this->id ?? null)) {
            $this->errors[] = 'email already taken';
       }

       // Password
       if (isset($this->password)) {
           
           
            if (strlen($this->password) < 6) {
               $this->errors[] = 'Please enter at least 6 characters for the password';
           }

           if (preg_match('/.*[a-z]+.*/i', $this->password) == 0) {
               $this->errors[] = 'Password needs at least one letter';
           }

           if (preg_match('/.*\d+.*/i', $this->password) == 0) {
               $this->errors[] = 'Password needs at least one number';
           }
           
        }
    }
    
    public static function emailExists($email, $ignore_id = null)
    {
        $user = static::findByEmail($email);
        
        if($user) {
            if ($user->id != $ignore_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find a user model by email address
     *
     * @param string $email email address to search for
     *
     * @return mixed User object if found, false otherwise
     */
    public static function findByEmail($email)
    {
        $sql = 'SELECT * FROM users WHERE email = :email';

        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class()); // zamieni wektor na obiekt

        $stmt->execute();

        return $stmt->fetch();
    }
    
        
    public static function authenticate($email, $password)
    {
        
        $user = static::findByEmail($email);

        if($user && !$user->are_cat_loaded) {
            static::addDefaults($user->id);
        }
        
        if ($user && $user->is_active) {
            if (password_verify($password, $user->password_hash)) {
                return $user;
            }
        }

        return false;
    }

    public static function setRememberedTheme($email)
    {
        $user = static::findByEmail($email);
        
        if($user->theme == 1){
            $_SESSION['ut'] = $user->theme;
        }
    }
    
    public static function findByID($id)
    {
        $sql = 'SELECT * FROM users WHERE id = :id';

        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        return $stmt->fetch();
    }
    
    public function rememberLogin()
    { 
        $token = new Token();
        $hashed_token = $token->getHash();
        $this->remember_token = $token->getValue();
        
        $this->expiry_timestamp = time() + 60 * 60 * 24 * 30; //30 days
        
        $sql = 'INSERT INTO remembered_logins (token_hash, user_id, expires_at)
                VALUES (:token_hash, :user_id, :expires_at)';
        
        $db = static::getDB();
        $stmt = $db->prepare($sql);
        
        $stmt->bindValue(':token_hash', $hashed_token, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue(':expires_at', date('Y-m-d H:i:s', $this->expiry_timestamp), PDO::PARAM_STR);
                         
        return $stmt->execute();  
    }
    
public static function sendPasswordReset($email)
    {
        $user = static::findByEmail($email);

        if ($user) {

            if ($user->startPasswordReset()) {

                $user->sendPasswordResetEmail();

            }
        }
    }

    /**
     * Start the password reset process by generating a new token and expiry
     *
     * @return void
     */
    protected function startPasswordReset()
    {
        $token = new Token();
        $hashed_token = $token->getHash();
        $this->password_reset_token = $token->getValue();

        $expiry_timestamp = time() + 60 * 60 * 2;  // 2 hours from now

        $sql = 'UPDATE users
                SET password_reset_hash = :token_hash,
                    password_reset_expires_at = :expires_at
                WHERE id = :id';

        $db = static::getDB();
        $stmt = $db->prepare($sql);

        $stmt->bindValue(':token_hash', $hashed_token, PDO::PARAM_STR);
        $stmt->bindValue(':expires_at', date('Y-m-d H:i:s', $expiry_timestamp), PDO::PARAM_STR);
        $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Send password reset instructions in an email to the user
     *
     * @return void
     */
    protected function sendPasswordResetEmail()
    {
        $url = 'http://' . $_SERVER['HTTP_HOST'] . '/password/reset/' . $this->password_reset_token;

        $text = View::getTemplate('Password/reset_email.txt', ['url' => $url]);
        $html = View::getTemplate('Password/reset_email.html', ['url' => $url]);

        //Mail::send($this->email, 'Password reset', $text, $html);
        mail($this->email, 'Password reset', $html, Mail::sender()); 
    }
    
    public static function findByPasswordReset($token)
    {
        $token = new Token($token);
        $hashed_token = $token->getHash();

        $sql = 'SELECT * FROM users
                WHERE password_reset_hash = :token_hash';

        $db = static::getDB();
        $stmt = $db->prepare($sql);

        $stmt->bindValue(':token_hash', $hashed_token, PDO::PARAM_STR);

        $stmt->setFetchMode(PDO::FETCH_CLASS, get_called_class());

        $stmt->execute();

        $user = $stmt->fetch();

        if ($user) {

            // Check password reset token hasn't expired
            if (strtotime($user->password_reset_expires_at) > time()) {

                return $user;

            }
        }
    }
    
    public function resetPassword($password)
    {
        $this->password = $password;

        $this->validate();

        if (empty($this->errors)) {

            $password_hash = password_hash($this->password, PASSWORD_DEFAULT);

            $sql = 'UPDATE users
                    SET password_hash = :password_hash,
                        password_reset_hash = NULL,
                        password_reset_expires_at = NULL
                    WHERE id = :id';

            $db = static::getDB();
            $stmt = $db->prepare($sql);

            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
            $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR);

            return $stmt->execute();
        }

        return false;
    }
    
    public function sendActivationEmail()
    {
        $url = 'http://' . $_SERVER['HTTP_HOST'] . '/signup/activate/' . $this->activation_token;

        $text = View::getTemplate('Signup/activation_email.txt', ['url' => $url]);
        $html = View::getTemplate('Signup/activation_email.html', ['url' => $url]);

        //Mail::send($this->email, 'Account activation', $text, $html);

        mail($this->email, 'Account activation', $html, Mail::sender());  
    }
    
    public static function activate($value)
    {
        $token = new Token($value);
        $hashed_token = $token->getHash();

        $sql = 'UPDATE users
                SET is_active = 1,
                    activation_hash = null
                WHERE activation_hash = :hashed_token';

        $db = static::getDB();
        $stmt = $db->prepare($sql);

        $stmt->bindValue(':hashed_token', $hashed_token, PDO::PARAM_STR);

        $stmt->execute();  


    }
    
    public function updateProfile($data)
    {
        if(isset($data['theme']))
        {
            $this->theme = 1;
            $_SESSION['ut'] = true;

        } else {
            $this->theme = 0;
            unset($_SESSION['ut']);
        }

        
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->currency = $data['currency'];
        
        if ($data['password'] != '') {
            $this->password = $data['password'];
        }
    
        $this->validate();
      

        if (empty($this->errors)) {
            $sql = 'UPDATE users
                    SET name = :name,
                        theme = :theme,
                        currency = :currency,
                        email = :email';
            //if password isset
            if (isset($this->password)) {
                $sql .= ', password_hash = :password_hash';
            }
  
            $sql .= "\nWHERE id = :id";
        
            //Zpaytanie SQL z 3 części połączonych znakami .=
            
            $db = static::getDB();
            $stmt = $db->prepare($sql);
            
            $stmt->bindValue(':name', $this->name, PDO::PARAM_STR);
            $stmt->bindValue(':email', $this->email, PDO::PARAM_STR);
            $stmt->bindValue(':theme', $this->theme, PDO::PARAM_INT);
            $stmt->bindValue(':currency', $this->currency, PDO::PARAM_STR);
            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
            
            if (isset($this->password)) {
            
            $password_hash = password_hash($this->password, PASSWORD_DEFAULT);
            $stmt->bindValue(':password_hash', $password_hash, PDO::PARAM_STR);
            
            }

            return $stmt->execute();
        }
        
        return false;
    }
    
    
    public static function addDefaults($user_id)
    {
        static::addDefaultIncomes($user_id);
        static::addDefaultExpenses($user_id);
        static::addDefaultMethods($user_id);
        static::catLoaded($user_id);
          
    }
    
    public static function addDefaultIncomes($user_id)
    {
        
        $sql = 'INSERT INTO incomes_category_assigned_to_users (user_id, name)
                SELECT users.id, incomes_category_default.name
                FROM users, incomes_category_default
                WHERE users.id = :id';
        
        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
        
        $stmt->execute(); 
          
    }
    
    public static function addDefaultExpenses($user_id)
    {
        
        $sql = 'INSERT INTO expenses_category_assigned_to_users (user_id, name)
                SELECT users.id, expenses_category_default.name
                FROM users, expenses_category_default
                WHERE users.id = :id';
        
        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
        
        $stmt->execute(); 
          
    }
    
    public static function addDefaultMethods($user_id)
    {
        
        $sql = 'INSERT INTO payment_methods_assigned_to_users (user_id, name)
                SELECT users.id, payment_methods_default.name
                FROM users, payment_methods_default
                WHERE users.id = :id';
        
        $db = static::getDB();
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
        
        $stmt->execute();   
    }
    
    public static function catLoaded($user_id)
    {
        
        $sql = 'UPDATE users
                SET are_cat_loaded = 1
                WHERE id = :user_id';

        $db = static::getDB();
        $stmt = $db->prepare($sql);

        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);

        $stmt->execute(); 
    }
}

