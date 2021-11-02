<?php

class Guardian{
    private $last_name;
    private $first_name;
    private $tel;
    private $email_address;

    function __construct($last_name, $first_name, $tel, $email_address) { 
        $this->last_name        = $last_name;
        $this->first_name       = $first_name;
        $this->tel              = $tel;
        $this->email_address    = $email_address;
     }
     
    function setLast_name($last_name) { $this->last_name = $last_name; }
    function getLast_name() { return $this->last_name; }
    function setFirst_name($first_name) { $this->first_name = $first_name; }
    function getFirst_name() { return $this->first_name; }
    function setTel($tel) { $this->tel = $tel; }
    function getTel() { return $this->tel; }
    function setEmail_address($email_address) { $this->email_address = $email_address; }
    function getEmail_address() { return $this->email_address; }
}