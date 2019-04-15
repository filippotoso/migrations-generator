# Migrations Generator

A Laravel Artisan command to automatically generate migrations from database tables.

## Requirements

- PHP 5.6+
- Laravel 5.4+
- Doctrine DBAL +2.8

## Installing

Use Composer to install it:

```
composer require filippo-toso/migrations-generator
```

## How does it work?

This generator is very simple. It builds the migrations from the database and saves them in the database folder.

By default the generator doesn't create the models of Laravel's tables like jobs, cache, and so on. You can modify this behavior publishing the package resources and editing the config/migrations-generator.php file.

## Configuration

You can publish the configuration file with the following command:

```
php artisan vendor:publish --tag=config --provider="FilippoToso\MigrationsGenerator\ServiceProvider"
```

The config/migration-generator.php file allows you to:

- define which tables exclude form the generation (ie. cache, jobs, migrations, ...)

Just open the file and read the comments :)

## Options

The predefined use from command line is:

```
php artisan generate:migrations
```

This command generates the migrations with the current time (plus one second for each table).

If there are existing migrations they will not be overwritten.

You can modify the default behavior using the following parameters:

```
php artisan generate:migrations --overwrite
```

With the overwrite option the generator will remove the previously generated migrations with the same class name.

```
php artisan generate:migrations --connection=sqlite
```

You can specify a different connection if you need to.

## Workflow

To gain the maximum benefits from this package you should follow this workflow:

- design the database (i.e. with MySQL Workbench)
- execute the SQL CREATE statement in your MySQL
- configure the generator
- run the generator
- customize the migrations in the database folder

You must follow Laravel's guidelines about tables and columns names and also include in your SQL statements all the required foreign keys and indexes.

## Known Issues

If you have two (or more) tables inter-related, you will need to manually move the foreign key definitions in a separate migrations that will run after all the tables has been created 