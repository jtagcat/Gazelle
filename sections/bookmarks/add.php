<?php
authorize();

$bookmark = new \Gazelle\Bookmark;
try {
    $bookmark->create($LoggedUser['ID'], $_GET['type'], (int)$_GET['id']);
}
catch (Exception $e) {
    error(0);
}
