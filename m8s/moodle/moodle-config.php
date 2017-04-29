<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'moodle-postgresql';
$CFG->dbname    = 'moodle';
$CFG->dbuser    = 'moodle';
$CFG->dbpass    = 'moodle';
$CFG->prefix    = 'mdl';
$CFG->dboptions = array (
  'dbpersist' => 0,
);

$CFG->wwwroot  = 'http://moodle.local';
$CFG->dataroot  = '/moodle/data';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 02775;

$CFG->passwordsaltmain = 'y0uR34l!ySh0uldtU$3-th1sS&lt';

require_once "/var/www/html/lib/setup.php";
// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
