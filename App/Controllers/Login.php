<?php

namespace App\Controllers;
use \Core\View;
use \App\Models\User;
use \App\Auth;
use \App\Flash;


/**
 * Login controller
 *
 * PHP version 7.0
 */
class Login extends \Core\Controller
{
    public function newAction() 
    {
        View::renderTemplate('Login/new.html');
    }  
    
    public function createAction() 
    {
        $user = User::authenticate($_POST['email'], $_POST['password']);

        $remember_me = isset($_POST['remember_me']);
        
        if($user)
        {
            Auth::login($user, $remember_me);

            User::setRememberedTheme($_POST['email']);
            
            Flash::addMessage('Login successful');
            
            $this->redirect(Auth::getReturnToPage());

            exit;
        } else {
            
            Flash::addMessage('Login usuccessful, please try again', Flash::WARNING);
            
            View::renderTemplate('Login/new.html', [
                'email' => $_POST['email'], 
                'remember_me' => $remember_me
            ]);
        }
    }
    
    public function destroyAction()
    {
        Auth::logout();
        $this->redirect('/login/show-logout-message');
        //Trzeba tak zrobić bo sesja jest zamykana i nie ma się jak wyświetlić komunikat

    }
    
    public function showLogoutMessageAction()
    {
        Flash::addMessage('Logout successful');
        
        $this->redirect('/');
    }

}
