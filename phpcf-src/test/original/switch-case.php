<?php
switch ($argc) {
    case 1:
        echo "Hello world!\n";
        break;
    case 2:
        echo "Shit\n";
        break;
    case 3:
        echo 'Hello!';

    case 4:
        echo "WTF\n";
        break;


    default:
        echo "Mazafaka";
        break;
}

echo "Hello!\n";

// Artem Soldatkin!!!
switch ($bad) {
    case 1: {
        return 'something';
    }
    default:return null;
}


switch ($errCode) {
    case Employee::BAD_LOGIN:
        $vars['login_msg']   = 'Bad email';
        $vars['login_class'] = 'error';
        break;
    case Employee::NAME_EXISTS:
        $vars['login_msg']   = 'Name exist';
        $vars['login_class'] = 'error';
        break;
    case Employee::EMPTY_LOGIN:
        $vars['login_msg']   = 'Empty login';
        $vars['login_class'] = 'error';
        break;
    case Employee::EMAIL_EXISTS:
    case Employee::LOGIN_EXISTS:
        $vars['email_msg']   = 'Email already used';
        $vars['email_class'] = 'error';
        break;
    case Employee::EMPTY_NAME:
        $vars['name_msg']   = 'Please fill full name';
        $vars['name_class'] = 'error';
        break;
    case Employee::EMPTY_ADDRESS:
        break;
    case Employee::EMPTY_GROUP_SET:
        break;

    default:
        $unknown_error = 'Error code #' . $errCode;
        break;
}

// other switch

switch ($something) {
    case "something":
    default:
        echo "hello world\n";
        break;
}
