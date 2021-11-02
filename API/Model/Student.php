<?php

class Student {
    private $last_name;
    private $first_name;
    private $age;
    private $gender;
    private $class_id;
    private $class_name;
    private $guardians = array();
    private $attendances = array();

    function setLast_name($last_name) { $this->last_name = $last_name; }
    function getLast_name() { return $this->last_name; }
    function setFirst_name($first_name) { $this->first_name = $first_name; }
    function getFirst_name() { return $this->first_name; }
    function setAge($age) { $this->age = $age; }
    function getAge() { return $this->age; }
    function setGender($gender) { $this->gender = $gender; }
    function getGender() { return $this->gender; }
    function setClass_id($class_id) { $this->class_id = $class_id; }
    function getClass_id() { return $this->class_id; }
    function setClass_name($class_name) { $this->class_name = $class_name; }
    function getClass_name() { return $this->class_name; }
    function setGuardians($guardians) { array_push($this->guardians, $guardians); }
    function getGuardians() { return $this->guardians; }
    function setAttendances($attendances) { array_push($this->attendances, $attendances); }
    function getAttendances() { return $this->attendances; }
}