<?php

namespace Kanboard\Plugin\SortaFolders\Schema;

use PDO;

const VERSION = 4;

function version_1(PDO $pdo)
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER NOT NULL DEFAULT 0,
            name TEXT NOT NULL,
            position INTEGER NOT NULL DEFAULT 0
        )
    ');
    $pdo->exec('CREATE INDEX IF NOT EXISTS sortafolders_groups_parent ON sortafolders_groups(parent_id)');
}

function version_2(PDO $pdo)
{
    $pdo->exec('ALTER TABLE sortafolders_groups ADD COLUMN description TEXT');
}

function version_3(PDO $pdo)
{
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_access (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            folder_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL DEFAULT 0,
            group_id INTEGER NOT NULL DEFAULT 0,
            role TEXT NOT NULL
        )
    ');
    $pdo->exec('CREATE INDEX IF NOT EXISTS sortafolders_access_folder ON sortafolders_access(folder_id)');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_access_applied (
            grant_id INTEGER NOT NULL,
            project_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL DEFAULT 0,
            group_id INTEGER NOT NULL DEFAULT 0,
            role TEXT NOT NULL,
            PRIMARY KEY (grant_id, project_id)
        )
    ');
}

function version_4(PDO $pdo)
{
    $pdo->exec('ALTER TABLE sortafolders_groups ADD COLUMN created_by INTEGER NOT NULL DEFAULT 0');
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS sortafolders_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            folder_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL DEFAULT 0,
            flag TEXT NOT NULL,
            body TEXT NOT NULL,
            date_creation INTEGER NOT NULL
        )
    ');
    $pdo->exec('CREATE INDEX IF NOT EXISTS sortafolders_notes_folder ON sortafolders_notes(folder_id, date_creation)');
}
