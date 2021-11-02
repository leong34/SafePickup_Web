<?php

class Attendance{
    private $date;
    private $status;
    private $check_in;
    private $check_out;

    function setDate($date) { $this->date = $date; }
    function getDate() { return $this->date; }
    function setStatus($status) { $this->status = $status; }
    function getStatus() { return $this->status; }
    function setCheck_in($check_in) { $this->check_in = $check_in; }
    function getCheck_in() { return $this->check_in; }
    function setCheck_out($check_out) { $this->check_out = $check_out; }
    function getCheck_out() { return $this->check_out; }
}