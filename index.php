<?php

include_once "vendor/autoload.php";

//$query = new \Deimos\Query("
//  SELECT *
//  FROM `employees.xml`
//  WHERE
//        `employee.@attributes.id` IN (1, 2, 3, 4, 5) OR
//        `employee.@attributes.id` BETWEEN 2 AND 54
//  ORDER BY `employee.@attributes.id` DESC
//");

$query = new \Deimos\Query("
  SELECT *
  FROM `employees.xml`
  WHERE `employee.weight.@value.value` < 60
        AND `employee.weight.@value.value` > 56
");

var_dump($query->execute());