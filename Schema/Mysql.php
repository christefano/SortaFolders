<?php

namespace Kanboard\Plugin\SortaFolders\Schema;

use PDO;

const VERSION = 5;

function version_1(PDO $pdo)
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_groups (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL DEFAULT 0,
            name VARCHAR(191) NOT NULL,
            position INT NOT NULL DEFAULT 0,
            KEY sortafolders_groups_parent (parent_id)
        ) ENGINE=InnoDB CHARSET=utf8
    ');
}

function version_2(PDO $pdo)
{
    $pdo->exec('ALTER TABLE sortafolders_groups ADD COLUMN description TEXT NULL');
}

function version_3(PDO $pdo)
{
    // Kanboard connects with utf8mb4, so a name with an emoji in it must fit the table too.
    $pdo->exec('ALTER TABLE sortafolders_groups CONVERT TO CHARACTER SET utf8mb4');
}

function version_4(PDO $pdo)
{
    // Folder access: the rules, and the project memberships the rules added (so removing a rule removes only those).
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_access (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            folder_id INT NOT NULL,
            user_id INT NOT NULL DEFAULT 0,
            group_id INT NOT NULL DEFAULT 0,
            role VARCHAR(255) NOT NULL,
            KEY sortafolders_access_folder (folder_id)
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_access_applied (
            grant_id INT NOT NULL,
            project_id INT NOT NULL,
            user_id INT NOT NULL DEFAULT 0,
            group_id INT NOT NULL DEFAULT 0,
            role VARCHAR(255) NOT NULL,
            PRIMARY KEY (grant_id, project_id)
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ');
}

function version_5(PDO $pdo)
{
    // Who created a folder, so a Project Manager can sort a project into a folder they made before it holds a project.
    $pdo->exec('ALTER TABLE sortafolders_groups ADD COLUMN created_by INT NOT NULL DEFAULT 0');

    // Status notes: a short, dated note with a green, yellow or red flag on a folder.
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_notes (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            folder_id INT NOT NULL,
            user_id INT NOT NULL DEFAULT 0,
            flag VARCHAR(10) NOT NULL,
            body VARCHAR(1000) NOT NULL,
            date_creation INT NOT NULL,
            KEY sortafolders_notes_folder (folder_id, date_creation)
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ');
}
